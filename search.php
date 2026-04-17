<?php
// search.php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($query !== '') {
    // Adiciona o asterisco para permitir correspondência de prefixo (ex: pesqui* encontra pesquisa, pesquisar)
    $search_term = $query . '*';

    // A mágica FTS5 BM25 usando UNION ALL (une resultados de perguntas e de respostas)
    $stmt = $pdo->prepare("
        SELECT
            'question' as result_type,
            q.id as question_id,
            q.id as item_id,
            q.title,
            q.body,
            q.post_type,
            s.name as sig_name,
            s.id as sig_id,
            u.username,
            (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id) as answer_count,
            fts.rank as bm25_score
        FROM questions_fts fts
        JOIN questions q ON fts.rowid = q.id
        JOIN sigs s ON q.sig_id = s.id
        JOIN users u ON q.user_id = u.id
        WHERE questions_fts MATCH :search

        UNION ALL

        SELECT
            'answer' as result_type,
            q.id as question_id,
            a.id as item_id,
            q.title,
            a.body,
            'answer' as post_type,
            s.name as sig_name,
            s.id as sig_id,
            u.username,
            0 as answer_count,
            fts.rank as bm25_score
        FROM answers_fts fts
        JOIN answers a ON fts.rowid = a.id
        JOIN questions q ON a.question_id = q.id
        JOIN sigs s ON q.sig_id = s.id
        JOIN users u ON a.user_id = u.id
        WHERE answers_fts MATCH :search

        ORDER BY bm25_score ASC
        LIMIT 50
    ");
    $stmt->execute(['search' => $search_term]);
    $results = $stmt->fetchAll();
}

function getPostBadge($type) {
    switch($type) {
        case 'post': return '<span class="badge bg-success text-white border me-1"><i class="fas fa-file-alt"></i> Ensaio</span>';
        case 'short': return '<span class="badge bg-warning text-dark border me-1"><i class="fas fa-bolt"></i> Curto</span>';
        case 'answer': return '<span class="badge bg-info text-dark border me-1"><i class="fas fa-reply"></i> Resposta</span>';
        default: return '<span class="badge bg-primary text-white border me-1"><i class="fas fa-question-circle"></i> Pergunta</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Pesquisa: <?= htmlspecialchars($query) ?> - Reddora</title>
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
                    <input type="text" name="q" class="form-control border-0" placeholder="Pesquisar na Reddora..." value="<?= htmlspecialchars($query) ?>" required>
                    <button class="btn btn-light text-primary fw-bold px-3" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>

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

    <div class="container mb-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h4 class="mb-4 fw-bold">Resultados para "<?= htmlspecialchars($query) ?>"</h4>

                <?php if (empty($results) && $query !== ''): ?>
                    <div class="alert alert-light border text-center p-5 shadow-sm text-muted">
                        <i class="fas fa-search fa-3x mb-3 opacity-50"></i><br>
                        Nenhum resultado encontrado para a sua pesquisa.
                    </div>
                <?php endif; ?>

                <?php foreach($results as $q): ?>
                <div class="card mb-3 border-0 shadow-sm hover-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                            <div class="d-flex align-items-center">
                                <?= getPostBadge($q['post_type']) ?>
                                <span class="ms-1 text-secondary">em <a href="sig.php?id=<?= $q['sig_id'] ?>" class="fw-bold text-dark text-decoration-none"><?= htmlspecialchars($q['sig_name']) ?></a></span>
                                <span class="mx-2">&bull;</span>
                                <span>u/<?= htmlspecialchars($q['username']) ?></span>
                            </div>
                            <span class="badge bg-light text-muted border" title="Pontuação de relevância do motor de pesquisa">BM25 Score: <?= round(abs($q['bm25_score']), 2) ?></span>
                        </div>

                        <h5 class="card-title fw-bold mb-2">
                            <a href="question.php?id=<?= $q['question_id'] ?>" class="text-dark text-decoration-none">
                                <?= $q['result_type'] === 'answer' ? 'Re: ' : '' ?><?= htmlspecialchars($q['title']) ?>
                            </a>
                        </h5>

                        <div class="text-muted small mb-3" style="line-height: 1.5;">
                            <?= htmlspecialchars(mb_substr(strip_tags($q['body']), 0, 200, 'UTF-8')) ?>...
                        </div>

                        <a href="question.php?id=<?= $q['question_id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">
                            <i class="far fa-comments me-1"></i> Ir para Discussão <?= $q['result_type'] === 'question' ? '(' . $q['answer_count'] . ')' : '' ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
