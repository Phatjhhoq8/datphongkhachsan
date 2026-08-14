#!/bin/sh
set -eu

echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
until php -r '
    try {
        $pdo = new PDO(
            sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE")),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD"),
            [PDO::ATTR_TIMEOUT => 2]
        );
        $pdo->query("SELECT 1");
    } catch (Throwable $exception) {
        exit(1);
    }
'; do
    sleep 2
done

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

gosu www-data php artisan migrate --force
gosu www-data php artisan db:seed --force

exec gosu www-data "$@"
