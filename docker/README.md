# Vývojový Docker stack pro modul Pays (`pays_ps`)

Trvalé lokální vývojové prostředí s **PrestaShopem 9** a **MariaDB 10.11**, ve kterém je modul připojen v režimu **read-write** (`/var/www/html/modules/pays_ps`) a ve kterém lze přímo spouštět PHP, Symfony konzoli, testy i simulaci platebních notifikací na pozadí.

Stack používá vlastní Docker projekt `pays-ps9-dev` a výchozí port `8088`, takže nekoliduje s jednorázovým integračním stackem v `tests/` (port `8080`).

## Rychlý start

```sh
bash docker/dev.sh up
```

Při prvním spuštění skript:
1. Vygeneruje lokální přístupy do ignorovaného souboru `docker/.env` (práva `0600`).
2. Spustí MariaDB 10.11 a PrestaShop (výchozí `prestashop/prestashop:9.1.5-8.5` na PHP 8.5).
3. Počká na automatickou instalaci e-shopu, nainstaluje modul `pays_ps`, předvyplní testovací údaje brány a vypne reálné odesílání e-mailů (`PS_MAIL_METHOD=3`).
4. Vypíše URL adresy obchodu a administrace (přihlašovací heslo administrátora najdete v `docker/.env`).

## Běžné příkazy pro vývoj a práci v kontejneru

```sh
# Stav kontejnerů a URL adresy
bash docker/dev.sh status

# Interaktivní shell v kontejneru přímo v adresáři modulu (/var/www/html/modules/pays_ps)
bash docker/dev.sh shell
bash docker/dev.sh shell --root

# Spuštění PHP CLI uvnitř kontejneru (např. na stroji bez lokálního PHP)
bash docker/dev.sh php -v
bash docker/dev.sh php -l pays_ps.php

# Spuštění PrestaShop Symfony konzole
bash docker/dev.sh console prestashop:module upgrade pays_ps --no-interaction
bash docker/dev.sh console cache:clear

# Kontrola syntaxe PHP a spuštění unit testů v kontejneru
bash docker/dev.sh test

# Simulace podepsaného potvrzení platby na pozadí (HMAC-MD5 z uloženého PAYS_PS_PASSWORD)
# Formát: confirm <ORDER_REF> <AMOUNT_V_HALERICH> [STATUS=3] [CURRENCY=CZK] [BASE_UNITS=100] [PAYMENT_ID]
bash docker/dev.sh confirm UXYZ12345 25000
bash docker/dev.sh confirm UXYZ12345 25000 2

# Zastavení kontejnerů (data e-shopu i databáze zůstanou zachována)
bash docker/dev.sh down

# Úplné smazání kontejnerů i vývojových volumes (např. před změnou PRESTASHOP_IMAGE)
bash docker/dev.sh reset
```

## Konfigurace verze PrestaShopu a PHP

Před prvním `up` (nebo po `bash docker/dev.sh reset`) lze zkopírovat `docker/.env.example` do `docker/.env` a upravit například `PRESTASHOP_IMAGE` (`prestashop/prestashop:9.1.5-8.5` nebo `prestashop/prestashop:9.0.0-8.4`), `SHOP_PORT` či `PS_LANGUAGE`.

> **Poznámka k balíčkování:** Standardní `bash scripts/build-package.sh` složku `docker/` do produkčního ZIPu **nepřidává**. Pokud chcete sestavit balíček včetně tohoto vývojového kontejneru, použijte `bash scripts/build-package.sh --with-docker`, který vytvoří samostatný archiv `dist/pays_ps-<verze>-docker.zip` (lokální `docker/.env` je vždy vyloučen).
