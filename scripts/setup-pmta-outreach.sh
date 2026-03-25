#!/bin/bash
# =============================================================================
# PMTA Setup for SOS-Expat Outreach
# Run on a FRESH Hetzner VPS (Ubuntu 24.04)
# =============================================================================
#
# BEFORE RUNNING:
# 1. Get a Hetzner CPX11 or CPX22 (Ubuntu 24.04)
# 2. Set up reverse DNS (PTR) for the server IP → mail.provider-expat.com
# 3. Configure DNS records for ALL 3 domains (see below)
#
# DNS RECORDS NEEDED (for each of the 3 domains):
#
# provider-expat.com:
#   A       mail.provider-expat.com    → [SERVER_IP]
#   MX      provider-expat.com         → mail.provider-expat.com (priority 10)
#   TXT     provider-expat.com         → "v=spf1 ip4:[SERVER_IP] -all"
#   TXT     dkim._domainkey.provider-expat.com → (DKIM key, generated below)
#   TXT     _dmarc.provider-expat.com  → "v=DMARC1; p=none; rua=mailto:dmarc@provider-expat.com"
#
# hub-travelers.com:
#   (same pattern, replace domain)
#
# spaceship.com:
#   (same pattern, replace domain)
#
# USAGE:
#   ssh root@[NEW_SERVER_IP] 'bash -s' < setup-pmta-outreach.sh
# =============================================================================

set -euo pipefail

SERVER_IP=$(curl -s4 ifconfig.co)
echo "Server IP: $SERVER_IP"

DOMAINS=("provider-expat.com" "hub-travelers.com" "spaceship.com")
PMTA_SMTP_USER="outreach@sos-expat.com"
PMTA_SMTP_PASS="$(openssl rand -base64 24)"

echo "==========================================="
echo "PMTA Outreach Setup — 3 domain rotation"
echo "==========================================="

# 1. System updates
echo "[1/8] System updates..."
apt-get update -qq && apt-get upgrade -y -qq
apt-get install -y -qq wget curl openssl dnsutils

# 2. Install PMTA
echo "[2/8] Installing PowerMTA..."
# Note: PMTA requires a license. Download from port25.com with your license key.
# If PMTA is already installed, skip this step.
if ! command -v pmta &>/dev/null; then
    echo "ERROR: PMTA not found. Please install PMTA 5.x manually first."
    echo "Download from: https://port25.com/download/"
    echo "Then re-run this script."
    exit 1
fi

# 3. Generate DKIM keys for each domain
echo "[3/8] Generating DKIM keys..."
mkdir -p /home/pmta/conf/mail
for DOMAIN in "${DOMAINS[@]}"; do
    DKIM_DIR="/home/pmta/conf/mail/$DOMAIN"
    mkdir -p "$DKIM_DIR"
    if [ ! -f "$DKIM_DIR/dkim.pem" ]; then
        openssl genrsa -out "$DKIM_DIR/dkim.pem" 2048
        echo "Generated DKIM key for $DOMAIN"
    fi
    # Extract public key for DNS
    PUB=$(openssl rsa -in "$DKIM_DIR/dkim.pem" -pubout -outform DER 2>/dev/null | openssl base64 -A)
    echo ""
    echo "=== DNS TXT record for $DOMAIN ==="
    echo "Host: dkim._domainkey.$DOMAIN"
    echo "Value: v=DKIM1; k=rsa; p=$PUB"
    echo "=================================="
    echo ""
done

# 4. Generate PMTA config
echo "[4/8] Generating PMTA config..."
cat > /home/pmta/conf/config << PMTACONFIG
# =============================================================================
# PowerMTA Configuration — SOS-Expat Outreach (3 domains)
# =============================================================================

postmaster admin@sos-expat.com
run-as-root no
log-file /var/log/pmta/log

# SMTP listener (for Laravel to submit emails)
smtp-listener $SERVER_IP:2525
    source $SERVER_IP
    process-x-virtual-mta yes
    require-starttls no
    auth-username $PMTA_SMTP_USER
    auth-password $PMTA_SMTP_PASS

smtp-listener 127.0.0.1:2525
    source 127.0.0.1
    auth-username $PMTA_SMTP_USER
    auth-password $PMTA_SMTP_PASS

# Management interface (localhost only)
http-mgmt-port 1983
http-access $SERVER_IP monitor
http-access 127.0.0.1 admin

# Spool
spool /var/spool/pmta

# Logging
<acct-file /var/log/pmta/acct.csv>
    records d b
    max-size 50M
</acct-file>

<acct-file /var/log/pmta/diag.csv>
    records d b
    max-size 50M
</acct-file>

# =============================================================================
# VIRTUAL MTAs — one per domain
# =============================================================================

<virtual-mta vmta-provider>
    smtp-source-host $SERVER_IP mail.provider-expat.com
    <domain *>
        use-starttls yes
        require-starttls no
        dkim-sign yes
        dkim-key /home/pmta/conf/mail/provider-expat.com/dkim.pem
        dkim-domain provider-expat.com
        dkim-selector dkim
    </domain>
</virtual-mta>

<virtual-mta vmta-hub>
    smtp-source-host $SERVER_IP mail.hub-travelers.com
    <domain *>
        use-starttls yes
        require-starttls no
        dkim-sign yes
        dkim-key /home/pmta/conf/mail/hub-travelers.com/dkim.pem
        dkim-domain hub-travelers.com
        dkim-selector dkim
    </domain>
</virtual-mta>

<virtual-mta vmta-spaceship>
    smtp-source-host $SERVER_IP mail.spaceship.com
    <domain *>
        use-starttls yes
        require-starttls no
        dkim-sign yes
        dkim-key /home/pmta/conf/mail/spaceship.com/dkim.pem
        dkim-domain spaceship.com
        dkim-selector dkim
    </domain>
</virtual-mta>

# Pool: round-robin across 3 domains
<virtual-mta-pool outreach-pool>
    virtual-mta vmta-provider
    virtual-mta vmta-hub
    virtual-mta vmta-spaceship
</virtual-mta-pool>

# =============================================================================
# ISP-SPECIFIC RATE LIMITS (conservative for warmup)
# =============================================================================

<domain gmail.com>
    max-msg-rate 100/h
    max-smtp-out 1
    max-msg-per-connection 5
</domain>

<domain googlemail.com>
    max-msg-rate 100/h
    max-smtp-out 1
</domain>

<domain hotmail.com>
    max-msg-rate 100/h
    max-smtp-out 1
    max-msg-per-connection 2
</domain>

<domain outlook.com>
    max-msg-rate 100/h
    max-smtp-out 1
</domain>

<domain yahoo.com>
    max-msg-rate 100/h
    max-smtp-out 1
    max-msg-per-connection 2
</domain>

<domain orange.fr>
    max-msg-rate 80/h
    max-smtp-out 1
</domain>

<domain free.fr>
    max-msg-rate 80/h
    max-smtp-out 1
</domain>

<domain sfr.fr>
    max-msg-rate 80/h
    max-smtp-out 1
</domain>

# Default for all other domains
<domain *>
    max-msg-rate 200/h
    max-smtp-out 2
    max-msg-per-connection 10
    max-errors-per-connection 10
    retry-after 10m
    bounce-after 24h
    use-starttls yes
</domain>

# =============================================================================
# BACKOFF (auto slow down on errors)
# =============================================================================

<pattern-list blocking-errors>
    reply /too many/ mode=backoff
    reply /rate limit/ mode=backoff
    reply /try again later/ mode=backoff
    reply /connection limit/ mode=backoff
    reply /temporarily deferred/ mode=backoff
    reply /service unavailable/ mode=backoff
</pattern-list>

PMTACONFIG

# 5. Set permissions
echo "[5/8] Setting permissions..."
chown -R pmta:pmta /home/pmta/conf
chmod 600 /home/pmta/conf/mail/*/dkim.pem
mkdir -p /var/log/pmta /var/spool/pmta
chown -R pmta:pmta /var/log/pmta /var/spool/pmta

# 6. Systemd service
echo "[6/8] Setting up systemd service..."
cat > /etc/systemd/system/pmta.service << 'SVC'
[Unit]
Description=PowerMTA Mail Transfer Agent
After=network.target

[Service]
Type=simple
ExecStart=/usr/sbin/pmta
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SVC

systemctl daemon-reload
systemctl enable pmta
systemctl restart pmta

# 7. Firewall
echo "[7/8] Configuring firewall..."
ufw allow 25/tcp   # Outbound SMTP
ufw allow 2525/tcp # Inbound from Laravel
ufw allow 22/tcp   # SSH

# 8. Summary
echo ""
echo "==========================================="
echo "PMTA SETUP COMPLETE"
echo "==========================================="
echo ""
echo "Server IP:     $SERVER_IP"
echo "SMTP Port:     2525"
echo "SMTP User:     $PMTA_SMTP_USER"
echo "SMTP Password: $PMTA_SMTP_PASS"
echo ""
echo "SAVE THESE CREDENTIALS! Add to Influenceurs Tracker .env.production:"
echo ""
echo "OUTREACH_PMTA_HOST=$SERVER_IP"
echo "OUTREACH_PMTA_PORT=2525"
echo "OUTREACH_PMTA_USER=$PMTA_SMTP_USER"
echo "OUTREACH_PMTA_PASS=$PMTA_SMTP_PASS"
echo "OUTREACH_SENDING_EMAILS=williams@provider-expat.com,williams@hub-travelers.com,williams@spaceship.com"
echo ""
echo "NEXT STEPS:"
echo "1. Add DNS records for each domain (SPF, DKIM, DMARC) — see output above"
echo "2. Set reverse DNS (PTR) for $SERVER_IP → mail.provider-expat.com"
echo "3. Add the env vars above to .env.production on the Influenceurs Tracker server"
echo "4. Test: pmta show status"
echo "==========================================="
