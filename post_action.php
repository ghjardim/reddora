<?php
// post_action.php
require 'db.php'; // Carrega sessão e banco

header('Content-Type: application/json'); // Padrão para AJAX é JSON, exceto redirecionamentos

$action = $_POST['action'] ?? '';

// Para ações normais (Login, Registro, etc), precisamos desligar o header JSON
// ou o navegador vai mostrar o JSON em vez de redirecionar.
// Vamos lidar com isso caso a caso.

if ($action === 'vote_ajax') {
    // === VOTAÇÃO VIA AJAX (SEM REFRESH) ===
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Login necessário']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $ans_id = (int)$_POST['ans_id'];
    $val = (int)$_POST['val']; // +1 ou -1

    // 1. Verifica se já votou
    $stmt = $pdo->prepare("SELECT vote_type FROM answer_votes WHERE user_id = ? AND answer_id = ?");
    $stmt->execute([$user_id, $ans_id]);
    $existing = $stmt->fetch();

    $new_user_vote = 0; // Estado final do voto do usuário (0 = neutro, 1 = up, -1 = down)

    if ($existing) {
        $old_val = (int)$existing['vote_type'];
        if ($old_val === $val) {
            // Remove voto (Toggle)
            $pdo->prepare("DELETE FROM answer_votes WHERE user_id = ? AND answer_id = ?")->execute([$user_id, $ans_id]);
            $pdo->prepare("UPDATE answers SET votes = votes - ? WHERE id = ?")->execute([$old_val, $ans_id]);
            $new_user_vote = 0;
        } else {
            // Troca voto
            $pdo->prepare("UPDATE answer_votes SET vote_type = ? WHERE user_id = ? AND answer_id = ?")->execute([$val, $user_id, $ans_id]);
            $diff = $val - $old_val;
            $pdo->prepare("UPDATE answers SET votes = votes + ? WHERE id = ?")->execute([$diff, $ans_id]);
            $new_user_vote = $val;
        }
    } else {
        // Novo voto
        $pdo->prepare("INSERT INTO answer_votes (user_id, answer_id, vote_type) VALUES (?, ?, ?)")->execute([$user_id, $ans_id, $val]);
        $pdo->prepare("UPDATE answers SET votes = votes + ? WHERE id = ?")->execute([$val, $ans_id]);
        $new_user_vote = $val;
    }

    // Busca o total atualizado
    $stmt = $pdo->prepare("SELECT votes FROM answers WHERE id = ?");
    $stmt->execute([$ans_id]);
    $new_total = $stmt->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'new_total' => $new_total,
        'user_vote' => $new_user_vote
    ]);
    exit;
}

// === A PARTIR DAQUI SÃO AS AÇÕES ANTIGAS (REDIRECIONAMENTO) ===
// Como definimos header JSON lá em cima, precisamos "desfazer" isso ou ignorar
// se for um redirect, mas o PHP lida bem com header Location sobrepondo Content-Type.

if ($action === 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
    } else {
        header("Location: login.php?error=1");
    }
} elseif ($action === 'register') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        header("Location: register.php?error=exists");
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        $new_user_id = $pdo->lastInsertId();
        $pdo->exec("INSERT INTO sig_memberships (user_id, sig_id) VALUES ($new_user_id, 1)");
        header("Location: login.php?registered=1");
    } catch (PDOException $e) {
        die("Erro: " . $e->getMessage());
    }
} elseif ($action === 'logout') {
    session_destroy();
    header("Location: login.php");
} elseif ($action === 'create_question') {
    if (!isset($_SESSION['user_id'])) die("Não autorizado");
    $stmt = $pdo->prepare("INSERT INTO questions (user_id, sig_id, title, body) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['sig_id'], $_POST['title'], $_POST['body']]);
    header("Location: index.php");
} elseif ($action === 'answer') {
    if (!isset($_SESSION['user_id'])) die("Não autorizado");
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : NULL;
    $stmt = $pdo->prepare("INSERT INTO answers (question_id, user_id, parent_id, body) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['question_id'], $_SESSION['user_id'], $parent_id, $_POST['body']]);
    header("Location: question.php?id=" . $_POST['question_id']);
} elseif ($action === 'join_sig') {
    if (!isset($_SESSION['user_id'])) die("Não autorizado");
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO sig_memberships (user_id, sig_id) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['sig_id']]);
    header("Location: " . (!empty($_POST['redirect']) ? $_POST['redirect'] : "sigs.php"));
} elseif ($action === 'leave_sig') {
    if (!isset($_SESSION['user_id'])) die("Não autorizado");
    $stmt = $pdo->prepare("DELETE FROM sig_memberships WHERE user_id = ? AND sig_id = ?");
    $stmt->execute([$_SESSION['user_id'], $_POST['sig_id']]);
    header("Location: " . (!empty($_POST['redirect']) ? $_POST['redirect'] : "sigs.php"));
}
?>
