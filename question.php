<?php
require 'db.php';

if (!isset($_GET['id'])) header('Location: index.php');
$q_id = (int)$_GET['id'];

// 1. Busca Pergunta + Autor
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

// 2. Busca TODAS as Respostas
$stmt = $pdo->prepare("
    SELECT a.*, u.username
    FROM answers a
    JOIN users u ON a.user_id = u.id
    WHERE a.question_id = ?
    ORDER BY a.votes DESC, a.created_at ASC
");
$stmt->execute([$q_id]);
$all_answers = $stmt->fetchAll();

// 3. Organiza em Árvore
$comments_by_parent = [];
foreach ($all_answers as $ans) {
    $pid = !empty($ans['parent_id']) ? $ans['parent_id'] : 0;
    $comments_by_parent[$pid][] = $ans;
}

// === FUNÇÃO RECURSIVA (Para renderizar o conteúdo colapsado) ===
// Esta função só desenha do Nível 1 para baixo (Replies)
if (!function_exists('render_replies')) {
    function render_replies($parent_id, $comments_by_parent, $q_id) {
        if (!isset($comments_by_parent[$parent_id])) return;

        foreach ($comments_by_parent[$parent_id] as $ans) {
            ?>
            <div class="mt-3 ps-3 border-start border-2">
                <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                    <small class="fw-bold">
                        <a href="profile.php?id=<?= $ans['user_id'] ?>" class="text-dark text-decoration-none">
                            <?= htmlspecialchars($ans['username']) ?>
                        </a>
                    </small>
                    <div class="small">
                        <span class="fw-bold text-muted"><?= $ans['votes'] ?> pts</span>
                        <form action="post_action.php" method="POST" class="d-inline ms-1">
                            <input type="hidden" name="action" value="vote">
                            <input type="hidden" name="ans_id" value="<?= $ans['id'] ?>">
                            <input type="hidden" name="q_id" value="<?= $q_id ?>">
                            <input type="hidden" name="val" value="1">
                            <button class="btn btn-link p-0 text-success text-decoration-none">▲</button>
                        </form>
                        <form action="post_action.php" method="POST" class="d-inline">
                            <input type="hidden" name="action" value="vote">
                            <input type="hidden" name="ans_id" value="<?= $ans['id'] ?>">
                            <input type="hidden" name="q_id" value="<?= $q_id ?>">
                            <input type="hidden" name="val" value="-1">
                            <button class="btn btn-link p-0 text-danger text-decoration-none">▼</button>
                        </form>
                    </div>
                </div>

                <div class="p-2 text-dark small">
                    <?= nl2br(htmlspecialchars($ans['body'])) ?>
                </div>

                <button class="btn btn-sm btn-link text-decoration-none p-0 ps-2 small mb-2"
                        onclick="document.getElementById('reply-form-<?= $ans['id'] ?>').classList.toggle('d-none')">
                    Responder
                </button>

                <div id="reply-form-<?= $ans['id'] ?>" class="d-none ms-2 mb-2">
                    <form action="post_action.php" method="POST">
                        <input type="hidden" name="action" value="answer">
                        <input type="hidden" name="question_id" value="<?= $q_id ?>">
                        <input type="hidden" name="parent_id" value="<?= $ans['id'] ?>">
                        <textarea name="body" class="form-control form-control-sm mb-1" rows="2" required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm py-0" style="font-size: 0.7rem;">Enviar</button>
                    </form>
                </div>

                <?php render_replies($ans['id'], $comments_by_parent, $q_id); ?>
            </div>
            <?php
        }
    }
}

// Função auxiliar para contar filhos totais (apenas visual)
function count_children($parent_id, $comments_by_parent) {
    if (!isset($comments_by_parent[$parent_id])) return 0;
    $count = count($comments_by_parent[$parent_id]);
    foreach ($comments_by_parent[$parent_id] as $child) {
        $count += count_children($child['id'], $comments_by_parent);
    }
    return $count;
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
                <a href="index.php" class="btn btn-sm btn-outline-light">Feed</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="row">
            <div class="col-md-8 mx-auto">

                <div class="mb-2">
                    <a href="sig.php?id=<?= $question['sig_id'] ?>" class="badge bg-primary text-decoration-none">
                        s/<?= htmlspecialchars($question['sig_name']) ?>
                    </a>
                </div>

                <h1 class="h3 fw-bold mb-3"><?= htmlspecialchars($question['title']) ?></h1>

                <div class="d-flex align-items-center mb-3">
                    <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 30px; height: 30px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="small">
                        <a href="profile.php?id=<?= $question['user_id'] ?>" class="fw-bold text-dark text-decoration-none">
                            <?= htmlspecialchars($question['username']) ?>
                        </a>
                        <span class="text-muted mx-1">&bull;</span>
                        <span class="text-muted"><?= date('d/m/Y', strtotime($question['created_at'])) ?></span>
                    </div>
                </div>

                <div class="fs-5 text-dark mb-4">
                    <?= nl2br(htmlspecialchars($question['body'])) ?>
                </div>

                <div class="d-flex justify-content-between align-items-center border-top border-bottom py-2 mb-4">
                    <div class="fw-bold text-muted small">
                        <?= isset($comments_by_parent[0]) ? count($comments_by_parent[0]) : 0 ?> Respostas
                    </div>
                    <button class="btn btn-primary rounded-pill px-4" type="button" data-bs-toggle="collapse" data-bs-target="#mainReplyForm">
                        <i class="fas fa-pen"></i> Responder
                    </button>
                </div>

                <div class="collapse mb-4" id="mainReplyForm">
                    <div class="card bg-light border-0 p-3">
                        <form action="post_action.php" method="POST">
                            <input type="hidden" name="action" value="answer">
                            <input type="hidden" name="question_id" value="<?= $q_id ?>">
                            <textarea name="body" class="form-control mb-2" rows="3" placeholder="Escreva sua resposta..." required></textarea>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-sm">Postar Resposta</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (isset($comments_by_parent[0])): ?>
                    <?php foreach ($comments_by_parent[0] as $root_ans): ?>
                        <div class="card mb-3 shadow-sm border-0">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light border rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 32px; height: 32px;">
                                            <?= strtoupper(substr($root_ans['username'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <a href="profile.php?id=<?= $root_ans['user_id'] ?>" class="fw-bold text-dark text-decoration-none d-block lh-1">
                                                <?= htmlspecialchars($root_ans['username']) ?>
                                            </a>
                                            <small class="text-muted" style="font-size: 0.75rem;">
                                                <?= date('d M', strtotime($root_ans['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3 text-dark">
                                    <?= nl2br(htmlspecialchars($root_ans['body'])) ?>
                                </div>

                                <div class="d-flex align-items-center bg-light rounded-pill px-2 py-1" style="width: fit-content;">
                                    <form action="post_action.php" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="vote">
                                        <input type="hidden" name="ans_id" value="<?= $root_ans['id'] ?>">
                                        <input type="hidden" name="q_id" value="<?= $q_id ?>">
                                        <input type="hidden" name="val" value="1">
                                        <button class="btn btn-sm text-primary fw-bold border-0"><i class="fas fa-arrow-up"></i></button>
                                    </form>

                                    <span class="fw-bold mx-2"><?= $root_ans['votes'] ?></span>

                                    <form action="post_action.php" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="vote">
                                        <input type="hidden" name="ans_id" value="<?= $root_ans['id'] ?>">
                                        <input type="hidden" name="q_id" value="<?= $q_id ?>">
                                        <input type="hidden" name="val" value="-1">
                                        <button class="btn btn-sm text-secondary border-0"><i class="fas fa-arrow-down"></i></button>
                                    </form>

                                    <div class="vr mx-2"></div>

                                    <button class="btn btn-sm text-muted" onclick="document.getElementById('root-reply-form-<?= $root_ans['id'] ?>').classList.toggle('d-none')">
                                        <i class="far fa-comment"></i> Responder
                                    </button>
                                </div>

                                <div id="root-reply-form-<?= $root_ans['id'] ?>" class="d-none mt-3 p-3 bg-light rounded">
                                    <form action="post_action.php" method="POST">
                                        <input type="hidden" name="action" value="answer">
                                        <input type="hidden" name="question_id" value="<?= $q_id ?>">
                                        <input type="hidden" name="parent_id" value="<?= $root_ans['id'] ?>">
                                        <textarea name="body" class="form-control mb-2" rows="2" placeholder="Responda a <?= htmlspecialchars($root_ans['username']) ?>..." required></textarea>
                                        <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
                                    </form>
                                </div>

                                <?php
                                    $reply_count = count_children($root_ans['id'], $comments_by_parent);
                                    if ($reply_count > 0):
                                ?>
                                    <div class="mt-3">
                                        <button class="btn btn-light btn-sm w-100 text-start text-primary fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#thread-<?= $root_ans['id'] ?>">
                                            <i class="fas fa-level-down-alt me-2"></i>
                                            Ver <?= $reply_count ?> <?= $reply_count == 1 ? 'resposta' : 'respostas' ?>
                                        </button>

                                        <div class="collapse" id="thread-<?= $root_ans['id'] ?>">
                                            <div class="ps-2 pt-2">
                                                <?php render_replies($root_ans['id'], $comments_by_parent, $q_id); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="far fa-comment-dots fa-3x mb-3"></i><br>
                        Ninguém respondeu ainda.
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
