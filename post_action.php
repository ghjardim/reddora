<?php
// post_action.php
require 'db.php'; // Carrega sessão e banco

$action = $_POST['action'] ?? '';

// === LOGIN ===
if ($action === 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Sucesso
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
    } else {
        // Falha
        header("Location: login.php?error=1");
    }

// === REGISTRO ===
} elseif ($action === 'register') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Verifica se já existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        header("Location: register.php?error=exists");
        exit;
    }

    // Cria hash seguro da senha
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        $new_user_id = $pdo->lastInsertId();

        // Inscreve automaticamente no Sig #1 (Tecnologia) para não ficar vazio
        $pdo->exec("INSERT INTO sig_memberships (user_id, sig_id) VALUES ($new_user_id, 1)");

        header("Location: login.php?registered=1");
    } catch (PDOException $e) {
        die("Erro ao registrar: " . $e->getMessage());
    }

// === SAIR (LOGOUT) ===
} elseif ($action === 'logout') {
    session_destroy();
    header("Location: login.php");

// === CRIAR PERGUNTA ===
} elseif ($action === 'create_question') {
    if (!isset($_SESSION['user_id'])) die("Não autorizado");

    $stmt = $pdo->prepare("INSERT INTO questions (user_id, sig_id, title, body) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['sig_id'], $_POST['title'], $_POST['body']]);
    header("Location: index.php");

// === RESPONDER ===
} elseif ($action === 'answer') {
    if (!isset($_SESSION['user_id'])) die("Não autorizado");

    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : NULL;
    $stmt = $pdo->prepare("INSERT INTO answers (question_id, user_id, parent_id, body) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['question_id'], $_SESSION['user_id'], $parent_id, $_POST['body']]);
    header("Location: question.php?id=" . $_POST['question_id']);

// === VOTAR ===
} elseif ($action === 'vote') {
    if (!isset($_SESSION['user_id'])) die("Não autorizado");

    $val = (int)$_POST['val'];
    $ans_id = (int)$_POST['ans_id'];
    $sql = ($val > 0) ? "UPDATE answers SET votes = votes + 1 WHERE id = ?" : "UPDATE answers SET votes = votes - 1 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ans_id]);
    header("Location: question.php?id=" . $_POST['q_id']);
// === ENTRAR NO SIG ===
} elseif ($action === 'join_sig') {
    if (!isset($_SESSION['user_id'])) die("Não autorizado");

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO sig_memberships (user_id, sig_id) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['sig_id']]);
    
    // ALTERAÇÃO: Se houver redirect, usa ele. Se não, vai pro padrão.
    if (!empty($_POST['redirect'])) {
        header("Location: " . $_POST['redirect']);
    } else {
        header("Location: sigs.php");
    }

// === SAIR DO SIG ===
} elseif ($action === 'leave_sig') {
    if (!isset($_SESSION['user_id'])) die("Não autorizado");

    $stmt = $pdo->prepare("DELETE FROM sig_memberships WHERE user_id = ? AND sig_id = ?");
    $stmt->execute([$_SESSION['user_id'], $_POST['sig_id']]);
    
    // ALTERAÇÃO:
    if (!empty($_POST['redirect'])) {
        header("Location: " . $_POST['redirect']);
    } else {
        header("Location: sigs.php");
    }
}
?>
