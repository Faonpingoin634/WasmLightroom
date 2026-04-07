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
<div class="container-fluid py-4">
    <h1 class="text-center mb-4">Éditeur d'images <span class="text-primary">WebAssembly</span></h1>

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
<script src="filters.js"></script>
<script src="main.js"></script>
</body>
</html>
