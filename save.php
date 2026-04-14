<?php
session_start();
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$userId    = (int) $_SESSION['user_id'];
$recipeRaw = $_POST['recipe'] ?? null;
$photoId   = isset($_POST['photo_id']) ? (int) $_POST['photo_id'] : null;

if (!$recipeRaw) {
    echo json_encode(['success' => false, 'error' => 'Recipe manquante.']);
    exit;
}
if (json_decode($recipeRaw) === null) {
    echo json_encode(['success' => false, 'error' => 'Recipe JSON invalide.']);
    exit;
}

try {
    $pdo = getPDO();

    // --- Mise à jour de la recette sur une photo existante ---
    if ($photoId !== null) {
        $stmt = $pdo->prepare('UPDATE photos SET recipe = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$recipeRaw, $photoId, $userId]);
        echo json_encode(['success' => true, 'id' => $photoId]);
        exit;
    }

    // --- Nouveau upload : on attend l'image originale ---
    if (!isset($_FILES['original']) || $_FILES['original']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Image originale manquante.']);
        exit;
    }

    $file    = $_FILES['original'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // Vérification MIME réelle (pas seulement le type déclaré)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($file['tmp_name']);
    if (!in_array($mimeReal, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé.']);
        exit;
    }

    // Récupère (ou crée) l'album par défaut de l'utilisateur
    $stmt = $pdo->prepare('SELECT id FROM albums WHERE user_id = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute([$userId]);
    $album = $stmt->fetch();

    if (!$album) {
        $pdo->prepare('INSERT INTO albums (user_id, name) VALUES (?, ?)')->execute([$userId, 'Mon album']);
        $albumId = (int) $pdo->lastInsertId();
    } else {
        $albumId = (int) $album['id'];
    }

    // Renommage sécurisé : on conserve l'extension d'origine
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safename = uniqid('orig_', true) . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $dest = $uploadDir . $safename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'error' => 'Échec de l\'enregistrement du fichier.']);
        exit;
    }

    // Dimensions de l'image
    [$width, $height] = @getimagesize($dest) ?: [0, 0];

    // Insertion en base (image originale + recette)
    $stmt = $pdo->prepare(
        'INSERT INTO photos (album_id, user_id, filename, filepath, filesize, width, height, recipe)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $albumId,
        $userId,
        $file['name'],
        'uploads/' . $safename,
        $file['size'],
        $width,
        $height,
        $recipeRaw,
    ]);

    echo json_encode(['success' => true, 'id' => (int) $pdo->lastInsertId()]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
