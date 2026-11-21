<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/WhatsAppCloudService.php';
require_once __DIR__ . '/../../app/Services/TeamsRegistrationService.php';

use SmartTrade\Services\WhatsAppCloudService;
use SmartTrade\Services\TeamsRegistrationService;

$flwConfig = WhatsAppCloudService::getFlwConfig();
$storeTitle = WhatsAppCloudService::resolveStoreBrandName();
$publicKey  = $flwConfig['public_key'];
$subaccount = $flwConfig['subaccount_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><= htmlspecialchars($storeTitle) ?> — Live Teams Event Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://checkout.flutterwave.com/v3.js"></script>
    <style>
        body { background-color: #f8fafc; padding-bottom: 60px; }
        .hero-banner { background: linear-gradient(135deg, #075E54, #128C7E); color: #fff; padding: 30px 20px; text-align: center; border-radius: 0 0 20px 20px; }
        .card-reg { border: 0; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
    </style>
</head>
<body>

<div class="hero-banner mb-4 shadow-sm">
    <h3 class="fw-bold mb-1"><?= htmlspecialchars($storeTitle) ?></h3>
    <p class="mb-0 opacity-75 small">Secure Registration &bull; Instant Microsoft Teams Access</p>
</div>

<div class="container" style="max-width: 500px;">
    <div class="card card-reg p-4 bg-white">
        <h5 class="fw-bold mb-3 text-dark">Event Registration</h5>
        <form id="teamsRegForm" onsubmit="initTeamsCheckout(event)">
            <div class="mb-3">
                <label class="form-label small fw-bold">Full Name</label>
                <input type="text" id="attName" class="form-control" placeholder="e.g., Mando Chishimba" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Email Address</label>
                <input type="email" id="attEmail" class="form-control" placeholder="e.g., mando@odeliazm.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">WhatsApp / Mobile Money Number</label>
                <input type="tel" id="attPhone" class="form-control" placeholder="e.g., 0977123456" autocomplete="one-time-code" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">Ticket Tier & Fee</label>
                <select id="ticketTier" class="form-select" onchange="updateFeeDisplay()">
                    <option value="Standard Pass|150.00">Standard Pass - ZMW 150.00</option>
                    <option value="VIP Access|350.00">VIP Access - ZMW 350.00</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success w-100 fw-bold py-3 mt-2" id="payBtn">
                Pay &amp; Get Teams Access Link
            </button>
        </form>
    </div>
</div>

<script>
const FLW_PUBLIC_KEY = <?= json_encode($publicKey) ?>;
const FLW_SUBACCOUNT = <?= json_encode($subaccount) ?>;
const STORE_TITLE = <?= json_encode($storeTitle) ?>;

function updateFeeDisplay() {
    // Optional helper if dynamic fee formatting is required
}

async function initTeamsCheckout(e) {
    e.preventDefault();
    const name = document.getElementById('attName').value.trim();
    const email = document.getElementById('attEmail').value.trim();
    const phone = document.getElementById('attPhone').value.trim();
    const [tier, priceStr] = document.getElementById('ticketTier').value.split('|');
    const amount = parseFloat(priceStr);

    const btn = document.getElementById('payBtn');
    btn.disabled = true;
    btn.textContent = 'Initializing Secure Payment...';

    try {
        const res = await fetch('/api/teams/init_registration.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attendee_name: name, attendee_email: email, attendee_phone: phone, ticket_tier: tier, amount_zmw: amount })
        });
        const data = await res.json();
        if (data.status !== 'success') {
            throw new Error(data.message || 'Could not initialize registration.');
        }

        const flwPayload = {
            public_key: FLW_PUBLIC_KEY,
            tx_ref: data.tx_ref,
            amount: amount,
            currency: 'ZMW',
            payment_options: 'mobilemoneyzambia, card',
            customer: { email: email, phone_number: phone, name: name },
            customizations: { title: STORE_TITLE, description: 'Teams Registration - ' + tier },
            callback: async function (payment) {
                let joinUrl = '';
                try {
                    const verifyRes = await fetch('/api/teams/complete_registration.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ tx_ref: data.tx_ref, transaction_id: String(payment.transaction_id || '') })
                    });
                    const verifyData = await verifyRes.json();
                    if (verifyData && verifyData.teams_join_url) {
                        joinUrl = verifyData.teams_join_url;
                    }
                } catch (err) {}

                let msgLines = [
                    `*REGISTRATION CONFIRMED - ${tier}*`,
                    `Name: ${name}`,
                    `Reference: ${data.tx_ref}`,
                    ``,
                    `*Your Direct Microsoft Teams Meeting Link:*`,
                    joinUrl || (window.location.origin + '/teams/join/' + data.receipt_token)
                ];

                const waUrl = `https://wa.me/${phone.replace(/\D+/g, '')}?text=` + encodeURIComponent(msgLines.join('\n'));
                window.location.href = waUrl;
            },
            onclose: function () {
                btn.disabled = false;
                btn.textContent = 'Pay & Get Teams Access Link';
            }
        };

        if (FLW_SUBACCOUNT && FLW_SUBACCOUNT.startsWith('RS_')) {
            flwPayload.subaccounts = [{ id: FLW_SUBACCOUNT }];
        }

        FlutterwaveCheckout(flwPayload);
    } catch (err) {
        alert(err.message);
        btn.disabled = false;
        btn.textContent = 'Pay & Get Teams Access Link';
    }
}
</script>
</body>
</html>
