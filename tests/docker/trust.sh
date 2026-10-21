#!/bin/sh
set -eu
# Use the runner's trust store, including a managed proxy CA when present.
# PrestaShop keeps a separate curl trust cache. Do not disable verification.
for cache in /var/www/html/var/cache/prod /var/www/html/var/cache/dev; do
    mkdir -p "$cache"
    cp /setup/system-ca.pem "$cache/cacert.pem"
    chown www-data:www-data "$cache/cacert.pem"
done
