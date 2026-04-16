<?php
require 'db.php';

if (!isset($_GET['id'])) die("<div class='alert alert-danger'>Erro: ID necessário. <a href='index.php'>Voltar</a></div>");
$q_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

function getPostBadge($type) {
    switch($type) {
        case 'post': return '<span class="badge bg-success text-white border me-2"><i class="fas fa-file-alt"></i> Ensaio</span>';
        case 'short': return '<span class="badge bg-warning text-dark border me-2"><i class="fas fa-bolt"></i> Curto</span>';
        default: return '<span class="badge bg-primary text-white border me-2"><i class="fas fa-question-circle"></i> Pergunta</span>';
    }
}

// 1. Busca a Pergunta Atual
$stmt = $pdo->prepare("SELECT q.*, s.name as sig_name, u.username FROM questions q JOIN sigs s ON q.sig_id = s.id JOIN users u ON q.user_id = u.id WHERE q.id = ?");
$stmt->execute([$q_id]);
$question = $stmt->fetch();
if (!$question) die("Publicação não encontrada.");

// 2. BUSCA INTELIGENTE (BM25 + VOTOS): Mais do Autor Relacionado
// Limpamos o título para evitar erros de sintaxe no FTS5 (removemos pontuações)
$clean_title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $question['title']);
$words = array_filter(explode(' ', trim($clean_title)));

// Transforma as palavras numa pesquisa do tipo OR (ex: "A OR B OR C")
$search_query = implode(' OR ', $words);
$more_from_user = [];

if (!empty($search_query)) {
    $stmt = $pdo->prepare("
        SELECT q.*,
               (SELECT COUNT(*) FROM answers WHERE question_id = q.id) as answer_count,
               (SELECT COALESCE(SUM(votes), 0) FROM answers WHERE question_id = q.id) as total_score
        FROM questions_fts fts
        JOIN questions q ON fts.rowid = q.id
        WHERE questions_fts MATCH ?
          AND q.user_id = ?
          AND q.id != ?
        ORDER BY fts.rank ASC, total_score DESC
        LIMIT 3
    ");
    $stmt->execute([$search_query, $question['user_id'], $q_id]);
    $more_from_user = $stmt->fetchAll();
}

// Fallback: se o MATCH BM25 não retornar nada (temas muito diferentes ou título só com símbolos)
if (empty($more_from_user)) {
    $stmt = $pdo->prepare("
        SELECT id, title, post_type, created_at,
        (SELECT COUNT(*) FROM answers WHERE question_id = questions.id) as answer_count,
        (SELECT COALESCE(SUM(votes), 0) FROM answers WHERE question_id = questions.id) as total_score
        FROM questions
        WHERE user_id = ? AND id != ?
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$question['user_id'], $q_id]);
    $more_from_user = $stmt->fetchAll();
}

// 3. Busca as Respostas do Post Atual
$stmt = $pdo->prepare("
    SELECT a.*, u.username,
           v.vote_type as user_vote,
           ag.agreement_type as user_agreement
    FROM answers a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN answer_votes v ON a.id = v.answer_id AND v.user_id = ?
    LEFT JOIN answer_agreements ag ON a.id = ag.answer_id AND ag.user_id = ?
    WHERE a.question_id = ?
    ORDER BY a.votes DESC, a.created_at ASC
");
$stmt->execute([$user_id, $user_id, $q_id]);
$all_answers = $stmt->fetchAll();

$comments_by_parent = [];
foreach ($all_answers as $ans) { $pid = $ans['parent_id'] ?: 0; $comments_by_parent[$pid][] = $ans; }

// Função Recursiva para renderizar FILHOS
function render_replies($parent_id, $comments_by_parent, $q_id) {
    echo '<div id="replies-container-' . $parent_id . '">';
    if (isset($comments_by_parent[$parent_id])) {
        foreach ($comments_by_parent[$parent_id] as $ans) {
            $ans_id = $ans['id'];
            ?>
            <div class="mt-3 ps-3 thread-line">
                <div class="mb-1 d-flex align-items-center">
                    <a href="profile.php?id=<?= $ans['user_id'] ?>" class="fw-bold text-dark text-decoration-none small"><?= htmlspecialchars($ans['username']) ?></a>
                    <span class="text-muted small ms-2" style="font-size:0.75rem;"><?= date('d M', strtotime($ans['created_at'])) ?></span>
                </div>

                <div class="markdown-content text-dark small mb-2" style="line-height:1.5;"><?= htmlspecialchars($ans['body']) ?></div>

                <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
                    <div class="bg-light rounded-pill border px-2 d-flex align-items-center" style="transform:scale(0.9); transform-origin:left;" title="Avalie a qualidade técnica/relevância">
                        <button id="btn-up-<?= $ans_id ?>" onclick="vote(<?= $ans_id ?>, 1)" class="btn btn-sm btn-link p-0 <?= $ans['user_vote']==1?'text-success':'text-secondary' ?>" style="border:none;"><i class="fas fa-arrow-up"></i></button>
                        <span id="vote-count-<?= $ans_id ?>" class="fw-bold mx-2 text-dark small"><?= $ans['votes'] ?></span>
                        <button id="btn-down-<?= $ans_id ?>" onclick="vote(<?= $ans_id ?>, -1)" class="btn btn-sm btn-link p-0 <?= $ans['user_vote']==-1?'text-danger':'text-secondary' ?>" style="border:none;"><i class="fas fa-arrow-down"></i></button>
                    </div>

                    <div class="bg-light rounded-pill border px-2 d-flex align-items-center" style="transform:scale(0.9); transform-origin:left;" title="Você concorda com esta opinião?">
                        <button id="btn-agree-<?= $ans_id ?>" onclick="agree(<?= $ans_id ?>, 1)" class="btn btn-sm btn-link p-0 <?= $ans['user_agreement']==1?'text-primary':'text-secondary' ?>" style="border:none;"><i class="fas fa-check"></i></button>
                        <span id="agree-count-<?= $ans_id ?>" class="fw-bold mx-2 text-dark small"><?= isset($ans['agreement']) ? $ans['agreement'] : 0 ?></span>
                        <button id="btn-disagree-<?= $ans_id ?>" onclick="agree(<?= $ans_id ?>, -1)" class="btn btn-sm btn-link p-0 <?= $ans['user_agreement']==-1?'text-warning':'text-secondary' ?>" style="border:none;"><i class="fas fa-times"></i></button>
                    </div>

                    <button class="btn btn-sm text-muted fw-bold p-0 small" onclick="document.getElementById('reply-form-<?= $ans_id ?>').classList.toggle('d-none')">Responder</button>
                </div>

                <div id="reply-form-<?= $ans_id ?>" class="d-none ms-1 mt-2 mb-3">
                    <form onsubmit="submitAnswer(event, this)">
                        <input type="hidden" name="action" value="answer_ajax">
                        <input type="hidden" name="question_id" value="<?= $q_id ?>">
                        <input type="hidden" name="parent_id" value="<?= $ans_id ?>">
                        <textarea name="body" class="form-control form-control-sm mb-1" rows="2" placeholder="A sua resposta (Markdown suportado)..."></textarea>
                        <button class="btn btn-primary btn-sm py-0 px-3">Enviar</button>
                    </form>
                </div>
                <?php render_replies($ans_id, $comments_by_parent, $q_id); ?>
            </div>
            <?php
        }
    }
    echo '</div>';
}

function count_children($parent_id, $comments_by_parent) {
    if (!isset($comments_by_parent[$parent_id])) return 0;
    $count = count($comments_by_parent[$parent_id]);
    foreach ($comments_by_parent[$parent_id] as $child) { $count += count_children($child['id'], $comments_by_parent); }
    return $count;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($question['title']) ?> - Reddora</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
    <script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js"></script>

    <link rel="stylesheet" href="style.css">
    <style>
        .question-body { font-size: 1.1rem; line-height: 1.6; color: #2c3e50; }
        .main-question-card { border-top: 5px solid var(--reddora-red); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .thread-line { border-left: 2px solid #e9ecef; }
        .thread-line:hover { border-left-color: #ced4da; }
        .fade-in-comment { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .markdown-content img { max-width: 100%; height: auto; border-radius: 8px; }
        .markdown-content pre { background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef; }
        .markdown-content blockquote { border-left: 4px solid #dee2e6; padding-left: 1rem; color: #6c757d; }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">Reddora</a>

            <form action="search.php" method="GET" class="mx-auto d-none d-md-flex" style="max-width: 400px; width: 100%;">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control border-0" placeholder="Pesquisar na Reddora..." required>
                    <button class="btn btn-light text-primary fw-bold px-3" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <div class="d-flex align-items-center">
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="text-white me-3 text-decoration-none"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?></a>
                <a href="index.php" class="btn btn-sm btn-outline-light opacity-75">Feed</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card main-question-card border-0 shadow-sm mb-5">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <?= getPostBadge($question['post_type']) ?>
                            <a href="sig.php?id=<?= $question['sig_id'] ?>" class="badge bg-light text-dark border text-decoration-none me-2"><?= htmlspecialchars($question['sig_name']) ?></a>
                            <span class="text-muted small">u/<?= htmlspecialchars($question['username']) ?> &bull; <?= date('d/m/Y', strtotime($question['created_at'])) ?></span>
                        </div>
                        <h1 class="fw-bold mb-4 text-dark" style="font-size:1.75rem;"><?= htmlspecialchars($question['title']) ?></h1>
                        <div class="markdown-content question-body mb-4"><?= htmlspecialchars($question['body']) ?></div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="text-muted fw-bold text-uppercase small mb-0">
                        <i class="far fa-comments"></i> <?= isset($comments_by_parent[0]) ? count($comments_by_parent[0]) : 0 ?> Respostas Principais
                    </h5>
                    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#mainReplyForm">
                        <i class="fas fa-pen me-2"></i>Escrever resposta
                    </button>
                </div>

                <div class="collapse mb-4" id="mainReplyForm">
                    <div class="card bg-white p-3 shadow-sm border-0">
                        <form onsubmit="submitAnswer(event, this)">
                            <input type="hidden" name="action" value="answer_ajax">
                            <input type="hidden" name="question_id" value="<?= $q_id ?>">
                            <label class="form-label small fw-bold text-muted">A sua resposta</label>
                            <textarea name="body" id="mainAnsEditor" class="form-control mb-2" rows="4" placeholder="Adicione à discussão..."></textarea>
                            <div class="text-end"><button class="btn btn-primary px-4 fw-bold">Postar</button></div>
                        </form>
                    </div>
                </div>

                <div id="replies-container-0">
                    <?php if(isset($comments_by_parent[0])): ?>
                        <?php foreach($comments_by_parent[0] as $root_ans): $ans_id = $root_ans['id']; ?>
                            <div class="card mb-3 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-2" style="width:32px; height:32px; font-weight:bold; color:var(--reddora-dark);"><?= strtoupper(substr($root_ans['username'], 0, 1)) ?></div>
                                        <div>
                                            <a href="profile.php?id=<?= $root_ans['user_id'] ?>" class="fw-bold text-dark d-block lh-1 text-decoration-none"><?= htmlspecialchars($root_ans['username']) ?></a>
                                            <small class="text-muted"><?= date('d M', strtotime($root_ans['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    <div class="markdown-content mb-3 text-dark"><?= htmlspecialchars($root_ans['body']) ?></div>

                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <div class="bg-light rounded-pill border px-2 d-flex align-items-center" title="Avalie a qualidade técnica/relevância">
                                            <button id="btn-up-<?= $ans_id ?>" onclick="vote(<?= $ans_id ?>, 1)" class="btn btn-sm btn-link p-0 <?= $root_ans['user_vote']==1?'text-success':'text-secondary' ?>" style="border:none;"><i class="fas fa-arrow-up"></i></button>
                                            <span id="vote-count-<?= $ans_id ?>" class="fw-bold mx-2 text-dark small"><?= $root_ans['votes'] ?></span>
                                            <button id="btn-down-<?= $ans_id ?>" onclick="vote(<?= $ans_id ?>, -1)" class="btn btn-sm btn-link p-0 <?= $root_ans['user_vote']==-1?'text-danger':'text-secondary' ?>" style="border:none;"><i class="fas fa-arrow-down"></i></button>
                                        </div>

                                        <div class="bg-light rounded-pill border px-2 d-flex align-items-center" title="Você concorda com esta opinião?">
                                            <button id="btn-agree-<?= $ans_id ?>" onclick="agree(<?= $ans_id ?>, 1)" class="btn btn-sm btn-link p-0 <?= $root_ans['user_agreement']==1?'text-primary':'text-secondary' ?>" style="border:none;"><i class="fas fa-check"></i></button>
                                            <span id="agree-count-<?= $ans_id ?>" class="fw-bold mx-2 text-dark small"><?= isset($root_ans['agreement']) ? $root_ans['agreement'] : 0 ?></span>
                                            <button id="btn-disagree-<?= $ans_id ?>" onclick="agree(<?= $ans_id ?>, -1)" class="btn btn-sm btn-link p-0 <?= $root_ans['user_agreement']==-1?'text-warning':'text-secondary' ?>" style="border:none;"><i class="fas fa-times"></i></button>
                                        </div>

                                        <button class="btn btn-sm text-secondary fw-bold ms-2" onclick="document.getElementById('root-reply-form-<?= $ans_id ?>').classList.toggle('d-none')"><i class="far fa-comment"></i> Responder</button>
                                    </div>

                                    <div id="root-reply-form-<?= $ans_id ?>" class="d-none mt-3 p-3 bg-light rounded">
                                        <form onsubmit="submitAnswer(event, this)">
                                            <input type="hidden" name="action" value="answer_ajax">
                                            <input type="hidden" name="question_id" value="<?= $q_id ?>">
                                            <input type="hidden" name="parent_id" value="<?= $ans_id ?>">
                                            <textarea name="body" class="form-control mb-2" rows="2" placeholder="A sua resposta (Markdown)..."></textarea>
                                            <button class="btn btn-primary btn-sm">Enviar</button>
                                        </form>
                                    </div>
                                    <div class="mt-3">
                                        <?php if(count_children($ans_id, $comments_by_parent) > 0): ?>
                                            <button class="btn btn-light btn-sm w-100 text-start fw-bold text-primary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#thread-<?= $ans_id ?>"><i class="fas fa-level-down-alt me-2"></i> Ver respostas</button>
                                            <div class="collapse show" id="thread-<?= $ans_id ?>"><div class="ps-2 pt-2"><?php render_replies($ans_id, $comments_by_parent, $q_id); ?></div></div>
                                        <?php else: ?>
                                            <div class="ps-2 pt-2"><?php render_replies($ans_id, $comments_by_parent, $q_id); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="mt-5 mb-5 pt-4 border-top">
                    <h5 class="fw-bold text-dark mb-4">Mais de u/<?= htmlspecialchars($question['username']) ?></h5>

                    <?php if (!empty($more_from_user)): ?>
                        <div class="row">
                            <?php foreach($more_from_user as $mfu): ?>
                            <div class="col-12 mb-3">
                                <div class="card border-0 shadow-sm hover-card h-100">
                                    <div class="card-body py-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <?= getPostBadge($mfu['post_type']) ?>
                                            <small class="text-muted ms-2"><?= date('d/m/Y', strtotime($mfu['created_at'])) ?></small>
                                        </div>
                                        <h6 class="fw-bold mb-2">
                                            <a href="question.php?id=<?= $mfu['id'] ?>" class="text-dark text-decoration-none">
                                                <?= htmlspecialchars($mfu['title']) ?>
                                            </a>
                                        </h6>
                                        <div class="d-flex align-items-center text-muted small">
                                            <span class="badge bg-light text-dark border me-3" title="Karma total deste post">
                                                <i class="fas fa-arrow-up text-success me-1"></i> <?= $mfu['total_score'] ?>
                                            </span>
                                            <span><i class="far fa-comments me-1"></i> <?= $mfu['answer_count'] ?> discussões</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light text-center border shadow-sm text-muted py-4">
                            <i class="fas fa-layer-group fa-2x mb-3 opacity-50"></i><br>
                            Este utilizador não tem outras publicações.
                        </div>
                    <?php endif; ?>
                </div>
                </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Inicializa o Editor no formulário principal de resposta
    let mdeInstance = null;
    const ansTextarea = document.getElementById('mainAnsEditor');
    if (ansTextarea) {
        mdeInstance = new EasyMDE({
            element: ansTextarea,
            spellChecker: false,
            status: false,
            toolbar: ["bold", "italic", "quote", "|", "unordered-list", "ordered-list", "|", "preview"]
        });
    }

    // Processa o Markdown
    function parseMarkdown() {
        document.querySelectorAll('.markdown-content').forEach(el => {
            // Verifica se já foi parseado para não parsear duas vezes
            if (!el.dataset.parsed) {
                el.innerHTML = DOMPurify.sanitize(marked.parse(el.textContent));
                el.dataset.parsed = true;
            }
        });
    }

    // Roda na primeira carga
    document.addEventListener("DOMContentLoaded", parseMarkdown);

    function submitAnswer(e, form) {
        e.preventDefault();

        // Se for o formulário principal, joga o texto do EasyMDE pra textarea
        if (form.querySelector('textarea').id === 'mainAnsEditor' && mdeInstance) {
            form.querySelector('textarea').value = mdeInstance.value();
        }

        let btn = form.querySelector('button');
        let txt = btn.innerText;
        btn.innerText = '...'; btn.disabled = true;

        fetch('post_action.php', { method:'POST', body:new FormData(form) })
        .then(r=>r.json())
        .then(d=>{
            btn.innerText=txt; btn.disabled=false;
            if(d.status==='success') {
                form.querySelector('textarea').value='';
                if(mdeInstance) mdeInstance.value(''); // limpa o editor se usado
                if(!form.closest('#mainReplyForm')) form.parentElement.classList.add('d-none');

                let pid = new FormData(form).get('parent_id');
                let targetId = pid ? 'replies-container-'+pid : 'replies-container-0';
                let container = document.getElementById(targetId);

                if(container) {
                    let div = document.createElement('div');
                    div.innerHTML = d.html;
                    container.prepend(div.firstElementChild);
                    parseMarkdown(); // Aplica o markdown na resposta nova
                } else {
                    location.reload();
                }
            } else { alert(d.message); }
        })
        .catch(e => { console.error(e); btn.innerText=txt; btn.disabled=false; });
    }

    function vote(id, val) {
        let fd = new FormData(); fd.append('action','vote_ajax'); fd.append('ans_id',id); fd.append('val',val);
        fetch('post_action.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
            if(d.status==='success'){
                document.getElementById('vote-count-'+id).innerText = d.new_total;
                let up = document.getElementById('btn-up-'+id), down = document.getElementById('btn-down-'+id);
                up.className = `btn btn-sm btn-link p-0 ${d.user_vote==1?'text-success':'text-secondary'}`;
                down.className = `btn btn-sm btn-link p-0 ${d.user_vote==-1?'text-danger':'text-secondary'}`;
            }
        });
    }

    function agree(id, val) {
        let fd = new FormData(); fd.append('action', 'agreement_ajax'); fd.append('ans_id', id); fd.append('val', val);
        fetch('post_action.php', {method: 'POST', body: fd}).then(r => r.json()).then(d => {
            if(d.status === 'success'){
                document.getElementById('agree-count-'+id).innerText = d.new_total;
                let agreeBtn = document.getElementById('btn-agree-'+id);
                let disagreeBtn = document.getElementById('btn-disagree-'+id);
                agreeBtn.className = `btn btn-sm btn-link p-0 ${d.user_agreement==1?'text-primary':'text-secondary'}`;
                disagreeBtn.className = `btn btn-sm btn-link p-0 ${d.user_agreement==-1?'text-warning':'text-secondary'}`;
            }
        });
    }
    </script>
</body>
</html>
