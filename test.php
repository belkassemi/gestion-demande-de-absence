<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$chef = App\Models\User::where('email', 'karimi@gmail.com')->first(); 
echo "Total chef requests: " . App\Models\AbsenceRequest::whereHas('user', function($q) use ($chef) { $q->where('chef_service_id', $chef->id); })->count() . "\n";
echo "Pending level 1 requests: " . App\Models\AbsenceRequest::whereHas('user', function($q) use ($chef) { $q->where('chef_service_id', $chef->id); })->where('status', 'pending')->where('current_level', 1)->count() . "\n";
