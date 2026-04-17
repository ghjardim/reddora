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
$current_user_id = $_SESSION['user_id'];

// 1. Busca Usuário (Agora incluindo real_name e profile_pic)
$stmt = $pdo->prepare("SELECT username, real_name, created_at, profile_pic FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$profile_user = $stmt->fetch();
if (!$profile_user) die("Usuário não encontrado.");

// 2. Busca Perguntas
$stmt = $pdo->prepare("SELECT q.*, s.name as sig_name FROM questions q JOIN sigs s ON q.sig_id = s.id WHERE q.user_id = ? ORDER BY q.created_at DESC");
$stmt->execute([$profile_id]);
$questions = $stmt->fetchAll();

// 3. Busca Respostas
$stmt = $pdo->prepare("
    SELECT a.*, q.title as question_title, q.id as question_id, v.vote_type as user_vote
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    LEFT JOIN answer_votes v ON a.id = v.answer_id AND v.user_id = ?
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$current_user_id, $profile_id]);
$answers = $stmt->fetchAll();

// 4. Busca Sigs
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

function getPostBadge($type) {
    switch($type) {
        case 'post': return '<span class="badge bg-success text-white border me-1"><i class="fas fa-file-alt"></i> Ensaio</span>';
        case 'short': return '<span class="badge bg-warning text-dark border me-1"><i class="fas fa-bolt"></i> Curto</span>';
        default: return '<span class="badge bg-primary text-white border me-1"><i class="fas fa-question-circle"></i> Pergunta</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Perfil de <?= htmlspecialchars($profile_user['username']) ?> - Reddora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>

    <link rel="stylesheet" href="style.css">
    <style>
        .read-more-link { cursor: pointer; font-size: 0.9em; text-decoration: none; font-weight: bold; }
        .read-more-link:hover { text-decoration: underline; }

        .markdown-content img { max-width: 100%; height: auto; border-radius: 8px; }
        .markdown-content pre { background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef; }
        .markdown-content blockquote { border-left: 4px solid #dee2e6; padding-left: 1rem; color: #6c757d; }

        .nested-reply-card {
            background-color: #f8f9fa;
            border-left: 3px solid #ced4da;
            padding: 10px 15px;
            font-size: 0.9rem;
        }
        .nested-reply-card .reply-meta {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Reddora</a>

            <form action="search.php" method="GET" class="mx-auto d-none d-md-flex" style="max-width: 400px; width: 100%;">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control border-0" placeholder="Pesquisar na Reddora..." required>
                    <button class="btn btn-light text-primary fw-bold px-3" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>

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
            <div class="card-body p-4 d-flex align-items-center position-relative">
                <div class="bg-white rounded-circle d-flex align-items-center justify-content-center me-4 shadow-sm" style="width: 100px; height: 100px; color: var(--reddora-dark); overflow: hidden; border: 3px solid white; flex-shrink: 0;">
                    <?php if (!empty($profile_user['profile_pic'])): ?>
                        <img src="uploads/profiles/<?= htmlspecialchars($profile_user['profile_pic']) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-user fa-3x"></i>
                    <?php endif; ?>
                </div>

                <div>
                    <h2 class="mb-0 fw-bold">
                        <?= !empty($profile_user['real_name']) ? htmlspecialchars($profile_user['real_name']) : htmlspecialchars($profile_user['username']) ?>
                    </h2>

                    <?php if (!empty($profile_user['real_name'])): ?>
                        <p class="mb-1 text-light opacity-75 fw-bold" style="font-size: 0.95rem;">@<?= htmlspecialchars($profile_user['username']) ?></p>
                    <?php endif; ?>

                    <p class="mb-0 opacity-75 small">
                        <i class="far fa-calendar-alt me-1"></i> Membro desde <?= date('d/m/Y', strtotime($profile_user['created_at'])) ?>
                    </p>
                </div>

                <?php if ($current_user_id === $profile_id): ?>
                <div class="ms-auto">
                    <button class="btn btn-sm btn-outline-light fw-bold" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-camera me-1"></i> Editar Foto
                    </button>
                </div>
                <?php endif; ?>
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
                                        <h5 class="mb-1 fw-bold">
                                            <?= getPostBadge($q['post_type']) ?>
                                            <a href="question.php?id=<?= $q['id'] ?>" class="question-link text-dark text-decoration-none">
                                                <?= htmlspecialchars($q['title']) ?>
                                            </a>
                                        </h5>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($q['created_at'])) ?></small>
                                    </div>
                                    <div class="mt-2 text-muted small">
                                        <i class="fas fa-folder-open"></i> Em
                                        <a href="sig.php?id=<?= $q['sig_id'] ?>" class="text-decoration-none fw-bold" style="color: var(--reddora-dark);">
                                            <?= htmlspecialchars($q['sig_name']) ?>
                                        </a>
                                    </div>
                                </div>

                            <?php else:
                                $ans = $item['data'];
                                $ans_id = $ans['id'];
                                $is_nested = !empty($ans['parent_id']);
                                $full_body = $ans['body'];
                                $limit = 200;
                                $is_long = mb_strlen($full_body, 'UTF-8') > $limit;
                            ?>
                                <div class="list-group-item py-3 border-0 border-bottom">
                                    <?php if ($is_nested): ?>
                                        <div class="nested-reply-card rounded mb-2">
                                            <div class="reply-meta d-flex justify-content-between">
                                                <span><i class="fas fa-reply me-1"></i> Réplica em: <a href="question.php?id=<?= $ans['question_id'] ?>" class="text-muted fw-bold text-decoration-none"><?= htmlspecialchars($ans['question_title']) ?></a></span>
                                                <span><?= date('d/m', strtotime($ans['created_at'])) ?></span>
                                            </div>
                                            <div class="markdown-content text-secondary small fst-italic">
                                                <?= htmlspecialchars(mb_substr($full_body, 0, 100)) . (strlen($full_body)>100?'...':'') ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <small class="text-muted">
                                            <i class="fas fa-comment"></i> Respondeu em:
                                            <a href="question.php?id=<?= $ans['question_id'] ?>" class="text-dark fw-bold text-decoration-none">
                                                <?= htmlspecialchars($ans['question_title']) ?>
                                            </a>
                                        </small>

                                        <div class="mb-1 mt-2 text-dark">
                                            <?php if ($is_long): $short_body = mb_substr($full_body, 0, $limit, 'UTF-8') . '...'; ?>
                                                <span id="short-text-<?= $ans_id ?>">
                                                    <span class="markdown-content d-inline"><?= htmlspecialchars($short_body) ?></span>
                                                    <a class="read-more-link text-primary" onclick="toggleAnswer(<?= $ans_id ?>)">Ler mais</a>
                                                </span>
                                                <span id="full-text-<?= $ans_id ?>" class="d-none">
                                                    <span class="markdown-content d-inline"><?= htmlspecialchars($full_body) ?></span>
                                                    <a class="read-more-link text-secondary" onclick="toggleAnswer(<?= $ans_id ?>)">Ler menos</a>
                                                </span>
                                            <?php else: ?>
                                                <div class="markdown-content"><?= htmlspecialchars($full_body) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($ans['created_at'])) ?></small>
                                        <span class="badge <?= $ans['votes'] >= 0 ? 'bg-success' : 'bg-danger' ?>"><?= $ans['votes'] ?> pts</span>
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
                                    <h5 class="mb-1 fw-bold">
                                        <?= getPostBadge($q['post_type']) ?>
                                        <a href="question.php?id=<?= $q['id'] ?>" class="question-link text-dark text-decoration-none">
                                            <?= htmlspecialchars($q['title']) ?>
                                        </a>
                                    </h5>
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($q['created_at'])) ?></small>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <i class="fas fa-folder-open"></i> Em <?= htmlspecialchars($q['sig_name']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($questions)) echo "<div class='p-4 bg-white text-center text-muted border rounded'>Nenhuma pergunta encontrada.</div>"; ?>
                    </div>
                <?php endif; ?>

                <?php if ($active_tab === 'answers'): ?>
                    <div class="list-group shadow-sm">
                        <?php foreach($answers as $ans):
                            $ans_id = $ans['id'];
                            $full_body = $ans['body'];
                            $limit = 280;
                            $is_long = mb_strlen($full_body, 'UTF-8') > $limit;
                        ?>
                            <div class="list-group-item py-3 border-0 border-bottom">
                                <small class="text-muted">
                                    <i class="fas fa-reply"></i> Em: <a href="question.php?id=<?= $ans['question_id'] ?>" class="text-dark fw-bold text-decoration-none"><?= htmlspecialchars($ans['question_title']) ?></a>
                                </small>
                                <div class="mb-1 mt-2 text-dark">
                                    <?php if ($is_long): $short_body = mb_substr($full_body, 0, $limit, 'UTF-8') . '...'; ?>
                                        <span id="short-text-<?= $ans_id ?>">
                                            <span class="markdown-content d-inline"><?= htmlspecialchars($short_body) ?></span>
                                            <a class="read-more-link text-primary" onclick="toggleAnswer(<?= $ans_id ?>)">Ler mais</a>
                                        </span>
                                        <span id="full-text-<?= $ans_id ?>" class="d-none">
                                            <span class="markdown-content d-inline"><?= htmlspecialchars($full_body) ?></span>
                                            <a class="read-more-link text-secondary" onclick="toggleAnswer(<?= $ans_id ?>)">Ler menos</a>
                                        </span>
                                    <?php else: ?>
                                        <div class="markdown-content"><?= htmlspecialchars($full_body) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted"><?= date('d/m/Y', strtotime($ans['created_at'])) ?></small>
                                    <span class="badge bg-light text-dark border"><?= $ans['votes'] ?> pts</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($answers)) echo "<div class='p-4 bg-white text-center text-muted border rounded'>Nenhuma resposta encontrada.</div>"; ?>
                    </div>
                <?php endif; ?>

            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-3 border-0">
                    <div class="card-header bg-white fw-bold text-uppercase small text-muted">Estatísticas</div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h4 fw-bold mb-0 text-primary"><?= $total_karma ?></div>
                                <small class="text-muted text-uppercase" style="font-size:0.65rem;">Karma</small>
                            </div>
                            <div class="col-4 border-start border-end">
                                <div class="h4 fw-bold text-dark mb-0"><?= count($questions) ?></div>
                                <small class="text-muted text-uppercase" style="font-size:0.65rem;">Posts</small>
                            </div>
                            <div class="col-4">
                                <div class="h4 fw-bold text-dark mb-0"><?= count($answers) ?></div>
                                <small class="text-muted text-uppercase" style="font-size:0.65rem;">Respostas</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3 border-0">
                    <div class="card-header bg-white fw-bold text-uppercase small text-muted d-flex justify-content-between align-items-center">
                        <span>Comunidades</span>
                        <span class="badge bg-light text-dark border rounded-pill"><?= count($user_sigs) ?></span>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach($user_sigs as $sig): ?>
                            <a href="sig.php?id=<?= $sig['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center border-0">
                                <span class="fw-bold" style="color: var(--reddora-dark);"><?= htmlspecialchars($sig['name']) ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if(empty($user_sigs)): ?>
                            <div class="list-group-item text-muted small py-3 border-0 fst-italic">Não participa de nenhuma comunidade.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($current_user_id === $profile_id): ?>
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="post_action.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Atualizar Foto de Perfil</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_pfp">
                        <div class="mb-3">
                            <label for="profile_pic" class="form-label text-muted small">Escolha uma imagem (JPG, PNG, GIF, WEBP)</label>
                            <input class="form-control" type="file" id="profile_pic" name="profile_pic" accept=".jpg,.jpeg,.png,.gif,.webp" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold">Salvar Foto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Motor de Renderização Markdown
    function renderMarkdown() {
        document.querySelectorAll('.markdown-content').forEach(el => {
            if (!el.dataset.parsed) {
                el.innerHTML = DOMPurify.sanitize(marked.parse(el.textContent));
                el.dataset.parsed = true;
            }
        });
    }

    document.addEventListener("DOMContentLoaded", renderMarkdown);

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
        // Reprocessa o Markdown se necessário ao expandir
        renderMarkdown();
    }
    </script>
</body>
</html>
