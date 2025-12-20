<?php
require 'db.php';

$user_id = $_SESSION['user_id'];

// 1. Buscar os Sigs que o usuário PERTENCE
$stmt = $pdo->prepare("
    SELECT s.* FROM sigs s
    JOIN sig_memberships m ON s.id = m.sig_id
    WHERE m.user_id = ?
    ORDER BY s.name ASC
");
$stmt->execute([$user_id]);
$my_sigs = $stmt->fetchAll();

// 2. Buscar Perguntas (FEED)
$stmt = $pdo->prepare("
    SELECT q.*, s.name as sig_name, u.username
    FROM questions q
    JOIN sigs s ON q.sig_id = s.id
    JOIN sig_memberships m ON q.sig_id = m.sig_id
    JOIN users u ON q.user_id = u.id
    WHERE m.user_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$user_id]);
$feed_questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Reddora - Feed</title>
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
                <form action="post_action.php" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn btn-sm btn-outline-light">Sair</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row">

            <div class="col-md-3">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-bold">Seus Sigs</div>
                    <div class="list-group list-group-flush">
                        <?php foreach($my_sigs as $sig): ?>
                            <a href="sig.php?id=<?= $sig['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary">s/<?= htmlspecialchars($sig['name']) ?></span>
                            </a>
                        <?php endforeach; ?>

                        <?php if(empty($my_sigs)): ?>
                            <div class="list-group-item text-muted small">Você não segue nenhum Sig.</div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer bg-white text-center">
                        <a href="sigs.php" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-compass"></i> Gerenciar Sigs
                        </a>
                    </div>
                </div>

                <div class="alert alert-info small">
                    Nota: Posts de Sigs que você não segue não aparecem no feed.
                </div>
            </div>

            <div class="col-md-8">

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Nova Discussão</h5>
                        <form action="post_action.php" method="POST">
                            <input type="hidden" name="action" value="create_question">

                            <div class="mb-2">
                                <label class="form-label small text-muted">Postar em qual Sig?</label>
                                <select name="sig_id" class="form-select" required>
                                    <option value="" disabled selected>Selecione um Sig...</option>
                                    <?php foreach($my_sigs as $sig): ?>
                                        <option value="<?= $sig['id'] ?>">s/<?= htmlspecialchars($sig['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <input type="text" name="title" class="form-control" placeholder="Título da pergunta..." required>
                            </div>
                            <div class="mb-2">
                                <textarea name="body" class="form-control" placeholder="Contexto (opcional)..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Postar</button>
                        </form>
                    </div>
                </div>

                <h5 class="mb-3">Seu Feed Personalizado</h5>

                <?php if(empty($feed_questions)): ?>
                    <div class="text-center py-5 text-muted">
                        Nenhuma pergunta encontrada nos seus Sigs. Seja o primeiro a postar!
                    </div>
                <?php endif; ?>

                <?php foreach($feed_questions as $q): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <small class="text-uppercase fw-bold" style="font-size: 0.75rem;">
                                <a href="sig.php?id=<?= $q['sig_id'] ?>" class="text-decoration-none text-primary">
                                    s/<?= htmlspecialchars($q['sig_name']) ?>
                                </a>
                            </small>
                            <small>
                                <a href="profile.php?id=<?= $q['user_id'] ?>" class="text-muted text-decoration-none">
                                    u/<?= htmlspecialchars($q['username']) ?>
                                </a>
                            </small>
                        </div>

                        <h5 class="card-title mt-1">
                            <a href="question.php?id=<?= $q['id'] ?>" class="text-decoration-none text-dark">
                                <?= htmlspecialchars($q['title']) ?>
                            </a>
                        </h5>
                        <p class="card-text text-muted small"><?= htmlspecialchars(substr($q['body'], 0, 120)) ?>...</p>
                        <a href="question.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary">Ver Discussão</a>
                    </div>
                </div>
                <?php endforeach; ?>

            </div> </div> </div> </body>
</html>
