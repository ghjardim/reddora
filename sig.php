<?php
require 'db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$sig_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Função para renderizar as badges de tipo
function getPostBadge($type) {
    switch($type) {
        case 'post': return '<span class="badge bg-success text-white border me-1"><i class="fas fa-file-alt"></i> Ensaio</span>';
        case 'short': return '<span class="badge bg-warning text-dark border me-1"><i class="fas fa-bolt"></i> Curto</span>';
        default: return '<span class="badge bg-primary text-white border me-1"><i class="fas fa-question-circle"></i> Pergunta</span>';
    }
}

// 1. Busca Informações do SIG
$stmt = $pdo->prepare("SELECT * FROM sigs WHERE id = ?");
$stmt->execute([$sig_id]);
$sig = $stmt->fetch();

if (!$sig) die("Sig não encontrado.");

// 2. Verifica membro
$stmt = $pdo->prepare("SELECT 1 FROM sig_memberships WHERE user_id = ? AND sig_id = ?");
$stmt->execute([$user_id, $sig_id]);
$is_member = (bool)$stmt->fetch();

// 3. Busca Perguntas/Posts
$stmt = $pdo->prepare("
    SELECT q.*, u.username,
    (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id) as answer_count
    FROM questions q
    JOIN users u ON q.user_id = u.id
    WHERE q.sig_id = ?
    ORDER BY q.created_at DESC
");
$stmt->execute([$sig_id]);
$questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>s/<?= htmlspecialchars($sig['name']) ?> - Reddora</title>
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

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Reddora</a>
            <div class="d-flex align-items-center">
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="text-white text-decoration-none me-3">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                </a>
                <a href="index.php" class="btn btn-sm btn-outline-light opacity-75">Voltar</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">

        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="fw-bold text-dark mb-0"><?= htmlspecialchars($sig['name']) ?></h1>
                        <p class="text-muted mt-1 mb-0 fs-5"><?= htmlspecialchars($sig['description']) ?></p>
                    </div>

                    <div>
                        <?php if ($is_member): ?>
                            <form action="post_action.php" method="POST">
                                <input type="hidden" name="action" value="leave_sig">
                                <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
                                <input type="hidden" name="redirect" value="sig.php?id=<?= $sig['id'] ?>">
                                <button class="btn btn-outline-danger btn-lg px-4 fw-bold rounded-pill">Sair</button>
                            </form>
                        <?php else: ?>
                            <form action="post_action.php" method="POST">
                                <input type="hidden" name="action" value="join_sig">
                                <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
                                <input type="hidden" name="redirect" value="sig.php?id=<?= $sig['id'] ?>">
                                <button class="btn btn-primary btn-lg px-4 fw-bold rounded-pill">Entrar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mx-auto">

                <?php if($is_member): ?>
                    <div class="card mb-4 shadow-sm border-0">
                        <div class="card-body bg-white rounded">
                            <h6 class="card-title text-muted text-uppercase small fw-bold mb-3">Criar publicação em <?= htmlspecialchars($sig['name']) ?></h6>
                            <form action="post_action.php" method="POST">
                                <input type="hidden" name="action" value="create_question">
                                <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
                                <input type="hidden" name="redirect" value="sig.php?id=<?= $sig['id'] ?>">

                                <select name="post_type" class="form-select form-select-sm mb-2" required>
                                    <option value="question">❓ Pergunta</option>
                                    <option value="post">📝 Ensaio / Post</option>
                                    <option value="short">⚡ Curto</option>
                                </select>
                                <input type="text" name="title" class="form-control mb-2 fw-bold" placeholder="Título interessante..." required>
                                <textarea name="body" class="form-control mb-2" placeholder="O que pretendes partilhar?" rows="2"></textarea>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary px-4 rounded-pill fw-bold">Publicar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light text-center border shadow-sm mb-4">
                        Precisas de entrar nesta comunidade para publicar.
                    </div>
                <?php endif; ?>

                <h5 class="mb-3 fw-bold text-dark border-bottom pb-2">Discussões Recentes</h5>

                <?php if(empty($questions)): ?>
                    <div class="text-center py-5 text-muted border rounded bg-white shadow-sm">
                        <i class="fas fa-wind fa-2x mb-2 opacity-50"></i><br>
                        Ainda não há discussões aqui.
                    </div>
                <?php endif; ?>

                <?php foreach($questions as $q):
                    $q_id = $q['id'];
                    $full_body = $q['body'];
                    $limit = 200; // Limite de caracteres para preview
                    $is_long = mb_strlen($full_body, 'UTF-8') > $limit;
                ?>
                <div class="card mb-3 hover-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                            <div class="d-flex align-items-center">
                                <?= getPostBadge($q['post_type']) ?>
                                <span class="ms-1">Postado por <a href="profile.php?id=<?= $q['user_id'] ?>" class="text-decoration-none fw-bold text-dark">u/<?= htmlspecialchars($q['username']) ?></a></span>
                            </div>
                            <span><?= date('d/m H:i', strtotime($q['created_at'])) ?></span>
                        </div>

                        <h4 class="card-title h5 mb-2">
                            <a href="question.php?id=<?= $q['id'] ?>" class="question-link text-dark">
                                <?= htmlspecialchars($q['title']) ?>
                            </a>
                        </h4>

                        <div class="card-text text-secondary mb-3">
                            <?php if ($is_long):
                                $short_body = mb_substr($full_body, 0, $limit, 'UTF-8') . '...';
                            ?>
                                <span id="short-text-<?= $q_id ?>">
                                    <?= nl2br(htmlspecialchars($short_body)) ?>
                                    <a class="read-more-link text-primary" onclick="togglePost(<?= $q_id ?>)">Ler mais</a>
                                </span>
                                <span id="full-text-<?= $q_id ?>" class="d-none">
                                    <?= nl2br(htmlspecialchars($full_body)) ?>
                                    <a class="read-more-link text-secondary" onclick="togglePost(<?= $q_id ?>)">Ler menos</a>
                                </span>
                            <?php else: ?>
                                <?= nl2br(htmlspecialchars($full_body)) ?>
                            <?php endif; ?>
                        </div>

                        <a href="question.php?id=<?= $q['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">
                            <i class="far fa-comments me-1"></i> Ver Discussão (<?= $q['answer_count'] ?>)
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function togglePost(id) {
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
    </script>
</body>
</html>
