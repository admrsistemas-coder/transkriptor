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
$transcription = $stmt->fetch();

if (!$transcription || $transcription['status'] !== 'COMPLETED') {
    header("Location: dashboard.php?error=" . urlencode("Transcrição não encontrada ou não finalizada."));
    exit;
}

$jsonPath = STORAGE_PATH . '/transcriptions/' . pathinfo($transcription['stored_filename'], PATHINFO_FILENAME) . '.json';
$segments = [];
if (file_exists($jsonPath)) {
    $segments = json_decode(file_get_contents($jsonPath), true);
} else {
    $segments = [['start' => 0, 'end' => 0, 'text' => $transcription['transcription_text']]];
}

function formatTime($seconds) {
    $ms = round(($seconds - floor($seconds)) * 1000);
    $s = floor($seconds);
    return sprintf('%02d:%02d:%02d,%03d', ($s/3600),($s/60%60), $s%60, $ms);
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de Transcrição - Transcritor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .segment-row { transition: all 0.2s ease; border-left: 3px solid transparent; }
        .segment-row:hover { background-color: rgba(255,255,255,0.05); border-left-color: #0d6efd; }
        .segment-input { background: transparent; border: 1px dashed transparent; color: inherit; width: 100%; resize: none; overflow: hidden; padding: 4px; border-radius: 4px; }
        .segment-input:focus { outline: none; border-color: #0d6efd; background: rgba(255,255,255,0.02); }
        .time-badge { font-family: monospace; font-size: 0.85rem; color: #adb5bd; cursor: pointer; }
    </style>
</head>
<body class="bg-body-tertiary">

<nav class="navbar navbar-expand-lg bg-dark border-bottom border-secondary mb-4" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="dashboard.php">Transcritor</a>
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2"><i class="bi bi-arrow-left"></i> Voltar</a>
            <button class="btn btn-primary btn-sm" id="btn-save"><i class="bi bi-floppy"></i> Salvar Edições</button>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card shadow-sm border-0 rounded-4 glass-card">
                <div class="card-header bg-transparent border-bottom border-secondary py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1 fw-bold"><?= htmlspecialchars($transcription['original_filename']) ?></h5>
                        <small class="text-muted">
                            Idioma: <span class="text-uppercase"><?= $transcription['language'] ?></span> | 
                            Duração: <?= $transcription['duration'] ?> | 
                            Modelo: <?= $transcription['model'] ?>
                        </small>
                    </div>
                    <div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i> Baixar Legenda
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><a class="dropdown-item" href="download.php?id=<?= $transcription['id'] ?>&format=txt">TXT (Texto Puro)</a></li>
                                <li><a class="dropdown-item" href="download.php?id=<?= $transcription['id'] ?>&format=srt">SRT (YouTube, Players)</a></li>
                                <li><a class="dropdown-item" href="download.php?id=<?= $transcription['id'] ?>&format=vtt">VTT (Web)</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item fw-medium text-danger" href="download.php?id=<?= $transcription['id'] ?>&format=pdf"><i class="bi bi-file-earmark-pdf"></i> Exportar PDF</a></li>
                                <li><a class="dropdown-item fw-medium text-primary" href="download.php?id=<?= $transcription['id'] ?>&format=docx"><i class="bi bi-file-earmark-word"></i> Exportar DOCX</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush rounded-bottom-4" id="segments-container">
                        <?php foreach($segments as $index => $seg): ?>
                            <div class="list-group-item segment-row py-3" data-index="<?= $index ?>">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="time-badge">
                                        <i class="bi bi-clock"></i> <?= formatTime($seg['start']) ?> --> <?= formatTime($seg['end']) ?>
                                    </span>
                                </div>
                                <textarea class="segment-input fs-5" rows="1"><?= htmlspecialchars($seg['text']) ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="saveToast" class="toast text-bg-success" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-body d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>Transcrição atualizada com sucesso!</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const textareas = document.querySelectorAll('.segment-input');
    const btnSave = document.getElementById('btn-save');
    const toast = new bootstrap.Toast(document.getElementById('saveToast'));

    textareas.forEach(ta => {
        ta.style.height = 'auto';
        ta.style.height = (ta.scrollHeight) + 'px';
        ta.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });

    btnSave.addEventListener('click', async () => {
        const segments = [];
        const rows = document.querySelectorAll('.segment-row');
        
        rows.forEach(row => {
            const index = row.dataset.index;
            const text = row.querySelector('.segment-input').value.trim();
            segments.push({ index: parseInt(index), text: text });
        });

        btnSave.disabled = true;
        btnSave.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Salvando...';

        try {
            const formData = new URLSearchParams();
            formData.append('id', <?= $transcription['id'] ?>);
            formData.append('segments', JSON.stringify(segments));

            const response = await fetch('save_segments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            });
            
            const result = await response.json();
            if(result.status === 'success') {
                toast.show();
            } else {
                alert('Erro ao salvar: ' + result.message);
            }
        } catch (e) {
            alert('Erro de comunicação.');
        } finally {
            btnSave.disabled = false;
            btnSave.innerHTML = '<i class="bi bi-floppy"></i> Salvar Edições';
        }
    });
});
</script>
</body>
</html>
