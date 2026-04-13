<?php
session_start();
require_once __DIR__ . '/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT id, password FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $username;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = 'Identifiants invalides.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditeur d'images WebAssembly</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<?php if (!$isLoggedIn): ?>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card p-4 shadow" style="width:360px">
        <h4 class="text-center mb-4">Connexion</h4>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="mb-3">
                <label class="form-label">Nom d'utilisateur</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Se connecter</button>
        </form>
    </div>
</div>
<?php else: ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Éditeur d'images <span class="text-primary">WebAssembly</span></h1>
        <form method="POST" class="mb-0">
            <input type="hidden" name="action" value="logout">
            <span class="me-3 text-muted">Connecté : <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
            <button type="submit" class="btn btn-sm btn-outline-danger">Déconnexion</button>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-lg-9">
            <div id="canvas-container">
                <div id="drop-hint" class="text-center">
                    <p class="fs-5">Glissez une image ici ou</p>
                    <label class="btn btn-outline-primary" for="file-input">Choisir un fichier</label>
                    <input type="file" id="file-input" accept="image/*" class="d-none">
                </div>
                <canvas id="main-canvas"></canvas>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="sidebar">
                <h5 class="mb-3">Filtres</h5>

                <div class="mb-3">
                    <span id="wasm-status" class="badge bg-warning">WebAssembly : chargement...</span>
                </div>

                <button class="btn btn-secondary btn-filter" id="btn-original" disabled>Image originale</button>

                <hr class="border-secondary">
                <p class="text-muted small">Filtres (via Wasm)</p>

                <button class="btn btn-outline-light btn-filter" id="btn-grayscale" disabled>Niveaux de gris</button>
                <button class="btn btn-outline-light btn-filter" id="btn-sepia" disabled>Sépia</button>
                <button class="btn btn-outline-light btn-filter" id="btn-invert" disabled>Inverser</button>
                <button class="btn btn-outline-light btn-filter" id="btn-blur" disabled>Flou</button>
                <button class="btn btn-outline-light btn-filter" id="btn-brightness" disabled>Luminosité +</button>

                <hr class="border-secondary">
                <button class="btn btn-success btn-filter" id="btn-export" disabled>Exporter (PNG)</button>

                <hr class="border-secondary">
                <div>
                    <p class="text-muted small mb-1">Infos image</p>
                    <small id="img-info">Aucune image chargée</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="builds/filters.js"></script>
<script src="main.js"></script>
<?php endif; ?>
</body>
</html>