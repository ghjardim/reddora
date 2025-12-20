<?php
require 'db.php';

$user_id = $_SESSION['user_id'];

// 1. Busca TODOS os Sigs disponíveis no sistema
$stmt = $pdo->query("SELECT * FROM sigs ORDER BY name ASC");
$all_sigs = $stmt->fetchAll();

// 2. Busca apenas os IDs dos Sigs que EU sigo
// FETCH_COLUMN retorna um array simples: [1, 2, 5] em vez de array associativo
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
</head>
<body class="bg-light">

	<nav class="navbar navbar-dark bg-primary mb-4">
		<div class="container">
			<a class="navbar-brand fw-bold" href="index.php">Reddora</a>
			<a href="index.php" class="btn btn-sm btn-outline-light">Voltar ao Feed</a>
		</div>
	</nav>

	<div class="container">
		<div class="row">
			<div class="col-md-8 mx-auto">
				<h3 class="mb-4"><i class="fas fa-compass"></i> Explorar Comunidades</h3>

				<div class="list-group shadow-sm">
					<?php foreach($all_sigs as $sig): ?>
<?php 
// Verifica se o ID deste Sig está na minha lista de inscrições
$is_member = in_array($sig['id'], $my_memberships); 
?>

						<div class="list-group-item d-flex justify-content-between align-items-center p-3">
							<div>
								<h5 class="mb-1 fw-bold">
    <a href="sig.php?id=<?= $sig['id'] ?>" class="text-decoration-none text-primary">
        s/<?= htmlspecialchars($sig['name']) ?>
    </a>
</h5>
								<p class="mb-0 text-muted small"><?= htmlspecialchars($sig['description']) ?></p>
							</div>

							<div>
								<?php if ($is_member): ?>
									<form action="post_action.php" method="POST">
										<input type="hidden" name="action" value="leave_sig">
										<input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
										<button class="btn btn-outline-danger btn-sm" style="width: 100px;">
											<i class="fas fa-minus-circle"></i> Sair
										</button>
									</form>
								<?php else: ?>
									<form action="post_action.php" method="POST">
										<input type="hidden" name="action" value="join_sig">
										<input type="hidden" name="sig_id" value="<?= $sig['id'] ?>">
										<button class="btn btn-success btn-sm" style="width: 100px;">
											<i class="fas fa-plus-circle"></i> Entrar
										</button>
									</form>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

			</div>
		</div>
	</div>
</body>
</html>
