# Mykanban

> **Statut :** 🚧 En développement — voir [`AGENTS.md`](./AGENTS.md) pour la spec verrouillée du MVP et le backlog.

## Description
Application kanban personnelle mono-utilisateur (solo, single-user), pensée pour un usage individuel quotidien. Un seul board, colonnes configurables, tâches avec tags / importance / urgence automatique, drag & drop, et synchronisation persistante en base de données.

## Stack technique
- **Backend :** PHP 8.2+, Symfony 7.4, Doctrine ORM 3
- **API :** API Platform 4 (JSON-LD / OpenAPI) exposée sous `/api/v1`
- **Base de données :** MySQL 8.x (installation locale / VPS — pas de Docker)
- **Frontend :** Twig, AssetMapper + Importmap, Stimulus, Symfony UX Turbo, Tailwind CSS (via `symfonycasts/tailwind-bundle`, sans Node.js)
- **Auth :** Symfony Security (form_login + remember_me 15j, CSRF) + JWT pour l'API
- **Tests :** PHPUnit 13
- **Qualité :** GrumPHP + PHP Parallel Lint
- **Timezone :** Europe/Paris (PHP + Twig)

## Prérequis
- PHP >= 8.2 (extensions `pdo_mysql`, `intl`, `ctype`, `iconv`)
- Composer 2.x
- MySQL 8.x installé en local
- Symfony CLI (recommandé)

> ℹ️ **Pas de Node.js requis** — Tailwind est compilé via `symfonycasts/tailwind-bundle` (binaire standalone téléchargé dans `var/tailwind/`, gitignoré).

## Fonctionnalités

### Board & tâches
- 1 seul board, **colonnes configurables** (statuts personnalisables, réordonnables)
- Tâches avec **tags obligatoires** (multi-sélection, filtre AND), **importance** (1–5), **urgence automatique** selon le temps écoulé jusqu'à la deadline
- Définition de la deadline : durée (1–9) + unité (jour/mois) ou date/heure directe
- **Tri** : manuel (drag & drop) ou automatique (smart, urgence auto, importance, deadline)
- **Drag & drop** desktop sur le board ; sur mobile (`< lg` ou tactile `< 1280px`), boutons `←` / `→` sur les cartes pour changer de colonne
- Sur mobile : une seule colonne affichée à la fois, navigation par **swipe horizontal**
- Cartes teintées par un dégradé basé sur la couleur de leur premier tag
- Badge `Urgent` affiché uniquement quand l'urgence automatique atteint 4 ou 5

### UI / Theme
- Style Airtable-like : fond clair, texte navy, accent bleu `#1b61c9`, cartes arrondies, ombres douces
- Switch theme clair / sombre avec mémorisation `localStorage`
- Toolbar dense, colonnes compactes, hiérarchie typographique renforcée

### Settings & CRUD
- `/settings/columns` : CRUD + reorder drag avec persistance DB
- `/settings/tags` : CRUD + reorder
- `/tasks/new`, `/tasks/{id}/edit` : CRUD tâches
- `/board` : board principal avec filtres tags AND + tri `manual|smart|urgency|importance|deadline`

### Automatisation
- **Suppression auto** des tâches terminées depuis plus d'un mois (`app:tasks:auto-archive`)
- Modèle de données : entités `User`, `Column`, `Tag`, `Task` + relation many-to-many `Task` ↔ `Tag`

### API REST (`/api/v1`)
- `GET/POST/PATCH/DELETE /api/v1/tasks.{_format}`
- `GET/POST/PATCH/DELETE /api/v1/columns.{_format}`
- `GET/POST/PATCH/DELETE /api/v1/tags.{_format}`
- Login JWT : `POST /api/v1/login_check` (JSON `{ "username": "...", "password": "..." }`)
- Doc OpenAPI / Hydra : `/api/v1/docs`

## Installation

```bash
# 1. Cloner le dépôt
git clone <url> mykanban && cd mykanban

# 2. Installer les dépendances PHP
composer install

# 3. Configurer l'environnement
cp .env .env.local
# Adapter DATABASE_URL et APP_SECRET

# 4. Créer la base et le schéma
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Créer l'utilisateur admin (mot de passe en interactif, caché)
php bin/console app:user:set-admin admin admin@admin.com

# 6. Build initial de Tailwind
php bin/console tailwind:build

# 7. Lancer le serveur
symfony serve -d
```

Connexion ensuite sur [https://localhost:8000/login](https://localhost:8000/login).

En développement, garder Tailwind en mode watch dans un second terminal :
```bash
php bin/console tailwind:build --watch
```

## Utilisation

### Endpoints principaux
- Board : [https://localhost:8000/board](https://localhost:8000/board)
- Settings : `/settings/columns`, `/settings/tags`
- API doc : `/api/v1/docs`

### Exemple d'appel API (JWT)
```bash
# 1) Obtenir un token
curl -X POST http://localhost:8000/api/v1/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin"}'

# 2) Appeler l'API avec Bearer
curl http://localhost:8000/api/v1/tasks.json \
  -H "Authorization: Bearer <TOKEN>"
```

### Commandes utiles
```bash
# Doctrine
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load --no-interaction

# Tests (SQLite en APP_ENV=test pour isolation)
php bin/phpunit

# Qualité
vendor/bin/grumphp run

# Cache
php bin/console cache:clear

# Sécurité
php bin/console app:user:set-admin <username> <email> [password]

# Purge auto (cron quotidien en prod)
php bin/console app:tasks:auto-archive

# Update VPS (pull + tailwind + asset-map + cache)
bash scripts/update-vps.sh
```

### Structure du projet
```
mykanban/
├── AGENTS.md              # Spec fonctionnelle verrouillée (MVP + backlog)
├── CLAUDE.md              # Règles de collaboration avec Claude Code
├── README.md              # Ce fichier
├── docs/                  # Doc technique (ARCHITECTURE.md, CHANGELOG_TECHNIQUE.md)
├── scripts/               # Scripts utilitaires (update-vps.sh)
├── assets/                # Sources front (Stimulus, app.js, Tailwind)
├── bin/console            # CLI Symfony
├── config/                # Configuration Symfony
├── migrations/            # Versions Doctrine
├── public/                # Document root
├── src/                   # Code applicatif (PSR-4 App\)
├── templates/             # Vues Twig
├── tests/                 # Tests PHPUnit
└── translations/
```

### Documentation
- **Spec fonctionnelle :** [`AGENTS.md`](./AGENTS.md)
- **Architecture :** [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md)
- **Historique technique :** [`docs/CHANGELOG_TECHNIQUE.md`](./docs/CHANGELOG_TECHNIQUE.md)

## Licence
À définir (MIT envisagé). En attendant, tous droits réservés.
