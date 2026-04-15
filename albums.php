<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$pdo    = getPDO();
$error  = '';
$ok     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $vis  = in_array($_POST['visibility'] ?? '', ['private','public','shared']) ? $_POST['visibility'] : 'private';
        if ($name === '') {
            $error = 'Le nom de l\'album est requis.';
        } else {
            $pdo->prepare('INSERT INTO albums (user_id, name, visibility) VALUES (?,?,?)')
                ->execute([$userId, $name, $vis]);
            $ok = 'Album créé.';
        }

    } elseif ($action === 'update_visibility') {
        $albumId = (int) ($_POST['album_id'] ?? 0);
        $vis     = in_array($_POST['visibility'] ?? '', ['private','public','shared']) ? $_POST['visibility'] : 'private';
        $pdo->prepare('UPDATE albums SET visibility=? WHERE id=? AND user_id=?')
            ->execute([$vis, $albumId, $userId]);
        $ok = 'Visibilité mise à jour.';

    } elseif ($action === 'share') {
        $albumId        = (int) ($_POST['album_id'] ?? 0);
        $targetUsername = trim($_POST['username'] ?? '');
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        $stmt->execute([$targetUsername]);
        $target = $stmt->fetch();
        if (!$target) {
            $error = 'Utilisateur introuvable.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM albums WHERE id=? AND user_id=? LIMIT 1');
            $stmt->execute([$albumId, $userId]);
            if (!$stmt->fetch()) {
                $error = 'Album introuvable.';
            } else {
                $pdo->prepare('INSERT IGNORE INTO album_shares (album_id, user_id) VALUES (?,?)')
                    ->execute([$albumId, $target['id']]);
                $ok = 'Album partagé avec ' . htmlspecialchars($targetUsername) . '.';
            }
        }

    } elseif ($action === 'unshare') {
        $albumId  = (int) ($_POST['album_id'] ?? 0);
        $targetId = (int) ($_POST['target_user_id'] ?? 0);
        $pdo->prepare(
            'DELETE FROM album_shares WHERE album_id = ? AND user_id = ?
             AND EXISTS (SELECT 1 FROM albums WHERE id = ? AND user_id = ?)'
        )->execute([$albumId, $targetId, $albumId, $userId]);
        $ok = 'Partage retiré.';
    }
}

$stmt = $pdo->prepare("
    SELECT a.id, a.name, a.visibility, a.created_at,
           COUNT(p.id) AS photo_count
    FROM albums a
    LEFT JOIN photos p ON p.album_id = a.id
    WHERE a.user_id = ?
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$stmt->execute([$userId]);
$albums = $stmt->fetchAll();

$shares = [];
foreach ($albums as $album) {
    $s = $pdo->prepare('SELECT u.id, u.username FROM album_shares s JOIN users u ON u.id=s.user_id WHERE s.album_id=?');
    $s->execute([$album['id']]);
    $shares[$album['id']] = $s->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes albums — WasmLightroom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Mes <span class="text-primary">Albums</span></h1>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">Éditeur</a>
            <a href="feed.php" class="btn btn-sm btn-outline-secondary">Feed</a>
            <form method="POST" action="index.php" class="mb-0">
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-sm btn-outline-danger">Déconnexion</button>
            </form>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($ok):    ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Créer un album</h5>
            <form method="POST" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="create">
                <div class="col-md-5">
                    <label class="form-label">Nom</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Visibilité</label>
                    <select name="visibility" class="form-select">
                        <option value="private">Privé</option>
                        <option value="public">Public</option>
                        <option value="shared">Partagé</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($albums as $album): ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="mb-1"><?= htmlspecialchars($album['name']) ?></h5>
                    <small class="text-muted"><?= $album['photo_count'] ?> photo(s) · créé le <?= date('d/m/Y', strtotime($album['created_at'])) ?></small>
                </div>
                <form method="POST" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="action" value="update_visibility">
                    <input type="hidden" name="album_id" value="<?= $album['id'] ?>">
                    <select name="visibility" class="form-select form-select-sm" style="width:auto">
                        <option value="private" <?= $album['visibility']==='private'?'selected':'' ?>>Privé</option>
                        <option value="public"  <?= $album['visibility']==='public' ?'selected':'' ?>>Public</option>
                        <option value="shared"  <?= $album['visibility']==='shared' ?'selected':'' ?>>Partagé</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary">OK</button>
                </form>
            </div>

            <?php if ($album['visibility'] === 'shared'): ?>
            <hr>
            <form method="POST" class="row g-2 align-items-end mb-2">
                <input type="hidden" name="action" value="share">
                <input type="hidden" name="album_id" value="<?= $album['id'] ?>">
                <div class="col-auto">
                    <input type="text" name="username" class="form-control form-control-sm" placeholder="Nom d'utilisateur" required>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-success">Partager</button>
                </div>
            </form>
            <?php if ($shares[$album['id']]): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($shares[$album['id']] as $s): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="unshare">
                    <input type="hidden" name="album_id" value="<?= $album['id'] ?>">
                    <input type="hidden" name="target_user_id" value="<?= $s['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary">
                        <?= htmlspecialchars($s['username']) ?> x
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</body>
</html>
