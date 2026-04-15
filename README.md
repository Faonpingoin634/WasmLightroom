# WasmLightroom

Application web d'édition d'images côté client. Les filtres sont compilés en WebAssembly (C++ → Emscripten) et appliqués en temps réel sur le canvas. Le backend PHP gère l'authentification, le stockage non-destructif et le feed social.

## Lancement rapide (Docker)

```bash
docker compose up -d
```

Ouvrir http://localhost:8080

**Compte par défaut :** `admin` / `password`

```bash
docker compose down
```

## Lancement manuel

**Prérequis :** Docker Desktop + PHP 8.x avec les extensions `pdo_mysql` et `gd`.

**1. Démarrer MySQL**

```bash
docker compose up -d mysql
```

**2. Démarrer PHP**

```bash
php -S localhost:8080 -t .
```

**3. Ouvrir** http://localhost:8080

## Compiler les filtres C++ → WebAssembly

Les fichiers compilés (`builds/filters.js` et `builds/filters.wasm`) sont déjà versionnés. Pour recompiler depuis les sources :

```bash
docker run --rm -v "$(pwd):/src" emscripten/emsdk bash -c \
  "em++ /src/demos/filters.cpp -o /src/builds/filters.js -O2 \
  -s WASM=1 \
  -s EXPORTED_FUNCTIONS=\"['_apply_grayscale','_apply_sepia','_apply_invert','_apply_blur','_apply_brightness','_apply_contrast','_apply_grayscale_zone','_apply_sepia_zone','_apply_invert_zone','_apply_blur_zone','_apply_brightness_zone','_apply_contrast_zone','_malloc','_free']\" \
  -s EXPORTED_RUNTIME_METHODS=\"['HEAPU8']\" \
  -s ALLOW_MEMORY_GROWTH=1"
```

## Fonctionnalités

### Authentification

- Inscription avec validation (email, mot de passe ≥ 6 caractères, unicité)
- Connexion avec session PHP, mot de passe hashé bcrypt
- Déconnexion

### Éditeur d'images

- Chargement par glisser-déposer ou sélection de fichier
- Affichage des dimensions et du poids de l'image
- Export PNG de l'image retouchée
- Sauvegarde non-destructive : l'image originale est conservée, seule la recette des modifications est stockée en base

### Filtres WebAssembly (globaux)

Les pixels sont extraits du canvas via `ImageData`, injectés dans la mémoire WASM (`_malloc` + `HEAPU8`), traités en C++ avec des vues `uint32_t` (4 octets par pixel en une seule lecture), puis recopiés sur le canvas.

| Filtre | Algorithme |
|---|---|
| Niveaux de gris | Luminance ITU-R BT.601 : `0.299R + 0.587G + 0.114B` |
| Sépia | Matrice de conversion 3×3 |
| Inverser | XOR `0x00FFFFFF` (RGB seul, alpha préservé) |
| Flou | Noyau gaussien 3×3 `[[1,2,1],[2,4,2],[1,2,1]] / 16` |
| Luminosité | Delta additif clampé entre 0 et 255 |
| Contraste | Facteur `(259 × (v+255)) / (255 × (259-v))` clampé entre 0 et 255 |

### Retouche ciblée par zone

Activation du mode zone : le filtre actif s'applique uniquement aux pixels dont la distance euclidienne au point cliqué est inférieure au rayon choisi (`dx²+dy² ≤ r²`). Rayon ajustable de 10 à 600 px.

### Historique undo/redo

Pile de 20 états maximum. Chaque état sauvegarde les pixels du canvas + la recette courante. Raccourcis : `Ctrl+Z` / `Ctrl+Y` / `Ctrl+Shift+Z`.

### Albums

- Création d'albums avec visibilité **Privé**, **Public** ou **Partagé**
- Partage d'un album avec un utilisateur spécifique (par nom d'utilisateur)
- Retrait de partage

### Feed

- Affiche les photos publiques, les photos partagées avec l'utilisateur connecté et ses propres photos
- Miniatures générées automatiquement à l'upload (GD, max 400 px de large)
- Pagination par lots de 10 (`?page=N`)
- Badges : filtre appliqué, luminosité, contraste, visibilité de l'album

## Structure

```
projet-wasm/
├── index.php              ← point d'entrée : auth + éditeur
├── feed.php               ← feed social paginé
├── albums.php             ← gestion des albums
├── save.php               ← API upload + sauvegarde recette
├── style.css              ← thème sombre (palette Photoshop)
├── database.sql           ← schéma MySQL + données initiales
├── docker-compose.yml     ← MySQL 8.4 + PHP Apache
├── controllers/
│   └── auth.php           ← logique register / login / logout
├── config/
│   └── db.php             ← connexion PDO + migrations automatiques
├── js/
│   ├── main.js            ← point d'entrée JS (module ES)
│   ├── WasmLoader.js      ← init async Emscripten
│   ├── History.js         ← pile undo/redo
│   ├── WasmProcessor.js   ← appels WASM (recipe + zone)
│   └── ZoneMode.js        ← curseur circulaire + coords canvas
├── demos/
│   └── filters.cpp        ← source C++ des filtres
├── builds/
│   ├── filters.js         ← module Emscripten compilé
│   └── filters.wasm       ← binaire WebAssembly
└── uploads/               ← images originales + miniatures (gitignored)
```

## Base de données

Le schéma est initialisé automatiquement au premier démarrage via `database.sql`. Les migrations suivantes sont appliquées à chaque connexion PDO (idempotentes) :

- Colonne `recipe JSON` sur `photos`
- Colonne `visibility ENUM` sur `albums`
- Colonne `thumb_path VARCHAR` sur `photos`
- Table `album_shares`

### Schéma simplifié

```
users       (id, username, email, password, created_at)
albums      (id, user_id, name, visibility, created_at)
album_shares(album_id, user_id)
photos      (id, album_id, user_id, filename, filepath, thumb_path,
             filesize, width, height, recipe, created_at)
```

## Sécurité

- Requêtes préparées PDO sur toutes les interactions SQL (protection injection SQL)
- `htmlspecialchars()` sur toutes les données affichées (protection XSS)
- Vérification MIME réelle (`finfo`) + whitelist d'extensions sur les uploads
- Mots de passe hashés bcrypt
- Vérification de propriété sur toutes les opérations d'album (protection IDOR)
