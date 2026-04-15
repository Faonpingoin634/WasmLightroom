<?php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$pdo    = getPDO();

$limit  = 10;
$page   = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql = "
    SELECT p.id, p.filename, p.filepath, p.thumb_path, p.recipe, p.created_at,
           p.width, p.height, p.filesize,
           u.username, a.name AS album_name, a.visibility
    FROM photos p
    JOIN albums a ON p.album_id  = a.id
    JOIN users  u ON p.user_id   = u.id
    WHERE
        a.visibility = 'public'
        OR p.user_id = :uid1
        OR (
            a.visibility = 'shared'
            AND EXISTS (
                SELECT 1 FROM album_shares s
                WHERE s.album_id = a.id AND s.user_id = :uid2
            )
        )
    ORDER BY p.created_at DESC
    LIMIT :lim OFFSET :off
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':uid1', $userId, PDO::PARAM_INT);
$stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
$stmt->bindValue(':lim',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':off',  $offset, PDO::PARAM_INT);
$stmt->execute();
$photos = $stmt->fetchAll();

$sqlCount = "
    SELECT COUNT(*) FROM photos p
    JOIN albums a ON p.album_id = a.id
    WHERE
        a.visibility = 'public'
        OR p.user_id = :uid1
        OR (
            a.visibility = 'shared'
            AND EXISTS (
                SELECT 1 FROM album_shares s
                WHERE s.album_id = a.id AND s.user_id = :uid2
            )
        )
";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->bindValue(':uid1', $userId, PDO::PARAM_INT);
$stmtCount->bindValue(':uid2', $userId, PDO::PARAM_INT);
$stmtCount->execute();
$total      = (int) $stmtCount->fetchColumn();
$totalPages = (int) ceil($total / $limit);

function visibilityBadge(string $vis): string {
    return match($vis) {
        'public'  => '<span class="badge bg-success">Public</span>',
        'shared'  => '<span class="badge bg-info text-dark">Partagé</span>',
        default   => '<span class="badge bg-secondary">Privé</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed — WasmLightroom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Feed <span class="text-primary">Photos</span></h1>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">Éditeur</a>
            <a href="albums.php" class="btn btn-sm btn-outline-secondary">Mes albums</a>
            <form method="POST" action="index.php" class="mb-0">
                <input type="hidden" name="action" value="logout">
                <span class="me-2 text-muted"><strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                <button class="btn btn-sm btn-outline-danger">Déconnexion</button>
            </form>
        </div>
    </div>

    <p class="text-muted"><?= $total ?> photo(s) · page <?= $page ?>/<?= max(1, $totalPages) ?></p>

    <?php if (empty($photos)): ?>
        <div class="text-center text-muted py-5">
            <p class="fs-5">Aucune photo pour l'instant.</p>
            <a href="index.php" class="btn btn-primary">Uploader une image</a>
        </div>
    <?php else: ?>

    <div class="row g-3">
        <?php foreach ($photos as $photo):
            $recipe = $photo['recipe'] ? json_decode($photo['recipe'], true) : null;
            $filterLabel = $recipe['filter'] ?? null;
        ?>
        <div class="col-md-4 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div style="height:180px;overflow:hidden;background:#222;display:flex;align-items:center;justify-content:center;">
                    <img src="<?= htmlspecialchars($photo['thumb_path'] ?? $photo['filepath']) ?>"
                         alt="<?= htmlspecialchars($photo['filename']) ?>"
                         style="max-height:180px;max-width:100%;object-fit:contain;">
                </div>
                <div class="card-body p-2">
                    <p class="mb-1 fw-semibold text-truncate" title="<?= htmlspecialchars($photo['filename']) ?>">
                        <?= htmlspecialchars($photo['filename']) ?>
                    </p>
                    <small class="text-muted d-block">
                        par <strong><?= htmlspecialchars($photo['username']) ?></strong>
                        · <?= htmlspecialchars($photo['album_name']) ?>
                    </small>
                    <small class="text-muted d-block"><?= date('d/m/Y H:i', strtotime($photo['created_at'])) ?></small>
                    <div class="mt-1 d-flex flex-wrap gap-1">
                        <?= visibilityBadge($photo['visibility']) ?>
                        <?php if ($filterLabel): ?>
                            <span class="badge bg-dark"><?= htmlspecialchars($filterLabel) ?></span>
                        <?php endif; ?>
                        <?php if ($recipe && ($recipe['brightness'] ?? 0) != 0): ?>
                            <span class="badge bg-warning text-dark"><?= (int)$recipe['brightness'] > 0 ? '+' : '' ?><?= htmlspecialchars((string)(int)$recipe['brightness']) ?></span>
                        <?php endif; ?>
                        <?php if ($recipe && ($recipe['contrast'] ?? 0) != 0): ?>
                            <span class="badge bg-secondary"><?= (int)$recipe['contrast'] > 0 ? '+' : '' ?><?= htmlspecialchars((string)(int)$recipe['contrast']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($photo['width'] && $photo['height']): ?>
                    <small class="text-muted"><?= $photo['width'] ?>×<?= $photo['height'] ?> px</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>">‹ Précédent</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>">Suivant ›</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>
