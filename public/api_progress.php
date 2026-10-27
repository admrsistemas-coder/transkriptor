<?php
require_once __DIR__ . '/../app/auth.php';
requireLogin();
header('Content-Type: application/json');

$db = getDB();
$sql = "SELECT id, status, progress FROM transcriptions";
if ($_SESSION['user_role'] !== 'admin') {
    $sql .= " WHERE user_id = " . $_SESSION['user_id'];
}
$stmt = $db->query($sql);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($tasks);
