#!/bin/sh
set -eu

DOMAIN="${PROD_DOMAIN:-taqsimot.test}"
CERT_DIR="/etc/nginx/certs"
CERT_FILE="${CERT_DIR}/site.crt"
KEY_FILE="${CERT_DIR}/site.key"
LOCAL_CERT_DIR="/usr/local/share/book-keeper-certs"
SOURCE_CERT_FILE="${PROD_SSL_CERT_FILE:-${LOCAL_CERT_DIR}/${DOMAIN}.pem}"
SOURCE_KEY_FILE="${PROD_SSL_KEY_FILE:-${LOCAL_CERT_DIR}/${DOMAIN}-key.pem}"

mkdir -p "${CERT_DIR}"

if [ -f "${SOURCE_CERT_FILE}" ] && [ -f "${SOURCE_KEY_FILE}" ]; then
    cp "${SOURCE_CERT_FILE}" "${CERT_FILE}"
    cp "${SOURCE_KEY_FILE}" "${KEY_FILE}"
elif [ ! -f "${CERT_FILE}" ] || [ ! -f "${KEY_FILE}" ]; then
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
