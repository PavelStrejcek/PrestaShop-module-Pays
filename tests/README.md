# Regression tests

Run `php tests/unit.php` (PHP 8.1–8.4) and
`node --test tests/frontend.test.cjs` (Node 18+). Both return nonzero on failure
and report the number of executed tests. Unit tests use a small platform double;
integration tests bootstrap the real PrestaShop classes and use real HTTP requests.

Run `python3 tests/bootstrap.test.py` to exercise readiness, startup failures,
timeouts and test exit-status propagation without Docker or network access.
`bash tests/docker-trust.test.sh` checks a fresh prod/dev cache in an isolated
official PrestaShop 9 container: `www-data` must be able to write its class index,
and the supplied CA bundle must remain unchanged. It mounts no shop/database
volumes. GitHub Actions runs both regression checks before the full integration
suite.

For a disposable Docker shop, with port 8080 free:

```sh
bash tests/docker.sh
```

Requires Docker Compose, Python 3, and readable source files for the container's
web user. The script generates local dummy passwords in ignored `tests/.env`
(mode 0600), starts PrestaShop 9.0.0 and MariaDB 10.11, waits for installation and
a rendered page, installs the module, and runs unit/integration tests. Network
access is needed for Docker Hub, PrestaShop language packs at
`i18n.prestashop-project.org` and the installer's standard module dependencies.
On a Linux runner it uses `/etc/ssl/certs/ca-certificates.crt`; set
`PAYS_TEST_CA_BUNDLE` to another trusted PEM bundle if needed. All TLS checks
remain enabled. PHP and Docker download errors must be resolved, not ignored.
The trust hook gives `www-data` ownership of newly created cache directories as
well as the CA files, because the installer runs as that user. If the installer
container exits, the runner fails immediately and prints container state and
installer/database logs; a running shop that never becomes ready still times out.

The shop and database use named Docker volumes; the module is mounted read-only
as `modules/pays_ps`. PHP test/console commands run as `www-data`, matching the
web server, to avoid creating root-owned Symfony/Smarty cache files.
An empty read-only tmpfs hides `.git` inside the module mount, so Symfony's dev
scanner neither traverses private Git metadata nor exposes it through the shop.
Start an existing stack again with
`docker compose -f tests/compose.yaml --env-file tests/.env up -d`.
Stop only this test stack with `docker compose -f tests/compose.yaml stop`.
To deliberately delete its **disposable test data**, use
`docker compose -f tests/compose.yaml down --volumes`.
Do not use a production database for integration tests.

The integration runner refuses to run without `PAYS_TEST_ALLOW_WRITE=1`. It leaves
test orders for inspection, temporarily installs a mail probe, and uses triggers
to widen concurrency races and simulate database failures. Configuration is
restored in a `finally` block. Only a mail probe installed by the run is uninstalled.
The database account therefore needs `TRIGGER` privilege. Nothing submits a real
payment to Pays. Tests and CI files are excluded from `git archive` packages.

The default official-image stack is validated with PrestaShop 9.0.0, PHP 8.4.12,
MariaDB 10.11 and development mode enabled, including module installation and
upgrade. The cache regression starts with absent prod/dev directories, so a reused
volume cannot hide incorrect ownership. The integration suite also verifies that
published extension hooks exist without subscribing Pays to missing handlers.
