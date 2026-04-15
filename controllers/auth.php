<?php
function handleAuth(PDO $pdo): array
{
    $error    = '';
    $success  = '';
    $authMode = $_GET['mode'] ?? 'login';

    if (($_POST['action'] ?? '') === 'logout') {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return compact('error', 'success', 'authMode');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        [$error, $success, $authMode] = handleRegister($pdo, $authMode);
    } elseif ($action === 'login') {
        $error = handleLogin($pdo);
    }

    return compact('error', 'success', 'authMode');
}

function handleRegister(PDO $pdo, string $authMode): array
{
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email']    ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        return ['Veuillez remplir tous les champs.', '', $authMode];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['Adresse e-mail invalide.', '', $authMode];
    }
    if (strlen($password) < 6) {
        return ['Le mot de passe doit contenir au moins 6 caractères.', '', $authMode];
    }
    if ($password !== $password2) {
        return ['Les mots de passe ne correspondent pas.', '', $authMode];
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['Ce nom d\'utilisateur ou cet e-mail est déjà utilisé.', '', $authMode];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)')
            ->execute([$username, $email, $hash]);
        $userId = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO albums (user_id, name) VALUES (?, ?)')
            ->execute([$userId, 'Mon album']);

        return ['', 'Compte créé ! Vous pouvez maintenant vous connecter.', 'login'];
    } catch (Exception $e) {
        return ['Erreur serveur : ' . $e->getMessage(), '', $authMode];
    }
}

function handleLogin(PDO $pdo): string
{
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        return 'Veuillez remplir tous les champs.';
    }

    $stmt = $pdo->prepare('SELECT id, password FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $username;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    return 'Identifiants invalides.';
}
