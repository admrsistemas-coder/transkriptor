<?php
require_once __DIR__ . '/../app/auth.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_POST['segments'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$id = (int)$_POST['id'];
$updates = json_decode($_POST['segments'], true);

$db = getDB();
$sql = "SELECT * FROM transcriptions WHERE id = ?";
if ($_SESSION['user_role'] !== 'admin') {
    $sql .= " AND user_id = " . $_SESSION['user_id'];
}
$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$transcription = $stmt->fetch();

if (!$transcription || $transcription['status'] !== 'COMPLETED') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$jsonPath = STORAGE_PATH . '/transcriptions/' . pathinfo($transcription['stored_filename'], PATHINFO_FILENAME) . '.json';
if (!file_exists($jsonPath)) {
    echo json_encode(['status' => 'error', 'message' => 'JSON not found']);
    exit;
}

$segments = json_decode(file_get_contents($jsonPath), true);
$fullTextParts = [];

$updateMap = [];
foreach($updates as $u) {
    $updateMap[$u['index']] = $u['text'];
}

foreach($segments as $k => &$seg) {
    if (isset($updateMap[$k])) {
        $seg['text'] = $updateMap[$k];
    }
    $fullTextParts[] = $seg['text'];
}

file_put_contents($jsonPath, json_encode($segments, JSON_UNESCAPED_UNICODE));

$newText = implode("\n", $fullTextParts);
$upd = $db->prepare("UPDATE transcriptions SET transcription_text = ? WHERE id = ?");
$upd->execute([$newText, $id]);

echo json_encode(['status' => 'success']);
