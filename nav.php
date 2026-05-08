<?php
/**
 * nav.php — Navbar unificada da Reddora
 *
 * Parâmetros opcionais (definir ANTES de incluir):
 *   $nav_search_query  string  — preenche o campo de busca (ex: na search.php)
 *   $nav_back          array   — botão contextual: ['label' => 'Texto', 'href' => 'url']
 *                                Se não definido, nenhum botão extra aparece.
 *
 * A navbar lê da sessão: user_id, username, profile_pic
 */

// Garante que a sessão já foi iniciada (db.php cuida disso, mas por segurança:)
if (session_status() === PHP_SESSION_NONE) session_start();

$_nav_uid      = $_SESSION['user_id']    ?? null;
$_nav_username = $_SESSION['username']   ?? null;
$_nav_pic      = $_SESSION['profile_pic'] ?? null;
$_nav_query    = $nav_search_query ?? '';
$_nav_back     = $nav_back ?? null;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm" style="z-index: 1030;">
    <div class="container position-relative">

        <!-- Brand -->
        <a class="navbar-brand fw-bold me-3" href="index.php" style="letter-spacing: -0.5px;">
            Reddora
        </a>

        <!-- Busca — centro absoluto, não afetado pelos lados -->
        <form action="search.php" method="GET"
              class="d-none d-md-flex position-absolute start-50 translate-middle-x"
              style="width: 380px;">
            <div class="input-group input-group-sm">
                <input type="text" name="q" class="form-control border-0"
                       placeholder="Pesquisar na Reddora..."
                       value="<?= htmlspecialchars($_nav_query) ?>">
                <button class="btn btn-light text-primary fw-bold px-3" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>

        <!-- Lado direito -->
        <div class="d-flex align-items-center gap-2 ms-auto">

            <?php if ($_nav_uid): ?>

                <!-- Avatar + Username -->
                <a href="profile.php?id=<?= $_nav_uid ?>"
                   class="d-flex align-items-center gap-2 text-white text-decoration-none"
                   title="Meu Perfil">
                    <?php if ($_nav_pic): ?>
                        <img src="uploads/profiles/<?= htmlspecialchars($_nav_pic) ?>"
                             class="rounded-circle border border-white border-opacity-50"
                             style="width:30px; height:30px; object-fit:cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary bg-opacity-75 border border-white border-opacity-50 d-flex align-items-center justify-content-center"
                             style="width:30px; height:30px; font-size:0.8rem; font-weight:700; flex-shrink:0; color:white;">
                            <?= strtoupper(substr($_nav_username, 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <span class="d-none d-lg-inline small fw-semibold">
                        <?= htmlspecialchars($_nav_username) ?>
                    </span>
                </a>

                <!-- Botão contextual (voltar / SIG / etc.) -->
                <?php if ($_nav_back): ?>
                    <a href="<?= htmlspecialchars($_nav_back['href']) ?>"
                       class="btn btn-sm btn-outline-light opacity-75 d-flex align-items-center gap-1">
                        <i class="fas fa-arrow-left" style="font-size:0.7rem;"></i>
                        <?= htmlspecialchars($_nav_back['label']) ?>
                    </a>
                <?php endif; ?>

                <!-- Sair (sempre presente) -->
                <form action="post_action.php" method="POST" class="m-0">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit"
                            class="btn btn-sm btn-outline-light opacity-75"
                            title="Sair da conta">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="d-none d-lg-inline ms-1">Sair</span>
                    </button>
                </form>

            <?php else: ?>

                <!-- Não logado -->
                <a href="login.php" class="btn btn-sm btn-outline-light opacity-75">Entrar</a>
                <a href="register.php" class="btn btn-sm btn-light text-primary fw-bold">Registar</a>

            <?php endif; ?>
        </div>

    </div>
</nav>

<!-- Busca mobile (abaixo da navbar) -->
<div class="d-md-none bg-primary pb-2 px-3" style="margin-top:-1px;">
    <form action="search.php" method="GET">
        <div class="input-group input-group-sm">
            <input type="text" name="q" class="form-control border-0"
                   placeholder="Pesquisar..."
                   value="<?= htmlspecialchars($_nav_query) ?>">
            <button class="btn btn-light text-primary px-3" type="submit">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>
</div>
<div class="mb-4"></div>
