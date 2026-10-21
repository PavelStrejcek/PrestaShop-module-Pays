# Regression tests

Run `php tests/unit.php` (PHP 8.1–8.4) and
`node --test tests/frontend.test.cjs` (Node 18+). Both return nonzero on failure
and report the number of executed tests. Unit tests use a small platform double;
integration tests bootstrap the real PrestaShop classes and use real HTTP requests.

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

The shop and database use named Docker volumes; the module is mounted read-only
as `modules/pays_ps`. PHP test/console commands run as `www-data`, matching the
web server, to avoid creating root-owned Symfony/Smarty cache files.
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

Cloud validation used the verified application layer of the official PrestaShop
9.0.0 image with the existing PHP 8.1.33 runtime, because pulling the entire image
hit the Docker daemon's storage limit. Unit tests also ran on PHP 8.4. The default
Compose stack above is supplied for reproduction; the complete official-image
path was not executed successfully in that storage-constrained daemon.
