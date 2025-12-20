<?php
require 'db.php';

// Redirecionamentos de segurança
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
$active_tab = $_GET['tab'] ?? 'all';

// 1. Busca Usuário
$stmt = $pdo->prepare("SELECT username, created_at FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$profile_user = $stmt->fetch();
if (!$profile_user) die("Usuário não encontrado.");

// 2. Busca Perguntas
$stmt = $pdo->prepare("SELECT q.*, s.name as sig_name FROM questions q JOIN sigs s ON q.sig_id = s.id WHERE q.user_id = ? ORDER BY q.created_at DESC");
$stmt->execute([$profile_id]);
$questions = $stmt->fetchAll();

// 3. Busca Respostas
$stmt = $pdo->prepare("SELECT a.*, q.title as question_title, q.id as question_id FROM answers a JOIN questions q ON a.question_id = q.id WHERE a.user_id = ? ORDER BY a.created_at DESC");
$stmt->execute([$profile_id]);
$answers = $stmt->fetchAll();

// 4. Busca Sigs que o usuário participa
$stmt = $pdo->prepare("
    SELECT s.* FROM sigs s
    JOIN sig_memberships m ON s.id = m.sig_id
    WHERE m.user_id = ?
    ORDER BY s.name ASC
");
$stmt->execute([$profile_id]);
$user_sigs = $stmt->fetchAll();

// 5. Calcula Karma
$stmt = $pdo->prepare("SELECT SUM(votes) FROM answers WHERE user_id = ?");
$stmt->execute([$profile_id]);
$total_karma = $stmt->fetchColumn() ?: 0;

// Lógica de Feed Misto
$mixed_feed = [];
if ($active_tab === 'all') {
    foreach ($questions as $q) $mixed_feed[] = ['type' => 'question', 'date' => $q['created_at'], 'data' => $q];
    foreach ($answers as $a) $mixed_feed[] = ['type' => 'answer', 'date' => $a['created_at'], 'data' => $a];
    usort($mixed_feed, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Perfil de <?= htmlspecialchars($profile_user['username']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Reddora</a>
            <div class="d-flex align-items-center">
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="text-white text-decoration-none me-3">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                </a>
                <a href="index.php" class="btn btn-sm btn-outline-light opacity-75">Voltar</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

        <div class="card shadow-sm mb-4 border-0" style="background: linear-gradient(135deg, var(--reddora-dark) 0%, #34495e 100%); color: white;">
            <div class="card-body p-4 d-flex align-items-center">
                <div class="bg-white rounded-circle d-flex align-items-center justify-content-center me-4" style="width: 90px; height: 90px; color: var(--reddora-dark);">
                    <i class="fas fa-user fa-3x"></i>
                </div>
                <div>
                    <h2 class="mb-1 fw-bold"><?= htmlspecialchars($profile_user['username']) ?></h2>
                    <p class="mb-0 opacity-75">
                        <i class="far fa-calendar-alt me-1"></i> Membro desde <?= date('d/m/Y', strtotime($profile_user['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row">

            <div class="col-lg-8 mb-4">

                <ul class="nav nav-tabs nav-fill mb-4 border-bottom-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab == 'all' ? 'active fw-bold' : '' ?>" href="?id=<?= $profile_id ?>&tab=all" style="color: var(--reddora-dark);">
                            <i class="fas fa-stream"></i> Geral
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab == 'questions' ? 'active fw-bold' : '' ?>" href="?id=<?= $profile_id ?>&tab=questions" style="color: var(--reddora-dark);">
                            Perguntas <span class="badge bg-secondary rounded-pill"><?= count($questions) ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab == 'answers' ? 'active fw-bold' : '' ?>" href="?id=<?= $profile_id ?>&tab=answers" style="color: var(--reddora-dark);">
                            Respostas <span class="badge bg-secondary rounded-pill"><?= count($answers) ?></span>
                        </a>
                    </li>
                </ul>

                <?php if ($active_tab === 'all'): ?>
                    <?php if (empty($mixed_feed)): ?>
                        <div class="text-center py-5 text-muted bg-white border rounded">Este usuário ainda não postou nada.</div>
                    <?php endif; ?>

                    <div class="list-group shadow-sm">
                        <?php foreach ($mixed_feed as $item): ?>

                            <?php if ($item['type'] === 'question'): $q = $item['data']; ?>
                                <div class="list-group-item list-group-item-action py-3 border-0 border-bottom">
                                    <div class="d-flex w-100 justify-content-between mb-1">
                                        <h5 class="mb-1">
                                            <a href="question.php?id=<?= $q['id'] ?>" class="question-link">
                                                <?= htmlspecialchars($q['title']) ?>
                                            </a>
                                        </h5>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($q['created_at'])) ?></small>
                                    </div>
                                    <div class="mt-2 text-muted small">
                                        <i class="fas fa-question-circle"></i> Perguntou em
                                        <a href="sig.php?id=<?= $q['sig_id'] ?>" class="text-decoration-none fw-bold" style="color: var(--reddora-dark);">
                                            s/<?= htmlspecialchars($q['sig_name']) ?>
                                        </a>
                                    </div>
                                </div>

                            <?php else: $ans = $item['data']; ?>
                                <div class="list-group-item py-3 border-0 border-bottom">
                                    <small class="text-muted">
                                        <i class="fas fa-comment"></i> Respondeu em:
                                        <a href="question.php?id=<?= $ans['question_id'] ?>" class="text-dark fw-bold text-decoration-none">
                                            <?= htmlspecialchars($ans['question_title']) ?>
                                        </a>
                                    </small>
                                    <p class="mb-1 mt-2 text-dark">
                                        <?= nl2br(htmlspecialchars(substr($ans['body'], 0, 200))) . (strlen($ans['body']) > 200 ? '...' : '') ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($ans['created_at'])) ?></small>
                                        <span class="badge bg-light text-dark border"><?= $ans['votes'] ?> pts</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($active_tab === 'questions'): ?>
                    <div class="list-group shadow-sm">
                        <?php foreach($questions as $q): ?>
                            <div class="list-group-item list-group-item-action py-3 border-0 border-bottom">
                                <div class="d-flex w-100 justify-content-between mb-1">
                                    <h5 class="mb-1">
                                        <a href="question.php?id=<?= $q['id'] ?>" class="question-link">
                                            <?= htmlspecialchars($q['title']) ?>
                                        </a>
                                    </h5>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($q['created_at'])) ?></small>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <i class="fas fa-question-circle"></i> Perguntou em
                                    <a href="sig.php?id=<?= $q['sig_id'] ?>" class="text-decoration-none fw-bold" style="color: var(--reddora-dark);">
                                        s/<?= htmlspecialchars($q['sig_name']) ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($questions)) echo "<div class='p-4 bg-white text-center text-muted border rounded'>Nenhuma pergunta.</div>"; ?>
                    </div>
                <?php endif; ?>

                <?php if ($active_tab === 'answers'): ?>
                    <div class="list-group shadow-sm">
                        <?php foreach($answers as $ans): ?>
                            <div class="list-group-item py-3 border-0 border-bottom">
                                <small class="text-muted">
                                    <i class="fas fa-comment"></i> Respondeu em:
                                    <a href="question.php?id=<?= $ans['question_id'] ?>" class="text-dark fw-bold text-decoration-none"><?= htmlspecialchars($ans['question_title']) ?></a>
                                </small>
                                <p class="mb-1 mt-2 text-dark">
                                    <?= nl2br(htmlspecialchars($ans['body'])) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($ans['created_at'])) ?></small>
                                    <span class="badge <?= $ans['votes'] >= 0 ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $ans['votes'] ?> pts
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($answers)) echo "<div class='p-4 bg-white text-center text-muted border rounded'>Nenhuma resposta.</div>"; ?>
                    </div>
                <?php endif; ?>

            </div>

            <div class="col-lg-4">

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-bold text-uppercase small text-muted">Estatísticas</div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h4 fw-bold mb-0" style="color: var(--reddora-red)"><?= $total_karma ?></div>
                                <small class="text-muted text-uppercase" style="font-size:0.65rem;">Karma</small>
                            </div>
                            <div class="col-4 border-start border-end">
                                <div class="h4 fw-bold text-dark mb-0"><?= count($questions) ?></div>
                                <small class="text-muted text-uppercase" style="font-size:0.65rem;">Perguntas</small>
                            </div>
                            <div class="col-4">
                                <div class="h4 fw-bold text-dark mb-0"><?= count($answers) ?></div>
                                <small class="text-muted text-uppercase" style="font-size:0.65rem;">Respostas</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white fw-bold text-uppercase small text-muted d-flex justify-content-between align-items-center">
                        <span>Comunidades</span>
                        <span class="badge bg-light text-dark border rounded-pill"><?= count($user_sigs) ?></span>
                    </div>

                    <div class="list-group list-group-flush">
                        <?php foreach($user_sigs as $sig): ?>
                            <a href="sig.php?id=<?= $sig['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center border-0">
                                <span class="fw-bold" style="color: var(--reddora-dark);">s/<?= htmlspecialchars($sig['name']) ?></span>
                            </a>
                        <?php endforeach; ?>

                        <?php if(empty($user_sigs)): ?>
                            <div class="list-group-item text-muted small py-3 border-0 fst-italic">
                                Não participa de nenhuma comunidade.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
