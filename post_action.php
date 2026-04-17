<?php
// post_action.php
require 'db.php';

$action = $_POST['action'] ?? '';

// === VOTAÇÃO DE KARMA (QUALIDADE NAS RESPOSTAS) ===
if ($action === 'vote_ajax') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Login necessário']); exit; }

    $user_id = $_SESSION['user_id'];
    $ans_id = (int)$_POST['ans_id'];
    $val = (int)$_POST['val'];

    $stmt = $pdo->prepare("SELECT vote_type FROM answer_votes WHERE user_id = ? AND answer_id = ?");
    $stmt->execute([$user_id, $ans_id]);
    $existing = $stmt->fetch();
    $new_user_vote = 0;

    if ($existing) {
        $old_val = (int)$existing['vote_type'];
        if ($old_val === $val) {
            $pdo->prepare("DELETE FROM answer_votes WHERE user_id = ? AND answer_id = ?")->execute([$user_id, $ans_id]);
            $pdo->prepare("UPDATE answers SET votes = votes - ? WHERE id = ?")->execute([$old_val, $ans_id]);
        } else {
            $pdo->prepare("UPDATE answer_votes SET vote_type = ? WHERE user_id = ? AND answer_id = ?")->execute([$val, $user_id, $ans_id]);
            $diff = $val - $old_val;
            $pdo->prepare("UPDATE answers SET votes = votes + ? WHERE id = ?")->execute([$diff, $ans_id]);
            $new_user_vote = $val;
        }
    } else {
        $pdo->prepare("INSERT INTO answer_votes (user_id, answer_id, vote_type) VALUES (?, ?, ?)")->execute([$user_id, $ans_id, $val]);
        $pdo->prepare("UPDATE answers SET votes = votes + ? WHERE id = ?")->execute([$val, $ans_id]);
        $new_user_vote = $val;
    }

    $stmt = $pdo->prepare("SELECT votes FROM answers WHERE id = ?");
    $stmt->execute([$ans_id]);
    $new_total = $stmt->fetchColumn();

    echo json_encode(['status' => 'success', 'new_total' => $new_total, 'user_vote' => $new_user_vote]);
    exit;
}

// === CONCORDÂNCIA VIA AJAX (NAS RESPOSTAS) ===
elseif ($action === 'agreement_ajax') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Login necessário']); exit; }

    $user_id = $_SESSION['user_id'];
    $ans_id = (int)$_POST['ans_id'];
    $val = (int)$_POST['val'];

    $stmt = $pdo->prepare("SELECT agreement_type FROM answer_agreements WHERE user_id = ? AND answer_id = ?");
    $stmt->execute([$user_id, $ans_id]);
    $existing = $stmt->fetch();
    $new_user_agreement = 0;

    if ($existing) {
        $old_val = (int)$existing['agreement_type'];
        if ($old_val === $val) {
            $pdo->prepare("DELETE FROM answer_agreements WHERE user_id = ? AND answer_id = ?")->execute([$user_id, $ans_id]);
            $pdo->prepare("UPDATE answers SET agreement = agreement - ? WHERE id = ?")->execute([$old_val, $ans_id]);
        } else {
            $pdo->prepare("UPDATE answer_agreements SET agreement_type = ? WHERE user_id = ? AND answer_id = ?")->execute([$val, $user_id, $ans_id]);
            $diff = $val - $old_val;
            $pdo->prepare("UPDATE answers SET agreement = agreement + ? WHERE id = ?")->execute([$diff, $ans_id]);
            $new_user_agreement = $val;
        }
    } else {
        $pdo->prepare("INSERT INTO answer_agreements (user_id, answer_id, agreement_type) VALUES (?, ?, ?)")->execute([$user_id, $ans_id, $val]);
        $pdo->prepare("UPDATE answers SET agreement = agreement + ? WHERE id = ?")->execute([$val, $ans_id]);
        $new_user_agreement = $val;
    }

    $stmt = $pdo->prepare("SELECT agreement FROM answers WHERE id = ?");
    $stmt->execute([$ans_id]);
    $new_total = $stmt->fetchColumn();

    echo json_encode(['status' => 'success', 'new_total' => $new_total, 'user_agreement' => $new_user_agreement]);
    exit;
}

// === VOTAÇÃO DE KARMA NO POST PRINCIPAL (PERGUNTA) ===
elseif ($action === 'vote_question_ajax') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Login necessário']); exit; }

    $user_id = $_SESSION['user_id'];
    $q_id = (int)$_POST['q_id'];
    $val = (int)$_POST['val'];

    $stmt = $pdo->prepare("SELECT vote_type FROM question_votes WHERE user_id = ? AND question_id = ?");
    $stmt->execute([$user_id, $q_id]);
    $existing = $stmt->fetch();
    $new_user_vote = 0;

    if ($existing) {
        $old_val = (int)$existing['vote_type'];
        if ($old_val === $val) {
            $pdo->prepare("DELETE FROM question_votes WHERE user_id = ? AND question_id = ?")->execute([$user_id, $q_id]);
            $pdo->prepare("UPDATE questions SET votes = votes - ? WHERE id = ?")->execute([$old_val, $q_id]);
        } else {
            $pdo->prepare("UPDATE question_votes SET vote_type = ? WHERE user_id = ? AND question_id = ?")->execute([$val, $user_id, $q_id]);
            $diff = $val - $old_val;
            $pdo->prepare("UPDATE questions SET votes = votes + ? WHERE id = ?")->execute([$diff, $q_id]);
            $new_user_vote = $val;
        }
    } else {
        $pdo->prepare("INSERT INTO question_votes (user_id, question_id, vote_type) VALUES (?, ?, ?)")->execute([$user_id, $q_id, $val]);
        $pdo->prepare("UPDATE questions SET votes = votes + ? WHERE id = ?")->execute([$val, $q_id]);
        $new_user_vote = $val;
    }

    $stmt = $pdo->prepare("SELECT votes FROM questions WHERE id = ?");
    $stmt->execute([$q_id]);
    $new_total = $stmt->fetchColumn();

    echo json_encode(['status' => 'success', 'new_total' => $new_total, 'user_vote' => $new_user_vote]);
    exit;
}

// === CONCORDÂNCIA NO POST PRINCIPAL (PERGUNTA) ===
elseif ($action === 'agreement_question_ajax') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Login necessário']); exit; }

    $user_id = $_SESSION['user_id'];
    $q_id = (int)$_POST['q_id'];
    $val = (int)$_POST['val'];

    $stmt = $pdo->prepare("SELECT agreement_type FROM question_agreements WHERE user_id = ? AND question_id = ?");
    $stmt->execute([$user_id, $q_id]);
    $existing = $stmt->fetch();
    $new_user_agreement = 0;

    if ($existing) {
        $old_val = (int)$existing['agreement_type'];
        if ($old_val === $val) {
            $pdo->prepare("DELETE FROM question_agreements WHERE user_id = ? AND question_id = ?")->execute([$user_id, $q_id]);
            $pdo->prepare("UPDATE questions SET agreement = agreement - ? WHERE id = ?")->execute([$old_val, $q_id]);
        } else {
            $pdo->prepare("UPDATE question_agreements SET agreement_type = ? WHERE user_id = ? AND question_id = ?")->execute([$val, $user_id, $q_id]);
            $diff = $val - $old_val;
            $pdo->prepare("UPDATE questions SET agreement = agreement + ? WHERE id = ?")->execute([$diff, $q_id]);
            $new_user_agreement = $val;
        }
    } else {
        $pdo->prepare("INSERT INTO question_agreements (user_id, question_id, agreement_type) VALUES (?, ?, ?)")->execute([$user_id, $q_id, $val]);
        $pdo->prepare("UPDATE questions SET agreement = agreement + ? WHERE id = ?")->execute([$val, $q_id]);
        $new_user_agreement = $val;
    }

    $stmt = $pdo->prepare("SELECT agreement FROM questions WHERE id = ?");
    $stmt->execute([$q_id]);
    $new_total = $stmt->fetchColumn();

    echo json_encode(['status' => 'success', 'new_total' => $new_total, 'user_agreement' => $new_user_agreement]);
    exit;
}

// === RESPOSTA VIA AJAX (RENDERIZAÇÃO CONDICIONAL) ===
elseif ($action === 'answer_ajax') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'Login necessário']); exit; }

    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $question_id = $_POST['question_id'];
    $body = $_POST['body'];
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : NULL;

    if(empty(trim($body))) { echo json_encode(['status' => 'error', 'message' => 'Texto vazio']); exit; }

    // Inserção no Banco
    $stmt = $pdo->prepare("INSERT INTO answers (question_id, user_id, parent_id, body) VALUES (?, ?, ?, ?)");
    $stmt->execute([$question_id, $user_id, $parent_id, $body]);
    $new_ans_id = $pdo->lastInsertId();

    ob_start();

    // === LAYOUT 1: RESPOSTA PRINCIPAL (CARD) ===
    if (!$parent_id):
    ?>
    <div class="card mb-3 border-0 shadow-sm fade-in-comment">
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-2" style="width:32px; height:32px; font-weight:bold; color:var(--reddora-dark);">
                    <?= strtoupper(substr($username, 0, 1)) ?>
                </div>
                <div>
                    <a href="profile.php?id=<?= $user_id ?>" class="fw-bold text-dark d-block lh-1 text-decoration-none"><?= htmlspecialchars($username) ?></a>
                    <small class="text-muted">Agora mesmo</small>
                </div>
            </div>
            <div class="mb-3 text-dark"><?= nl2br(htmlspecialchars($body)) ?></div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <div class="bg-light rounded-pill border px-2 d-flex align-items-center">
                    <button id="btn-up-<?= $new_ans_id ?>" onclick="vote(<?= $new_ans_id ?>, 1)" class="btn btn-sm btn-link p-0 text-secondary" style="border:none;"><i class="fas fa-arrow-up"></i></button>
                    <span id="vote-count-<?= $new_ans_id ?>" class="fw-bold mx-2 text-dark small">0</span>
                    <button id="btn-down-<?= $new_ans_id ?>" onclick="vote(<?= $new_ans_id ?>, -1)" class="btn btn-sm btn-link p-0 text-secondary" style="border:none;"><i class="fas fa-arrow-down"></i></button>
                </div>

                <div class="bg-light rounded-pill border px-2 d-flex align-items-center">
                    <button id="btn-agree-<?= $new_ans_id ?>" onclick="agree(<?= $new_ans_id ?>, 1)" class="btn btn-sm btn-link p-0 text-secondary" style="border:none;"><i class="fas fa-check"></i></button>
                    <span id="agree-count-<?= $new_ans_id ?>" class="fw-bold mx-2 text-dark small">0</span>
                    <button id="btn-disagree-<?= $new_ans_id ?>" onclick="agree(<?= $new_ans_id ?>, -1)" class="btn btn-sm btn-link p-0 text-secondary" style="border:none;"><i class="fas fa-times"></i></button>
                </div>

                <button class="btn btn-sm text-secondary fw-bold ms-2" onclick="document.getElementById('root-reply-form-<?= $new_ans_id ?>').classList.toggle('d-none')">
                    <i class="far fa-comment"></i> Responder
                </button>
            </div>

            <div id="root-reply-form-<?= $new_ans_id ?>" class="d-none mt-3 p-3 bg-light rounded">
                <form onsubmit="submitAnswer(event, this)">
                    <input type="hidden" name="action" value="answer_ajax">
                    <input type="hidden" name="question_id" value="<?= $question_id ?>">
                    <input type="hidden" name="parent_id" value="<?= $new_ans_id ?>">
                    <textarea name="body" class="form-control mb-2" rows="2"></textarea>
                    <button class="btn btn-primary btn-sm">Enviar</button>
                </form>
            </div>
            <div class="mt-3"><div id="replies-container-<?= $new_ans_id ?>"></div></div>
        </div>
    </div>

    <?php
    // === LAYOUT 2: SUB-RESPOSTA (THREAD) ===
    else:
    ?>
    <div class="mt-3 ps-3 thread-line fade-in-comment">
        <div class="mb-1 d-flex align-items-center">
            <a href="profile.php?id=<?= $user_id ?>" class="fw-bold text-dark text-decoration-none small"><?= htmlspecialchars($username) ?></a>
            <span class="text-muted small ms-2" style="font-size: 0.75rem;">Agora</span>
        </div>
        <div class="text-dark small mb-2" style="line-height: 1.5;"><?= nl2br(htmlspecialchars($body)) ?></div>

        <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
            <div class="bg-light rounded-pill border px-2 d-flex align-items-center" style="transform: scale(0.9); transform-origin: left center;">
                <button id="btn-up-<?= $new_ans_id ?>" onclick="vote(<?= $new_ans_id ?>, 1)" class="btn btn-sm btn-link p-0 text-secondary" style="border:none;"><i class="fas fa-arrow-up"></i></button>
                <span id="vote-count-<?= $new_ans_id ?>" class="fw-bold mx-2 text-dark small">0</span>
                <button id="btn-down-<?= $new_ans_id ?>" onclick="vote(<?= $new_ans_id ?>, -1)" class="btn btn-sm btn-link p-0 text-secondary" style="border:none;"><i class="fas fa-arrow-down"></i></button>
            </div>

            <div class="bg-light rounded-pill border px-2 d-flex align-items-center" style="transform: scale(0.9); transform-origin: left center;">
                <button id="btn-agree-<?= $new_ans_id ?>" onclick="agree(<?= $new_ans_id ?>, 1)" class="btn btn-sm btn-link p-0 text-secondary" style="border:none;"><i class="fas fa-check"></i></button>
                <span id="agree-count-<?= $new_ans_id ?>" class="fw-bold mx-2 text-dark small">0</span>
                <button id="btn-disagree-<?= $new_ans_id ?>" onclick="agree(<?= $new_ans_id ?>, -1)" class="btn btn-sm btn-link p-0 text-secondary" style="border:none;"><i class="fas fa-times"></i></button>
            </div>

            <button class="btn btn-sm text-muted fw-bold p-0 small ms-2" onclick="document.getElementById('reply-form-<?= $new_ans_id ?>').classList.toggle('d-none')">Responder</button>
        </div>

        <div id="reply-form-<?= $new_ans_id ?>" class="d-none ms-1 mt-2 mb-3">
            <form onsubmit="submitAnswer(event, this)">
                <input type="hidden" name="action" value="answer_ajax">
                <input type="hidden" name="question_id" value="<?= $question_id ?>">
                <input type="hidden" name="parent_id" value="<?= $new_ans_id ?>">
                <textarea name="body" class="form-control form-control-sm mb-1" rows="2" placeholder="Sua resposta..."></textarea>
                <button class="btn btn-primary btn-sm py-0 px-3">Enviar</button>
            </form>
        </div>
        <div id="replies-container-<?= $new_ans_id ?>"></div>
    </div>
    <?php
    endif;

    $html = ob_get_clean();
    echo json_encode(['status' => 'success', 'html' => $html]);
    exit;
}

// === OUTRAS AÇÕES ===

// --- UPLOAD DE FOTO DE PERFIL ---
elseif ($action === 'upload_pfp') {
    if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

    $user_id = $_SESSION['user_id'];

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
        $fileName = $_FILES['profile_pic']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Cria a pasta se não existir
            $uploadFileDir = './uploads/profiles/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            // Gera um nome único para evitar conflitos de cache
            $newFileName = 'pfp_' . $user_id . '_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Atualiza a base de dados com o novo nome
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $stmt->execute([$newFileName, $user_id]);
            }
        }
    }
    // Redireciona de volta para o perfil
    header("Location: profile.php?id=" . $user_id);
    exit;
}

elseif ($action === 'login') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
    } else { header("Location: login.php?error=1"); }

} elseif ($action === 'register') {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    if ($stmt->fetch()) { header("Location: register.php?error=exists"); exit; }

    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute([$_POST['username'], $hash]);
    $pdo->exec("INSERT INTO sig_memberships (user_id, sig_id) VALUES (" . $pdo->lastInsertId() . ", 1)");
    header("Location: login.php?registered=1");

} elseif ($action === 'logout') {
    session_destroy();
    header("Location: login.php");

} elseif ($action === 'create_question') {
    if (!isset($_SESSION['user_id'])) die("403");
    // Captura post_type, padrão 'question'
    $post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'question';

    $stmt = $pdo->prepare("INSERT INTO questions (user_id, sig_id, title, body, post_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['sig_id'], $_POST['title'], $_POST['body'], $post_type]);

    $redirect = $_POST['redirect'] ?? "index.php";
    header("Location: " . $redirect);

} elseif ($action === 'join_sig' || $action === 'leave_sig') {
    if (!isset($_SESSION['user_id'])) die("403");
    $sql = ($action === 'join_sig') ? "INSERT OR IGNORE INTO sig_memberships (user_id, sig_id) VALUES (?, ?)" : "DELETE FROM sig_memberships WHERE user_id = ? AND sig_id = ?";
    $pdo->prepare($sql)->execute([$_SESSION['user_id'], $_POST['sig_id']]);
    header("Location: " . ($_POST['redirect'] ?? "sigs.php"));

} elseif ($action === 'update_profile') {
    if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
    $bio = trim($_POST['bio']);
    if (strlen($bio) > 100) $bio = substr($bio, 0, 100);
    $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
    $stmt->execute([$bio, $_SESSION['user_id']]);
    header("Location: profile.php?id=" . $_SESSION['user_id']);
    exit;
}
?>
