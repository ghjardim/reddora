<?php
require 'db.php';

// Verifica ID
if (!isset($_GET['id'])) {
    die("<div class='container mt-5 alert alert-danger'>Erro: Nenhum ID fornecido. <a href='index.php'>Voltar</a></div>");
}
$q_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Busca Pergunta
$stmt = $pdo->prepare("SELECT q.*, s.name as sig_name, u.username FROM questions q JOIN sigs s ON q.sig_id = s.id JOIN users u ON q.user_id = u.id WHERE q.id = ?");
$stmt->execute([$q_id]);
$question = $stmt->fetch();

if (!$question) {
    die("<div class='container mt-5 alert alert-danger'>Erro: Pergunta não encontrada. <a href='index.php'>Voltar</a></div>");
}

// Busca Respostas e Votos
$stmt = $pdo->prepare("
    SELECT a.*, u.username, v.vote_type as user_vote
    FROM answers a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN answer_votes v ON a.id = v.answer_id AND v.user_id = ?
    WHERE a.question_id = ?
    ORDER BY a.votes DESC, a.created_at ASC
");
$stmt->execute([$user_id, $q_id]);
$all_answers = $stmt->fetchAll();

// Organiza Hierarquia
$comments_by_parent = [];
foreach ($all_answers as $ans) { $pid = !empty($ans['parent_id']) ? $ans['parent_id'] : 0; $comments_by_parent[$pid][] = $ans; }

// Função Recursiva para Comentários (Visual Limpo)
function render_replies($parent_id, $comments_by_parent, $q_id) {
    if (!isset($comments_by_parent[$parent_id])) return;
    foreach ($comments_by_parent[$parent_id] as $ans) {
        $ans_id = $ans['id'];
        ?>
        <div class="mt-3 ps-3 thread-line">

            <div class="mb-1 d-flex align-items-center">
                <a href="profile.php?id=<?= $ans['user_id'] ?>" class="fw-bold text-dark text-decoration-none small">
                    <?= htmlspecialchars($ans['username']) ?>
                </a>
                </div>

            <div class="text-dark small mb-2" style="line-height: 1.5;">
                <?= nl2br(htmlspecialchars($ans['body'])) ?>
            </div>

            <div class="d-flex align-items-center mb-2">

                <div class="bg-light rounded-pill border px-2 d-flex align-items-center me-2" style="transform: scale(0.9); transform-origin: left center;">
                    <button id="btn-up-<?= $ans_id ?>"
                            onclick="vote(<?= $ans_id ?>, 1)"
                            class="btn btn-sm btn-link p-0 <?= $ans['user_vote'] == 1 ? 'text-success' : 'text-secondary' ?>"
                            style="text-decoration:none; border:none;">
                        <i class="fas fa-arrow-up"></i>
                    </button>

                    <span id="vote-count-<?= $ans_id ?>" class="fw-bold mx-2 text-dark small"><?= $ans['votes'] ?></span>

                    <button id="btn-down-<?= $ans_id ?>"
                            onclick="vote(<?= $ans_id ?>, -1)"
                            class="btn btn-sm btn-link p-0 <?= $ans['user_vote'] == -1 ? 'text-danger' : 'text-secondary' ?>"
                            style="text-decoration:none; border:none;">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                </div>

                <button class="btn btn-sm text-muted fw-bold p-0 small" onclick="document.getElementById('reply-form-<?= $ans['id'] ?>').classList.toggle('d-none')">
                    Responder
                </button>
            </div>

            <div id="reply-form-<?= $ans['id'] ?>" class="d-none ms-1 mt-2 mb-3">
                <form action="post_action.php" method="POST">
                    <input type="hidden" name="action" value="answer">
                    <input type="hidden" name="question_id" value="<?= $q_id ?>">
                    <input type="hidden" name="parent_id" value="<?= $ans['id'] ?>">
                    <textarea name="body" class="form-control form-control-sm mb-1" rows="2" placeholder="Sua resposta..."></textarea>
                    <button class="btn btn-primary btn-sm py-0 px-3">Enviar</button>
                </form>
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
    <style>
        .question-body {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #2c3e50;
        }
        .main-question-card {
            border-top: 5px solid var(--reddora-red);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        /* Ajuste fino para a linha da thread ficar elegante */
        .thread-line {
            border-left: 2px solid #e9ecef;
        }
        .thread-line:hover {
            border-left-color: #ced4da; /* Fica mais escuro ao passar o mouse na área */
        }
    </style>
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

                <div class="card main-question-card border-0 mb-5">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <a href="sig.php?id=<?= $question['sig_id'] ?>" class="badge bg-light text-dark border text-decoration-none me-2">
                                    s/<?= htmlspecialchars($question['sig_name']) ?>
                                </a>
                                <span class="text-muted small">
                                    u/<a href="profile.php?id=<?= $question['user_id'] ?>" class="text-decoration-none fw-bold text-dark"><?= htmlspecialchars($question['username']) ?></a>
                                    &bull; <?= date('d/m/Y', strtotime($question['created_at'])) ?>
                                </span>
                            </div>
                        </div>

                        <h1 class="fw-bold mb-4 text-dark" style="font-size: 1.75rem;"><?= htmlspecialchars($question['title']) ?></h1>

                        <div class="question-body mb-4">
                            <?= nl2br(htmlspecialchars($question['body'])) ?>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="text-muted fw-bold text-uppercase small mb-0">
                        <i class="far fa-comments"></i>
                        <?= isset($comments_by_parent[0]) ? count($comments_by_parent[0]) : 0 ?> Respostas Principais
                    </h5>

                    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#mainReplyForm">
                        <i class="fas fa-pen me-2"></i>Escrever resposta
                    </button>
                </div>

                <div class="collapse mb-4" id="mainReplyForm">
                    <div class="card bg-white p-3 shadow-sm border-0">
                        <form action="post_action.php" method="POST">
                            <input type="hidden" name="action" value="answer">
                            <input type="hidden" name="question_id" value="<?= $q_id ?>">
                            <label class="form-label small fw-bold text-muted">Sua resposta</label>
                            <textarea name="body" class="form-control mb-2" rows="4" placeholder="Adicione à discussão..."></textarea>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary px-4 fw-bold">Postar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (isset($comments_by_parent[0])): ?>
                    <?php foreach ($comments_by_parent[0] as $root_ans):
                        $ans_id = $root_ans['id'];
                    ?>
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

                                    <div class="bg-light rounded-pill border px-2 d-flex align-items-center">
                                        <button id="btn-up-<?= $ans_id ?>"
                                                onclick="vote(<?= $ans_id ?>, 1)"
                                                class="btn btn-sm <?= $root_ans['user_vote'] == 1 ? 'text-success' : 'text-secondary' ?> fw-bold border-0">
                                            <i class="fas fa-arrow-up"></i>
                                        </button>

                                        <span id="vote-count-<?= $ans_id ?>" class="fw-bold mx-1 text-dark"><?= $root_ans['votes'] ?></span>

                                        <button id="btn-down-<?= $ans_id ?>"
                                                onclick="vote(<?= $ans_id ?>, -1)"
                                                class="btn btn-sm <?= $root_ans['user_vote'] == -1 ? 'text-danger' : 'text-secondary' ?> border-0">
                                            <i class="fas fa-arrow-down"></i>
                                        </button>
                                    </div>

                                    <button class="btn btn-sm text-secondary fw-bold ms-3" onclick="document.getElementById('root-reply-form-<?= $root_ans['id'] ?>').classList.toggle('d-none')">
                                        <i class="far fa-comment"></i> Responder
                                    </button>
                                </div>

                                <div id="root-reply-form-<?= $root_ans['id'] ?>" class="d-none mt-3 p-3 bg-light rounded">
                                    <form action="post_action.php" method="POST">
                                        <input type="hidden" name="action" value="answer">
                                        <input type="hidden" name="question_id" value="<?= $q_id ?>">
                                        <input type="hidden" name="parent_id" value="<?= $root_ans['id'] ?>">
                                        <textarea name="body" class="form-control mb-2" rows="2"></textarea>
                                        <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
                                    </form>
                                </div>

                                <?php $reply_count = count_children($root_ans['id'], $comments_by_parent); if ($reply_count > 0): ?>
                                    <div class="mt-3">
                                        <button class="btn btn-light btn-sm w-100 text-start fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#thread-<?= $root_ans['id'] ?>">
                                            <i class="fas fa-level-down-alt me-2"></i> Ver <?= $reply_count ?> respostas
                                        </button>
                                        <div class="collapse show" id="thread-<?= $root_ans['id'] ?>"> <div class="ps-2 pt-2">
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

    <script>
    function vote(ansId, val) {
        let formData = new FormData();
        formData.append('action', 'vote_ajax');
        formData.append('ans_id', ansId);
        formData.append('val', val);

        fetch('post_action.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('vote-count-' + ansId).innerText = (data.new_total == null ? 0 : data.new_total);

                let btnUp = document.getElementById('btn-up-' + ansId);
                let btnDown = document.getElementById('btn-down-' + ansId);

                btnUp.classList.remove('text-success', 'text-secondary');
                btnDown.classList.remove('text-danger', 'text-secondary');

                if (data.user_vote == 1) {
                    btnUp.classList.add('text-success'); btnDown.classList.add('text-secondary');
                } else if (data.user_vote == -1) {
                    btnUp.classList.add('text-secondary'); btnDown.classList.add('text-danger');
                } else {
                    btnUp.classList.add('text-secondary'); btnDown.classList.add('text-secondary');
                }
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => console.error('Erro:', error));
    }
    </script>
</body>
</html>
