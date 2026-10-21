# Module Pays for PrestaShop 9

Connection to the Pays payment gateway.  

Version **2.0.1**, developed on branch `PS9-dev`. Requires PrestaShop 9 and
PHP 8.1–8.4 (use the PHP versions supported by your particular PrestaShop release).
The original PrestaShop 8 version remains on `master`.

## Installation and upgrade

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
checkout order creation, retry links and PHP 8.4 compatibility. Actual Pays
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
[doc/Navod_k_pouziti_modulu_Pays_v1.4-PS8.pdf](doc/Navod_k_pouziti_modulu_Pays_v1.4-PS8.pdf)

Author:    Pavel Strejček <aplikace@brainweb.cz>  
Copyright: 2019 - 2024 Pavel Strejček  
License:   Licensed under the Open Software License version 3.0  https://opensource.org/licenses/OSL-3.0

Payment gateway operator and support: www.Pays.cz  
Module development: www.BrainWeb.cz
