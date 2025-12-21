<?php
require 'db.php';

$user_id = $_SESSION['user_id'];

// 1. Sidebar Sigs
$stmt = $pdo->prepare("SELECT s.* FROM sigs s JOIN sig_memberships m ON s.id = m.sig_id WHERE m.user_id = ? ORDER BY s.name ASC");
$stmt->execute([$user_id]);
$my_sigs = $stmt->fetchAll();

// 2. Feed
$stmt = $pdo->prepare("
    SELECT
        a.id as answer_id,
        a.body as answer_body,
        a.votes as answer_votes,
        a.created_at as answer_date,
        u.id as answer_user_id,
        u.username as answer_username,
        q.id as question_id,
        q.title as question_title,
        s.id as sig_id,
        s.name as sig_name,
        v.vote_type as user_vote
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    JOIN sigs s ON q.sig_id = s.id
    JOIN users u ON a.user_id = u.id
    JOIN sig_memberships m ON s.id = m.sig_id
    LEFT JOIN answer_votes v ON a.id = v.answer_id AND v.user_id = ?
    WHERE m.user_id = ?
    ORDER BY a.created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id, $user_id]);
$feed_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Reddora - Feed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .read-more-link {
            cursor: pointer;
            font-size: 0.9em;
            text-decoration: none;
            font-weight: bold;
        }
        .read-more-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Reddora</a>
            <div class="d-flex align-items-center">
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="text-white text-decoration-none me-3">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                </a>
                <form action="post_action.php" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn btn-sm btn-outline-light opacity-75">Sair</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row">

            <div class="col-md-3 d-none d-md-block">
                <div class="card shadow-sm mb-3 sticky-top" style="top: 90px; z-index: 1;">
                    <div class="card-header bg-white fw-bold text-uppercase small text-muted">Seus Sigs</div>
                    <div class="list-group list-group-flush">
                        <?php foreach($my_sigs as $sig): ?>
                            <a href="sig.php?id=<?= $sig['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center border-0">
                                <span class="fw-bold" style="color: var(--reddora-dark)">s/<?= htmlspecialchars($sig['name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if(empty($my_sigs)): ?>
                            <div class="list-group-item text-muted small">Você não segue nada.</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white p-3">
                        <a href="sigs.php" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-compass"></i> Explorar Sigs
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-body d-flex align-items-center bg-white rounded">
                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-3 text-muted" style="width: 40px; height: 40px;">
                            <i class="fas fa-pen"></i>
                        </div>
                        <button class="btn btn-light text-start text-muted flex-grow-1 rounded-pill border" type="button" data-bs-toggle="collapse" data-bs-target="#questionForm">
                            O que você quer perguntar?
                        </button>
                    </div>
                    <div class="collapse p-3 border-top" id="questionForm">
                        <form action="post_action.php" method="POST">
                            <input type="hidden" name="action" value="create_question">
                            <div class="mb-2">
                                <select name="sig_id" class="form-select form-select-sm mb-2" required>
                                    <option value="" disabled selected>Escolha a Comunidade...</option>
                                    <?php foreach($my_sigs as $sig): ?>
                                        <option value="<?= $sig['id'] ?>">s/<?= htmlspecialchars($sig['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="title" class="form-control mb-2 fw-bold" placeholder="Título..." required>
                                <textarea name="body" class="form-control mb-2" placeholder="Contexto..."></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-sm px-4">Perguntar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php foreach($feed_items as $item):
                    $ans_id = $item['answer_id'];
                    $full_body = $item['answer_body'];
                    $limit = 280; // Limite de caracteres
                    // mb_strlen conta caracteres reais (UTF-8), não bytes
                    $is_long = mb_strlen($full_body, 'UTF-8') > $limit;
                ?>
                <div class="card mb-3 hover-card">
                    <div class="card-body pb-2">
                        <div class="mb-2 text-muted small">
                            <span class="text-secondary">Pergunta em </span>
                            <a href="sig.php?id=<?= $item['sig_id'] ?>" class="text-decoration-none fw-bold text-dark" style="position: relative; z-index: 2;">
                                s/<?= htmlspecialchars($item['sig_name']) ?>
                            </a>
                        </div>

                        <h5 class="mb-3">
                            <a href="question.php?id=<?= $item['question_id'] ?>" class="question-link text-dark" style="position: relative; z-index: 2;">
                                <?= htmlspecialchars($item['question_title']) ?>
                            </a>
                        </h5>

                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                <?= strtoupper(substr($item['answer_username'], 0, 1)) ?>
                            </div>
                            <div class="small">
                                <a href="profile.php?id=<?= $item['answer_user_id'] ?>" class="fw-bold text-dark text-decoration-none" style="position: relative; z-index: 2;">
                                    <?= htmlspecialchars($item['answer_username']) ?>
                                </a>
                                <span class="text-muted">respondeu:</span>
                            </div>
                        </div>

                        <div class="text-dark mb-3" style="line-height: 1.6;">
                            <?php if ($is_long):
                                // mb_substr corta corretamente strings UTF-8
                                $short_body = mb_substr($full_body, 0, $limit, 'UTF-8') . '...';
                            ?>
                                <span id="short-text-<?= $ans_id ?>">
                                    <?= nl2br(htmlspecialchars($short_body)) ?>
                                    <a class="read-more-link text-primary" onclick="toggleAnswer(<?= $ans_id ?>)">Ler mais</a>
                                </span>

                                <span id="full-text-<?= $ans_id ?>" class="d-none">
                                    <?= nl2br(htmlspecialchars($full_body)) ?>
                                    <a class="read-more-link text-secondary" onclick="toggleAnswer(<?= $ans_id ?>)">Ler menos</a>
                                </span>
                            <?php else: ?>
                                <?= nl2br(htmlspecialchars($full_body)) ?>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-3">
                            <div class="btn-group bg-light rounded-pill border">
                                <button id="btn-up-<?= $ans_id ?>"
                                        onclick="vote(<?= $ans_id ?>, 1)"
                                        class="btn btn-sm px-3 fw-bold <?= $item['user_vote'] == 1 ? 'text-success' : 'text-secondary' ?>"
                                        style="border:none; z-index: 5;">
                                    <i class="fas fa-arrow-up"></i>
                                </button>

                                <span id="vote-count-<?= $ans_id ?>" class="btn btn-sm px-2 text-dark fw-bold" style="background:transparent; border:none; cursor:default;">
                                    <?= $item['answer_votes'] ?>
                                </span>

                                <button id="btn-down-<?= $ans_id ?>"
                                        onclick="vote(<?= $ans_id ?>, -1)"
                                        class="btn btn-sm px-3 fw-bold <?= $item['user_vote'] == -1 ? 'text-danger' : 'text-secondary' ?>"
                                        style="border:none; z-index: 5;">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                            </div>

                            <a href="question.php?id=<?= $item['question_id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold" style="position: relative; z-index: 2;">
                                <i class="far fa-comments me-1"></i> Ver Discussão
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="col-md-2 d-none d-lg-block">
               <div class="small text-muted mt-3"><p>© 2025 Reddora v1.0</p></div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Função para expandir/recolher o texto
    function toggleAnswer(id) {
        const shortText = document.getElementById('short-text-' + id);
        const fullText = document.getElementById('full-text-' + id);

        if (shortText.classList.contains('d-none')) {
            shortText.classList.remove('d-none');
            fullText.classList.add('d-none');
        } else {
            shortText.classList.add('d-none');
            fullText.classList.remove('d-none');
        }
    }

    function vote(ansId, val) {
        let formData = new FormData();
        formData.append('action', 'vote_ajax');
        formData.append('ans_id', ansId);
        formData.append('val', val);

        fetch('post_action.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('vote-count-' + ansId).innerText = data.new_total;

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
