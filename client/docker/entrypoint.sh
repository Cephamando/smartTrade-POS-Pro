#!/bin/sh
# Prepare writable runtime data, then start Apache.
# The application tree itself stays read-only. Host source is never mounted over it.
set -eu

uploads=/var/www/html/public/uploads
mkdir -p "$uploads/promo"
chown -R www-data:www-data "$uploads"
chmod 0775 "$uploads" "$uploads/promo"

if [ "$#" -eq 0 ]; then
    set -- apache2-foreground
fi

exec "$@"
