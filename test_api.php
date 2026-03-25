<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/admin/departments', 'GET');
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);
echo "\n--- DEPARTMENTS --- \n";
echo "STATUS: " . $response->getStatusCode() . "\n";
echo "BODY: " . substr($response->getContent(), 0, 500) . "\n";
