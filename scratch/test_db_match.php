<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

$request = Request::create('/api/v1/hotels', 'GET', [
    'location' => 'Đà Lat',
]);

$response = $app->handle($request);
echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response JSON Hotels Count: ";
$data = json_decode($response->getContent(), true);
echo count($data['data'] ?? []) . "\n";
foreach ($data['data'] ?? [] as $hotel) {
    echo "  - Hotel: {$hotel['name']} | City: {$hotel['city']}\n";
}
