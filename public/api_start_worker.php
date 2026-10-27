<?php
require_once __DIR__ . '/../app/auth.php';
requireLogin();
header('Content-Type: application/json');

// Apenas admins podem iniciar o worker (ou remova a checagem se quiser que qualquer um inicie)
if ($_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
    exit;
}

$batPath = realpath(__DIR__ . '/../start_worker.bat');
if ($batPath) {
    // Executa o .bat abrindo uma nova janela do CMD (funciona se o Apache não for um serviço isolado)
    pclose(popen("start \"\" \"$batPath\"", "r"));
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Arquivo .bat não encontrado.']);
}
