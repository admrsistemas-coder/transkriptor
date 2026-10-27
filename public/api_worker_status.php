<?php
require_once __DIR__ . '/../app/auth.php';
requireLogin();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$pingFile = __DIR__ . '/../storage/worker_ping.txt';
$isOnline = false;

if (file_exists($pingFile)) {
    $lastPing = (int)file_get_contents($pingFile);
    // Considera online se o ping foi nos últimos 15 segundos
    if (time() - $lastPing < 15) {
        $isOnline = true;
    }
}

echo json_encode(['online' => $isOnline]);
