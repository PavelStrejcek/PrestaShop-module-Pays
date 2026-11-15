# Module Pays for PrestaShop 9

Connection to the Pays payment gateway.  

Version **2.0.1** (2026-11-15), developed on branch `PS9-dev`. Requires PrestaShop 9 and
PHP 8.1–8.4 on PrestaShop 9.0, or PHP 8.1–8.5 on PrestaShop 9.1 and later
(always use a PHP version supported by your particular PrestaShop release).
The original PrestaShop 8 version remains on `master`.

## Installation and upgrade

Build an installable ZIP from the **last commit of the current branch**:

```sh
bash scripts/build-package.sh
# Optional output directory (may be outside the repository):
bash scripts/build-package.sh /tmp/pays-release
# Optional separate archive including the docker/ development stack (pays_ps-2.0.1-docker.zip):
bash scripts/build-package.sh --with-docker
```

Requires Bash and Git. The default output is `dist/pays_ps-2.0.1.zip`; the version
is read from committed `pays_ps.php`. Commit production changes before building.
The ZIP contains a single `pays_ps/` directory with the module code, assets,
translations, installation/upgrade/uninstallation files, default settings template,
the PDF user manual (`doc/*.pdf`), license and changelog. It excludes Git/CI files,
`docker/`, tests, build scripts, development documentation, the manual source file
(`doc/*.odt`), local `settings.php`, and untracked files. Passing `--with-docker`
builds a separate `dist/pays_ps-2.0.1-docker.zip` containing `pays_ps/docker/`
(while still excluding `docker/.env`).
An existing ZIP of the same version is replaced only after a successful build.
Upload this ZIP through the PrestaShop module manager.

Install the module in `modules/pays_ps` using the module manager, or run
`php bin/console prestashop:module install pays_ps` from the shop root.
Configure the merchant ID, shop ID and API password in the module settings.
Keep the API password nonempty; unsigned callbacks cannot confirm a payment.

When upgrading an existing installation, back up the shop/database, replace
the module files and run the module manager's upgrade action (or
`php bin/console prestashop:module upgrade pays_ps`). Do **not** uninstall and
reinstall to upgrade: reinstallation resets the module's payment tables.
The 2.0.0 upgrade retains payment history, configuration and order states and
expands the diagnostic-code column without truncating existing codes.
The 2.0.1 upgrade also removes obsolete self-subscriptions while preserving the
custom hooks and subscriptions belonging to currency-conversion extensions.
Recheck the success/error/background URLs displayed in the settings after
upgrading the shop to PrestaShop 9.

## Payment confirmation

Both the browser return and background confirmation use the same authenticated
processor. It verifies all six signed fields before looking up orders, rejects
missing passwords/signatures and malformed parameters, checks the shop/module,
currency and base units, and serializes processing with a MySQL/MariaDB advisory
lock. Payments, receipts, audit messages and order-state changes commit together.
The ordinary order-status email is sent after commit; transport failure is logged
without rebooking a successful payment. Third-party hooks and PrestaShop's other
external side effects do not form a distributed transaction with the database.

Repeated notifications are acknowledged without adding another payment. A late
cancellation cannot undo an already recorded payment or downgrade a paid/shipped
order. Partial payments remain pending until fully paid. Retry links appear only
for unpaid Pays orders in the module's awaiting/unrealized states.
An unsigned browser return can display information only to the customer session
that owns the order; it never records a bank payment or authenticates an anonymous
visitor using just an order reference.

The background endpoint returns 202 on success, 400 for malformed request types,
401 for missing/invalid authentication, 422 for incompatible order/payment data,
and 500/503 for failures that can be retried. Errors contain no raw gateway query,
customer key or database details. HMAC-MD5 is retained because Pays requires it;
signatures are compared with `hash_equals`.

Only a single order per reference is supported. Split-order references are
rejected with 422 for manual reconciliation, rather than crediting just the first
order. The checkout regression covers a normal single-package cart. Test split
shipping and any currency-conversion extension separately before deployment.
Without a conversion hook, the callback currency must equal the order currency;
conversion hooks must return a positive amount and a loaded order-currency object.
Unsigned free-text gateway descriptions are not stored as authenticated evidence.

## Development and tests

### Local development Docker stack (`docker/dev.sh`)

`docker/` provides a persistent local PrestaShop 9 + MariaDB 10.11 stack (default
`prestashop/prestashop:9.1.5-8.5` on `http://localhost:8088/`, isolated from the
disposable `tests/` stack on `8080`) with the module mounted read-write in
`/var/www/html/modules/pays_ps` and generated credentials stored in ignored
`docker/.env` (see [docker/README.md](docker/README.md)):

```sh
# Start MariaDB + PrestaShop 9, install pays_ps and prefill dev credentials
bash docker/dev.sh up

# Show container state, Storefront URL and Back Office URL
bash docker/dev.sh status

# Tail container logs (e.g. follow the shop container)
bash docker/dev.sh logs -f shop

# Open an interactive shell in /var/www/html/modules/pays_ps (as www-data or root)
bash docker/dev.sh shell
bash docker/dev.sh shell --root

# Run PHP CLI inside the container (working directory: modules/pays_ps)
bash docker/dev.sh php -v
bash docker/dev.sh php -l pays_ps.php

# Run PrestaShop's Symfony console as www-data
bash docker/dev.sh console prestashop:module upgrade pays_ps --no-interaction
bash docker/dev.sh console cache:clear

# Run PHP syntax checks and unit tests inside the container
bash docker/dev.sh test

# Simulate an HMAC-MD5 signed background confirmation against the dev shop:
# confirm <ORDER_REF> <AMOUNT_MINOR_UNITS> [STATUS=3] [CURRENCY=CZK] [BASE_UNITS=100] [PAYMENT_ID]
bash docker/dev.sh confirm UXYZ12345 25000
bash docker/dev.sh confirm UXYZ12345 25000 2

# Stop containers while preserving shop and database volumes
bash docker/dev.sh down

# Remove containers and wipe development volumes (e.g. before changing PRESTASHOP_IMAGE)
bash docker/dev.sh reset
```

### Regression suites

No Composer or npm dependencies are required for the regression runners:

```sh
php tests/unit.php
node --test tests/frontend.test.cjs
```

Integration tests use a **disposable installed PrestaShop 9 shop**, its real
database and real HTTP module controllers. They create fixture orders/payments,
install a temporary mail probe and create temporary database triggers to simulate
slow and failed writes. They restore configuration and uninstall a probe installed
by that run; fixture orders remain for diagnosis. Never run them on a live shop.

```sh
# From a PrestaShop 9 root containing this checkout in modules/pays_ps:
php bin/console prestashop:module install pays_ps
PAYS_TEST_ALLOW_WRITE=1 php modules/pays_ps/tests/integration.php
```

The test shop must have demo products/customers/addresses, listen internally on
port 80 with domain `localhost:8080`, and use MySQL/MariaDB with permission to
create triggers. `PRESTASHOP_ROOT` can override the bootstrap directory.
Fixtures use dummy merchant credentials and suppress real mail; they never
contact Pays or initiate a charge. A reproducible Docker test stack is in
[tests/README.md](tests/README.md).

Coverage includes signature tampering, array inputs, anonymous returns, duplicate
and parallel notifications, partial payments, late cancellation, currency/order
mismatches, transaction rollback/retry, post-commit mail, data-preserving upgrades,
checkout order creation, retry links, Hummingbird/Classic frontend scripts and
PHP 8.1–8.5 compatibility. Actual Pays
transactions, external callbacks over public HTTPS, conversion add-ons and other
themes require separate acceptance testing with the merchant's sandbox setup.

![Logo Pays](views/img/pays_ps-payment.png)

## Module functions

- Customer Pays Payment Selection
- Receive response from payment gateway
- Possibility of repeated payment in case of blocked card, etc.
- Overview of received orders for order
- Possibility of payment from order notification
- Possibility of payment from customer order detail
- URL for payment to be copied
- Option to add language versions
- Message logging

## Price conversion for currencies not supported by the payment gateway

If you have prices in a currency that is not supported by the payment gateway,
you can purchase the Pays Conversion module, which converts prices into the correct currency.
More information at https://www.BrainWeb.cz

## Further information

Further information (in Czech) is in the Instructions for Use:  
[doc/Navod_k_pouziti_modulu_Pays_v1.5-PS9.pdf](doc/Navod_k_pouziti_modulu_Pays_v1.5-PS9.pdf) (source: [doc/Navod_k_pouziti_modulu_Pays_v1.5-PS9.odt](doc/Navod_k_pouziti_modulu_Pays_v1.5-PS9.odt))

Author:    Pavel Strejček <aplikace@brainweb.cz>  
Copyright: 2019 - 2026 Pavel Strejček  
License:   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0

Payment gateway operator and support: www.Pays.cz  
Module development: www.BrainWeb.cz
