#!/bin/sh
# =============================================================
#  docker/entrypoint.sh — Starlight Express container startup
# =============================================================
#
#  Generates /etc/msmtprc from environment variables so PHP's
#  mail() function can route through:
#    • dev:  Mailhog   (SMTP_HOST=mailhog, no auth, no TLS)
#    • prod: real SMTP (set SMTP_USER + SMTP_PASS for auth+TLS)
#
#  Then hands off to the default Apache foreground process.
# =============================================================
set -e

SMTP_HOST="${SMTP_HOST:-mailhog}"
SMTP_PORT="${SMTP_PORT:-1025}"
SMTP_FROM="${SMTP_FROM:-noreply@starlight.com.np}"

# Build auth block conditionally — Mailhog needs no auth/TLS;
# a real SMTP relay needs both.
if [ -n "${SMTP_USER}" ]; then
  AUTH_BLOCK="auth           on
tls            on
tls_trust_file /etc/ssl/certs/ca-certificates.crt
user           ${SMTP_USER}
password       ${SMTP_PASS}"
else
  AUTH_BLOCK="auth           off
tls            off"
fi

cat > /etc/msmtprc << MSMTP_EOF
# Generated at container start — do not edit manually
defaults
logfile        /proc/1/fd/2

account        default
host           ${SMTP_HOST}
port           ${SMTP_PORT}
from           ${SMTP_FROM}
${AUTH_BLOCK}
MSMTP_EOF

chmod 644 /etc/msmtprc

exec apache2-foreground "$@"
