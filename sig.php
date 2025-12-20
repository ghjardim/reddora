<?php
require 'db.php';

// Se não tiver ID na URL, volta para o início
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$sig_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// 1. Busca Informações do SIG
$stmt = $pdo->prepare("SELECT * FROM sigs WHERE id = ?");
$stmt->execute([$sig_id]);
$sig = $stmt->fetch();

if (!$sig) die("Sig não encontrado.");

// 2. Verifica se o usuário já é membro (para decidir botão Entrar ou Sair)
$stmt = $pdo->prepare("SELECT 1 FROM sig_memberships WHERE user_id = ? AND sig_id = ?");
$stmt->execute([$user_id, $sig_id]);
$is_member = (bool)$stmt->fetch();

// 3. Busca Perguntas DESTE Sig apenas
$stmt = $pdo->prepare("
    SELECT q.*, u.username
    FROM questions q
    JOIN users u ON q.user_id = u.id
    WHERE q.sig_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$sig_id]);
$questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>s/<?= htmlspecialchars($sig['name']) ?> - Reddora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Reddora</a>
            <div class="d-flex align-items-center">
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="text-white text-decoration-none me-3">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                </a>
                <a href="index.php" class="btn btn-sm btn-outline-light">Voltar ao Feed</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

        <div class="card shadow border-0 mb-4">
            <div class="card-body p-4 bg-white rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="fw-bold text-primary mb-0">s/<?= htmlspecialchars($sig['name']) ?></h1>
                        <p class="text-muted mt-1 mb-0 fs-5"><?= htmlspecialchars($sig['description']) ?></p>
                    </div>

                    <div>
                        <?php if ($is_member): ?>
                            <form action="post_action.php" method="POST">
                                <input type="hidden" name="action" value="leave_sig">
                                <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
                                <input type="hidden" name="redirect" value="sig.php?id=<?= $sig['id'] ?>">
                                <button class="btn btn-outline-danger btn-lg px-4 fw-bold rounded-pill">Sair</button>
                            </form>
                        <?php else: ?>
                            <form action="post_action.php" method="POST">
                                <input type="hidden" name="action" value="join_sig">
                                <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
                                <input type="hidden" name="redirect" value="sig.php?id=<?= $sig['id'] ?>">
                                <button class="btn btn-primary btn-lg px-4 fw-bold rounded-pill">Entrar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mx-auto">

                <?php if($is_member): ?>
                    <div class="card mb-4 shadow-sm border-primary">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Criar post em s/<?= htmlspecialchars($sig['name']) ?></h6>
                            <form action="post_action.php" method="POST">
                                <input type="hidden" name="action" value="create_question">
                                <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">

                                <input type="text" name="title" class="form-control mb-2 fw-bold" placeholder="Título interessante..." required>
                                <textarea name="body" class="form-control mb-2" placeholder="Conteúdo do post..." rows="2"></textarea>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary px-4">Postar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary text-center">
                        Você precisa entrar nesta comunidade para postar.
                    </div>
                <?php endif; ?>

                <h4 class="mb-3">Discussões Recentes</h4>

                <?php if(empty($questions)): ?>
                    <div class="text-center py-5 text-muted border rounded bg-white">
                        <i class="fas fa-wind fa-2x mb-2"></i><br>
                        Ainda não há discussões aqui.<br>Seja o primeiro a postar!
                    </div>
                <?php endif; ?>

                <?php foreach($questions as $q): ?>
                <div class="card mb-3 shadow-sm hover-shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span>
                                Postado por
                                <a href="profile.php?id=<?= $q['user_id'] ?>" class="text-decoration-none fw-bold text-dark">
                                    u/<?= htmlspecialchars($q['username']) ?>
                                </a>
                            </span>
                            <span><?= date('d/m H:i', strtotime($q['created_at'])) ?></span>
                        </div>

                        <h4 class="card-title">
                            <a href="question.php?id=<?= $q['id'] ?>" class="text-decoration-none text-dark">
                                <?= htmlspecialchars($q['title']) ?>
                            </a>
                        </h4>

                        <p class="card-text text-secondary">
                            <?= htmlspecialchars(substr($q['body'], 0, 140)) . (strlen($q['body']) > 140 ? '...' : '') ?>
                        </p>

                        <a href="question.php?id=<?= $q['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="fas fa-comments"></i> Ver Discussão
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>
</body>
</html>
