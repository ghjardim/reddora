<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - Reddora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="height: 100vh;">

    <div class="card shadow p-4 border-0" style="width: 350px;">
        <h2 class="text-center mb-4 fw-bold" style="font-family: 'Georgia', serif; color: var(--reddora-red);">Reddora</h2>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger p-2 small text-center">Usuário ou senha inválidos.</div>
        <?php endif; ?>
        <?php if(isset($_GET['registered'])): ?>
            <div class="alert alert-success p-2 small text-center">Conta criada! Faça login.</div>
        <?php endif; ?>

        <form action="post_action.php" method="POST">
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold text-uppercase">Usuário</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold text-uppercase">Senha</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold">Entrar</button>
        </form>
        <div class="text-center mt-3">
            <a href="register.php" class="small text-decoration-none">Criar nova conta</a>
        </div>
    </div>

</body>
</html>
