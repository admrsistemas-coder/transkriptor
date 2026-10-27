<?php
require_once __DIR__ . '/../app/auth.php';
requireLogin();

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();

// Limpeza manual de arquivos > 7 dias
if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
    $stmt = $db->query("SELECT * FROM transcriptions WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $oldFiles = $stmt->fetchAll();
    
    foreach($oldFiles as $t) {
        $baseName = pathinfo($t['stored_filename'], PATHINFO_FILENAME);
        @unlink(STORAGE_PATH . '/uploads/' . $t['stored_filename']);
        @unlink(STORAGE_PATH . '/audio/' . $baseName . '.wav');
        @unlink(STORAGE_PATH . '/transcriptions/' . $baseName . '.json');
        
        $del = $db->prepare("DELETE FROM transcriptions WHERE id = ?");
        $del->execute([$t['id']]);
    }
    
    header('Location: admin.php?msg=cleanup_done&count=' . count($oldFiles));
    exit;
}

$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalTranscriptions = $db->query("SELECT COUNT(*) FROM transcriptions")->fetchColumn();
$transcriptions = $db->query("SELECT t.*, u.name as username FROM transcriptions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração - Transcritor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-body-tertiary">

<nav class="navbar navbar-expand-lg bg-dark border-bottom border-secondary mb-4" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="dashboard.php"><i class="bi bi-mic"></i> Transcritor Admin</a>
        <div class="d-flex align-items-center">
            <span class="text-light me-3"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">Voltar ao App</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </div>
</nav>

<div class="container">
    <?php if(isset($_GET['msg']) && $_GET['msg'] === 'cleanup_done'): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> Limpeza automática concluída! <strong><?= (int)$_GET['count'] ?></strong> transcrições antigas foram deletadas.</div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card glass-card text-center py-4">
                <h1 class="display-4 text-primary fw-bold"><?= $totalUsers ?></h1>
                <p class="text-muted mb-0">Usuários Registrados</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card glass-card text-center py-4">
                <h1 class="display-4 text-success fw-bold"><?= $totalTranscriptions ?></h1>
                <p class="text-muted mb-0">Total de Transcrições</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card glass-card text-center py-4">
                <h1 class="display-4 text-warning fw-bold"><i class="bi bi-stars"></i></h1>
                <a href="admin.php?action=cleanup" class="btn btn-warning mt-2 w-75 mx-auto fw-bold" onclick="return confirm('Apagar transcrições de todos os usuários com mais de 7 dias?')"><i class="bi bi-trash"></i> Forçar Limpeza (+7 dias)</a>
            </div>
        </div>
    </div>

    <div class="card glass-card">
        <div class="card-header bg-transparent border-bottom border-secondary">
            <h5 class="mb-0 py-2"><i class="bi bi-database"></i> Servidor Global - Todas as Transcrições</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive-lg">
                <table class="table table-hover table-dark mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Arquivo</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th class="text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transcriptions) === 0): ?>
                            <tr><td colspan="5" class="text-center py-4">Nenhuma transcrição encontrada.</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach($transcriptions as $t): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($t['username']) ?></span></td>
                            <td><?= htmlspecialchars($t['original_filename']) ?></td>
                            <td>
                                <?php if($t['status'] === 'COMPLETED'): ?>
                                    <span class="badge bg-success">Concluído</span>
                                <?php elseif($t['status'] === 'ERROR'): ?>
                                    <span class="badge bg-danger">Erro</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Processando</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                            <td class="text-end">
                                <a href="delete.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ATENÇÃO: Excluir este arquivo vai apagá-lo da conta do usuário. Tem certeza?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
