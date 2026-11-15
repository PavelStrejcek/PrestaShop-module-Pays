#!/bin/sh
set -eu
# Install the host CA bundle into PrestaShop's prod/dev cache with www-data ownership.
for cache in /var/www/html/var/cache/prod /var/www/html/var/cache/dev; do
    mkdir -p "$cache"
    cp /setup/system-ca.pem "$cache/cacert.pem"
    chown www-data:www-data /var/www/html/var/cache "$cache" "$cache/cacert.pem"
done
# Block any HTTP access to VCS metadata or docker helper files inside modules/pays_ps.
if [ -d /etc/apache2/conf-enabled ]; then
    cat >/etc/apache2/conf-enabled/pays-dev.conf <<'APACHE'
RedirectMatch 404 /\.git
<Directory "/var/www/html/modules/pays_ps/docker">
    Require all denied
</Directory>
APACHE
fi
