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

// Busca configuração do formulário de aplicação
$stmt = $pdo->prepare("SELECT * FROM sig_application_forms WHERE sig_id = ?");
$stmt->execute([$sig_id]);
$app_form = $stmt->fetch();
$requires_application = $app_form && $app_form['requires_application'];
$form_questions = ($app_form && $app_form['questions_json']) ? json_decode($app_form['questions_json'], true) : [];

// Verifica se o usuário já tem candidatura pendente/rejeitada
$my_application = null;
if ($user_id && !$is_member) {
    $stmt = $pdo->prepare("SELECT * FROM sig_applications WHERE sig_id = ? AND user_id = ?");
    $stmt->execute([$sig_id, $user_id]);
    $my_application = $stmt->fetch();
}

// Busca aplicações pendentes (para mods)
$pending_applications = [];
if ($is_mod) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username, u.real_name, u.profile_pic
        FROM sig_applications a
        JOIN users u ON u.id = a.user_id
        WHERE a.sig_id = ? AND a.status = 'pending'
        ORDER BY a.created_at ASC
    ");
    $stmt->execute([$sig_id]);
    $pending_applications = $stmt->fetchAll();
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

// Busca todos os membros para exibição pública (mods primeiro, depois members)
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.real_name, u.profile_pic, sm.role
    FROM sig_memberships sm
    JOIN users u ON u.id = sm.user_id
    WHERE sm.sig_id = ?
    ORDER BY sm.role DESC, u.username ASC
");
$stmt->execute([$sig_id]);
$all_members = $stmt->fetchAll();
$member_count = count($all_members);

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

    <?php
    $nav_back = ['label' => 'Comunidades', 'href' => 'sigs.php'];
    require 'nav.php';
    ?>

    <div class="container mb-5">
        <div class="card shadow-sm border-0 mb-4 overflow-hidden">
            <div class="bg-primary" style="height: 100px; background: linear-gradient(45deg, var(--reddora-red), #b92b27);"></div>
            <div class="card-body pt-0 px-4 pb-4">
                <div class="d-flex align-items-end mb-3" style="margin-top: -40px;">
                    <div class="bg-white p-1 rounded shadow-sm me-3 position-relative" style="width: 80px; height: 80px;">
                        <img src="uploads/sigs/<?= htmlspecialchars($sig['icon'] ?? 'default_sig.png') ?>"
                             alt="<?= htmlspecialchars($sig['name']) ?>"
                             class="rounded"
                             style="width: 100%; height: 100%; object-fit: cover;">
                        <?php if ($is_mod): ?>
                        <button type="button"
                                class="position-absolute bottom-0 end-0 btn btn-dark btn-sm p-0 d-flex align-items-center justify-content-center rounded-circle shadow"
                                style="width:24px;height:24px;font-size:0.65rem;transform:translate(25%,25%);"
                                data-bs-toggle="modal" data-bs-target="#changeSigIconModal"
                                title="Alterar imagem do SIG">
                            <i class="fas fa-camera"></i>
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="flex-grow-1">
                        <h2 class="fw-bold mb-0">s/<?= htmlspecialchars($sig['name']) ?></h2>
                        <p class="text-muted mb-0"><?= htmlspecialchars($sig['description']) ?></p>
                    </div>

                    <?php if ($user_id): ?>
                    <div>
                        <?php if ($is_member): ?>
                            <form action="post_action.php" method="POST">
                                <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                                <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                                <input type="hidden" name="action" value="leave_sig">
                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-4 fw-bold">Sair</button>
                            </form>
                        <?php elseif ($requires_application): ?>
                            <?php if ($my_application && $my_application['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                                    <i class="fas fa-clock me-1"></i> Candidatura em análise
                                </span>
                            <?php elseif ($my_application && $my_application['status'] === 'rejected'): ?>
                                <button class="btn btn-outline-secondary btn-sm rounded-pill px-4 fw-bold"
                                        data-bs-toggle="modal" data-bs-target="#applyModal">
                                    <i class="fas fa-redo me-1"></i> Candidatar-se novamente
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm"
                                        data-bs-toggle="modal" data-bs-target="#applyModal">
                                    <i class="fas fa-paper-plane me-1"></i> Candidatar-se
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <form action="post_action.php" method="POST">
                                <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                                <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                                <input type="hidden" name="action" value="join_sig">
                                <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">Participar</button>
                            </form>
                        <?php endif; ?>
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
                            <div>
                                <div class="h5 fw-bold mb-0"><?= $member_count ?></div>
                                <small class="text-muted text-uppercase" style="font-size: 0.65rem;">Membros</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Membros -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold small text-muted text-uppercase d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-users me-1"></i> Membros</span>
                        <button class="btn btn-link btn-sm p-0 text-muted text-decoration-none" style="font-size:0.75rem;"
                                data-bs-toggle="modal" data-bs-target="#allMembersModal">
                            Ver todos (<?= $member_count ?>)
                        </button>
                    </div>
                    <div class="card-body pb-2">
                        <?php if (empty($all_members)): ?>
                            <p class="text-muted small mb-0">Nenhum membro ainda.</p>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <?php foreach(array_slice($all_members, 0, 12) as $m): ?>
                                <a href="profile.php?id=<?= $m['id'] ?>"
                                   title="u/<?= htmlspecialchars($m['username']) ?><?= $m['role'] === 'mod' ? ' (MOD)' : '' ?>"
                                   class="position-relative text-decoration-none d-inline-block">
                                    <?php if ($m['profile_pic']): ?>
                                        <img src="uploads/profiles/<?= htmlspecialchars($m['profile_pic']) ?>"
                                             class="rounded-circle border <?= $m['role'] === 'mod' ? 'border-danger border-2' : 'border-white' ?>"
                                             style="width:36px;height:36px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center border <?= $m['role'] === 'mod' ? 'border-danger border-2 bg-danger bg-opacity-10 text-danger' : 'border-white bg-primary text-white' ?>"
                                             style="width:36px;height:36px;font-size:0.8rem;font-weight:700;">
                                            <?= strtoupper(substr($m['username'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                <?php endforeach; ?>
                                <?php if ($member_count > 12): ?>
                                <button class="rounded-circle border border-light bg-light d-flex align-items-center justify-content-center text-muted fw-bold"
                                        style="width:36px;height:36px;font-size:0.7rem;cursor:pointer;"
                                        data-bs-toggle="modal" data-bs-target="#allMembersModal">
                                    +<?= $member_count - 12 ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_mod && count($pending_applications) > 0): ?>
                <div class="card shadow-sm border-0 mb-4 border-warning border-2">
                    <div class="card-header bg-warning bg-opacity-10 fw-bold small text-uppercase d-flex justify-content-between align-items-center">
                        <span class="text-warning-emphasis"><i class="fas fa-inbox me-1"></i> Candidaturas Pendentes</span>
                        <span class="badge bg-warning text-dark"><?= count($pending_applications) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach($pending_applications as $ap): ?>
                        <div class="border-bottom px-3 py-2">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php if ($ap['profile_pic']): ?>
                                    <img src="uploads/profiles/<?= htmlspecialchars($ap['profile_pic']) ?>" class="rounded-circle" style="width:24px;height:24px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:24px;height:24px;font-size:0.65rem;font-weight:bold;"><?= strtoupper(substr($ap['username'],0,1)) ?></div>
                                <?php endif; ?>
                                <span class="small fw-bold">u/<?= htmlspecialchars($ap['username']) ?></span>
                                <span class="text-muted small ms-auto"><?= date('d/m/Y', strtotime($ap['created_at'])) ?></span>
                            </div>
                            <button class="btn btn-outline-primary btn-sm w-100 py-0" style="font-size:0.75rem;"
                                    data-bs-toggle="modal" data-bs-target="#reviewModal<?= $ap['id'] ?>">
                                <i class="fas fa-eye me-1"></i> Revisar
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold small text-muted text-uppercase d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-shield-alt me-1 text-danger"></i> Moderadores</span>
                        <?php if ($is_mod): ?>
                            <div class="d-flex gap-1">
                                <?php if ($requires_application): ?>
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.7rem;" data-bs-toggle="modal" data-bs-target="#formBuilderModal">
                                    <i class="fas fa-clipboard-list"></i> Formulário
                                </button>
                                <?php else: ?>
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.7rem;" data-bs-toggle="modal" data-bs-target="#formBuilderModal">
                                    <i class="fas fa-clipboard-list"></i> Formulário
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.7rem;" data-bs-toggle="modal" data-bs-target="#modManageModal">
                                    <i class="fas fa-cog"></i> Gerir
                                </button>
                            </div>
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
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:28px;height:28px;font-size:0.75rem;font-weight:bold;">
                                            <?= strtoupper(substr($mod['username'], 0, 1)) ?>
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

    <?php if ($user_id && !$is_member && $requires_application): ?>
    <!-- Modal: Formulário de Candidatura -->
    <div class="modal fade" id="applyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <form action="post_action.php" method="POST">
                    <input type="hidden" name="action" value="apply_sig">
                    <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                    <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                    <div class="modal-header border-0">
                        <div>
                            <h5 class="modal-title fw-bold">Candidatura para s/<?= htmlspecialchars($sig['name']) ?></h5>
                            <p class="text-muted small mb-0">Responda às perguntas abaixo. Os moderadores irão avaliar sua candidatura.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-0">
                        <?php if ($my_application && $my_application['status'] === 'rejected' && $my_application['mod_note']): ?>
                        <div class="alert alert-danger small py-2 mb-3">
                            <i class="fas fa-times-circle me-1"></i> <strong>Candidatura anterior rejeitada:</strong>
                            <?= htmlspecialchars($my_application['mod_note']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if (empty($form_questions)): ?>
                            <p class="text-muted small">Este SIG não possui perguntas específicas. Sua candidatura será enviada sem respostas adicionais.</p>
                        <?php else: ?>
                            <?php foreach($form_questions as $i => $fq): ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small"><?= htmlspecialchars($fq['label']) ?><?= $fq['required'] ? ' <span class="text-danger">*</span>' : '' ?></label>
                                <?php if ($fq['type'] === 'textarea'): ?>
                                    <textarea name="answers[<?= $i ?>]" class="form-control form-control-sm" rows="3" <?= $fq['required'] ? 'required' : '' ?>></textarea>
                                <?php else: ?>
                                    <input type="text" name="answers[<?= $i ?>]" class="form-control form-control-sm" <?= $fq['required'] ? 'required' : '' ?>>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">
                            <i class="fas fa-paper-plane me-1"></i> Enviar Candidatura
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($is_mod): ?>
    <!-- Modais de revisão de cada candidatura -->
    <?php foreach($pending_applications as $ap): ?>
    <div class="modal fade" id="reviewModal<?= $ap['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-1">
                    <div>
                        <h5 class="modal-title fw-bold">Candidatura de u/<?= htmlspecialchars($ap['username']) ?></h5>
                        <small class="text-muted">Enviada em <?= date('d/m/Y H:i', strtotime($ap['created_at'])) ?></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php
                    $ap_answers = json_decode($ap['answers_json'], true) ?? [];
                    if (empty($form_questions) || empty($ap_answers)): ?>
                        <p class="text-muted small fst-italic">Candidatura sem respostas adicionais.</p>
                    <?php else: ?>
                        <?php foreach($form_questions as $i => $fq): ?>
                        <div class="mb-3">
                            <p class="small fw-bold mb-1 text-muted text-uppercase" style="font-size:0.7rem;"><?= htmlspecialchars($fq['label']) ?></p>
                            <p class="small mb-0"><?= htmlspecialchars($ap_answers[$i] ?? '—') ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <hr>
                    <p class="small fw-bold text-muted text-uppercase mb-1">Nota para o candidato (opcional)</p>
                    <form id="reviewForm<?= $ap['id'] ?>Approve" action="post_action.php" method="POST">
                        <input type="hidden" name="action" value="review_application">
                        <input type="hidden" name="application_id" value="<?= $ap['id'] ?>">
                        <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                        <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                        <input type="hidden" name="decision" value="approved">
                        <textarea name="mod_note" form="reviewForm<?= $ap['id'] ?>Approve" class="form-control form-control-sm mb-3" rows="2" placeholder="Mensagem opcional ao candidato..."></textarea>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success btn-sm flex-fill fw-bold">
                                <i class="fas fa-check me-1"></i> Aprovar
                            </button>
                        </div>
                    </form>
                    <form action="post_action.php" method="POST" class="mt-2">
                        <input type="hidden" name="action" value="review_application">
                        <input type="hidden" name="application_id" value="<?= $ap['id'] ?>">
                        <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                        <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                        <input type="hidden" name="decision" value="rejected">
                        <textarea name="mod_note" class="form-control form-control-sm mb-2" rows="2" placeholder="Explique o motivo da rejeição (recomendado)..."></textarea>
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fas fa-times me-1"></i> Rejeitar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Modal: Alterar Imagem do SIG -->
    <div class="modal fade" id="changeSigIconModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content border-0 shadow">
                <form action="post_action.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_sig_icon">
                    <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                    <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                    <div class="modal-header border-0 pb-1">
                        <h5 class="modal-title fw-bold"><i class="fas fa-image text-primary me-2"></i>Imagem do SIG</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-3">
                        <div class="mb-3">
                            <img src="uploads/sigs/<?= htmlspecialchars($sig['icon'] ?? 'default_sig.png') ?>"
                                 id="sigIconPreview"
                                 class="rounded shadow-sm"
                                 style="width:100px;height:100px;object-fit:cover;">
                        </div>
                        <label for="sigIconInput" class="btn btn-outline-primary btn-sm rounded-pill px-4">
                            <i class="fas fa-upload me-1"></i> Escolher imagem
                        </label>
                        <input type="file" id="sigIconInput" name="sig_icon" accept="image/jpeg,image/png,image/gif,image/webp"
                               class="d-none">
                        <p class="text-muted mt-2 mb-0" style="font-size:0.75rem;">JPG, PNG, GIF ou WEBP · máx. 2 MB</p>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="saveIconBtn" disabled>
                            <i class="fas fa-save me-1"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Construtor de Formulário -->
    <div class="modal fade" id="formBuilderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0">
                    <div>
                        <h5 class="modal-title fw-bold"><i class="fas fa-clipboard-list text-primary me-2"></i>Formulário de Candidatura</h5>
                        <p class="small text-muted mb-0">Configure as perguntas que os candidatos devem responder.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="formBuilderBody">
                    <form action="post_action.php" method="POST" id="formBuilderForm">
                        <input type="hidden" name="action" value="save_application_form">
                        <input type="hidden" name="sig_id" value="<?= $sig_id ?>">
                        <input type="hidden" name="redirect" value="sig.php?id=<?= $sig_id ?>">
                        <input type="hidden" name="questions_json" id="questionsJsonInput" value="">

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="requiresAppToggle"
                                   name="requires_application" value="1"
                                   <?= $requires_application ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="requiresAppToggle">
                                Exigir candidatura para entrar neste SIG
                            </label>
                        </div>

                        <div id="questionsContainer">
                            <!-- Perguntas renderizadas via JS -->
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addQuestionBtn">
                            <i class="fas fa-plus me-1"></i> Adicionar Pergunta
                        </button>

                        <hr>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold" id="saveFormBtn">
                                <i class="fas fa-save me-1"></i> Salvar Formulário
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Lista completa de membros -->
    <div class="modal fade" id="allMembersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-1">
                    <div>
                        <h5 class="modal-title fw-bold"><i class="fas fa-users me-2 text-primary"></i>Membros de s/<?= htmlspecialchars($sig['name']) ?></h5>
                        <small class="text-muted"><?= $member_count ?> <?= $member_count === 1 ? 'membro' : 'membros' ?></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <?php
                    $mods_list    = array_filter($all_members, fn($m) => $m['role'] === 'mod');
                    $members_list = array_filter($all_members, fn($m) => $m['role'] !== 'mod');
                    ?>
                    <?php if (!empty($mods_list)): ?>
                    <p class="small fw-bold text-muted text-uppercase mb-2" style="font-size:0.7rem;">
                        <i class="fas fa-shield-alt text-danger me-1"></i> Moderadores
                    </p>
                    <?php foreach($mods_list as $m): ?>
                    <a href="profile.php?id=<?= $m['id'] ?>" class="d-flex align-items-center gap-3 text-decoration-none text-dark py-2 border-bottom">
                        <?php if ($m['profile_pic']): ?>
                            <img src="uploads/profiles/<?= htmlspecialchars($m['profile_pic']) ?>"
                                 class="rounded-circle border border-danger border-2"
                                 style="width:40px;height:40px;object-fit:cover;flex-shrink:0;">
                        <?php else: ?>
                            <div class="rounded-circle bg-danger bg-opacity-10 border border-danger border-2 d-flex align-items-center justify-content-center text-danger fw-bold"
                                 style="width:40px;height:40px;flex-shrink:0;">
                                <?= strtoupper(substr($m['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1 min-width-0">
                            <div class="fw-semibold small lh-1 mb-1">
                                u/<?= htmlspecialchars($m['username']) ?>
                                <span class="badge bg-danger ms-1" style="font-size:0.55rem;">MOD</span>
                            </div>
                            <?php if ($m['real_name']): ?>
                            <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($m['real_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <i class="fas fa-chevron-right text-muted opacity-50" style="font-size:0.7rem;"></i>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($members_list)): ?>
                    <p class="small fw-bold text-muted text-uppercase mb-2 mt-3" style="font-size:0.7rem;">
                        <i class="fas fa-user me-1"></i> Membros
                    </p>
                    <?php foreach($members_list as $m): ?>
                    <a href="profile.php?id=<?= $m['id'] ?>" class="d-flex align-items-center gap-3 text-decoration-none text-dark py-2 border-bottom">
                        <?php if ($m['profile_pic']): ?>
                            <img src="uploads/profiles/<?= htmlspecialchars($m['profile_pic']) ?>"
                                 class="rounded-circle border border-light"
                                 style="width:40px;height:40px;object-fit:cover;flex-shrink:0;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white border border-light d-flex align-items-center justify-content-center fw-bold"
                                 style="width:40px;height:40px;flex-shrink:0;">
                                <?= strtoupper(substr($m['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1 min-width-0">
                            <div class="fw-semibold small lh-1 mb-1">u/<?= htmlspecialchars($m['username']) ?></div>
                            <?php if ($m['real_name']): ?>
                            <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($m['real_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <i class="fas fa-chevron-right text-muted opacity-50" style="font-size:0.7rem;"></i>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    // ── SIG Icon Preview ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const sigIconInput = document.getElementById('sigIconInput');
        if (sigIconInput) {
            sigIconInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;
                const preview = document.getElementById('sigIconPreview');
                const saveBtn = document.getElementById('saveIconBtn');
                preview.src = URL.createObjectURL(file);
                saveBtn.disabled = false;
            });
        }

        // ── Form Builder ─────────────────────────────────────────────────────
    });

    const initialQuestions = <?= json_encode($form_questions) ?>;
    let questions = JSON.parse(JSON.stringify(initialQuestions));

    function renderQuestions() {
        const container = document.getElementById('questionsContainer');
        if (!container) return;
        container.innerHTML = '';
        if (questions.length === 0) {
            container.innerHTML = '<p class="text-muted small fst-italic mb-3">Nenhuma pergunta adicionada ainda.</p>';
            return;
        }
        questions.forEach((q, i) => {
            const div = document.createElement('div');
            div.className = 'card border-0 bg-light mb-2';
            div.innerHTML = `
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="badge bg-secondary mt-1">${i + 1}</span>
                        <input type="text" class="form-control form-control-sm" placeholder="Texto da pergunta"
                               value="${q.label.replace(/"/g, '&quot;')}"
                               oninput="questions[${i}].label = this.value">
                        <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 ms-auto"
                                onclick="removeQuestion(${i})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-3 ms-4">
                        <select class="form-select form-select-sm" style="width:auto;"
                                onchange="questions[${i}].type = this.value">
                            <option value="text" ${q.type === 'text' ? 'selected' : ''}>Resposta curta</option>
                            <option value="textarea" ${q.type === 'textarea' ? 'selected' : ''}>Resposta longa</option>
                        </select>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="req_${i}"
                                   ${q.required ? 'checked' : ''}
                                   onchange="questions[${i}].required = this.checked">
                            <label class="form-check-label small" for="req_${i}">Obrigatória</label>
                        </div>
                    </div>
                </div>`;
            container.appendChild(div);
        });
    }

    function removeQuestion(i) {
        questions.splice(i, 1);
        renderQuestions();
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderQuestions();

        document.getElementById('addQuestionBtn')?.addEventListener('click', () => {
            questions.push({ label: '', type: 'textarea', required: true });
            renderQuestions();
        });

        document.getElementById('formBuilderForm')?.addEventListener('submit', (e) => {
            document.getElementById('questionsJsonInput').value = JSON.stringify(questions);
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
