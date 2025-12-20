<?php
require 'db.php';

if (!isset($_GET['id'])) header('Location: index.php');
$q_id = (int)$_GET['id'];

// 1. Busca Pergunta + Nome do Sig + Nome do Usuário (Autor)
$stmt = $pdo->prepare("
    SELECT q.*, s.name as sig_name, u.username
    FROM questions q
    JOIN sigs s ON q.sig_id = s.id
    JOIN users u ON q.user_id = u.id
    WHERE q.id = ?
");
$stmt->execute([$q_id]);
$question = $stmt->fetch();

if (!$question) die("Pergunta não encontrada.");

// 2. Busca Respostas + Nome do Usuário (Autor)
$stmt = $pdo->prepare("
    SELECT a.*, u.username
    FROM answers a
    JOIN users u ON a.user_id = u.id
    WHERE a.question_id = ?
    ORDER BY a.votes DESC, a.created_at ASC
");
$stmt->execute([$q_id]);
$all_answers = $stmt->fetchAll();

// 3. Organiza respostas em árvore
$comments_by_parent = [];
foreach ($all_answers as $ans) {
    $pid = !empty($ans['parent_id']) ? $ans['parent_id'] : 0;
    $comments_by_parent[$pid][] = $ans;
}

// === FUNÇÃO RECURSIVA ===
if (!function_exists('render_comments')) {
    function render_comments($parent_id, $comments_by_parent, $q_id, $level = 0) {
        if (!isset($comments_by_parent[$parent_id])) return;

        foreach ($comments_by_parent[$parent_id] as $ans) {
            $margin = $level * 20;
            $border_class = ($level % 2 == 0) ? 'border-secondary' : 'border-light';
            ?>

            <div class="mb-3" style="margin-left: <?= $margin ?>px;">
                <div class="card bg-light border-start border-3 <?= $border_class ?>">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">

                            <small class="fw-bold text-dark">
                                <i class="fas fa-user-circle text-secondary"></i>
                                <a href="profile.php?id=<?= $ans['user_id'] ?>" class="text-dark text-decoration-none">
                                    <?= htmlspecialchars($ans['username']) ?>
                                </a>
                            </small>

                            <div class="btn-group btn-group-sm">
                                <form action="post_action.php" method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="vote">
                                    <input type="hidden" name="ans_id" value="<?= $ans['id'] ?>">
                                    <input type="hidden" name="q_id" value="<?= $q_id ?>">
                                    <input type="hidden" name="val" value="1">
                                    <button class="btn btn-link text-success p-0 text-decoration-none fw-bold">▲</button>
                                </form>
                                <span class="mx-2 small fw-bold text-dark"><?= $ans['votes'] ?></span>
                                <form action="post_action.php" method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="vote">
                                    <input type="hidden" name="ans_id" value="<?= $ans['id'] ?>">
                                    <input type="hidden" name="q_id" value="<?= $q_id ?>">
                                    <input type="hidden" name="val" value="-1">
                                    <button class="btn btn-link text-danger p-0 text-decoration-none fw-bold">▼</button>
                                </form>
                            </div>
                        </div>

                        <div class="mt-2 text-break">
                            <?= nl2br(htmlspecialchars($ans['body'])) ?>
                        </div>

                        <div class="mt-2">
                            <button class="btn btn-sm btn-link text-decoration-none p-0 small"
                                    onclick="document.getElementById('reply-form-<?= $ans['id'] ?>').classList.toggle('d-none')">
                                <i class="fas fa-reply"></i> Responder
                            </button>
                        </div>

                        <div id="reply-form-<?= $ans['id'] ?>" class="d-none mt-2">
                            <form action="post_action.php" method="POST">
                                <input type="hidden" name="action" value="answer">
                                <input type="hidden" name="question_id" value="<?= $q_id ?>">
                                <input type="hidden" name="parent_id" value="<?= $ans['id'] ?>">
                                <textarea name="body" class="form-control form-control-sm mb-2" rows="2" placeholder="Sua resposta..." required></textarea>
                                <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
                            </form>
                        </div>
                    </div>
                </div>

                <?php render_comments($ans['id'], $comments_by_parent, $q_id, $level + 1); ?>

            </div>
            <?php
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($question['title']) ?></title>
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

    <div class="container mb-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">&larr; Voltar ao Feed</a>

                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <a href="sig.php?id=<?= $question['sig_id'] ?>" class="badge bg-primary text-decoration-none">
    s/<?= htmlspecialchars($question['sig_name']) ?>
</a>

                    <small class="text-muted">
                        Postado por
                        <a href="profile.php?id=<?= $question['user_id'] ?>" class="fw-bold text-dark text-decoration-none">
                            u/<?= htmlspecialchars($question['username']) ?>
                        </a>
                    </small>
                </div>

                <div class="card mb-4 border-primary shadow-sm">
                    <div class="card-body">
                        <h2 class="h4"><?= htmlspecialchars($question['title']) ?></h2>
                        <hr>
                        <p class="mb-0 fs-5"><?= nl2br(htmlspecialchars($question['body'])) ?></p>
                    </div>
                    <div class="card-footer bg-white">
                        <small class="text-muted">Adicione uma resposta pública:</small>
                        <form action="post_action.php" method="POST" class="mt-2">
                            <input type="hidden" name="action" value="answer">
                            <input type="hidden" name="question_id" value="<?= $q_id ?>">
                            <textarea name="body" class="form-control mb-2" rows="3" placeholder="Escreva sua resposta..." required></textarea>
                            <button type="submit" class="btn btn-primary">Postar Resposta</button>
                        </form>
                    </div>
                </div>

                <h5 class="mb-3">Discussão</h5>

                <?php
                    render_comments(0, $comments_by_parent, $q_id);

                    if (empty($all_answers)) {
                        echo "<div class='alert alert-info text-center'>Ninguém respondeu ainda. Seja o primeiro!</div>";
                    }
                ?>

            </div>
        </div>
    </div>
</body>
</html>
