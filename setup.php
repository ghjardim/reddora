<?php
// setup.php
require 'db.php';

// === 1. LIMPEZA TOTAL ===
// ADICIONADO: answer_agreements
$tables = ['answer_agreements', 'answer_votes', 'answers', 'questions', 'sig_memberships', 'sigs', 'users'];
foreach ($tables as $table) {
    $pdo->exec("DROP TABLE IF EXISTS $table");
}

echo "<div style='font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;'>";
echo "<h3 style='color: #b92b27;'>1. Base de dados limpa.</h3>";

// === 2. ESTRUTURA ===
$commands = [
    "CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        bio TEXT,
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
        post_type TEXT DEFAULT 'question', /* NOVA COLUNA: question, post, short */
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question_id INTEGER,
        user_id INTEGER,
        parent_id INTEGER DEFAULT NULL,
        body TEXT,
        votes INTEGER DEFAULT 0,          /* Karma (Qualidade) */
        agreement INTEGER DEFAULT 0,      /* NOVA COLUNA: Concordância */
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE answer_votes (
        user_id INTEGER,
        answer_id INTEGER,
        vote_type INTEGER,
        PRIMARY KEY (user_id, answer_id)
    )",
    "CREATE TABLE answer_agreements (
        user_id INTEGER,
        answer_id INTEGER,
        agreement_type INTEGER,
        PRIMARY KEY (user_id, answer_id)
    )"
];

foreach ($commands as $cmd) {
    $pdo->exec($cmd);
}
echo "<h3>2. Tabelas recriadas (com Post Types, Karma e Concordância).</h3>";

// === 3. FUNÇÕES AUXILIARES ===
function createUser($pdo, $name, $bio) {
    $pass = password_hash('123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, bio) VALUES (?, ?, ?)");
    $stmt->execute([$name, $pass, $bio]);
    return $pdo->lastInsertId();
}

function createSig($pdo, $name, $desc) {
    $stmt = $pdo->prepare("INSERT INTO sigs (name, description) VALUES (?, ?)");
    $stmt->execute([$name, $desc]);
    return $pdo->lastInsertId();
}

// Atualizado para suportar post_type
function createQuestion($pdo, $uid, $sid, $title, $body, $post_type = 'question') {
    $stmt = $pdo->prepare("INSERT INTO questions (user_id, sig_id, title, body, post_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$uid, $sid, $title, $body, $post_type]);
    return $pdo->lastInsertId();
}

// Atualizado para suportar agreement
function createAnswer($pdo, $qid, $uid, $body, $votes = 0, $agreement = 0, $pid = null) {
    $stmt = $pdo->prepare("INSERT INTO answers (question_id, user_id, parent_id, body, votes, agreement) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$qid, $uid, $pid, $body, $votes, $agreement]);
    return $pdo->lastInsertId();
}

function joinSig($pdo, $uid, $sid) {
    $pdo->exec("INSERT OR IGNORE INTO sig_memberships (user_id, sig_id) VALUES ($uid, $sid)");
}

// === 4. POPULANDO DADOS ===

// -- Utilizadores com Bios --
$u_admin   = createUser($pdo, 'admin', 'Administrador do Sistema | Mod');
$u_sofia   = createUser($pdo, 'Sofia_Data', 'Data Scientist Sênior | Python & Big Data');
$u_julia   = createUser($pdo, 'Julia_Psy', 'Psicóloga Clínica (TCC) | Especialista em Ansiedade');
$u_dani    = createUser($pdo, 'Dani_Neuro', 'Ativista da Neurodiversidade | TDAH & Autismo');
$u_felipe  = createUser($pdo, 'Felipe_GenZ', 'Front-end Dev | Entusiasta de Tech & Memes');
$u_renato  = createUser($pdo, 'Renato_Ceticismo', 'Engenheiro Civil | Cético Profissional');

echo "<h3>3. Utilizadores criados com Bios.</h3>";

// -- Sigs --
$s_ds      = createSig($pdo, 'Data Science', 'Python, R, IA, Estatística e Big Data.');
$s_psi     = createSig($pdo, 'Psicologia', 'Mente humana, comportamento e terapia.');
$s_neuro   = createSig($pdo, 'Neurodiversidade', 'TDAH, Autismo e convivência.');
$s_random  = createSig($pdo, 'Papos Aleatórios', 'Conversas sem rumo, memes e histórias.');
$s_genz    = createSig($pdo, 'Gen Z', 'Cringe é quem fala cringe. Discussões geracionais.');

echo "<h3>4. Sigs criados.</h3>";

// -- Assinaturas --
for($i=1; $i<=5; $i++) joinSig($pdo, $u_admin, $i);
joinSig($pdo, $u_sofia, $s_ds); joinSig($pdo, $u_sofia, $s_random);
joinSig($pdo, $u_julia, $s_psi); joinSig($pdo, $u_julia, $s_neuro);
joinSig($pdo, $u_dani, $s_neuro); joinSig($pdo, $u_dani, $s_psi); joinSig($pdo, $u_dani, $s_random);
joinSig($pdo, $u_felipe, $s_genz); joinSig($pdo, $u_felipe, $s_ds);
joinSig($pdo, $u_renato, $s_ds); joinSig($pdo, $u_renato, $s_psi); joinSig($pdo, $u_renato, $s_random);


// === 5. CONTEÚDO DENSO ===

// ---------------------------------------------------------
// THREAD 1: Data Science (Tipo: question)
// ---------------------------------------------------------
$body_q1 = "Estou a liderar um projeto de migração de dados numa fintech e a minha equipa técnica está num impasse ideológico.\n\nMetade dos engenheiros quer continuar a usar Pandas (v2.0 com PyArrow backend). A outra metade quer migrar tudo para Polars ou PySpark local. A minha dúvida é sobre o ROI desta refatoração.\n\nO custo de reescrever a base de código realmente compensa o ganho de performance?";

$q1 = createQuestion($pdo, $u_sofia, $s_ds, 'Pandas vs Polars em 2025: O custo de refatoração vale a pena?', $body_q1, 'question');

    $ans1 = "Fizemos exatamente essa migração no trimestre passado. A resposta curta é: SIM, compensa, e rápido.\n\nO Polars mudou o nosso jogo. O Pandas, mesmo com PyArrow, ainda tem um overhead significativo.";
    createAnswer($pdo, $q1, $u_admin, $ans1, 45, 30); // 45 Karma, 30 Agreement

    createAnswer($pdo, $q1, $u_renato, "Cuidado com o 'hype driven development'. Se a sua equipa inteira é fluente em Pandas, o tempo que vão perder a aprender a API do Polars pode sair mais caro que alugar uma máquina com mais RAM.", 12, -5); // 12 Karma, -5 Agreement


// ---------------------------------------------------------
// THREAD 2: Neurodiversidade (Tipo: post)
// ---------------------------------------------------------
$body_q2 = "Fui diagnosticado com TDAH do tipo misto tardiamente, aos 28 anos. \n\nO que sinto não é apenas alívio, é um luto profundo pelo 'eu potencial'. Quero partilhar convosco que esse sentimento é normal. Não existe 'quem poderíamos ter sido'. A pessoa que somos hoje, com todas as nossas defesas e hiperfocos, é o que importa. A medicação não apaga quem somos, apenas nos dá os óculos que sempre precisámos.";

$q2 = createQuestion($pdo, $u_admin, $s_neuro, 'Sobre o diagnóstico tardio e o luto do "eu potencial"', $body_q2, 'post');

    $ans2 = "Isto está incrivelmente bem escrito. Chamamos a isso de 'Luto pelo Eu Idealizado' na clínica. É um processo de desconstrução brutal mas necessário.";
    $a2 = createAnswer($pdo, $q2, $u_julia, $ans2, 89, 80);

        createAnswer($pdo, $q2, $u_dani, "Obrigado por partilharem isto. Fez-me chorar aqui, é exatamente o que estou a passar.", 34, 30, $a2);


// ---------------------------------------------------------
// THREAD 3: Gen Z (Tipo: post)
// ---------------------------------------------------------
$body_q3 = "O 'quiet quitting' não é preguiça geracional, é pura autodefesa económica. Os nossos pais compravam casas com um salário médio. Hoje, esse salário mal paga uma renda. Fazer apenas o que está no contrato é a única resposta racional a um contrato social que foi quebrado.";

$q3 = createQuestion($pdo, $u_felipe, $s_genz, 'Matemática do Quiet Quitting: Porque é que vestir a camisola não compensa', $body_q3, 'post');

    $ans3 = "Do ponto de vista de dados macroeconómicos, estás absolutamente correto. A lealdade é punida financeiramente hoje em dia.";
    createAnswer($pdo, $q3, $u_sofia, $ans3, 120, 100);

    createAnswer($pdo, $q3, $u_renato, "Uma tese muito bem estruturada, mas continuo a discordar da premissa. O mercado pune quem faz o mínimo, independente da conjuntura. Estão a romantizar a falta de ambição.", 50, -40); // Exemplo perfeito: Alto Karma (bem argumentado), Baixo Agreement (opinião impopular)


// ---------------------------------------------------------
// THREAD 4: Papos Aleatórios (Tipo: short)
// ---------------------------------------------------------
$body_q4 = "Massa com queijo > Sushi. Apenas factos.";

$q4 = createQuestion($pdo, $u_admin, $s_random, 'Verdades difíceis de engolir', $body_q4, 'short');

    createAnswer($pdo, $q4, $u_felipe, "Discordo totalmente, a cozinha asiática dá 10-0 a qualquer prato pesado de queijo.", 5, -10);


echo "<h3>5. Conteúdo estendido (com Posts e Shorts) gerado com sucesso!</h3>";
echo "<p class='lead'>Base de dados restaurada. Password de todos: 123</p>";
echo "<hr>";
echo "<a href='login.php' style='display:inline-block; background: #b92b27; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight:bold;'>Ir para Login</a>";
echo "</div>";
?>
