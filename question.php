<?php
require 'db.php';
if (!isset($_GET['id'])) header('Location: index.php');
$q_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT q.*, s.name as sig_name, u.username FROM questions q JOIN sigs s ON q.sig_id = s.id JOIN users u ON q.user_id = u.id WHERE q.id = ?");
$stmt->execute([$q_id]);
$question = $stmt->fetch();

if (!$question) die("Erro.");

$stmt = $pdo->prepare("SELECT a.*, u.username FROM answers a JOIN users u ON a.user_id = u.id WHERE a.question_id = ? ORDER BY a.votes DESC, a.created_at ASC");
$stmt->execute([$q_id]);
$all_answers = $stmt->fetchAll();

$comments_by_parent = [];
foreach ($all_answers as $ans) { $pid = !empty($ans['parent_id']) ? $ans['parent_id'] : 0; $comments_by_parent[$pid][] = $ans; }

// Função Recursiva
function render_replies($parent_id, $comments_by_parent, $q_id) {
    if (!isset($comments_by_parent[$parent_id])) return;
    foreach ($comments_by_parent[$parent_id] as $ans) {
        ?>
        <div class="mt-3 ps-3 thread-line">
            <div class="bg-white p-2 rounded mb-1 d-flex justify-content-between border">
                <small class="fw-bold text-dark">
                    <a href="profile.php?id=<?= $ans['user_id'] ?>" class="text-dark text-decoration-none"><?= htmlspecialchars($ans['username']) ?></a>
                </small>
                <div class="small text-muted">
                    <span class="fw-bold"><?= $ans['votes'] ?> pts</span>
                    <form action="post_action.php" method="POST" class="d-inline ms-1"><input type="hidden" name="action" value="vote"><input type="hidden" name="ans_id" value="<?= $ans['id'] ?>"><input type="hidden" name="q_id" value="<?= $q_id ?>"><input type="hidden" name="val" value="1"><button class="btn btn-link p-0 text-secondary" style="text-decoration:none">▲</button></form>
                    <form action="post_action.php" method="POST" class="d-inline"><input type="hidden" name="action" value="vote"><input type="hidden" name="ans_id" value="<?= $ans['id'] ?>"><input type="hidden" name="q_id" value="<?= $q_id ?>"><input type="hidden" name="val" value="-1"><button class="btn btn-link p-0 text-secondary" style="text-decoration:none">▼</button></form>
                </div>
            </div>
            <div class="text-dark small mb-2 ps-1"><?= nl2br(htmlspecialchars($ans['body'])) ?></div>

            <button class="btn btn-sm btn-link text-decoration-none p-0 ps-1 small text-primary fw-bold" onclick="document.getElementById('reply-form-<?= $ans['id'] ?>').classList.toggle('d-none')">Responder</button>

            <div id="reply-form-<?= $ans['id'] ?>" class="d-none ms-2 mt-2">
                <form action="post_action.php" method="POST"><input type="hidden" name="action" value="answer"><input type="hidden" name="question_id" value="<?= $q_id ?>"><input type="hidden" name="parent_id" value="<?= $ans['id'] ?>"><textarea name="body" class="form-control form-control-sm mb-1" rows="2"></textarea><button class="btn btn-primary btn-sm py-0">Enviar</button></form>
            </div>
            <?php render_replies($ans['id'], $comments_by_parent, $q_id); ?>
        </div>
        <?php
    }
}
function count_children($parent_id, $comments_by_parent) {
    if (!isset($comments_by_parent[$parent_id])) return 0;
    $count = count($comments_by_parent[$parent_id]);
    foreach ($comments_by_parent[$parent_id] as $child) { $count += count_children($child['id'], $comments_by_parent); }
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
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Reddora</a>
            <div class="d-flex align-items-center">
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="text-white me-3 text-decoration-none"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></a>
                <a href="index.php" class="btn btn-sm btn-outline-light opacity-75">Feed</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="row">
            <div class="col-md-8 mx-auto">

                <div class="mb-2">
                    <a href="sig.php?id=<?= $question['sig_id'] ?>" class="fw-bold text-uppercase small text-decoration-none" style="color: var(--reddora-dark);">
                        s/<?= htmlspecialchars($question['sig_name']) ?>
                    </a>
                </div>

                <h1 class="h3 fw-bold mb-3"><?= htmlspecialchars($question['title']) ?></h1>

                <div class="d-flex align-items-center mb-3">
                    <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 32px; height: 32px; font-size: 0.8rem;">
                        <?= strtoupper(substr($question['username'], 0, 1)) ?>
                    </div>
                    <div class="small">
                        <a href="profile.php?id=<?= $question['user_id'] ?>" class="fw-bold text-dark text-decoration-none"><?= htmlspecialchars($question['username']) ?></a>
                        <span class="text-muted mx-1">&bull;</span>
                        <span class="text-muted"><?= date('d/m/Y', strtotime($question['created_at'])) ?></span>
                    </div>
                </div>

                <div class="fs-5 text-dark mb-4" style="line-height: 1.7;">
                    <?= nl2br(htmlspecialchars($question['body'])) ?>
                </div>

                <div class="d-flex justify-content-between align-items-center border-top border-bottom py-3 mb-4">
                    <div class="fw-bold text-secondary"><?= isset($comments_by_parent[0]) ? count($comments_by_parent[0]) : 0 ?> Respostas</div>
                    <button class="btn btn-primary rounded-pill px-4 fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#mainReplyForm">
                        <i class="fas fa-pen"></i> Responder
                    </button>
                </div>

                <div class="collapse mb-4" id="mainReplyForm">
                    <div class="card bg-white p-3 shadow-sm border-0">
                        <form action="post_action.php" method="POST">
                            <input type="hidden" name="action" value="answer">
                            <input type="hidden" name="question_id" value="<?= $q_id ?>">
                            <textarea name="body" class="form-control mb-2" rows="3" placeholder="Sua resposta..."></textarea>
                            <button type="submit" class="btn btn-primary btn-sm float-end">Postar</button>
                        </form>
                    </div>
                </div>

                <?php if (isset($comments_by_parent[0])): ?>
                    <?php foreach ($comments_by_parent[0] as $root_ans): ?>
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 32px; height: 32px; font-weight: bold; color: var(--reddora-dark);">
                                        <?= strtoupper(substr($root_ans['username'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <a href="profile.php?id=<?= $root_ans['user_id'] ?>" class="fw-bold text-dark d-block lh-1 text-decoration-none"><?= htmlspecialchars($root_ans['username']) ?></a>
                                        <small class="text-muted"><?= date('d M', strtotime($root_ans['created_at'])) ?></small>
                                    </div>
                                </div>

                                <div class="mb-3 text-dark"><?= nl2br(htmlspecialchars($root_ans['body'])) ?></div>

                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-pill border px-2">
                                        <form action="post_action.php" method="POST" class="d-inline"><input type="hidden" name="action" value="vote"><input type="hidden" name="ans_id" value="<?= $root_ans['id'] ?>"><input type="hidden" name="q_id" value="<?= $q_id ?>"><input type="hidden" name="val" value="1"><button class="btn btn-sm text-success fw-bold border-0">▲</button></form>
                                        <span class="fw-bold mx-1 text-dark"><?= $root_ans['votes'] ?></span>
                                        <form action="post_action.php" method="POST" class="d-inline"><input type="hidden" name="action" value="vote"><input type="hidden" name="ans_id" value="<?= $root_ans['id'] ?>"><input type="hidden" name="q_id" value="<?= $q_id ?>"><input type="hidden" name="val" value="-1"><button class="btn btn-sm text-secondary border-0">▼</button></form>
                                    </div>
                                    <button class="btn btn-sm text-secondary fw-bold ms-3" onclick="document.getElementById('root-reply-form-<?= $root_ans['id'] ?>').classList.toggle('d-none')">
                                        <i class="far fa-comment"></i> Responder
                                    </button>
                                </div>

                                <div id="root-reply-form-<?= $root_ans['id'] ?>" class="d-none mt-3 p-3 bg-light rounded">
                                    <form action="post_action.php" method="POST"><input type="hidden" name="action" value="answer"><input type="hidden" name="question_id" value="<?= $q_id ?>"><input type="hidden" name="parent_id" value="<?= $root_ans['id'] ?>"><textarea name="body" class="form-control mb-2" rows="2"></textarea><button type="submit" class="btn btn-primary btn-sm">Enviar</button></form>
                                </div>

                                <?php $reply_count = count_children($root_ans['id'], $comments_by_parent); if ($reply_count > 0): ?>
                                    <div class="mt-3">
                                        <button class="btn btn-light btn-sm w-100 text-start fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#thread-<?= $root_ans['id'] ?>">
                                            <i class="fas fa-level-down-alt me-2"></i> Ver <?= $reply_count ?> respostas
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
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
