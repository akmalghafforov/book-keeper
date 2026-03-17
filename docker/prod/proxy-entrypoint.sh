#!/bin/sh
set -eu

DOMAIN="${PROD_DOMAIN:-taqsimot.test}"
CERT_DIR="/etc/nginx/certs"
CERT_FILE="${CERT_DIR}/site.crt"
KEY_FILE="${CERT_DIR}/site.key"

mkdir -p "${CERT_DIR}"

if [ ! -f "${CERT_FILE}" ] || [ ! -f "${KEY_FILE}" ]; then
    openssl req \
        -x509 \
        -nodes \
        -newkey rsa:2048 \
        -days 3650 \
        -keyout "${KEY_FILE}" \
        -out "${CERT_FILE}" \
        -subj "/CN=${DOMAIN}" \
        -addext "subjectAltName=DNS:${DOMAIN}"
fi

envsubst '${PROD_DOMAIN}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

exec "$@"
