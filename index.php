<?php
session_start();

// Lógica de Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Lógica de Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $_SESSION['xtream_user'] = [
        'server'   => rtrim($_POST['server'], '/'),
        'username' => $_POST['username'],
        'password' => $_POST['password']
    ];
    header('Location: index.php');
    exit;
}

// Se não estiver logado, mostra o formulário de login
if (!isset($_SESSION['xtream_user'])) {
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Player IPTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <form class="login-form" method="POST" action="index.php">
            <h2><i class="fa-solid fa-tv accent-color"></i> Player IPTV</h2>
            <p>Acesse seu conteúdo exclusivo</p>
            <div class="input-group">
                <i class="fa-solid fa-server"></i>
                <input type="text" id="server" name="server" placeholder="Servidor (http://...:porta )" required>
            </div>
            <div class="input-group">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="username" name="username" placeholder="Usuário" required>
            </div>
            <div class="input-group">
                <i class="fa-solid fa-key"></i>
                <input type="password" id="password" name="password" placeholder="Senha" required>
            </div>
            <button type="submit">Entrar <i class="fa-solid fa-arrow-right-to-bracket"></i></button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

// Se estiver logado, mostra a interface principal
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Player IPTV</title>
    <!-- BIBLIOTECAS ADICIONADAS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/shaka-player/4.3.4/controls.css">
    <!-- CSS PRINCIPAL -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo">
            <i class="fa-solid fa-rocket accent-color"></i>
            <h1>IPTV Player</h1>
        </div>
        <nav>
            <button id="btn-live" class="nav-btn active" data-type="live"><i class="fa-solid fa-satellite-dish"></i> Canais</button>
            <button id="btn-movies" class="nav-btn" data-type="movie"><i class="fa-solid fa-film"></i> Filmes</button>
            <button id="btn-series" class="nav-btn" data-type="series"><i class="fa-solid fa-clapperboard"></i> Séries</button>
        </nav>
        <div class="header-right">
            <div class="search-box">
                <input type="text" id="search-input" placeholder="Buscar...">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <a href="?action=logout" class="logout-btn" title="Sair"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </header>

    <main>
        <aside id="category-list">
            <h2><i class="fa-solid fa-tags"></i> Categorias</h2>
            <div id="categories-container">
                <!-- Categorias serão carregadas aqui -->
            </div>
        </aside>

        <section id="content-grid">
            <div id="grid-container">
                <!-- Conteúdo será carregado aqui -->
            </div>
        </section>
    </main>

    <div id="player-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <span class="close-btn">&times;</span>
            <h3 id="player-title"></h3>
            <div data-shaka-player-container>
                 <video id="video-player" poster="" data-shaka-player autoplay></video>
            </div>
            <div id="series-info" style="display: none;">
                <h4><i class="fa-solid fa-list-ol"></i> Episódios</h4>
                <div id="episodes-list"></div>
            </div>
        </div>
    </div>

    <div id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- BIBLIOTECAS JS ADICIONADAS -->
    <script src="https://ajax.googleapis.com/ajax/libs/shaka-player/4.3.4/shaka-player.ui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanilla-lazyload@17.8.3/dist/lazyload.min.js"></script>
    <!-- SCRIPT PRINCIPAL -->
    <script src="script.js"></script>
</body>
</html>
