<?php
require 'db.php';

if (!isset($_GET['id'])) {
    if (isset($_SESSION['user_id'])) {
        header("Location: profile.php?id=" . $_SESSION['user_id']);
        exit;
    } else {
        header("Location: index.php");
        exit;
    }
}

$profile_id = (int)$_GET['id'];

// 1. Busca dados do Usuário
$stmt = $pdo->prepare("SELECT username, created_at FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$profile_user = $stmt->fetch();

if (!$profile_user) die("Usuário não encontrado.");

// 2. Busca Perguntas
$stmt = $pdo->prepare("
    SELECT q.*, s.name as sig_name
    FROM questions q
    JOIN sigs s ON q.sig_id = s.id
    WHERE q.user_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$profile_id]);
$questions = $stmt->fetchAll();

// 3. Busca Respostas
$stmt = $pdo->prepare("
    SELECT a.*, q.title as question_title, q.id as question_id
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$profile_id]);
$answers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Perfil de <?= htmlspecialchars($profile_user['username']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Reddora</a>
            <div class="d-flex align-items-center text-white">
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="text-white text-decoration-none me-3">
                    <i class="fas fa-user"></i> Meu Perfil
                </a>
                <a href="index.php" class="btn btn-sm btn-outline-light">Voltar ao Feed</a>
            </div>
        </div>
    </nav>

    <div class="container">

        <div class="card shadow-sm mb-4 border-0 border-top border-4 border-primary">
            <div class="card-body d-flex align-items-center">
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-user fa-3x text-secondary"></i>
                </div>
                <div>
                    <h2 class="mb-0"><?= htmlspecialchars($profile_user['username']) ?></h2>
                    <p class="text-muted small mb-0">
                        Membro desde <?= date('d/m/Y', strtotime($profile_user['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <h4 class="mb-3 border-bottom pb-2"><i class="fas fa-question-circle text-primary"></i> Perguntas Feitas</h4>

                <?php if(empty($questions)): ?>
                    <p class="text-muted fst-italic">Nenhuma pergunta feita ainda.</p>
                <?php endif; ?>

                <div class="list-group">
                    <?php foreach($questions as $q): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <h6 class="mb-1">
                                    <a href="question.php?id=<?= $q['id'] ?>" class="text-decoration-none fw-bold text-primary">
                                        <?= htmlspecialchars($q['title']) ?>
                                    </a>
                                </h6>
                                <small class="text-muted ms-2"><?= date('d/m', strtotime($q['created_at'])) ?></small>
                            </div>

                            <div class="mt-1">
                                <a href="sig.php?id=<?= $q['sig_id'] ?>" class="text-decoration-none badge bg-light text-dark border">
                                    s/<?= htmlspecialchars($q['sig_name']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <h4 class="mb-3 border-bottom pb-2"><i class="fas fa-comment-dots text-success"></i> Respostas Dadas</h4>

                <?php if(empty($answers)): ?>
                    <p class="text-muted fst-italic">Nenhuma resposta dada ainda.</p>
                <?php endif; ?>

                <div class="list-group">
                    <?php foreach($answers as $ans): ?>
                        <div class="list-group-item">
                            <small class="text-muted">Em: <a href="question.php?id=<?= $ans['question_id'] ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($ans['question_title']) ?></a></small>
                            <p class="mb-1 mt-1 text-dark" style="font-size: 0.95rem;">
                                "<?= htmlspecialchars(substr($ans['body'], 0, 100)) . (strlen($ans['body']) > 100 ? '...' : '') ?>"
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted"><?= date('d/m/Y', strtotime($ans['created_at'])) ?></small>
                                <span class="badge <?= $ans['votes'] >= 0 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $ans['votes'] ?> votos
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
