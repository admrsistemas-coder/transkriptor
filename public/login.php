<?php
require_once __DIR__ . '/../app/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login($email, $password)) {
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "E-mail ou senha inválidos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Transcritor de Vídeo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex align-items-center py-4 bg-pattern" style="min-height: 100vh;">
    
    <main class="form-signin w-100 m-auto" style="max-width: 400px;">
        <div class="card shadow border-0 rounded-4 glass-card">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h1 class="h3 mb-3 fw-bold text-primary">Transcritor</h1>
                    <p class="text-muted">Faça login para acessar a plataforma.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="nome@exemplo.com" required>
                        <label for="email">E-mail</label>
                    </div>
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Senha" required>
                        <label for="password">Senha</label>
                    </div>
                    <button class="btn btn-primary w-100 py-2 fw-semibold shadow-sm" type="submit">Entrar</button>
                    <p class="mt-4 mb-0 text-body-secondary text-center small">Padrão: admin@teste.com / password</p>
                </form>
            </div>
        </div>
    </main>

</body>
</html>
