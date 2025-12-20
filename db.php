<?php
// db.php

// CORREÇÃO: Só inicia a sessão se ela ainda não existir
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Conecta ao banco SQLite
    $pdo = new PDO('sqlite:database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// === SISTEMA DE PROTEÇÃO (AUTH GUARD) ===

// Lista de arquivos públicos (que não precisam de login)
$public_pages = ['login.php', 'register.php', 'post_action.php', 'setup.php'];

// Pega apenas o nome do arquivo atual (ex: index.php)
$current_page = basename($_SERVER['PHP_SELF']);

// Se o usuário NÃO está logado E tenta acessar uma página privada...
// E também garante que não estamos num loop de redirecionamento
if (!isset($_SESSION['user_id']) && !in_array($current_page, $public_pages)) {
    header('Location: login.php');
    exit;
}
?>
