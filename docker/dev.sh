#!/usr/bin/env bash
# Manage the local Pays development stack and run commands inside the container.
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$script_dir"

usage() {
    cat <<'EOF'
Usage: bash docker/dev.sh <command> [arguments...]

Commands:
  up          Start MariaDB + PrestaShop 9, install pays_ps and configure dev defaults
  down        Stop containers (keeps shop and database volumes)
  reset       Remove containers and delete development volumes (down --volumes)
  status      Show container status and configured shop URLs
  logs [...]  Show container logs (e.g. `logs -f shop`)
  shell       Open a shell in /var/www/html/modules/pays_ps (`shell --root` for root)
  php [...]   Run PHP CLI inside the container in /var/www/html/modules/pays_ps
  console ... Run PrestaShop `php bin/console ...` as www-data
  test        Run PHP syntax checks and unit tests inside the container
  confirm <ORDER_REF> <AMOUNT_MINOR> [STATUS=3] [CURRENCY=CZK] [BASE_UNITS=100] [PAYMENT_ID]
              Send an HMAC-MD5 signed background confirmation to the dev shop
EOF
}

ensure_env() {
    if [ ! -f .env ]; then
        python3 - <<'PY'
import os, secrets
port = os.environ.get('SHOP_PORT', '8088')
domain = os.environ.get('SHOP_DOMAIN', f'localhost:{port}')
image = os.environ.get('PRESTASHOP_IMAGE', 'prestashop/prestashop:9.1.5-8.5')
lang = os.environ.get('PS_LANGUAGE', 'cs')
country = os.environ.get('PS_COUNTRY', 'cz')
content = (
    f'PRESTASHOP_IMAGE={image}\n'
    f'SHOP_PORT={port}\n'
    f'SHOP_DOMAIN={domain}\n'
    f'PS_LANGUAGE={lang}\n'
    f'PS_COUNTRY={country}\n'
    'PS_DEV_MODE=1\n'
    'PS_FOLDER_ADMIN=admin-dev\n'
    'ADMIN_MAIL=admin@example.test\n'
    f'ADMIN_PASSWORD=Admin1!{secrets.token_hex(8)}\n'
    'DB_NAME=prestashop\n'
    f'DB_PASSWORD={secrets.token_hex(24)}\n'
    'PAYS_MERCHANT=dev-merchant\n'
    'PAYS_SHOP=dev-shop\n'
    f'PAYS_PASSWORD={secrets.token_hex(16)}\n'
)
fd = os.open('.env', os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
with os.fdopen(fd, 'w') as handle:
    handle.write(content)
PY
        echo "Generated local credentials in docker/.env (mode 0600)."
    fi
}

read_env_var() {
    local key="$1" default_val="${2:-}"
    if [ -n "${!key:-}" ]; then
        printf '%s\n' "${!key}"
        return
    fi
    if [ -f .env ]; then
        local val
        val="$(awk -F '=' -v k="$key" '$1 == k {sub(/^[^=]*=/, ""); print; exit}' .env)"
        if [ -n "$val" ]; then
            printf '%s\n' "$val"
            return
        fi
    fi
    printf '%s\n' "$default_val"
}

compose() {
    docker compose -f compose.yaml --env-file .env "$@"
}

compose_exec() {
    local flags=()
    if [ ! -t 0 ] || [ ! -t 1 ]; then
        flags=(-T)
    fi
    compose exec "${flags[@]}" "$@"
}

cmd="${1:-help}"
shift || true

case "$cmd" in
    -h|--help|help)
        usage
        ;;
    up)
        ensure_env
        shop_domain="$(read_env_var SHOP_DOMAIN "localhost:$(read_env_var SHOP_PORT 8088)")"
        admin_dir="$(read_env_var PS_FOLDER_ADMIN admin-dev)"
        admin_mail="$(read_env_var ADMIN_MAIL admin@example.test)"
        pays_merchant="$(read_env_var PAYS_MERCHANT dev-merchant)"
        pays_shop="$(read_env_var PAYS_SHOP dev-shop)"
        pays_password="$(read_env_var PAYS_PASSWORD dev-api-password)"

        compose up -d
        ready=false
        for _ in $(seq 1 180); do
            state="$(compose ps -a --format '{{.State}}' shop)"
            case "$state" in
                exited|dead|removing|'')
                    echo "PrestaShop container stopped before readiness (state: ${state:-missing})." >&2
                    compose ps -a || true
                    compose logs --no-color --tail 80 shop db || true
                    exit 1
                    ;;
            esac
            if compose exec -T shop sh -c 'test -f app/config/parameters.php && test ! -d install-dev && curl --fail --silent --show-error http://127.0.0.1/ -H "Host: '"$shop_domain"'" -o /tmp/pays-dev-ready.html && grep -q "<html" /tmp/pays-dev-ready.html' 2>/dev/null; then
                ready=true
                break
            fi
            sleep 3
        done
        if [ "$ready" != true ]; then
            echo 'PrestaShop did not finish installation in time.' >&2
            compose logs --no-color --tail 80 shop db || true
            exit 1
        fi

        if ! compose exec -T --user www-data shop php -r 'require "config/config.inc.php"; exit(Module::isInstalled("pays_ps") ? 0 : 1);'; then
            compose exec -T --user www-data shop php bin/console prestashop:module install pays_ps --no-interaction
        fi

        compose exec -T --user www-data shop php -r '
            require "config/config.inc.php";
            if (!Configuration::get("PAYS_PS_MERCHANT")) { Configuration::updateValue("PAYS_PS_MERCHANT", $argv[1]); }
            if (!Configuration::get("PAYS_PS_SHOP")) { Configuration::updateValue("PAYS_PS_SHOP", $argv[2]); }
            if (!Configuration::get("PAYS_PS_PASSWORD")) { Configuration::updateValue("PAYS_PS_PASSWORD", $argv[3]); }
            if ((int) Configuration::get("PS_MAIL_METHOD") !== 3) { Configuration::updateValue("PS_MAIL_METHOD", 3); }
        ' -- "$pays_merchant" "$pays_shop" "$pays_password"

        printf 'Dev shop ready:\n  Storefront:  http://%s/\n  Back Office: http://%s/%s/\n  Admin user:  %s (password in docker/.env)\n' \
            "$shop_domain" "$shop_domain" "$admin_dir" "$admin_mail"
        ;;
    down)
        if [ -f .env ]; then
            compose stop
        fi
        ;;
    reset)
        if [ -f .env ]; then
            compose down --volumes
        fi
        ;;
    status)
        ensure_env
        shop_domain="$(read_env_var SHOP_DOMAIN "localhost:$(read_env_var SHOP_PORT 8088)")"
        admin_dir="$(read_env_var PS_FOLDER_ADMIN admin-dev)"
        compose ps -a
        printf 'Storefront:  http://%s/\nBack Office: http://%s/%s/\n' "$shop_domain" "$shop_domain" "$admin_dir"
        ;;
    logs)
        ensure_env
        compose logs "$@"
        ;;
    shell)
        ensure_env
        user="www-data"
        if [ "${1:-}" = "--root" ]; then
            user="root"
            shift
        fi
        compose_exec --user "$user" -w /var/www/html/modules/pays_ps shop bash "$@"
        ;;
    php)
        ensure_env
        compose_exec --user www-data -w /var/www/html/modules/pays_ps shop php "$@"
        ;;
    console)
        ensure_env
        compose_exec --user www-data -w /var/www/html shop php bin/console "$@"
        ;;
    test)
        ensure_env
        compose exec -T --user www-data -w /var/www/html/modules/pays_ps shop sh -c '
            set -eu
            find . -path "./.git" -prune -o -name "*.php" -print0 | xargs -0 -n1 php -d error_reporting=32767 -l >/dev/null
            echo "PHP syntax OK"
            if [ -f tests/unit.php ]; then
                php tests/unit.php
            fi
        '
        ;;
    confirm)
        if [ "$#" -lt 2 ] || [ "$#" -gt 6 ]; then
            echo 'Usage: bash docker/dev.sh confirm <ORDER_REF> <AMOUNT_MINOR> [STATUS=3] [CURRENCY=CZK] [BASE_UNITS=100] [PAYMENT_ID]' >&2
            exit 2
        fi
        ensure_env
        shop_domain="$(read_env_var SHOP_DOMAIN "localhost:$(read_env_var SHOP_PORT 8088)")"
        order_ref="$1"
        amount="$2"
        status="${3:-3}"
        currency="${4:-CZK}"
        base_units="${5:-100}"
        payment_id="${6:-}"
        compose exec -T --user www-data -w /var/www/html shop php -r '
            $_SERVER["HTTP_HOST"] = $argv[1];
            $_SERVER["SERVER_NAME"] = preg_replace("/:.*/", "", $argv[1]);
            $_SERVER["REQUEST_URI"] = "/";
            require "config/config.inc.php";
            $password = (string) Configuration::get("PAYS_PS_PASSWORD");
            if ($password === "") {
                fwrite(STDERR, "PAYS_PS_PASSWORD is empty in PrestaShop configuration.\n");
                exit(1);
            }
            $paymentId = $argv[7] !== "" ? $argv[7] : (string) random_int(1000000, 2000000000);
            $payload = [
                "PaymentOrderID" => $paymentId,
                "MerchantOrderNumber" => $argv[2],
                "PaymentOrderStatusID" => $argv[4],
                "CurrencyID" => $argv[5],
                "Amount" => $argv[3],
                "CurrencyBaseUnits" => $argv[6],
            ];
            $payload["hash"] = hash_hmac("md5", implode("", $payload), $password);
            $url = "http://127.0.0.1/index.php?" . http_build_query(["fc" => "module", "module" => "pays_ps", "controller" => "confirm"] + $payload);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ["Host: " . $argv[1]],
            ]);
            $body = curl_exec($ch);
            if ($body === false) {
                fwrite(STDERR, "cURL error: " . curl_error($ch) . "\n");
                exit(1);
            }
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            printf("PaymentOrderID: %s\nHTTP %d: %s\n", $paymentId, $code, trim($body));
            Cache::clean("*");
            $orders = Order::getByReference($argv[2]);
            if ($orders instanceof PrestaShopCollection && $orders->count() === 1) {
                $order = $orders->getFirst();
                printf("Order #%d (%s): state=%d, paid=%.2f\n", (int) $order->id, $order->reference, (int) $order->current_state, (float) $order->getTotalPaid());
            }
            exit($code === 202 ? 0 : 1);
        ' -- "$shop_domain" "$order_ref" "$amount" "$status" "$currency" "$base_units" "$payment_id"
        ;;
    *)
        echo "Unknown command: $cmd" >&2
        usage >&2
        exit 2
        ;;
esac
