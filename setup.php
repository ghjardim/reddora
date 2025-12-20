<?php
// setup.php
require 'db.php';

// === 1. LIMPEZA TOTAL ===
$tables = ['answers', 'questions', 'sig_memberships', 'sigs', 'users'];
foreach ($tables as $table) {
    $pdo->exec("DROP TABLE IF EXISTS $table");
}
echo "<h3>1. Banco de dados limpo.</h3>";

// === 2. CRIAÇÃO DA ESTRUTURA ===
$commands = [
    "CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE sigs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        description TEXT
    )",
    "CREATE TABLE sig_memberships (
        user_id INTEGER,
        sig_id INTEGER,
        PRIMARY KEY (user_id, sig_id)
    )",
    "CREATE TABLE questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        sig_id INTEGER,
        title TEXT,
        body TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question_id INTEGER,
        user_id INTEGER,
        parent_id INTEGER DEFAULT NULL,
        body TEXT,
        votes INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($commands as $cmd) {
    $pdo->exec($cmd);
}
echo "<h3>2. Tabelas criadas.</h3>";

// === 3. FUNÇÕES AUXILIARES (Para facilitar a criação de conteúdo) ===
function createUser($pdo, $name) {
    $pass = password_hash('123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute([$name, $pass]);
    return $pdo->lastInsertId();
}

function createSig($pdo, $name, $desc) {
    $stmt = $pdo->prepare("INSERT INTO sigs (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $desc]);
    return $pdo->lastInsertId();
}

function createQuestion($pdo, $uid, $sid, $title, $body) {
    $stmt = $pdo->prepare("INSERT INTO questions (user_id, sig_id, title, body) VALUES (?, ?, ?, ?)");
    $stmt->execute([$uid, $sid, $title, $body]);
    return $pdo->lastInsertId();
}

function createAnswer($pdo, $qid, $uid, $body, $votes = 0, $pid = null) {
    $stmt = $pdo->prepare("INSERT INTO answers (question_id, user_id, parent_id, body, votes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$qid, $uid, $pid, $body, $votes]);
    return $pdo->lastInsertId();
}

function joinSig($pdo, $uid, $sid) {
    $pdo->exec("INSERT OR IGNORE INTO sig_memberships (user_id, sig_id) VALUES ($uid, $sid)");
}

// === 4. POPULANDO DADOS ===

// -- Criando Usuários (Personas) --
$u_admin = createUser($pdo, 'admin');
$u_ana = createUser($pdo, 'Ana_Data');     // Data Scientist
$u_bob = createUser($pdo, 'Bob_Psycho');   // Estudante de Psico
$u_carl = createUser($pdo, 'Carl_Gamer');  // Gen Z gamer
$u_dani = createUser($pdo, 'Dani_Neuro');  // TDAH e ativista
$u_enzo = createUser($pdo, 'Enzo_2005');   // Gen Z estereotipado
$u_tio = createUser($pdo, 'Tio_Do_Pave');  // Boomer perdido

echo "<h3>3. Usuários criados (Senha: 123).</h3>";

// -- Criando SIGs --
$s_ds = createSig($pdo, 'Data Science', 'Python, R, IA e estatística.');
$s_psi = createSig($pdo, 'Psicologia', 'Mente humana, comportamento e terapia.');
$s_neuro = createSig($pdo, 'Neurodiversidade', 'TDAH, Autismo e convivência.');
$s_random = createSig($pdo, 'Papos Aleatórios', 'Conversas sem rumo e memes.');
$s_genz = createSig($pdo, 'Gen Z', 'Cringe é quem fala cringe.');

echo "<h3>4. Sigs criados.</h3>";

// -- Assinaturas (Quem segue o quê) --
// Admin segue tudo
for($i=1; $i<=5; $i++) joinSig($pdo, $u_admin, $i);

// Ana (Data Science, Neuro, Random)
joinSig($pdo, $u_ana, $s_ds); joinSig($pdo, $u_ana, $s_neuro); joinSig($pdo, $u_ana, $s_random);

// Bob (Psico, Neuro, Gen Z)
joinSig($pdo, $u_bob, $s_psi); joinSig($pdo, $u_bob, $s_neuro); joinSig($pdo, $u_bob, $s_genz);

// Dani (Neuro, Psico, Random)
joinSig($pdo, $u_dani, $s_neuro); joinSig($pdo, $u_dani, $s_psi); joinSig($pdo, $u_dani, $s_random);

// Enzo e Carl (Gen Z, Random, Jogos se tivesse)
joinSig($pdo, $u_enzo, $s_genz); joinSig($pdo, $u_enzo, $s_random);
joinSig($pdo, $u_carl, $s_genz); joinSig($pdo, $u_carl, $s_ds);

// Tio (Random, Psico)
joinSig($pdo, $u_tio, $s_random); joinSig($pdo, $u_tio, $s_psi);

echo "<h3>5. Assinaturas distribuídas.</h3>";

// === 6. GERANDO CONTEÚDO (PERGUNTAS E THREADS) ===

// --- SIG: DATA SCIENCE ---
$q = createQuestion($pdo, $u_ana, $s_ds, 'Pandas vs Polars em 2025?', 'Estou trabalhando com um dataset de 50GB. O Pandas está engasgando. Vale a pena migrar pro Polars ou Spark direto?');
    createAnswer($pdo, $q, $u_carl, 'Polars é vida. A sintaxe é muito mais limpa e é Rust por baixo.', 10);
    $a = createAnswer($pdo, $q, $u_tio, 'Na minha época a gente fazia isso no Excel com VBA.', -5);
        createAnswer($pdo, $q, $u_ana, 'Tio, 50GB no Excel explode o computador.', 20, $a);

$q = createQuestion($pdo, $u_carl, $s_ds, 'Alguém conseguindo emprego Jr em IA?', 'Tá difícil, pedem 5 anos de experiência em LLM sendo que o GPT saiu "ontem".');
    createAnswer($pdo, $q, $u_ana, 'Foca em Engenharia de Dados. O hype de IA vai passar, mas limpeza de dados é eterna.', 15);

// --- SIG: PSICOLOGIA ---
$q = createQuestion($pdo, $u_bob, $s_psi, 'Jung ou Freud?', 'Para análise de sonhos, qual abordagem vocês preferem?');
    $a = createAnswer($pdo, $q, $u_dani, 'Jung, sem dúvidas. Os arquétipos explicam muito mais.', 8);
        createAnswer($pdo, $q, $u_tio, 'Eu sonho que estou voando, o que significa?', 2, $a);
            createAnswer($pdo, $q, $u_bob, 'Fuga da realidade ou desejo de liberdade.', 3, $pdo->lastInsertId());

$q = createQuestion($pdo, $u_tio, $s_psi, 'Terapia online funciona mesmo?', 'Fico meio assim de falar meus problemas pro computador.');
    createAnswer($pdo, $q, $u_bob, 'Funciona sim! O vínculo terapêutico se estabelece igual. O importante é você se sentir seguro.', 12);
    createAnswer($pdo, $q, $u_dani, 'Pra mim é melhor, porque não preciso lidar com o trânsito (ansiedade social).', 10);

// --- SIG: NEURODIVERSIDADE ---
$q = createQuestion($pdo, $u_dani, $s_neuro, 'Dica de ouro pra TDAH: Body Doubling', 'Gente, descobri que trabalhar com alguém do lado (mesmo em silêncio) me faz focar 100x mais. Alguém usa?');
    $a = createAnswer($pdo, $q, $u_ana, 'Sim! Uso sites como o Focusmate. Mudou minha vida no home office.', 7);
    $a2 = createAnswer($pdo, $q, $u_enzo, 'Eu boto live da Twitch de fundo, conta?', 3);
        createAnswer($pdo, $q, $u_dani, 'Se não te distrair, conta sim!', 2, $a2);

$q = createQuestion($pdo, $u_bob, $s_neuro, 'Hiperfoco que durou 3 dias e acabou', 'Acabei de comprar R$ 500 de material de pintura e agora não quero mais pintar. Socorro.');
    createAnswer($pdo, $q, $u_dani, 'Bem-vindo ao clube kkkk. Guarda o material, daqui 6 meses a vontade volta.', 15);
    createAnswer($pdo, $q, $u_tio, 'Isso é falta de disciplina.', -10); // Comentário polêmico para gerar downvotes

// --- SIG: GEN Z ---
$q = createQuestion($pdo, $u_enzo, $s_genz, 'Usar emoji de caveira é cringe?', 'Me disseram que 💀 substituiu o 😂. Confere?');
    createAnswer($pdo, $q, $u_carl, 'Sim, 😂 é coisa de millennial. Usa 💀 ou 😭.', 5);
    $a = createAnswer($pdo, $q, $u_tio, 'Não entendi nada.', 0);
        createAnswer($pdo, $q, $u_enzo, 'É rir de nervoso, tio.', 2, $a);

$q = createQuestion($pdo, $u_carl, $s_genz, 'Skinny jeans morreu?', 'Vi no TikTok que agora só usam calça larga.');
    createAnswer($pdo, $q, $u_ana, 'Eu vou continuar usando minha calça justa e ninguém vai me impedir.', 20); // Millennial defendendo território

// --- SIG: PAPOS ALEATÓRIOS ---
$q = createQuestion($pdo, $u_tio, $s_random, 'Receita de pavê', 'Alguém tem aquela receita simples com bolacha maizena?');
    $a = createAnswer($pdo, $q, $u_enzo, 'É pavê ou pacumê? Kkkkk', -20); // A piada proibida
        createAnswer($pdo, $q, $u_tio, 'Essa é minha garoto!', 5, $a);
    createAnswer($pdo, $q, $u_ana, 'Creme de leite, leite condensado, limão. Bate tudo e intercala com a bolacha molhada no leite.', 10);

$q = createQuestion($pdo, $u_dani, $s_random, 'Se zumbis atacassem agora, qual seu objeto de defesa?', 'Olhe para a sua esquerda. O que tem lá?');
    createAnswer($pdo, $q, $u_bob, 'Uma caneca de café vazia. Estou morto.', 4);
    createAnswer($pdo, $q, $u_carl, 'Meu gato. Ele é brabo, sobrevivo.', 8);

echo "<h3>6. Conteúdo gerado! Muitas perguntas e respostas criadas.</h3>";
echo "<p><b>Logins disponíveis (Senha para todos: 123):</b></p>";
echo "<ul>";
echo "<li>admin</li>";
echo "<li>Ana_Data (Data Science)</li>";
echo "<li>Bob_Psycho (Psicologia)</li>";
echo "<li>Dani_Neuro (Neurodiversidade)</li>";
echo "<li>Enzo_2005 (Gen Z)</li>";
echo "<li>Tio_Do_Pave (Aleatórios)</li>";
echo "</ul>";
echo "<br><a href='login.php' class='btn btn-primary'>Ir para Login</a>";
?>
