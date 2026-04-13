# Éditeur d'images WebAssembly

Application web d'édition d'images côté client, avec filtres compilés en WebAssembly (C++ → Emscripten) et authentification PHP/MySQL.

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (pour MySQL)
- PHP 8.5 — `C:\Users\louka\Downloads\php-8.5.4-Win32-vs17-x64\php.exe`

## Lancement en local

**1. Démarrer MySQL via Docker**

```bash
docker compose -f c:/projet-wasm/docker-compose.yml up -d
```

**2. Démarrer le serveur PHP**

```bash
"C:\Users\louka\Downloads\php-8.5.4-Win32-vs17-x64\php.exe" -S localhost:8080 -t "c:/projet-wasm"
```

**3. Ouvrir** http://localhost:8080

**Compte par défaut :** `admin` / `password`

**Arrêter MySQL :**

```bash
docker compose -f c:/projet-wasm/docker-compose.yml down
```

## Compiler les filtres C++ → WebAssembly

Les fichiers compilés (`builds/filters.js` et `builds/filters.wasm`) sont déjà versionnés. Pour recompiler depuis les sources (`demos/filters.cpp`) via Docker :

```bash
docker run --rm -v "c:/projet-wasm:/src" emscripten/emsdk bash -c \
  "em++ /src/demos/filters.cpp -o /src/builds/filters.js -O2 \
  -s WASM=1 \
  -s EXPORTED_FUNCTIONS=\"['_apply_grayscale','_apply_sepia','_apply_invert','_apply_blur','_apply_brightness','_malloc','_free']\" \
  -s EXPORTED_RUNTIME_METHODS=\"['HEAPU8']\" \
  -s ALLOW_MEMORY_GROWTH=1"
```

## Ce que fait le projet

### Authentification

- Formulaire de login avec session PHP
- Vérification des identifiants en base (mot de passe hashé bcrypt)
- Bouton de déconnexion

### Interface d'édition

- Zone canvas avec glisser-déposer ou sélection de fichier image
- Affichage des dimensions et du poids de l'image chargée
- Barre latérale avec les boutons de filtres et un indicateur de statut WebAssembly
- Export de l'image modifiée en PNG

### Filtres WebAssembly

Chaque filtre alloue la mémoire Wasm (`_malloc`), copie les pixels, appelle la fonction C++, puis récupère le résultat.

- **Niveaux de gris** — luminance ITU-R BT.601
- **Sépia** — matrice de couleur 3×3
- **Inverser** — `255 - canal`
- **Flou** — noyau gaussien 3×3 (`[[1,2,1],[2,4,2],[1,2,1]] / 16`)
- **Luminosité** — delta additif +30

### Base de données (MySQL via Docker)

- Conteneur `wasm_mysql` — MySQL 8.4, port 3306, sans mot de passe root
- Schéma initialisé automatiquement depuis `database.sql` au premier démarrage
- Table `users`, `albums`, `photos`

## Structure

```
projet-wasm/
├── index.php            ← layout Bootstrap + logique auth PHP
├── main.js              ← chargement Wasm + gestion canvas/filtres
├── style.css            ← styles
├── database.sql         ← schéma MySQL + données initiales (admin)
├── docker-compose.yml   ← conteneur MySQL 8.4
├── demos/
│   └── filters.cpp      ← source C++ des filtres
├── builds/
│   ├── filters.js       ← module Emscripten compilé
│   └── filters.wasm     ← binaire WebAssembly
└── config/
    └── db.php           ← connexion PDO MySQL (non versionné)
```
