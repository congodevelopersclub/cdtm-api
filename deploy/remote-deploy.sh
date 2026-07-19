#!/bin/bash
set -e

DEPLOY_PATH=$1
RELEASE_NAME=$2
RELEASE_DIR="$DEPLOY_PATH/releases/$RELEASE_NAME"

echo "==> Extracting release $RELEASE_NAME"
mkdir -p "$RELEASE_DIR"
tar -xzf "$DEPLOY_PATH/releases/$RELEASE_NAME.tar.gz" -C "$RELEASE_DIR"
rm "$DEPLOY_PATH/releases/$RELEASE_NAME.tar.gz"

echo "==> Linking shared resources"
ln -sfn "$DEPLOY_PATH/_shared/.env" "$RELEASE_DIR/.env"
rm -rf "$RELEASE_DIR/storage"
ln -sfn "$DEPLOY_PATH/_shared/storage" "$RELEASE_DIR/storage"

echo "==> Running migrations"
php "$RELEASE_DIR/artisan" migrate --force

echo "==> Caching config/routes/views"
php "$RELEASE_DIR/artisan" config:cache
php "$RELEASE_DIR/artisan" route:cache
php "$RELEASE_DIR/artisan" view:cache
php "$RELEASE_DIR/artisan" event:cache

echo "==> Granting permission on database file"
chown "www-data:www-data" "/var/www/myapi/releases/$RELEASE_NAME/database"
chown "www-data:www-data" "/var/www/myapi/releases/$RELEASE_NAME/database/database.sqlite"

echo "==> Switching current symlink"
ln -sfn "$RELEASE_DIR" "$DEPLOY_PATH/current"

echo "==> Reloading PHP-FPM (clears OPcache)"
sudo systemctl reload php8.3-fpm

echo "==> Cleaning up old releases (keep last 5)"
cd "$DEPLOY_PATH/releases"
ls -1dt */ | tail -n +6 | xargs -r rm -rf

echo "==> Deploy complete: $RELEASE_NAME"
