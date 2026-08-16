<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PLUCK TEST ===\n";

echo "pluck('id'):\n";
$ids1 = App\Models\Booking::query()->pluck('id')->all();
print_r($ids1);

echo "pluck('_id'):\n";
$ids2 = App\Models\Booking::query()->pluck('_id')->all();
print_r($ids2);

echo "foreach on get():\n";
$ids3 = App\Models\Booking::query()->get()->map(fn($b) => $b->id)->all();
print_r($ids3);

$ids4 = App\Models\Booking::query()->get()->map(fn($b) => (string) $b->_id)->all();
print_r($ids4);
