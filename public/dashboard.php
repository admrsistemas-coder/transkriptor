<?php
require_once __DIR__ . '/../app/auth.php';
requireLogin();

$db = getDB();
// Busca as transcrições do usuário
$stmt = $db->prepare("SELECT * FROM transcriptions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$transcriptions = $stmt->fetchAll();

// Se for admin, busca todas
if ($_SESSION['user_role'] === 'admin') {
    $stmt = $db->query("SELECT t.*, u.name as user_name FROM transcriptions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC");
    $transcriptions = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Transcritor de Vídeo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-body-tertiary bg-pattern">

<nav class="navbar navbar-expand-lg bg-dark border-bottom border-secondary mb-4" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="dashboard.php">Transcritor</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">Meus Arquivos</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <span id="worker-status-badge" class="badge bg-secondary me-3" title="Status do Worker" style="cursor:pointer;" onclick="startWorker()">
                    <i class="bi bi-cpu"></i> <span id="worker-status-text">Verificando Worker...</span>
                </span>
                <span class="text-light me-3"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="admin.php" class="btn btn-outline-warning btn-sm me-2"><i class="bi bi-gear"></i> Admin</a>
                <?php endif; ?>
                
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </div>
</nav>

<div class="container pb-5">
    
    <!-- Alertas -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Upload realizado com sucesso! O processamento será iniciado em breve.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-5">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 rounded-4 glass-card">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-4">Nova Transcrição</h5>
                    
                    <form id="upload-form" action="upload.php" method="post" enctype="multipart/form-data">
                        
                        <div id="upload-area" class="upload-area mb-4">
                            <i class="bi bi-cloud-arrow-up display-4 text-secondary mb-3"></i>
                            <h5 class="upload-text">Arraste seu vídeo aqui ou clique para selecionar</h5>
                            <p class="text-muted small mb-0 mt-2">Formatos: MP4, MKV, AVI, MOV, MP3, WAV</p>
                        </div>
                        <input type="file" id="file-input" name="file" class="d-none" accept=".mp4,.mkv,.avi,.mov,.webm,.mpeg,.mp3,.wav,.m4a,.aac,.flac,.ogg" required>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Idioma</label>
                                <select name="language" class="form-select">
                                    <option value="auto">Automático (Detectar)</option>
                                    <option value="pt">Português</option>
                                    <option value="en">Inglês</option>
                                    <option value="es">Espanhol</option>
                                    <option value="fr">Francês</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Modelo</label>
                                <select name="model" class="form-select">
                                    <option value="tiny">Tiny (Mais rápido)</option>
                                    <option value="base">Base</option>
                                    <option value="small" selected>Small (Recomendado)</option>
                                    <option value="medium">Medium (Maior precisão)</option>
                                    <option value="large-v3">Large-v3 (Pesado)</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-play-fill me-1"></i> INICIAR TRANSCRIÇÃO
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <h5 class="fw-bold mb-3">Meus Arquivos</h5>
            <div class="card shadow-sm border-0 rounded-4 glass-card">
                <div class="table-responsive-lg" style="padding-bottom: 20px;">
                    <table class="table table-hover table-borderless align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Arquivo</th>
                                <th>Idioma</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transcriptions)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Nenhum arquivo encontrado.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($transcriptions as $t): ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium text-truncate" style="max-width: 250px;">
                                            <?= htmlspecialchars($t['original_filename']) ?>
                                        </div>
                                        <?php if(isset($t['user_name'])): ?>
                                            <span class="badge bg-secondary opacity-75 fw-normal mt-1"><i class="bi bi-person"></i> <?= htmlspecialchars($t['user_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info text-dark"><?= strtoupper($t['language']) ?></span></td>
                                    <td>
                                        <?php if($t['status'] === 'COMPLETED'): ?>
                                            <span class="badge bg-success">Concluído</span>
                                        <?php elseif($t['status'] === 'ERROR'): ?>
                                            <span class="badge bg-danger" title="<?= htmlspecialchars($t['error_message']) ?>">Erro</span>
                                        <?php else: ?>
                                            <div class="progress" style="height: 20px; min-width: 150px;">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning text-dark fw-bold" id="prog-bar-<?= $t['id'] ?>" role="progressbar" style="width: <?= max(5, $t['progress']) ?>%;"><?= $t['progress'] ?>%</div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($t['status'] === 'COMPLETED'): ?>
                                            <a href="editor.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i> Editar</a>
                                            
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li><a class="dropdown-item" href="download.php?id=<?= $t['id'] ?>&format=txt">Documento TXT</a></li>
                                                    <li><a class="dropdown-item" href="download.php?id=<?= $t['id'] ?>&format=srt">Legenda SRT</a></li>
                                                    <li><a class="dropdown-item" href="download.php?id=<?= $t['id'] ?>&format=vtt">Legenda VTT</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item fw-medium text-danger" href="download.php?id=<?= $t['id'] ?>&format=pdf"><i class="bi bi-file-earmark-pdf"></i> Exportar PDF</a></li>
                                                    <li><a class="dropdown-item fw-medium text-primary" href="download.php?id=<?= $t['id'] ?>&format=docx"><i class="bi bi-file-earmark-word"></i> Exportar DOCX</a></li>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-primary" disabled><i class="bi bi-pencil-square"></i> Editar</button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger ms-1" onclick="if(confirm('Excluir arquivo?')) window.location.href='delete.php?id=<?= $t['id'] ?>';"><i class="bi bi-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
setInterval(async () => {
    try {
        const res = await fetch('api_progress.php');
        const tasks = await res.json();
        let reloadNeeded = false;
        
        tasks.forEach(t => {
            const bar = document.getElementById('prog-bar-' + t.id);
            if (bar) {
                if (t.status === 'COMPLETED' || t.status === 'ERROR') {
                    reloadNeeded = true;
                } else {
                    bar.style.width = t.progress + '%';
                    bar.innerText = t.progress + '%';
                }
            }
        });
        
        if (reloadNeeded) {
            location.reload();
        }
    } catch(e) {}
    
    try {
        const workerRes = await fetch('api_worker_status.php?_t=' + new Date().getTime());
        const workerData = await workerRes.json();
        const badge = document.getElementById('worker-status-badge');
        const text = document.getElementById('worker-status-text');
        
        if (workerData.online) {
            badge.className = 'badge bg-success me-3';
            text.innerText = 'Worker Online';
        } else {
            badge.className = 'badge bg-danger me-3';
            text.innerText = 'Worker Offline';
        }
    } catch(e) {}
}, 2000);

async function startWorker() {
    const badgeText = document.getElementById('worker-status-text').innerText;
    if (badgeText.includes('Offline')) {
        if (confirm('Deseja iniciar o Worker agora?')) {
            document.getElementById('worker-status-text').innerText = 'Iniciando...';
            document.getElementById('worker-status-badge').className = 'badge bg-warning text-dark me-3';
            try {
                await fetch('api_start_worker.php');
            } catch (e) {
                alert('Erro ao tentar iniciar o worker.');
            }
        }
    } else {
        alert('O Worker já está rodando!');
    }
}
</script>
</body>
</html>
