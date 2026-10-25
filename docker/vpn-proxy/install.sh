#!/usr/bin/env bash
# Gives Docker containers internet through the host's sshuttle VPN.
#
# sshuttle only tunnels traffic of the host itself; bridge containers get
# "connection refused". This runs tinyproxy in a host-network container (its
# traffic goes through sshuttle) and makes Docker pass HTTP(S)_PROXY to every
# new container via ~/.docker/config.json.
#
# Requirements: docker + compose plugin, python3, the VPN running, and the
# sshuttle command excluding 172.16.0.0/12 (-x 172.16.0.0/12).
# Usage: bash install.sh            (re-running is safe)
set -euo pipefail

PORT=8888
DIR="$HOME/docker-vpn-proxy"

GATEWAY=$(ip -4 -o addr show docker0 2>/dev/null | awk '{print $4}' | cut -d/ -f1)
GATEWAY=${GATEWAY:-172.17.0.1}

# Physical LAN subnets (Wi-Fi/Ethernet), so the proxy refuses other LAN devices.
LAN_DENY=$(ip -4 route show proto kernel scope link \
    | grep -vE ' dev (docker|br-|veth|virbr|lo)' \
    | awk '{print "Deny " $1}')

mkdir -p "$DIR" "$HOME/.docker"
cd "$DIR"

cat > docker-compose.yml <<'EOF'
# HTTP proxy for all Docker containers.
# Runs on the host network, so its traffic goes through the host's VPN (sshuttle).
# Containers get HTTP(S)_PROXY from ~/.docker/config.json;
# the empty proxy args/env keep the proxy itself from using it.
services:
    proxy:
        build:
            context: .
            network: host
            args:
                HTTP_PROXY: ""
                HTTPS_PROXY: ""
                http_proxy: ""
                https_proxy: ""
        image: local/vpn-proxy
        container_name: vpn-proxy
        network_mode: host
        restart: unless-stopped
        environment:
            HTTP_PROXY: ""
            HTTPS_PROXY: ""
            http_proxy: ""
            https_proxy: ""
EOF

cat > Dockerfile <<'EOF'
FROM alpine:latest
RUN apk add --no-cache tinyproxy
COPY tinyproxy.conf /etc/tinyproxy/tinyproxy.conf
CMD ["tinyproxy", "-d", "-c", "/etc/tinyproxy/tinyproxy.conf"]
EOF

cat > tinyproxy.conf <<EOF
Port $PORT
Timeout 600
LogLevel Notice
MaxClients 200
DisableViaHeader Yes

# First match wins: refuse the LAN, then allow Docker networks only.
$LAN_DENY
Allow 127.0.0.1
Allow 172.16.0.0/12
Allow 192.168.0.0/16
Allow 10.0.0.0/8

ConnectPort 443
ConnectPort 80
ConnectPort 22
EOF

python3 - "$HOME/.docker/config.json" "http://$GATEWAY:$PORT" "$GATEWAY" <<'EOF'
import json, os, sys
path, proxy, gateway = sys.argv[1:]
config = {}
if os.path.exists(path) and os.path.getsize(path) > 0:
    with open(path) as f:
        config = json.load(f)
config.setdefault("proxies", {})["default"] = {
    "httpProxy": proxy,
    "httpsProxy": proxy,
    "noProxy": f"localhost,127.0.0.1,::1,{gateway},host.docker.internal,.local,.internal,"
               "172.16.0.0/12,192.168.0.0/16,10.0.0.0/8",
}
with open(path, "w") as f:
    json.dump(config, f, indent=4)
EOF

docker compose up -d --build
sleep 2

echo
echo "Proxy: http://$GATEWAY:$PORT"
echo "LAN denied: ${LAN_DENY:-none found}"
echo -n "Test from a bridge container: "
docker run --rm alpine:latest wget -q -T 10 -O /dev/null -S https://www.youtube.com 2>&1 | head -1 || echo "FAILED"
echo
echo "Recreate existing containers once so they get the proxy:"
echo "  docker compose up -d --force-recreate   (in each project)"
echo "Add dotless service names called over HTTP (e.g. meilisearch) to noProxy in ~/.docker/config.json."
