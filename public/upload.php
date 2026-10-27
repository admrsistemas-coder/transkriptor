<?php
require_once __DIR__ . '/../app/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

// Verifica se o limite de post_max_size do PHP foi atingido (faz o $_POST e $_FILES ficarem vazios)
if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $errorMsg = "O arquivo enviado é muito grande e ultrapassou o limite do servidor (post_max_size).";
    header("Location: dashboard.php?error=" . urlencode($errorMsg));
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = "Erro ao enviar o arquivo. Código: " . ($_FILES['file']['error'] ?? 'desconhecido');
    header("Location: dashboard.php?error=" . urlencode($errorMsg));
    exit;
}

$file = $_FILES['file'];
$language = $_POST['language'] ?? 'auto';
$model = $_POST['model'] ?? 'small';

// Validação simples
$allowedExtensions = ['mp4','mkv','avi','mov','webm','mpeg','mp3','wav','m4a','aac','flac','ogg'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    header("Location: dashboard.php?error=" . urlencode("Formato de arquivo não suportado."));
    exit;
}

// Validação MIME com Fileinfo (Segurança)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (strpos($mimeType, 'video/') !== 0 && strpos($mimeType, 'audio/') !== 0 && $mimeType !== 'application/octet-stream') {
    header("Location: dashboard.php?error=" . urlencode("O arquivo não é um áudio ou vídeo válido."));
    exit;
}

// Garante um nome único para o arquivo
$newFilename = uniqid('file_') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = STORAGE_PATH . '/uploads/' . $newFilename;

if (move_uploaded_file($file['tmp_name'], $destPath)) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO transcriptions (user_id, original_filename, stored_filename, mime_type, file_size, language, model, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')");
    $stmt->execute([
        $_SESSION['user_id'],
        $file['name'],
        $newFilename,
        $mimeType,
        $file['size'],
        $language,
        $model
    ]);

    header("Location: dashboard.php?success=1");
} else {
    header("Location: dashboard.php?error=" . urlencode("Erro ao salvar o arquivo no servidor. Verifique permissões."));
}
