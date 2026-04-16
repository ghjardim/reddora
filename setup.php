<?php
// setup.php
require 'db.php';

// === 1. LIMPEZA TOTAL ===
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

function createQuestion($pdo, $uid, $sid, $title, $body, $post_type = 'question') {
    $stmt = $pdo->prepare("INSERT INTO questions (user_id, sig_id, title, body, post_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$uid, $sid, $title, $body, $post_type]);
    return $pdo->lastInsertId();
}

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


// === 5. CONTEÚDO DENSO (AGORA COM MARKDOWN E POST TYPES) ===

// ---------------------------------------------------------
// THREAD 1: Data Science (Tipo: question)
// ---------------------------------------------------------
$body_q1 = "Estou a liderar um projeto de migração de dados numa fintech e a minha equipa técnica está num impasse ideológico.

Metade dos engenheiros quer continuar a usar **Pandas (v2.0 com PyArrow backend)** pela familiaridade e ecossistema. Porém, estamos a lidar com dumps diários de Parquet que já excedem 50GB. A máquina de desenvolvimento padrão tem 32GB de RAM, o que causa *OOM (Out of Memory)* constantemente se não processarmos em chunks.

A outra metade quer migrar tudo para **Polars** ou **PySpark** local. A minha dúvida é sobre o ROI desta refatoração.

> O custo de reescrever a base de código (refatoração de milhares de linhas de ETL) realmente compensa o ganho de performance e redução de infraestrutura?

Alguém tem benchmarks reais de produção comparando o custo de horas/homem da migração versus a economia na AWS?";

$q1 = createQuestion($pdo, $u_sofia, $s_ds, 'Pandas vs Polars em 2025: O custo de refatoração vale a pena para datasets médios (50GB+)?', $body_q1, 'question');

    $ans1 = "Fizemos exatamente essa migração na minha empresa no trimestre passado. A resposta curta é: **SIM, compensa, e rápido.**\n\nNão é apenas sobre 'caber na memória'. O Polars, por ser escrito em Rust e usar execução preguiçosa (*lazy evaluation*) e otimização de *query plan*, mudou o nosso jogo. O Pandas, mesmo com PyArrow, ainda tem um *overhead* significativo de gestão de memória e não paraleliza operações nativamente como o Polars.\n\n**O nosso cenário:** ETL que levava 4 horas no Pandas passou a rodar em 20 minutos com Polars streaming mode.";
    createAnswer($pdo, $q1, $u_admin, $ans1, 45, 30);

    createAnswer($pdo, $q1, $u_renato, "Cuidado com o *hype driven development*. Estão a olhar apenas para o benchmark de execução e a esquecer o custo de manutenção.\n\nSe a vossa equipa inteira é fluente em Pandas, o tempo que vão perder a aprender as idiossincrasias da API do Polars pode sair mais caro que simplesmente alugar uma máquina com 128GB de RAM na AWS.", 12, -5);


// ---------------------------------------------------------
// THREAD 2: Neurodiversidade (Tipo: question)
// ---------------------------------------------------------
$body_q2 = "Fui diagnosticado com **TDAH do tipo misto** tardiamente, aos 28 anos, na semana passada. Desde então, sinto um misto avassalador de alívio e luto profundo.

* **Alívio** por finalmente entender porque perdi tantas chaves, perdi prazos na faculdade e sempre me senti 'mais burro' ou 'preguiçoso' que os meus pares, mesmo esforçando-me o dobro.
* **Luto** por pensar 'quem eu poderia ter sido' se tivesse sido tratado na infância ou adolescência. Quantas oportunidades perdi? Quantas relações estraguei por impulsividade?

Alguém mais passou por isso? Como lidar com esta sensação de 'tempo perdido' e aceitar o diagnóstico na vida adulta sem se vitimizar?";

$q2 = createQuestion($pdo, $u_admin, $s_neuro, 'Diagnóstico tardio de TDAH: Como lidar com o luto do "eu potencial"?', $body_q2, 'question');

    $ans2 = "Olá. O que estás a sentir é validado clinicamente e extremamente comum. Chamamos a isso de **'Luto pelo Eu Idealizado'**.\n\nÉ um processo de desconstrução. Passaste 28 anos a construir uma autoimagem baseada em falhas morais ('sou preguiçoso', 'sou desatento'), quando na verdade lidavas com uma deficiência executiva não tratada.\n\n> Não existe 'quem poderias ter sido'. \n\nA pessoa que és hoje, com a tua criatividade, resiliência e mecanismos de *coping* (que desenvolveste sozinho para sobreviver!), é resultado dessa jornada.";
    $a2 = createAnswer($pdo, $q2, $u_julia, $ans2, 89, 85);

        createAnswer($pdo, $q2, $u_dani, "Julia, o teu comentário fez-me chorar aqui. É exatamente isso.\n\nOP, eu recebi o meu laudo aos 30. A raiva passa. O que fica é a compaixão pela tua 'criança interior' que sofreu sem saber porquê.", 34, 30, $a2);


// ---------------------------------------------------------
// THREAD 3: Gen Z (Tipo: post)
// ---------------------------------------------------------
$body_q3 = "Vejo muitos artigos no LinkedIn e críticas de gerações anteriores a dizer que a Gen Z 'não quer trabalhar' ou que inventámos o *Quiet Quitting* por preguiça.

Mas vamos aos números:
1. Os meus pais compraram uma casa de 3 quartos e sustentavam 2 filhos com um salário de gerente médio nos anos 90.
2. Hoje, o mesmo salário mal paga a renda de um estúdio no centro e a inflação dos bens alimentares.

O **quiet quitting** (fazer apenas o que está no contrato) não é apenas uma resposta racional de mercado a um contrato social que foi quebrado? Porque deveríamos 'vestir a camisola' e trabalhar 60h semanais se o prémio (estabilidade e património) já não existe?";

$q3 = createQuestion($pdo, $u_felipe, $s_genz, 'O "Quiet Quitting" é preguiça geracional ou autodefesa económica?', $body_q3, 'post');

    $ans3 = "É autodefesa, pura e simples, baseada em dados macroeconómicos. O contrato social antigo era: *'Dá lealdade à corporação e a corporação cuidará de ti com carreira e reforma'*. Isso morreu efetivamente na crise de 2008.\n\nHoje, a estatística mostra que a 'lealdade' é punida financeiramente, enquanto quem faz *job hopping* (troca de emprego a cada 2 anos) consegue aumentos reais significativos.";
    $a3 = createAnswer($pdo, $q3, $u_sofia, $ans3, 120, 100);

    createAnswer($pdo, $q3, $u_renato, "Uma tese muito bem estruturada, mas continuo a discordar da premissa. O mercado pune quem faz o mínimo, independente da geração ou da 'conjuntura económica'. Quem se destaca cresce. Quem faz *quiet quitting* será o primeiro a ser despedido no próximo layoff.", 50, -40);


// ---------------------------------------------------------
// THREAD 4: Papos Aleatórios (Tipo: short)
// ---------------------------------------------------------
$body_q4 = "Estou num debate acalorado aqui em casa e preciso de opiniões externas. Se tivessem de escolher **APENAS UMA** culinária nacional para comer pelo resto da vida (pequeno-almoço, almoço, jantar), qual seria?

**Regras:**
* Tem que ser variada o suficiente para não enjoar.
* Tem que ser saudável o suficiente para não fazer mal.
* Valem todas as variações regionais.";

$q4 = createQuestion($pdo, $u_admin, $s_random, 'Qual a melhor culinária do mundo para viver exclusivamente dela?', $body_q4, 'short');

    createAnswer($pdo, $q4, $u_felipe, "Japonesa, sem dúvidas. E não estou a falar só de sushi.\n\nEles têm *curries*, lámen, grelhados. É extremamente equilibrada, baseada em peixes e vegetais.", 20, 15);

    $ans4 = "Italiana. E não aceito discussões contrárias.\n\nMas calma, não a italiana comercial. Falo da dieta mediterrânea real: azeite, tomates frescos, frutos do mar e massa fresca. Viver sem um bom queijo e vinho não é viver, é apenas sobreviver.";
    createAnswer($pdo, $q4, $u_julia, $ans4, 45, 40);


echo "<h3>5. Conteúdo denso gerado com sucesso!</h3>";
echo "<p class='lead'>Base de dados restaurada. Password de todos: 123</p>";
echo "<hr>";
echo "<a href='login.php' style='display:inline-block; background: #b92b27; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight:bold;'>Ir para Login</a>";
echo "</div>";
?>
