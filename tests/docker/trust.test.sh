#!/bin/sh
set -eu
if [ "${PAYS_TEST_ISOLATED_CONTAINER:-}" != 1 ]; then
    echo 'Run this test only in its disposable Docker container.' >&2
    exit 2
fi
# A new installation has no prod/dev cache yet. Recreate that condition only
# inside the disposable container: no shop or database volume is mounted.
for environment in prod dev; do
    rm -rf "/var/www/html/var/cache/$environment"
done
sh /fixture/trust.sh
for environment in prod dev; do
    cache="/var/www/html/var/cache/$environment"
    cmp /setup/system-ca.pem "$cache/cacert.pem"
    runuser -u www-data -- sh -c 'printf "cache write test\n" > "$1/class_index.php"' sh "$cache"
    echo "PASS $environment cache is writable by www-data and retains the trusted CA bundle"
done
