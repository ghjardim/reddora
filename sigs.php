<?php
// sigs.php
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];

// Busca todos os SIGs com contagem de membros e status de adesão do usuário atual
$stmt = $pdo->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM sig_memberships WHERE sig_id = s.id) as member_count,
           (SELECT COUNT(*) FROM sig_memberships WHERE sig_id = s.id AND user_id = ?) as is_member
    FROM sigs s
    ORDER BY s.name ASC
");
$stmt->execute([$user_id]);
$all_sigs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Comunidades - Reddora</title>
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
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="text-white text-decoration-none me-3">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                </a>
                <a href="index.php" class="btn btn-sm btn-outline-light opacity-75">Voltar</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-dark mb-0">Explorar Comunidades</h3>
                    <span class="badge bg-white text-primary border rounded-pill px-3 shadow-sm"><?= count($all_sigs) ?> SIGs disponíveis</span>
                </div>

                <div class="row g-3">
                    <?php foreach($all_sigs as $sig): ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm hover-card">
                            <div class="card-body d-flex align-items-center p-3">

                                <div class="me-3">
                                    <img src="uploads/sigs/<?= htmlspecialchars($sig['icon'] ?? 'default_sig.png') ?>"
                                         alt="<?= htmlspecialchars($sig['name']) ?>"
                                         class="rounded shadow-sm"
                                         style="width: 60px; height: 60px; object-fit: cover; background-color: #f8f9fa;">
                                </div>

                                <div class="flex-grow-1">
                                    <h5 class="mb-1 fw-bold">
                                        <a href="sig.php?id=<?= $sig['id'] ?>" class="text-dark text-decoration-none">
                                            s/<?= htmlspecialchars($sig['name']) ?>
                                        </a>
                                    </h5>
                                    <p class="text-muted small mb-0 pe-3 text-truncate-2" style="max-width: 400px;">
                                        <?= htmlspecialchars($sig['description']) ?>
                                    </p>
                                    <div class="mt-1">
                                        <small class="text-primary fw-bold" style="font-size: 0.75rem;">
                                            <i class="fas fa-users me-1"></i> <?= $sig['member_count'] ?> membros
                                        </small>
                                    </div>
                                </div>

                                <div class="ms-auto text-end" style="min-width: 120px;">
                                    <form action="post_action.php" method="POST">
                                        <input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
                                        <input type="hidden" name="redirect" value="sigs.php">

                                        <?php if($sig['is_member']): ?>
                                            <input type="hidden" name="action" value="leave_sig">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold w-100">
                                                Sair
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="join_sig">
                                            <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold w-100 shadow-sm">
                                                Participar
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
