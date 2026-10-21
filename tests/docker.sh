#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
diagnostics_on_failure() {
    status=$?
    if [ "$status" -ne 0 ]; then
        docker compose ps -a || true
        docker compose logs --no-color --tail 80 shop db || true
    fi
    exit "$status"
}
trap diagnostics_on_failure EXIT
python3 - <<'PY'
import os, secrets
if not os.path.exists('.env'):
    fd = os.open('.env', os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as f:
        f.write('DB_PASSWORD=' + secrets.token_hex(24) + '\nADMIN_PASSWORD=' + secrets.token_hex(20) + '\n')
PY
docker compose up -d
ready=false
for attempt in $(seq 1 180); do
    state=$(docker compose ps -a --format '{{.State}}' shop)
    case "$state" in
        exited|dead|removing|'')
            echo "PrestaShop installer stopped before readiness (container state: ${state:-missing})." >&2
            exit 1
            ;;
    esac
    if docker compose exec -T shop sh -c 'test -f app/config/parameters.php && test ! -d install-dev && curl --fail --silent --show-error http://127.0.0.1/ -H "Host: localhost:8080" -o /tmp/pays-ready.html && grep -q "<html" /tmp/pays-ready.html' 2>/dev/null; then
        ready=true
        break
    fi
    sleep 3
done
if [ "$ready" != true ]; then
    echo 'PrestaShop did not finish installation.' >&2
    exit 1
fi
if ! docker compose exec -T --user www-data shop php -r 'require "config/config.inc.php"; exit(Module::isInstalled("pays_ps") ? 0 : 1);'; then
    docker compose exec -T --user www-data shop php bin/console prestashop:module install pays_ps --no-interaction
fi
docker compose exec -T --user www-data shop php modules/pays_ps/tests/unit.php
docker compose exec -T --user www-data -e PAYS_TEST_ALLOW_WRITE=1 shop php modules/pays_ps/tests/integration.php
