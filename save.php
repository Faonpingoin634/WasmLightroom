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

    if ($photoId !== null) {
        $pdo->prepare('UPDATE photos SET recipe = ? WHERE id = ? AND user_id = ?')
            ->execute([$recipeRaw, $photoId, $userId]);
        echo json_encode(['success' => true, 'id' => $photoId]);
        exit;
    }

    if (!isset($_FILES['original']) || $_FILES['original']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Image originale manquante.']);
        exit;
    }

    $file    = $_FILES['original'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($file['tmp_name']);
    if (!in_array($mimeReal, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Type de fichier non autorisé.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM albums WHERE user_id = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute([$userId]);
    $album = $stmt->fetch();

    if (!$album) {
        $pdo->prepare('INSERT INTO albums (user_id, name) VALUES (?, ?)')->execute([$userId, 'Mon album']);
        $albumId = (int) $pdo->lastInsertId();
    } else {
        $albumId = (int) $album['id'];
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        echo json_encode(['success' => false, 'error' => 'Extension non autorisée.']);
        exit;
    }
    $safename  = uniqid('orig_', true) . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $dest = $uploadDir . $safename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'error' => 'Échec de l\'enregistrement du fichier.']);
        exit;
    }

    [$width, $height] = @getimagesize($dest) ?: [0, 0];

    $thumbPath = generateThumbnail($dest, $uploadDir, $safename, $mimeReal, $width, $height);


    $stmt = $pdo->prepare(
        'INSERT INTO photos (album_id, user_id, filename, filepath, thumb_path, filesize, width, height, recipe)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $albumId,
        $userId,
        $file['name'],
        'uploads/' . $safename,
        $thumbPath,
        $file['size'],
        $width,
        $height,
        $recipeRaw,
    ]);

    echo json_encode(['success' => true, 'id' => (int) $pdo->lastInsertId()]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generateThumbnail(string $src, string $uploadDir, string $safename, string $mime, int $width, int $height): ?string
{
    if (!extension_loaded('gd') || $width === 0 || $height === 0) {
        return null;
    }

    $maxWidth = 400;
    if ($width <= $maxWidth) {
        return null;
    }

    $srcImage = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($src),
        'image/png'  => imagecreatefrompng($src),
        'image/gif'  => imagecreatefromgif($src),
        'image/webp' => imagecreatefromwebp($src),
        default      => null,
    };

    if (!$srcImage) {
        return null;
    }

    $ratio     = $maxWidth / $width;
    $newWidth  = $maxWidth;
    $newHeight = (int) round($height * $ratio);

    $thumb = imagecreatetruecolor($newWidth, $newHeight);

    if ($mime === 'image/png') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    imagecopyresampled($thumb, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $thumbName = 'thumb_' . pathinfo($safename, PATHINFO_FILENAME) . '.jpg';
    $thumbDest = $uploadDir . $thumbName;

    imagejpeg($thumb, $thumbDest, 85);
    imagedestroy($srcImage);
    imagedestroy($thumb);

    return 'uploads/' . $thumbName;
}
