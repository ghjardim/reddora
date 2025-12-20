<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro - Reddora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card shadow p-4" style="width: 350px;">
        <h3 class="text-center mb-4">Nova Conta</h3>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger p-2 small text-center">Este usuário já existe.</div>
        <?php endif; ?>

        <form action="post_action.php" method="POST">
            <input type="hidden" name="action" value="register">
            <div class="mb-3">
                <label>Escolha um Usuário</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Escolha uma Senha</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Cadastrar</button>
        </form>
        <div class="text-center mt-3">
            <a href="login.php" class="small">Já tenho conta</a>
        </div>
    </div>
</body>
</html>
