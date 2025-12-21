<?php
require 'db.php';

$user_id = $_SESSION['user_id'];

// 1. Busca TODOS os Sigs
$stmt = $pdo->query("SELECT * FROM sigs ORDER BY name ASC");
$all_sigs = $stmt->fetchAll();

// 2. Busca IDs que eu sigo
$stmt = $pdo->prepare("SELECT sig_id FROM sig_memberships WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_memberships = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Explorar Comunidades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
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

    <div class="container">

        <div class="row mb-4">
            <div class="col-12 text-center">
                <h2 class="fw-bold text-dark"><i class="fas fa-compass text-primary"></i> Explorar Comunidades</h2>
                <p class="text-muted">Encontre novos tópicos e junte-se às discussões.</p>
            </div>
        </div>

        <div class="row">
            <?php foreach($all_sigs as $sig): ?>
                <?php
                    $is_member = in_array($sig['id'], $my_memberships);
                ?>

                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm hover-card border-0">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold">
                                <a href="sig.php?id=<?= $sig['id'] ?>" class="text-dark text-decoration-none">
                                    <?= htmlspecialchars($sig['name']) ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted small flex-grow-1">
                                <?= htmlspecialchars($sig['description']) ?>
                            </p>

                            <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center">
                                <a href="sig.php?id=<?= $sig['id'] ?>" class="text-decoration-none small text-primary fw-bold">
                                    Visitar
                                </a>

                                <?php if ($is_member): ?>
                                    <form action="post_action.php" method="POST">
                                        <input type="hidden" name="action" value="leave_sig">
                                        <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold">Sair</button>
                                    </form>
                                <?php else: ?>
                                    <form action="post_action.php" method="POST">
                                        <input type="hidden" name="action" value="join_sig">
                                        <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
                                        <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">Entrar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</body>
</html>
