#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
docker run --rm --network none \
    --env PAYS_TEST_ISOLATED_CONTAINER=1 \
    --volume "$PWD/docker:/fixture:ro" \
    --volume "${PAYS_TEST_CA_BUNDLE:-/etc/ssl/certs/ca-certificates.crt}:/setup/system-ca.pem:ro" \
    --entrypoint sh "${PRESTASHOP_IMAGE:-prestashop/prestashop:9.1.5-8.5}" /fixture/trust.test.sh
