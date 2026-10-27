<?php
require_once __DIR__ . '/../app/auth.php';
requireLogin();

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$id = (int)$_GET['id'];
$db = getDB();

$sql = "SELECT * FROM transcriptions WHERE id = ?";
if ($_SESSION['user_role'] !== 'admin') {
    $sql .= " AND user_id = " . $_SESSION['user_id'];
}
$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$t = $stmt->fetch();

if ($t) {
    // Apaga os arquivos físicos
    $baseName = pathinfo($t['stored_filename'], PATHINFO_FILENAME);
    @unlink(STORAGE_PATH . '/uploads/' . $t['stored_filename']);
    @unlink(STORAGE_PATH . '/audio/' . $baseName . '.wav');
    @unlink(STORAGE_PATH . '/transcriptions/' . $baseName . '.json');
    
    // Apaga do banco
    $del = $db->prepare("DELETE FROM transcriptions WHERE id = ?");
    $del->execute([$id]);
}

header("Location: dashboard.php?success=1");
