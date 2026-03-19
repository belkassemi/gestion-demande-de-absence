<?php

$baseUrl = 'http://localhost:8000/api';

function request($method, $endpoint, $data = [], $token = null) {
    global $baseUrl;
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'GET') {
        // empty
    }
    
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decoded = json_decode($result, true);
    if ($status >= 400) {
        echo "Error {$status} on {$method} {$endpoint}\n";
        print_r($decoded ?? $result);
    }
    
    return ['status' => $status, 'body' => $decoded];
}

echo "1. Logging in as all roles...\n";
$authEmp = request('POST', '/login', ['email' => 'employe@test.ma', 'password' => 'password123']);
$tokenEmp = $authEmp['body']['token'] ?? null;

$authChef = request('POST', '/login', ['email' => 'chef@test.ma', 'password' => 'password123']);
$tokenChef = $authChef['body']['token'] ?? null;

$authDir = request('POST', '/login', ['email' => 'directeur@test.ma', 'password' => 'password123']);
$tokenDir = $authDir['body']['token'] ?? null;

$authAdmin = request('POST', '/login', ['email' => 'aulaayoune@gmail.com', 'password' => 'aulaayoune@gmail.com']);
$tokenAdmin = $authAdmin['body']['token'] ?? null;

echo "Tokens retrieved.\n";

echo "2. Create Absence Request (Employee)...\n";
$reqCreate = request('POST', '/absence-requests', [
    'absence_type_id' => 1,
    'start_date' => date('Y-m-d', strtotime('+1 day')),
    'end_date' => date('Y-m-d', strtotime('+3 days')),
    'reason' => 'Test approval workflow'
], $tokenEmp);
print_r($reqCreate);
$reqId = $reqCreate['body']['request']['id'];

echo "\n3. Check Chef Pending...\n";
$chefPending = request('GET', '/chef-service/pending-requests', [], $tokenChef);
echo "Chef pending count: " . count($chefPending['body']) . "\n";

echo "4. Chef Approve (Level 1)...\n";
$chefReview = request('POST', "/chef-service/requests/{$reqId}/review", ['action' => 'approve', 'comment' => 'OK Chef'], $tokenChef);
print_r($chefReview);

echo "\n5. Check Directeur Pending...\n";
$dirPending = request('GET', '/directeur/pending-requests', [], $tokenDir);
echo "Directeur pending count: " . count($dirPending['body']) . "\n";

echo "6. Directeur Approve (Level 2)...\n";
$dirReview = request('POST', "/directeur/requests/{$reqId}/review", ['action' => 'approve', 'comment' => 'OK Dir'], $tokenDir);
print_r($dirReview);

echo "\n7. Admin attempts to approve... (Should fail 403 or 404 depending on route middleware)\n";
$adminReview = request('POST', "/chef-service/requests/{$reqId}/review", ['action' => 'approve'], $tokenAdmin);
echo "Admin Review Status: " . $adminReview['status'] . "\n";

echo "\n8. Admin stats...\n";
$adminStats = request('GET', "/admin/statistics", [], $tokenAdmin);
echo "Admin stats loaded ok: " . ($adminStats['status'] === 200 ? 'YES' : 'NO') . "\n";

echo "Done.\n";
