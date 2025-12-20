<?php
// setup.php
require 'db.php';

// === 1. LIMPEZA TOTAL ===
// Ordem importa para não violar chaves estrangeiras (se existissem restrições)
$tables = ['answer_votes', 'answers', 'questions', 'sig_memberships', 'sigs', 'users'];
foreach ($tables as $table) {
    $pdo->exec("DROP TABLE IF EXISTS $table");
}

echo "<div style='font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;'>";
echo "<h3 style='color: #b92b27;'>1. Banco de dados limpo.</h3>";

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
    )",
    // Tabela de votos únicos
    "CREATE TABLE answer_votes (
        user_id INTEGER,
        answer_id INTEGER,
        vote_type INTEGER,
        PRIMARY KEY (user_id, answer_id)
    )"
];

foreach ($commands as $cmd) {
    $pdo->exec($cmd);
}
echo "<h3>2. Tabelas recriadas (com sistema de voto único).</h3>";

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
$u_admin   = createUser($pdo, 'admin');
$u_sofia   = createUser($pdo, 'Sofia_Data');       // Expert em Dados
$u_julia   = createUser($pdo, 'Julia_Psy');        // Psicóloga
$u_dani    = createUser($pdo, 'Dani_Neuro');       // Ativista Neurodiversidade
$u_felipe  = createUser($pdo, 'Felipe_GenZ');      // O Jovem
$u_renato  = createUser($pdo, 'Renato_Ceticismo'); // O Polêmico

echo "<h3>3. Usuários criados (incluindo admin e personagens).</h3>";

// -- Sigs --
$s_ds      = createSig($pdo, 'Data Science', 'Python, R, IA, Estatística e Big Data.');
$s_psi     = createSig($pdo, 'Psicologia', 'Mente humana, comportamento e terapia.');
$s_neuro   = createSig($pdo, 'Neurodiversidade', 'TDAH, Autismo e convivência.');
$s_random  = createSig($pdo, 'Papos Aleatórios', 'Conversas sem rumo, memes e histórias.');
$s_genz    = createSig($pdo, 'Gen Z', 'Cringe é quem fala cringe. Discussões geracionais.');

echo "<h3>4. Sigs criados.</h3>";

// -- Assinaturas --
// Admin segue tudo
for($i=1; $i<=5; $i++) joinSig($pdo, $u_admin, $i);

joinSig($pdo, $u_sofia, $s_ds); joinSig($pdo, $u_sofia, $s_random);
joinSig($pdo, $u_julia, $s_psi); joinSig($pdo, $u_julia, $s_neuro);
joinSig($pdo, $u_dani, $s_neuro); joinSig($pdo, $u_dani, $s_psi); joinSig($pdo, $u_dani, $s_random);
joinSig($pdo, $u_felipe, $s_genz); joinSig($pdo, $u_felipe, $s_ds);
joinSig($pdo, $u_renato, $s_ds); joinSig($pdo, $u_renato, $s_psi); joinSig($pdo, $u_renato, $s_random);


// === 5. CONTEÚDO DENSO (QUORA STYLE) ===

// ---------------------------------------------------------
// THREAD 1: Data Science (Sofia vs Renato vs Admin)
// ---------------------------------------------------------
$body_q1 = "Estou liderando um projeto de migração de dados em uma fintech e minha equipe técnica está em um impasse ideológico.\n\nMetade dos engenheiros quer continuar usando Pandas (v2.0 com PyArrow backend) pela familiaridade e ecosistema. Porém, estamos lidando com dumps diários de Parquet que já excedem 50GB. A máquina de desenvolvimento padrão tem 32GB de RAM, o que causa OOM (Out of Memory) constantemente se não processarmos em chunks.\n\nA outra metade quer migrar tudo para Polars ou PySpark local. Minha dúvida é sobre o ROI dessa refatoração.\n\nO custo de reescrever a base de código (refatoração de milhares de linhas de ETL) realmente compensa o ganho de performance e redução de infraestrutura? Alguém tem benchmarks reais de produção comparando o custo de horas/homem da migração versus a economia na AWS?";

$q1 = createQuestion($pdo, $u_sofia, $s_ds, 'Pandas vs Polars em 2025: O custo de refatoração vale a pena para datasets médios (50GB+)?', $body_q1);

    // Resposta Técnica (Admin)
    $ans1 = "Fizemos exatamente essa migração na minha empresa no trimestre passado. A resposta curta é: SIM, compensa, e rápido.\n\nNão é apenas sobre 'caber na memória'. O Polars, por ser escrito em Rust e usar execução preguiçosa (lazy evaluation) e otimização de query plan, mudou nosso jogo. O Pandas, mesmo com PyArrow, ainda tem um overhead significativo de gerenciamento de memória e não paraleliza operações nativamente como o Polars.\n\nNosso cenário: ETL que levava 4 horas no Pandas (single core, estourando RAM em instâncias r5.2xlarge) passou a rodar em 20 minutos com Polars streaming mode em uma máquina muito menor. A economia de cloud pagou as horas dos desenvolvedores em 2 meses.";
    createAnswer($pdo, $q1, $u_admin, $ans1, 45);

    // Resposta Cética (Renato)
    createAnswer($pdo, $q1, $u_renato, "Cuidado com o 'hype driven development'. Vocês estão olhando apenas para o benchmark de execução e esquecendo o custo de manutenção.\n\nSe sua equipe inteira é fluente em Pandas, o tempo que vocês vão perder aprendendo as idiossincrasias da API do Polars (que muda a cada update, diga-se de passagem) pode sair mais caro que simplesmente alugar uma máquina com 128GB de RAM na AWS.\n\nHardware é barato e escalável; hora de engenheiro sênior aprendendo ferramenta nova é caríssima. Eu só migraria se o Pandas estivesse literalmente impedindo o negócio de rodar.", 12);


// ---------------------------------------------------------
// THREAD 2: Neurodiversidade (Dani vs Julia)
// ---------------------------------------------------------
$body_q2 = "Fui diagnosticado com TDAH do tipo misto tardiamente, aos 28 anos, semana passada. Desde então, sinto um misto avassalador de alívio e luto profundo.\n\nAlívio por finalmente entender por que perdi tantas chaves, perdi prazos na faculdade e sempre me senti 'mais burro' ou 'preguiçoso' que meus pares, mesmo me esforçando o dobro.\n\nMas o luto... o luto é por pensar 'quem eu poderia ter sido' se tivesse sido tratado na infância ou adolescência. Quantas oportunidades perdi? Quantas relações estraguei por impulsividade?\n\nAlguém mais passou por isso? Como lidar com essa sensação de 'tempo perdido' e aceitar o diagnóstico na vida adulta sem se vitimizar?";

$q2 = createQuestion($pdo, $u_admin, $s_neuro, 'Diagnóstico tardio de TDAH: Como lidar com o luto do "eu potencial"?', $body_q2);

    // Resposta Profissional (Julia)
    $ans2 = "Olá. O que você está sentindo é validado clinicamente e extremamente comum. Chamamos isso de 'Luto pelo Eu Idealizado'.\n\nÉ um processo de desconstrução. Você passou 28 anos construindo uma autoimagem baseada em falhas morais ('sou preguiçoso', 'sou desatento'), quando na verdade lidava com uma deficiência executiva não tratada.\n\nSobre o tempo perdido: tente reenquadrar. Não existe 'quem você poderia ter sido'. A pessoa que você é hoje, com sua criatividade, resiliência e mecanismos de coping (que você desenvolveu sozinho para sobreviver!), é resultado dessa jornada. A medicação e a terapia não vão apagar quem você é, vão apenas te dar as ferramentas para que o esforço que você faz finalmente gere resultados proporcionais.";
    $a2 = createAnswer($pdo, $q2, $u_julia, $ans2, 89);

        // Comentário de Suporte (Dani)
        createAnswer($pdo, $q2, $u_dani, "Julia, seu comentário me fez chorar aqui. É exatamente isso.\n\nOP, eu recebi meu laudo aos 30. A raiva passa. O que fica é a compaixão pela sua 'criança interior' que sofreu sem saber porquê. Dê tempo ao tempo. E lembre-se: hiperfoco é um superpoder se bem direcionado!", 34, $a2);


// ---------------------------------------------------------
// THREAD 3: Gen Z (Felipe vs Renato vs Sofia)
// ---------------------------------------------------------
$body_q3 = "Vejo muitos artigos no LinkedIn e críticas de gerações anteriores (Millennials e Boomers) dizendo que a Gen Z 'não quer trabalhar' ou que inventamos o 'Quiet Quitting' por preguiça.\n\nMas vamos aos números: Meus pais compraram uma casa de 3 quartos e sustentavam 2 filhos com um salário de gerente médio nos anos 90. Hoje, o mesmo salário mal paga o aluguel de um estúdio no centro e a inflação dos alimentos.\n\nO 'quiet quitting' (fazer apenas o que está no contrato) não é apenas uma resposta racional de mercado a um contrato social que foi quebrado? Por que deveríamos 'vestir a camisa' e trabalhar 60h semanais se o prêmio (estabilidade e patrimônio) não existe mais?";

$q3 = createQuestion($pdo, $u_felipe, $s_genz, 'O "Quiet Quitting" é preguiça geracional ou autodefesa econômica?', $body_q3);

    // Apoio com dados (Sofia)
    $ans3 = "É autodefesa, pura e simples, baseada em dados macroeconômicos. O contrato social antigo era: 'Dê lealdade à corporação e a corporação cuidará de você com carreira e aposentadoria'. Isso morreu efetivamente na crise de 2008.\n\nHoje, a estatística mostra que a 'lealdade' é punida financeiramente (wage stagnation), enquanto quem faz 'job hopping' (troca de emprego a cada 2 anos) consegue aumentos reais de 20% a 30%. A Gen Z apenas percebeu a regra do jogo mais rápido porque já nasceu na instabilidade. Não faz sentido matemático dar 110% de esforço por 1% de retorno acima da inflação.";
    $a3 = createAnswer($pdo, $q3, $u_sofia, $ans3, 120);

    // Contraponto (Renato)
    createAnswer($pdo, $q3, $u_renato, "Vocês racionalizam demais a falta de garra e ambição. Toda geração enfrentou crises econômicas, inflação e guerras. A diferença é que a de vocês tem TikTok para chorar em público e ganhar validação por fazer o mínimo.\n\nO mercado pune quem faz o mínimo, independente da geração ou da 'conjuntura econômica'. Quem se destaca cresce. Quem faz 'quiet quitting' será o primeiro a ser demitido no próximo layoff. É uma estratégia de carreira suicida a longo prazo.", -15);


// ---------------------------------------------------------
// THREAD 4: Papos Aleatórios (Admin vs Julia vs Felipe)
// ---------------------------------------------------------
$body_q4 = "Estou num debate acalorado aqui em casa e preciso de opiniões externas para desempatar. Se vocês tivessem que escolher APENAS UMA culinária nacional para comer pelo resto da vida (café, almoço, jantar), qual seria?\n\nRegras:\n1. Tem que ser variada o suficiente para não enjoar em 1 mês.\n2. Tem que ser saudável o suficiente pra você não morrer de arterosclerose em 1 ano.\n3. Vale todas as variações regionais daquele país.";

$q4 = createQuestion($pdo, $u_admin, $s_random, 'Qual a melhor culinária do mundo para viver exclusivamente dela?', $body_q4);

    // Voto Felipe
    createAnswer($pdo, $q4, $u_felipe, "Japonesa, sem dúvidas. E não estou falando só de sushi.\n\nEles têm curries, lámen, grelhados (teppanyaki), cozidos. É extremamente equilibrada, baseada em peixes e vegetais, pouca gordura saturada. É a razão de eles terem a maior expectativa de vida do mundo. Dá para comer todo dia sem se sentir pesado.", 20);

    // Voto Julia
    $ans4 = "Italiana. E não aceito discussões contrárias.\n\nMas calma, não a italiana 'americana' (cheia de creme de leite e pepperoni). Falo da dieta mediterrânea real. Azeite de oliva de qualidade, tomates frescos, frutos do mar, grãos, vegetais grelhados e, claro, massa fresca feita em casa.\n\nÉ comida que abraça a alma. Viver sem um bom queijo e vinho não é viver, é apenas sobreviver.";
    createAnswer($pdo, $q4, $u_julia, $ans4, 45);


echo "<h3>5. Conteúdo gerado com sucesso!</h3>";
echo "<p class='lead'>Todas as discussões foram restauradas com textos estendidos.</p>";
echo "<hr>";
echo "<a href='login.php' style='display:inline-block; background: #b92b27; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight:bold;'>Ir para Login</a>";
echo "</div>";
?>

