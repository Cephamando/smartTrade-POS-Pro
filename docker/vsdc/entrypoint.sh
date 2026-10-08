#!/bin/bash
set -euo pipefail

echo "Updating system CA certificates to fix PKIX validation..."
apt-get update -qq
apt-get install -y -qq ca-certificates ca-certificates-java curl
update-ca-certificates -f

# sandboxapi.zra.org.zm sends only the leaf (*.zra.org.zm).
# Java 8 cannot build a path to Sectigo unless this intermediate is in cacerts.
CACERTS="${JAVA_HOME:-/opt/java/openjdk}/lib/security/cacerts"
if [ ! -f "$CACERTS" ]; then
    echo "ERROR: Java cacerts not found at $CACERTS"
    exit 1
fi
curl -fsSL -o /tmp/sectigo-r36.crt http://crt.sectigo.com/SectigoPublicServerAuthenticationCADVR36.crt
if ! keytool -list -alias sectigo-dv-r36 -keystore "$CACERTS" -storepass changeit >/dev/null 2>&1; then
    keytool -importcert -noprompt -trustcacerts -alias sectigo-dv-r36 \
        -keystore "$CACERTS" -storepass changeit -file /tmp/sectigo-r36.crt
    echo "Imported Sectigo DV R36 into the Java trust store"
else
    echo "Sectigo DV R36 already in the Java trust store"
fi

DEST_WAR="/usr/local/tomcat/webapps/sandboxvsdc.war"
# Context path is the WAR filename. Prefer the versioned sandbox build, then
# api/sandboxvsdc.war (the name kept in this repo), then any other sandbox WAR.
SRC_WAR=""
for candidate in \
    /opt/zra-api/sandboxvsdc1.0.8.0.war \
    /opt/zra-api/sandboxvsdc.war \
    /opt/zra-api/sandboxvsdc*.war \
    /opt/zra-api/vsdc.war
do
    if [ -f "$candidate" ]; then
        SRC_WAR="$candidate"
        break
    fi
done

if [ -z "$SRC_WAR" ]; then
    echo "ERROR: No VSDC WAR in /opt/zra-api. Place sandboxvsdc.war in the api/ folder."
    echo "Tomcat will start, but /sandboxvsdc/* will return HTTP 404."
elif [ ! -f "$DEST_WAR" ] || ! cmp -s "$SRC_WAR" "$DEST_WAR"; then
    # A changed WAR must replace the exploded app. Leaving the old directory
    # makes Tomcat keep serving it (or nothing) and the API stays on 404.
    # Unchanged bytes keep the exploded app so the device keystore survives.
    if [ -f "$DEST_WAR" ]; then
        rm -rf /usr/local/tomcat/webapps/sandboxvsdc
        echo "WAR bytes changed; replacing exploded sandboxvsdc"
    fi
    cp -f "$SRC_WAR" "$DEST_WAR"
    echo "Copied $(basename "$SRC_WAR") to sandboxvsdc.war"
else
    echo "WAR unchanged; keeping exploded sandboxvsdc"
fi

exec catalina.sh run
