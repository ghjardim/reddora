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
$active_tab = $_GET['tab'] ?? 'all'; // 'all', 'questions', 'answers'

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

// 4. Lógica da Aba "Perfil" (Mistura tudo)
$mixed_feed = [];
if ($active_tab === 'all') {
    foreach ($questions as $q) {
        $mixed_feed[] = ['type' => 'question', 'date' => $q['created_at'], 'data' => $q];
    }
    foreach ($answers as $a) {
        $mixed_feed[] = ['type' => 'answer', 'date' => $a['created_at'], 'data' => $a];
    }
    // Ordena por data (mais recente primeiro)
    usort($mixed_feed, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
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
            <div class="col-md-8 mx-auto">

                <ul class="nav nav-tabs nav-fill mb-4 bg-white rounded-top shadow-sm">
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab == 'all' ? 'active fw-bold' : '' ?>" href="?id=<?= $profile_id ?>&tab=all">
                            <i class="fas fa-stream"></i> Perfil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab == 'questions' ? 'active fw-bold' : '' ?>" href="?id=<?= $profile_id ?>&tab=questions">
                            <i class="fas fa-question-circle"></i> Perguntas (<?= count($questions) ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_tab == 'answers' ? 'active fw-bold' : '' ?>" href="?id=<?= $profile_id ?>&tab=answers">
                            <i class="fas fa-comment-dots"></i> Respostas (<?= count($answers) ?>)
                        </a>
                    </li>
                </ul>

                <?php if ($active_tab === 'all'): ?>
                    <?php if (empty($mixed_feed)): ?>
                        <div class="text-center py-5 text-muted bg-white border rounded">
                            Usuário sem atividades recentes.
                        </div>
                    <?php endif; ?>

                    <?php foreach ($mixed_feed as $item): ?>
                        <?php if ($item['type'] === 'question'): $q = $item['data']; ?>
                            <div class="card mb-3 shadow-sm border-start border-4 border-primary">
                                <div class="card-body">
                                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">
                                        <i class="fas fa-question-circle text-primary"></i> Perguntou em
                                        <a href="sig.php?id=<?= $q['sig_id'] ?>" class="text-decoration-none">s/<?= htmlspecialchars($q['sig_name']) ?></a>
                                    </small>
                                    <h5 class="mt-2">
                                        <a href="question.php?id=<?= $q['id'] ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($q['title']) ?>
                                        </a>
                                    </h5>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($q['created_at'])) ?></small>
                                </div>
                            </div>
                        <?php else: $ans = $item['data']; ?>
                            <div class="card mb-3 shadow-sm border-start border-4 border-success">
                                <div class="card-body">
                                    <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">
                                        <i class="fas fa-comment text-success"></i> Respondeu em
                                        <a href="question.php?id=<?= $ans['question_id'] ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($ans['question_title']) ?></a>
                                    </small>
                                    <div class="mt-2 text-dark bg-light p-2 rounded small fst-italic">
                                        "<?= htmlspecialchars(substr($ans['body'], 0, 150)) ?>..."
                                    </div>
                                    <div class="d-flex justify-content-between mt-2 align-items-center">
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($ans['created_at'])) ?></small>
                                        <span class="badge bg-secondary"><?= $ans['votes'] ?> votos</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($active_tab === 'questions'): ?>
                    <div class="list-group shadow-sm">
                        <?php foreach($questions as $q): ?>
                            <div class="list-group-item list-group-item-action py-3">
                                <div class="d-flex w-100 justify-content-between mb-1">
                                    <h5 class="mb-1">
                                        <a href="question.php?id=<?= $q['id'] ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($q['title']) ?>
                                        </a>
                                    </h5>
                                    <small class="text-muted"><?= date('d/m', strtotime($q['created_at'])) ?></small>
                                </div>
                                <div class="mt-2">
                                    <a href="sig.php?id=<?= $q['sig_id'] ?>" class="text-decoration-none badge bg-light text-dark border">
                                        s/<?= htmlspecialchars($q['sig_name']) ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($questions)) echo "<div class='p-4 bg-white text-center text-muted'>Nenhuma pergunta.</div>"; ?>
                    </div>
                <?php endif; ?>

                <?php if ($active_tab === 'answers'): ?>
                    <div class="list-group shadow-sm">
                        <?php foreach($answers as $ans): ?>
                            <div class="list-group-item py-3">
                                <small class="text-muted">
                                    Na discussão: <a href="question.php?id=<?= $ans['question_id'] ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($ans['question_title']) ?></a>
                                </small>
                                <p class="mb-1 mt-2 p-2 bg-light rounded text-dark">
                                    <?= nl2br(htmlspecialchars($ans['body'])) ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($ans['created_at'])) ?></small>
                                    <span class="badge <?= $ans['votes'] >= 0 ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $ans['votes'] ?> pontos
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($answers)) echo "<div class='p-4 bg-white text-center text-muted'>Nenhuma resposta.</div>"; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
