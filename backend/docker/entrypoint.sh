#!/bin/sh
set -eu

echo "Đang chờ MongoDB replica set..."
until php -r '
    try {
        $manager = new MongoDB\Driver\Manager(getenv("MONGODB_URI"));
        $result = $manager->executeCommand("admin", new MongoDB\Driver\Command(["hello" => 1]))->toArray()[0];
        if (empty($result->isWritablePrimary) || empty($result->setName)) {
            exit(1);
        }
    } catch (Throwable $exception) {
        exit(1);
    }
'; do
    sleep 2
done

echo "Đang chờ MySQL..."
until php -r '
    try {
        $dsn = "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE");
        $pdo = new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Throwable $exception) {
        exit(1);
    }
'; do
    sleep 2
done

mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -e public/storage ] || [ -L public/storage ]; then
    rm -rf public/storage
fi
ln -s ../storage/app/public public/storage

gosu www-data php artisan migrate --force
gosu www-data php artisan db:seed --force

exec gosu www-data "$@"
