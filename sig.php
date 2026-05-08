<?php
// sig.php
require 'db.php';
if (!isset($_GET['id'])) { header("Location: index.php"); exit; }

$sig_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'] ?? null;

// 1. Busca Detalhes do SIG
$stmt = $pdo->prepare("SELECT * FROM sigs WHERE id = ?");
$stmt->execute([$sig_id]);
$sig = $stmt->fetch();
if (!$sig) die("SIG não encontrado.");

// 2. Verifica se Usuário é membro
$is_member = false;
$is_mod = false;
if ($user_id) {
    $stmt = $pdo->prepare("SELECT role FROM sig_memberships WHERE user_id = ? AND sig_id = ?");
    $stmt->execute([$user_id, $sig_id]);
    $membership = $stmt->fetch();
    if ($membership) {
        $is_member = true;
        $is_mod = ($membership['role'] === 'mod');
    }
}

// Busca lista de moderadores do SIG
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.real_name, u.profile_pic
    FROM sig_memberships sm
    JOIN users u ON u.id = sm.user_id
    WHERE sm.sig_id = ? AND sm.role = 'mod'
    ORDER BY u.username
");
$stmt->execute([$sig_id]);
$mods = $stmt->fetchAll();

// Busca membros que não são mods (para painel de gestão)
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.real_name
    FROM sig_memberships sm
    JOIN users u ON u.id = sm.user_id
    WHERE sm.sig_id = ? AND sm.role = 'member'
    ORDER BY u.username
");
$stmt->execute([$sig_id]);
$regular_members = $stmt->fetchAll();

// 3. Busca Perguntas deste SIG
$stmt = $pdo->prepare("
    SELECT q.*, u.username,
    (SELECT COUNT(*) FROM answers WHERE question_id = q.id) as answer_count,
    (SELECT COALESCE(SUM(votes), 0) FROM answers WHERE question_id = q.id) as total_score
    FROM questions q
    JOIN users u ON q.user_id = u.id
    WHERE q.sig_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$sig_id]);
$questions = $stmt->fetchAll();

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
    <title>s/<?= htmlspecialchars($sig['name']) ?> - Reddora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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
                <?php if ($user_id): ?>
                    <a href="profile.php?id=<?= $user_id ?>" class="text-white text-decoration-none me-3">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                    </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-sm btn-outline-light opacity-75">Voltar</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="card shadow-sm border-0 mb-4 overflow-hidden">
            <div class="bg-primary" style="height: 100px; background: linear-gradient(45deg, var(--reddora-red), #b92b27);"></div>
            <div class="card-body pt-0 px-4 pb-4">
                <div class="d-flex align-items-end mb-3" style="margin-top: -40px;">
                    <div class="bg-white p-1 rounded shadow-sm me-3" style="width: 80px; height: 80px;">
                        <img src="uploads/sigs/<?= htmlspecialchars($sig['icon'] ?? 'default_sig.png') ?>"
                             alt="<?= htmlspecialchars($sig['name']) ?>"
                             class="rounded"
                             style="width: 100%; height: 100%; object-fit: cover;">
                    </div>

                    <div class="flex-grow-1">
                        <h2 class="fw-bold mb-0">s/<?= htmlspecialchars($sig['name']) ?></h2>
                        <p class="text-muted mb-0"><?= htmlspecialchars($sig['description']) ?></p>
                    </div>

                    <?php if ($user_id): ?>
                    <div>
                        <form action="post_action.php" method="POST">
                            <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                            <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                            <?php if ($is_member): ?>
                                <input type="hidden" name="action" value="leave_sig">
                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-4 fw-bold">Sair</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="join_sig">
                                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">Participar</button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">Discussões</h4>
                    <?php if ($is_member): ?>
                        <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newPostModal">
                            <i class="fas fa-plus me-1"></i> Novo Post
                        </button>
                    <?php endif; ?>
                </div>

                <div class="list-group shadow-sm">
                    <?php foreach($questions as $q): ?>
                        <a href="question.php?id=<?= $q['id'] ?>" class="list-group-item list-group-item-action py-3 border-0 border-bottom">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="mb-1">
                                        <?= getPostBadge($q['post_type']) ?>
                                        <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($q['title']) ?></span>
                                    </div>
                                    <?php $mod_ids = array_column($mods, 'id'); ?>
                                    <small class="text-muted">Postado por <b>u/<?= htmlspecialchars($q['username']) ?></b><?php if (in_array($q['user_id'], $mod_ids)): ?> <span class="badge bg-danger" style="font-size:0.55rem;">MOD</span><?php endif; ?> &bull; <?= date('d/m/Y H:i', strtotime($q['created_at'])) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                                        <i class="far fa-comments me-1"></i> <?= $q['answer_count'] ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>

                    <?php if (empty($questions)): ?>
                        <div class="text-center py-5 bg-white border rounded">
                            <i class="far fa-comment-dots fa-3x mb-3 text-muted opacity-50"></i>
                            <p class="text-muted">Ainda não há discussões nesta comunidade. Seja o primeiro!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold small text-muted text-uppercase">Sobre a Comunidade</div>
                    <div class="card-body">
                        <p class="small text-secondary mb-3"><?= htmlspecialchars($sig['description']) ?></p>
                        <hr>
                        <div class="d-flex justify-content-around text-center">
                            <div>
                                <div class="h5 fw-bold mb-0"><?= count($questions) ?></div>
                                <small class="text-muted text-uppercase" style="font-size: 0.65rem;">Discussões</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold small text-muted text-uppercase d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-shield-alt me-1 text-danger"></i> Moderadores</span>
                        <?php if ($is_mod): ?>
                            <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.7rem;" data-bs-toggle="modal" data-bs-target="#modManageModal">
                                <i class="fas fa-cog"></i> Gerir
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($mods)): ?>
                            <p class="text-muted small p-3 mb-0">Nenhum moderador definido.</p>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                            <?php foreach($mods as $mod): ?>
                                <li class="list-group-item d-flex align-items-center gap-2 py-2 px-3">
                                    <?php if ($mod['profile_pic']): ?>
                                        <img src="uploads/profiles/<?= htmlspecialchars($mod['profile_pic']) ?>" class="rounded-circle" style="width:28px;height:28px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width:28px;height:28px;">
                                            <i class="fas fa-user text-white" style="font-size:0.7rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="profile.php?id=<?= $mod['id'] ?>" class="text-decoration-none fw-bold small text-dark">u/<?= htmlspecialchars($mod['username']) ?></a>
                                        <span class="badge bg-danger ms-1" style="font-size:0.6rem;">MOD</span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_member): ?>
    <div class="modal fade" id="newPostModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <form action="post_action.php" method="POST">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold">Criar novo post em s/<?= htmlspecialchars($sig['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <input type="hidden" name="action" value="create_question">
                        <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                        <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">TIPO DE POST</label>
                            <div class="d-flex gap-2">
                                <input type="radio" class="btn-check" name="post_type" value="question" id="typeQ" checked>
                                <label class="btn btn-outline-primary btn-sm rounded-pill px-3" for="typeQ">Pergunta</label>

                                <input type="radio" class="btn-check" name="post_type" value="post" id="typeP">
                                <label class="btn btn-outline-success btn-sm rounded-pill px-3" for="typeP">Ensaio/Post</label>

                                <input type="radio" class="btn-check" name="post_type" value="short" id="typeS">
                                <label class="btn btn-outline-warning btn-sm rounded-pill px-3" for="typeS">Pensamento Curto</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <input type="text" name="title" class="form-control border-0 bg-light fw-bold" placeholder="Título do post" required>
                        </div>
                        <div class="mb-0">
                            <textarea name="body" class="form-control border-0 bg-light" rows="8" placeholder="O que queres discutir? (Markdown suportado)" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Publicar Post</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($is_mod): ?>
    <div class="modal fade" id="modManageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-shield-alt text-danger me-2"></i>Gerir Moderadores</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($mods)): ?>
                    <p class="small fw-bold text-muted text-uppercase mb-2">Moderadores Atuais</p>
                    <?php foreach($mods as $m): ?>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="small">u/<?= htmlspecialchars($m['username']) ?> <span class="badge bg-danger" style="font-size:0.6rem;">MOD</span></span>
                            <?php if ($m['id'] != $user_id): ?>
                            <form action="post_action.php" method="POST" class="m-0">
                                <input type="hidden" name="action" value="remove_mod">
                                <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                                <input type="hidden" name="target_user_id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size:0.75rem;">Remover</button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted small">(você)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($regular_members)): ?>
                    <hr>
                    <p class="small fw-bold text-muted text-uppercase mb-2">Promover Membro</p>
                    <form action="post_action.php" method="POST">
                        <input type="hidden" name="action" value="add_mod">
                        <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                        <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                        <div class="input-group input-group-sm">
                            <select name="target_user_id" class="form-select">
                                <?php foreach($regular_members as $m): ?>
                                    <option value="<?= $m['id'] ?>">u/<?= htmlspecialchars($m['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-danger">Promover a Mod</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <hr>
                    <p class="text-muted small">Todos os membros já são moderadores.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
