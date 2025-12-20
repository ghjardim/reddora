<?php
// setup.php
require 'db.php';

// === 1. LIMPEZA TOTAL ===
$tables = ['answers', 'questions', 'sig_memberships', 'sigs', 'users'];
foreach ($tables as $table) {
    $pdo->exec("DROP TABLE IF EXISTS $table");
}
echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h3>1. Banco de dados limpo.</h3>";

// === 2. ESTRUTURA ===
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
echo "<h3>2. Tabelas recriadas.</h3>";

// === 3. FUNÇÕES AUXILIARES ===
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

// -- Usuários --
$u_admin   = createUser($pdo, 'admin');            // O Admin voltou!
$u_sofia   = createUser($pdo, 'Sofia_Data');       // Expert em Dados
$u_julia   = createUser($pdo, 'Julia_Psy');        // Psicóloga
$u_dani    = createUser($pdo, 'Dani_Neuro');       // Ativista Neurodiversidade
$u_felipe  = createUser($pdo, 'Felipe_GenZ');      // O Jovem
$u_renato  = createUser($pdo, 'Renato_Ceticismo'); // O Polêmico

echo "<h3>3. Usuários criados (incluindo admin).</h3>";

// -- Sigs (A lista exata que você pediu) --
$s_ds      = createSig($pdo, 'Data Science', 'Python, R, IA, Estatística e Big Data.');
$s_psi     = createSig($pdo, 'Psicologia', 'Mente humana, comportamento e terapia.');
$s_neuro   = createSig($pdo, 'Neurodiversidade', 'TDAH, Autismo e convivência.');
$s_random  = createSig($pdo, 'Papos Aleatórios', 'Conversas sem rumo, memes e histórias.');
$s_genz    = createSig($pdo, 'Gen Z', 'Cringe é quem fala cringe. Discussões geracionais.');

echo "<h3>4. Sigs criados: Data Science, Psicologia, Neuro, Papos e Gen Z.</h3>";

// -- Assinaturas --
// Admin segue tudo para testar
for($i=1; $i<=5; $i++) joinSig($pdo, $u_admin, $i);

joinSig($pdo, $u_sofia, $s_ds); joinSig($pdo, $u_sofia, $s_random);
joinSig($pdo, $u_julia, $s_psi); joinSig($pdo, $u_julia, $s_neuro);
joinSig($pdo, $u_dani, $s_neuro); joinSig($pdo, $u_dani, $s_psi); joinSig($pdo, $u_dani, $s_random);
joinSig($pdo, $u_felipe, $s_genz); joinSig($pdo, $u_felipe, $s_ds);
joinSig($pdo, $u_renato, $s_ds); joinSig($pdo, $u_renato, $s_psi); joinSig($pdo, $u_renato, $s_random);

// === 5. CONTEÚDO DENSO (QUORA STYLE) ===

// ---------------------------------------------------------
// THREAD 1: Data Science (Sofia vs Renato)
// ---------------------------------------------------------
$body_q1 = "Estou liderando um projeto de migração de dados e minha equipe está dividida.\n\nMetade quer continuar usando Pandas pela familiaridade, mas estamos lidando com parquets de 50GB+. A outra metade quer migrar tudo para Polars ou PySpark.\n\nO custo de reescrever a base de código (refatoração) compensa o ganho de performance? Alguém tem benchmarks reais de produção, não apenas de tutoriais?";

$q1 = createQuestion($pdo, $u_sofia, $s_ds, 'Pandas vs Polars em 2025: O custo de refatoração vale a pena?', $body_q1);

    // Resposta do Admin (dando uma de técnico)
    $ans1 = "Fizemos essa migração na minha empresa mês passado. A resposta curta é: SIM.\n\nO Pandas (mesmo o 2.0 com PyArrow) ainda sofre com o consumo de memória RAM. O Polars, por ser escrito em Rust e usar execução preguiçosa (lazy evaluation), mudou nosso jogo.\n\nTínhamos um pipeline que levava 4 horas no Pandas e consumia 64GB de RAM. Reescrevemos em Polars em 3 dias e agora roda em 20 minutos usando 8GB. A sintaxe é diferente, mas intuitiva.";
    createAnswer($pdo, $q1, $u_admin, $ans1, 45);

    // Contraponto Cético
    createAnswer($pdo, $q1, $u_renato, "Cuidado com o hype train. Se sua equipe já sabe Pandas, o tempo que vocês vão perder aprendendo as idiossincrasias do Polars pode sair mais caro que simplesmente alugar uma máquina maior na AWS.\n\nHardware é barato; hora de engenheiro é cara.", 12);


// ---------------------------------------------------------
// THREAD 2: Neurodiversidade (Dani vs Julia)
// ---------------------------------------------------------
$body_q2 = "Fui diagnosticado com TDAH tardio (aos 28 anos) e sinto um misto de alívio e luto.\n\nAlívio por finalmente entender por que perdi tantas chaves e prazos. Luto por pensar 'quem eu poderia ter sido' se tivesse sido tratado na escola.\n\nComo lidar com essa sensação de 'tempo perdido'? O hiperfoco na medicação resolve tudo?";

$q2 = createQuestion($pdo, $u_admin, $s_neuro, 'Diagnóstico tardio de TDAH: Como lidar com o luto do "eu potencial"?', $body_q2);

    $ans2 = "Essa sensação é o que chamamos clinicamente de 'Luto pelo Eu Idealizado'. É extremamente comum em diagnósticos tardios.\n\nPrimeiro, entenda que a medicação não é mágica; ela é como um óculos. Ela te permite ver, mas você ainda precisa aprender a ler.\n\nSobre o tempo perdido: não existe. Sua história, com todo o caos e criatividade não-linear, formou quem você é hoje. Muitos pacientes meus descobrem que seus mecanismos de compensação (criados para sobreviver sem remédio) se tornam superpoderes quando a ansiedade é tratada.";
    $a2 = createAnswer($pdo, $q2, $u_julia, $ans2, 89);

        createAnswer($pdo, $q2, $u_dani, "Perfeito, Julia! Eu chorava pensando que era 'burra' ou 'preguiçosa'. O diagnóstico não muda o passado, mas reescreve a narrativa dele. Agora você sabe que não era preguiça, era uma deficiência executiva.", 34, $a2);


// ---------------------------------------------------------
// THREAD 3: Gen Z (Felipe vs Renato)
// ---------------------------------------------------------
$body_q3 = "Vejo muitos Millennials criticando a Gen Z por 'não querer trabalhar', mas a conta não fecha.\n\nMeus pais compraram casa e carro com o salário de um emprego mediano nos anos 90. Hoje, um salário júnior mal paga o aluguel de um estúdio.\n\nO 'quiet quitting' não é apenas uma resposta racional a um contrato social que foi quebrado pelas gerações anteriores?";

$q3 = createQuestion($pdo, $u_felipe, $s_genz, 'O "Quiet Quitting" é preguiça ou autodefesa econômica?', $body_q3);

    $ans3 = "É autodefesa, pura e simples. O contrato social antigo era: 'Dê lealdade à empresa e a empresa cuidará de você'. Isso morreu em 2008.\n\nHoje, a lealdade é punida com aumentos abaixo da inflação, enquanto quem troca de emprego a cada 2 anos aumenta o salário em 20%.\n\nA Gen Z apenas percebeu isso mais cedo porque já nascemos na crise. Não vamos dar 110% por 1% de retorno.";
    $a3 = createAnswer($pdo, $q3, $u_sofia, $ans3, 120);

    createAnswer($pdo, $q3, $u_renato, "Vocês racionalizam demais a falta de garra. Toda geração teve crises. A diferença é que a de vocês tem TikTok para chorar em público e ganhar like por isso. O mercado pune quem faz o mínimo, independente da geração.", -15);


// ---------------------------------------------------------
// THREAD 4: Papos Aleatórios (Admin)
// ---------------------------------------------------------
$body_q4 = "Estou num debate acalorado aqui em casa. Se vocês tivessem que comer APENAS UMA culinária pelo resto da vida, qual seria?\n\nRegras: Tem que incluir café da manhã, almoço e jantar. E tem que ser saudável o suficiente pra você não morrer em 1 ano.";

$q4 = createQuestion($pdo, $u_admin, $s_random, 'Qual a melhor culinária do mundo para viver exclusivamente dela?', $body_q4);

    createAnswer($pdo, $q4, $u_felipe, "Japonesa, sem dúvidas. Sushi, Ramen, Teishoku... tem tudo. É saudável, tem peixe, tem vegetais e é deliciosa.", 20);

    $ans4 = "Italiana. E não aceito discussões.\n\nMas não a italiana 'americana' cheia de creme. A italiana real mediterrânea. Azeite, tomate, massas frescas, frutos do mar. É a base da dieta mediterrânea, que é a mais saudável do mundo, e ainda por cima te faz feliz.";
    createAnswer($pdo, $q4, $u_julia, $ans4, 45);


echo "<h3>5. Conteúdo gerado com sucesso!</h3>";
echo "<p>Admin restaurado. Sigs restaurados. Textos longos inseridos.</p>";
echo "<br><a href='login.php' style='background: #b92b27; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir para Login</a>";
echo "</div>";
?>
