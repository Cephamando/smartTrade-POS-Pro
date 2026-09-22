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
SRC_WAR="/opt/zra-api/sandboxvsdc1.0.8.0.war"
if [ -f "$SRC_WAR" ]; then
    if [ ! -f "$DEST_WAR" ] || ! cmp -s "$SRC_WAR" "$DEST_WAR"; then
        cp -f "$SRC_WAR" "$DEST_WAR"
        echo "Copied sandboxvsdc.war"
    else
        echo "WAR unchanged; keeping exploded sandboxvsdc"
    fi
fi

exec catalina.sh run
