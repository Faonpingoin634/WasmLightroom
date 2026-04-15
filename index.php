<?php
session_start();
require_once __DIR__ . '/config/db.php';

$error   = '';
$success = '';
$authMode = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse e-mail invalide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $password2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            $pdo  = getPDO();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Ce nom d\'utilisateur ou cet e-mail est déjà utilisé.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)')
                    ->execute([$username, $email, $hash]);
                $userId = $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO albums (user_id, name) VALUES (?, ?)')
                    ->execute([$userId, 'Mon album']);
                $success  = 'Compte créé ! Vous pouvez maintenant vous connecter.';
                $authMode = 'login';
            }
        } catch (Exception $e) {
            $error = 'Erreur serveur : ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
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

if (($_POST['action'] ?? '') === 'logout') {
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
    <div class="card p-4 shadow" style="width:380px">

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $authMode === 'login' ? 'active' : '' ?>"
                   href="?mode=login">Connexion</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $authMode === 'register' ? 'active' : '' ?>"
                   href="?mode=register">Inscription</a>
            </li>
        </ul>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($authMode === 'login'): ?>
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

        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="register">
            <div class="mb-3">
                <label class="form-label">Nom d'utilisateur</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Adresse e-mail</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe <small class="text-muted">(min. 6 caractères)</small></label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirmer le mot de passe</label>
                <input type="password" name="password2" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Créer mon compte</button>
        </form>
        <?php endif; ?>

    </div>
</div>
<?php else: ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Éditeur d'images <span class="text-primary">WebAssembly</span></h1>
        <div class="d-flex align-items-center gap-3">
            <a href="feed.php" class="btn btn-sm btn-outline-secondary">Feed</a>
            <a href="albums.php" class="btn btn-sm btn-outline-secondary">Mes albums</a>
            <form method="POST" class="mb-0">
                <input type="hidden" name="action" value="logout">
                <span class="me-3 text-muted">Connecté : <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                <button type="submit" class="btn btn-sm btn-outline-danger">Déconnexion</button>
            </form>
        </div>
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
                <div id="zone-cursor" style="position:fixed;pointer-events:none;display:none;border:2px dashed gold;border-radius:50%;box-shadow:0 0 0 1px rgba(0,0,0,0.4);"></div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="sidebar">
                <h5 class="mb-3">Filtres</h5>

                <div class="mb-3">
                    <span id="wasm-status" class="badge bg-warning">WebAssembly : chargement...</span>
                </div>

                <p class="text-muted small">Historique</p>
                <div class="d-flex gap-2 mb-2">
                    <button class="btn btn-outline-secondary btn-sm flex-fill" id="btn-undo" disabled title="Ctrl+Z">&#8617; Annuler</button>
                    <button class="btn btn-outline-secondary btn-sm flex-fill" id="btn-redo" disabled title="Ctrl+Y">&#8618; Rétablir</button>
                </div>

                <hr class="border-secondary">

                <button class="btn btn-secondary btn-filter" id="btn-original" disabled>Image originale</button>

                <hr class="border-secondary">
                <p class="text-muted small">Filtres (via Wasm)</p>

                <button class="btn btn-outline-light btn-filter" id="btn-grayscale" disabled>Niveaux de gris</button>
                <button class="btn btn-outline-light btn-filter" id="btn-sepia" disabled>Sépia</button>
                <button class="btn btn-outline-light btn-filter" id="btn-invert" disabled>Inverser</button>
                <button class="btn btn-outline-light btn-filter" id="btn-blur" disabled>Flou</button>

                <hr class="border-secondary">
                <p class="text-muted small">Ajustements (via Wasm)</p>

                <div class="mb-3">
                    <label class="slider-label d-flex justify-content-between"><span>Luminosité</span><span id="val-brightness">0</span></label>
                    <input type="range" class="form-range" id="slider-brightness" min="-100" max="100" value="0" disabled>
                </div>
                <div class="mb-3">
                    <label class="slider-label d-flex justify-content-between"><span>Contraste</span><span id="val-contrast">0</span></label>
                    <input type="range" class="form-range" id="slider-contrast" min="-100" max="100" value="0" disabled>
                </div>

                <hr class="border-secondary">
                <p class="text-muted small">Retouche ciblée</p>

                <button class="btn btn-outline-warning btn-filter" id="btn-zone-mode" disabled>Mode zone : OFF</button>

                <div id="zone-controls" style="display:none" class="mt-2">
                    <label class="slider-label d-flex justify-content-between"><span>Rayon</span><span id="val-zone-radius">150</span></label>
                    <input type="range" class="form-range" id="slider-zone-radius" min="10" max="600" value="150">
                    <small class="text-muted">Cliquez sur l'image pour appliquer le filtre actif dans la zone.</small>
                </div>

                <hr class="border-secondary">
                <button class="btn btn-success btn-filter" id="btn-export" disabled>Exporter (PNG)</button>
                <button class="btn btn-primary btn-filter" id="btn-save" disabled>Sauvegarder</button>

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
