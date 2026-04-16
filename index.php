<?php
require 'db.php';
$user_id = $_SESSION['user_id'];

function getPostBadge($type) {
    switch($type) {
        case 'post': return '<span class="badge bg-success text-white border me-1"><i class="fas fa-file-alt"></i> Ensaio</span>';
        case 'short': return '<span class="badge bg-warning text-dark border me-1"><i class="fas fa-bolt"></i> Curto</span>';
        default: return '<span class="badge bg-primary text-white border me-1"><i class="fas fa-question-circle"></i> Pergunta</span>';
    }
}

// 1. Sidebar Sigs
$stmt = $pdo->prepare("SELECT s.* FROM sigs s JOIN sig_memberships m ON s.id = m.sig_id WHERE m.user_id = ? ORDER BY s.name ASC");
$stmt->execute([$user_id]);
$my_sigs = $stmt->fetchAll();

// 2. PENSAMENTOS RÁPIDOS
$stmt = $pdo->query("SELECT q.id, q.title, q.body, q.created_at, u.username, s.name as sig_name, s.id as sig_id FROM questions q JOIN users u ON q.user_id = u.id JOIN sigs s ON q.sig_id = s.id WHERE q.post_type = 'short' ORDER BY q.created_at DESC LIMIT 4");
$recent_shorts = $stmt->fetchAll();

// 3. ÚLTIMOS POSTS
$stmt = $pdo->prepare("SELECT q.id, q.title, q.post_type, q.created_at, u.username, s.name as sig_name, (SELECT COALESCE(SUM(votes), 0) FROM answers WHERE question_id = q.id) as total_score FROM questions q JOIN users u ON q.user_id = u.id JOIN sigs s ON q.sig_id = s.id JOIN sig_memberships m ON s.id = m.sig_id WHERE m.user_id = ? AND q.post_type != 'short' ORDER BY q.created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$recent_posts = $stmt->fetchAll();

// 4. FEED DE DISCUSSÕES
$stmt = $pdo->prepare("SELECT a.id as answer_id, a.body as answer_body, a.votes as answer_votes, a.created_at as answer_date, u.id as answer_user_id, u.username as answer_username, q.id as question_id, q.title as question_title, q.post_type, qu.username as question_username, qu.id as question_user_id, s.id as sig_id, s.name as sig_name, v.vote_type as user_vote FROM answers a JOIN questions q ON a.question_id = q.id JOIN sigs s ON q.sig_id = s.id JOIN users u ON a.user_id = u.id JOIN users qu ON q.user_id = qu.id JOIN sig_memberships m ON s.id = m.sig_id LEFT JOIN answer_votes v ON a.id = v.answer_id AND v.user_id = ? WHERE m.user_id = ? AND a.parent_id IS NULL ORDER BY a.created_at DESC LIMIT 50");
$stmt->execute([$user_id, $user_id]);
$feed_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Reddora - Início</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
    <script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>
    <link rel="stylesheet" href="style.css">
    <style>
        .read-more-link { cursor: pointer; font-size: 0.9em; text-decoration: none; font-weight: bold; }
        .markdown-content img { max-width: 100%; height: auto; border-radius: 8px; }
        .markdown-content pre { background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef; white-space: pre-wrap; }
        .markdown-content blockquote { border-left: 4px solid #dee2e6; padding-left: 1rem; color: #6c757d; }
        .post-list-item:hover { background-color: #f8f9fa; transform: translateX(2px); transition: 0.2s ease; }
        .short-card-body { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; font-size: 0.9rem; }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Reddora</a>
            <form action="search.php" method="GET" class="mx-auto d-none d-md-flex" style="max-width: 400px; width: 100%;">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control border-0" placeholder="Pesquisar..." required>
                    <button class="btn btn-light text-primary fw-bold px-3" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>
            <div class="d-flex align-items-center">
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="text-white text-decoration-none me-3"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></a>
                <form action="post_action.php" method="POST" class="d-inline"><input type="hidden" name="action" value="logout"><button class="btn btn-sm btn-outline-light opacity-75">Sair</button></form>
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
                                <span class="fw-bold" style="color: var(--reddora-dark)"><?= htmlspecialchars($sig['name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if(empty($my_sigs)): ?>
                            <div class="list-group-item text-muted small">Não segues nenhuma comunidade.</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white p-3">
                        <a href="sigs.php" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-compass"></i> Explorar Sigs
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-9 col-lg-7">
                <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold text-dark mb-0"><i class="fas fa-bolt text-warning me-2"></i>Pensamentos Rápidos</h4>
                        <button class="btn btn-outline-primary btn-sm rounded-pill fw-bold px-3 shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#questionForm"><i class="fas fa-pen me-1"></i> Escrever</button>
                    </div>

                    <div class="collapse mb-4" id="questionForm">
                        <div class="card shadow-sm border-0"><div class="card-body p-3">
                            <form action="post_action.php" method="POST" id="mainPostForm">
                                <input type="hidden" name="action" value="create_question">
                                <select name="sig_id" class="form-select form-select-sm mb-2" required>
                                    <option value="" disabled selected>Escolha a Comunidade...</option>
                                    <?php foreach($my_sigs as $sig): ?><option value="<?= $sig['id'] ?>"><?= htmlspecialchars($sig['name']) ?></option><?php endforeach; ?>
                                </select>
                                <input type="text" name="title" class="form-control mb-2 fw-bold" placeholder="Título (opcional para pensamentos)...">
                                <textarea name="body" id="postEditor" class="form-control mb-2"></textarea>
                                <div class="text-end"><button type="submit" class="btn btn-primary btn-sm px-4">Publicar</button></div>
                            </form>
                        </div></div>
                    </div>

                    <div class="row g-3">
                        <?php foreach($recent_shorts as $short): ?>
                        <div class="col-md-6">
                            <div class="card h-100 border-0 shadow-sm bg-white hover-card" style="border-top: 3px solid var(--bs-warning) !important;">
                                <div class="card-body p-3 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2 small">
                                        <span class="fw-bold text-dark">u/<?= htmlspecialchars($short['username']) ?></span>
                                        <span class="text-muted"><?= htmlspecialchars($short['sig_name']) ?></span>
                                    </div>
                                    <?php if(!empty($short['title'])): ?>
                                        <h6 class="fw-bold mb-2"><a href="question.php?id=<?= $short['id'] ?>" class="text-dark text-decoration-none"><?= htmlspecialchars($short['title']) ?></a></h6>
                                    <?php endif; ?>

                                    <div class="markdown-content short-card-body text-muted mb-3 flex-grow-1"><?= htmlspecialchars($short['body']) ?></div>
                                    <a href="question.php?id=<?= $short['id'] ?>" class="btn btn-sm btn-light border w-100 fw-bold text-primary">Ler Pensamento</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-5">
                    <h4 class="fw-bold text-dark mb-3">Últimos Posts</h4>
                    <div class="list-group shadow-sm">
                        <?php foreach($recent_posts as $rp): ?>
                        <a href="question.php?id=<?= $rp['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center border-0 border-bottom post-list-item py-3">
                            <div>
                                <div class="mb-1"><?= getPostBadge($rp['post_type']) ?> <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($rp['title']) ?></span></div>
                                <small class="text-muted">u/<?= htmlspecialchars($rp['username']) ?> em <?= htmlspecialchars($rp['sig_name']) ?></small>
                            </div>
                            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><i class="fas fa-arrow-up text-success"></i> <?= $rp['total_score'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="fw-bold text-dark mb-4 pt-3">Discussões Recentes</h4>
                    <?php foreach($feed_items as $item): $ans_id = $item['answer_id']; ?>
                    <div class="card mb-4 hover-card border-0 shadow-sm">
                        <div class="card-body pb-2">
                            <div class="mb-3 text-muted small bg-light p-2 rounded border d-flex justify-content-between align-items-center">
                                <div class="text-truncate me-3">
                                    <i class="fas fa-reply text-primary"></i> <b><?= htmlspecialchars($item['question_username']) ?>:</b> <?= htmlspecialchars($item['question_title']) ?>
                                </div>
                                <a href="sig.php?id=<?= $item['sig_id'] ?>" class="badge bg-white border text-secondary text-decoration-none fw-bold" style="white-space: nowrap;">
                                    <i class="fas fa-users text-muted me-1"></i> s/<?= htmlspecialchars($item['sig_name']) ?>
                                </a>
                            </div>

                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center me-2" style="width: 24px; height: 24px; font-size: 0.7rem;"><?= strtoupper(substr($item['answer_username'], 0, 1)) ?></div>
                                <span class="fw-bold small">u/<?= htmlspecialchars($item['answer_username']) ?></span>
                            </div>
                            <div class="markdown-content text-dark mb-3"><?= htmlspecialchars($item['answer_body']) ?></div>
                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                                <div class="btn-group bg-light rounded-pill border">
                                    <button onclick="vote(<?= $ans_id ?>, 1)" class="btn btn-sm px-3 <?= $item['user_vote'] == 1 ? 'text-success' : 'text-secondary' ?> border-0"><i class="fas fa-arrow-up"></i></button>
                                    <span class="btn btn-sm px-2 text-dark fw-bold disabled border-0"><?= $item['answer_votes'] ?></span>
                                    <button onclick="vote(<?= $ans_id ?>, -1)" class="btn btn-sm px-3 <?= $item['user_vote'] == -1 ? 'text-danger' : 'text-secondary' ?> border-0"><i class="fas fa-arrow-down"></i></button>
                                </div>
                                <a href="question.php?id=<?= $item['question_id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">Participar</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    if(document.getElementById('postEditor')) {
        new EasyMDE({ element: document.getElementById('postEditor'), spellChecker: false, status: false, toolbar: ["bold", "italic", "quote", "|", "link", "preview"] });
    }

    function renderMarkdown() {
        document.querySelectorAll('.markdown-content').forEach(el => {
            if (!el.dataset.parsed) {
                const raw = el.textContent.trim(); // TRIM resolve os espaços acidentais
                el.innerHTML = DOMPurify.sanitize(marked.parse(raw));
                el.dataset.parsed = true;
            }
        });
    }
    document.addEventListener("DOMContentLoaded", renderMarkdown);

    function toggleAnswer(id) {
        const short = document.getElementById('short-text-' + id), full = document.getElementById('full-text-' + id);
        if (short.classList.contains('d-none')) { short.classList.remove('d-none'); full.classList.add('d-none'); }
        else { short.classList.add('d-none'); full.classList.remove('d-none'); }
        renderMarkdown();
    }

    function vote(ansId, val) {
        let fd = new FormData(); fd.append('action', 'vote_ajax'); fd.append('ans_id', ansId); fd.append('val', val);
        fetch('post_action.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if(d.status === 'success') location.reload(); });
    }
    </script>
</body>
</html>
