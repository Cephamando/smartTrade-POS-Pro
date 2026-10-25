# WireGuard setup, onboarding, and revocation

The office computers reach the shop server through WireGuard. The website and the till stay on the public address. A tunnel does not close ports 22, 80, or 443, and it does not send the rest of a computer’s internet traffic through the shop.

Private keys stay in `/etc/wireguard/` on the server and in the WireGuard app on each computer. Do not copy a private key into this repository, into chat, or into a screenshot.

## Addresses in use

| Address | Who |
|---|---|
| `10.88.0.1/24` | Shop server, interface `wg0` |
| `10.88.0.2/32` | Office PC, already onboarded |
| `10.88.0.3/32` onward | Next new computer. Do not reuse an address that is still in `wg0.conf`. |

| | |
|---|---|
| Server | `odelia`, public address `185.227.110.183` |
| UDP port | `51820`, allowed in UFW as “wireguard office” |
| Server public key | `F5xTNTMITu61tYTV1I+I7wegJnCQeft6syj3A1Kc5Rk=` |
| Office public key | `wTEgd1BRPswlAKAd20lo8Rd8rGS9ff6cVTg5Swq1g0k=` |
| Service | `wg-quick@wg0`, enabled |

The server public key is the one every client file must name. Read it again before writing a new file:

```
sudo wg show wg0 public-key
```

## 1. Set up a new server

Use this only on a machine that does not already have `wg0`. The live shop server is already set up. Section 2 is the one to follow there. Generating a new server key on the live server cuts off every computer that already has a tunnel.

```
sudo apt-get update
sudo apt-get install wireguard
sudo mkdir -p /etc/wireguard/clients
umask 077
wg genkey | sudo tee /etc/wireguard/server.key | wg pubkey | sudo tee /etc/wireguard/server.pub
sudo chmod 600 /etc/wireguard/server.key /etc/wireguard/server.pub
```

Write `/etc/wireguard/wg0.conf`. Paste the contents of `server.key` as the private key. The mode of the file must be `600`.

```
[Interface]
Address = 10.88.0.1/24
ListenPort = 51820
PrivateKey = <server private key>
```

Allow the port and start the tunnel:

```
sudo ufw allow 51820/udp comment 'wireguard office'
sudo systemctl enable --now wg-quick@wg0
sudo wg show
```

`wg show` should list interface `wg0`, the public key, and UDP port 51820. No peers yet. Leave SSH and the website reachable on the public address.

## 2. The live server is already set up

Do not run section 1 on `odelia`. The server key, the office peer, and UFW are already in place.

```
sudo wg show
sudo systemctl status wg-quick@wg0
sudo ufw status | grep 51820
```

A handshake and peer `10.88.0.2/32` mean the office PC is connected. The server does not store that PC’s home address. The PC connects outward, and the server remembers the path until the next handshake.

`/etc/wireguard/wg0.conf` on the live server:

```
[Interface]
Address = 10.88.0.1/24
ListenPort = 51820
PrivateKey = <server private key>

[Peer]
# Office PC
PublicKey = wTEgd1BRPswlAKAd20lo8Rd8rGS9ff6cVTg5Swq1g0k=
AllowedIPs = 10.88.0.2/32
```

Saved key files:

| File | What it is |
|---|---|
| `/etc/wireguard/server.key` | Server private key |
| `/etc/wireguard/server.pub` | Server public key |
| `/etc/wireguard/clients/office.key` | Office PC private key |
| `/etc/wireguard/clients/office.pub` | Office PC public key |
| `/root/wireguard-office.conf` | Office tunnel file, private key included. Mode `600`. |

## 3. Onboard a new computer

Do this for a computer that has never had a tunnel. Pick the next free address from the table above. The examples use `10.88.0.3` and the short name `till2`. Change both.

On the server:

```
sudo mkdir -p /etc/wireguard/clients
umask 077
wg genkey | sudo tee /etc/wireguard/clients/till2.key | wg pubkey | sudo tee /etc/wireguard/clients/till2.pub
sudo chmod 600 /etc/wireguard/clients/till2.key /etc/wireguard/clients/till2.pub
```

Append a peer to `/etc/wireguard/wg0.conf`. Use the text in `till2.pub`. Do not put the private key in this file.

```
[Peer]
# till2
PublicKey = <contents of till2.pub>
AllowedIPs = 10.88.0.3/32
```

Apply the peer without dropping computers that are already connected:

```
sudo wg syncconf wg0 <(sudo wg-quick strip wg0)
sudo wg show
```

Build the tunnel file on the server, then copy that one file to the new computer and delete any extra copy. The private key is the single line in `till2.key`.

```
[Interface]
PrivateKey = <till2 private key>
Address = 10.88.0.3/32

[Peer]
PublicKey = F5xTNTMITu61tYTV1I+I7wegJnCQeft6syj3A1Kc5Rk=
Endpoint = 185.227.110.183:51820
AllowedIPs = 185.227.110.183/32
PersistentKeepalive = 25
```

`AllowedIPs` is only the shop server. Traffic to `185.227.110.183` uses the tunnel. Other internet traffic stays on the computer’s normal connection. `PersistentKeepalive` keeps the path open when a router forgets idle UDP.

On Windows, install WireGuard from https://www.wireguard.com/install , choose **Import tunnel(s) from file**, then **Activate**. On Linux:

```
sudo apt-get install wireguard
sudo install -m 600 till2.conf /etc/wireguard/wg0.conf
sudo systemctl enable --now wg-quick@wg0
```

The check is a handshake on the server within a minute, and an SSH login that `last` shows as `10.88.0.3`:

```
ssh root@185.227.110.183
```

Write the new name and address into the table at the top of this guide.

## 4. Existing computers

### 4.1 Office PC, already onboarded

The office PC is `10.88.0.2`. Its keys are already on the server. Reinstalling Windows, or installing WireGuard again, does not need a new key. Copy `/root/wireguard-office.conf` to that PC again and activate it.

The file has this shape. Read the private key from `/etc/wireguard/clients/office.key` if you have to rebuild the file. Keep the same public key in `wg0.conf`.

```
[Interface]
PrivateKey = <office private key>
Address = 10.88.0.2/32

[Peer]
PublicKey = F5xTNTMITu61tYTV1I+I7wegJnCQeft6syj3A1Kc5Rk=
Endpoint = 185.227.110.183:51820
AllowedIPs = 185.227.110.183/32
PersistentKeepalive = 25
```

Generating a second key for this PC, while the old public key is still in `wg0.conf`, creates a peer the server will reject. Section 4.3 is the way to change a key on purpose.

### 4.2 The tunnel file was lost, and the key file is still on the server

Rebuild the client file from the existing `clients/NAME.key`. Put the same address back in `Address`. The `PublicKey` under `[Peer]` is always the server public key, not the computer’s own public key. Do not edit `wg0.conf` and do not run `wg genkey`.

Activate that file on the computer. `wg show` on the server should show a new handshake for the same public key and the same `10.88.0.x/32`.

### 4.3 Replace the key of a computer that already has an address

Use this when a computer is stolen, a private key was copied, or the key file on the server is gone. The address stays. The old key stops working as soon as the server reloads.

On the server, with `till2` still at `10.88.0.3`:

```
umask 077
wg genkey | sudo tee /etc/wireguard/clients/till2.key | wg pubkey | sudo tee /etc/wireguard/clients/till2.pub
sudo chmod 600 /etc/wireguard/clients/till2.key
```

In `/etc/wireguard/wg0.conf`, replace only that peer’s `PublicKey` line with the new `till2.pub`. Leave `AllowedIPs` as it is.

```
sudo wg syncconf wg0 <(sudo wg-quick strip wg0)
```

Write a new tunnel file, as in section 3, using the new private key and the same address. Import it on the computer in place of the old tunnel. The previous key can no longer complete a handshake.

## 5. Revoke a computer

Revoking removes the peer from the server. The computer’s tunnel stays installed until someone deletes it there, but the server will not accept it.

If you are revoking the computer you are typing on, turn its tunnel off first, or use the Kamatera console. While that tunnel is still active, traffic to `185.227.110.183` is sent into it. After the server drops the peer, that path goes nowhere and SSH from that PC will not connect until the tunnel is switched off.

On the server, find the peer:

```
sudo wg show
```

Delete that computer’s whole `[Peer]` block, including the comment and `AllowedIPs`, from `/etc/wireguard/wg0.conf`. Then:

```
sudo wg syncconf wg0 <(sudo wg-quick strip wg0)
sudo wg show
```

The public key must be gone from `wg show`. Delete the saved keys so the file cannot be reused:

```
sudo shred -u /etc/wireguard/clients/NAME.key /etc/wireguard/clients/NAME.pub
```

If `shred` is not suitable for that disk, `sudo rm -f` those two files instead. Also delete `/root/wireguard-NAME.conf` if you wrote one.

On the computer, deactivate the tunnel and delete it from the WireGuard app. On Linux:

```
sudo systemctl disable --now wg-quick@wg0
sudo rm -f /etc/wireguard/wg0.conf
```

Remove that row from the address table. The address may be given to a different computer only after the old peer is gone from `wg show`.

Revoking a computer does not close the website or SSH on the public address.

## 6. When a computer cannot connect

On the server:

```
sudo wg show wg0
sudo ufw status | grep 51820
sudo systemctl status wg-quick@wg0
```

The server must be listening on UDP 51820. The client file’s server public key must match `wg show wg0 public-key`. The client `Address` must be the same `10.88.0.x/32` that `AllowedIPs` names for that public key.

An empty handshake means outbound UDP 51820 is blocked on the way out of that network, or the keys do not match. A handshake and no login usually means `AllowedIPs` on the computer no longer includes `185.227.110.183/32`, so SSH to the public address is not entering the tunnel.
