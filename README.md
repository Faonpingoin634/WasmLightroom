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

## Ce que fait le projet actuellement

### Authentification

- Formulaire de login avec session PHP
- Vérification des identifiants en base (mot de passe hashé bcrypt)
- Bouton de déconnexion

### Interface d'édition

- Zone canvas avec glisser-déposer ou sélection de fichier image
- Affichage des dimensions et du poids de l'image chargée
- Barre latérale avec les boutons de filtres et un indicateur de statut WebAssembly
- Export de l'image modifiée en PNG

### Bridge JS / WebAssembly

- `main.js` charge le module Emscripten (`filters.js`) et attend `onRuntimeInitialized`
- Chaque filtre alloue la mémoire Wasm (`_malloc`), copie les pixels, appelle la fonction C++, puis récupère le résultat
- `filters.cpp` contient un stub `apply_filter()` vide — les filtres réels (niveaux de gris, inversion, flou, luminosité) sont à implémenter

### Base de données (MySQL via Docker)

- Conteneur `wasm_mysql` — MySQL 8.4, port 3306, sans mot de passe root
- Schéma initialisé automatiquement depuis `database.sql` au premier démarrage
- Table `users` — comptes utilisateurs
- Table `albums` — albums photo par utilisateur
- Table `photos` — métadonnées des photos (nom, chemin, dimensions, taille)

## Compiler les filtres C++ (nécessite Emscripten)

```bash
em++ filters.cpp -O2 \
    -s WASM=1 \
    -s EXPORTED_RUNTIME_METHODS='["_malloc","_free","HEAPU8"]' \
    -s ALLOW_MEMORY_GROWTH=1 \
    -o filters.js
```

Cela génère `filters.js` et `filters.wasm` à placer à la racine du projet.

## Structure

```
projet-wasm/
├── index.php            ← layout Bootstrap + logique auth PHP
├── main.js              ← chargement Wasm + gestion canvas/filtres
├── filters.cpp          ← filtres image en C++ (stub pour l'instant)
├── style.css            ← styles
├── database.sql         ← schéma MySQL + données initiales (admin)
├── docker-compose.yml   ← conteneur MySQL 8.4
└── config/
    └── db.php           ← connexion PDO MySQL
```
