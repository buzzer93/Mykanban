# Mykanban

Application kanban personnelle (solo, single-user) construite avec Symfony 7.4.

> **Statut :** 🚧 En développement — voir `AGENTS.md` pour la spec verrouillée du MVP et le backlog.

## Sommaire

- [À propos](#à-propos)
- [Stack](#stack)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Commandes utiles](#commandes-utiles)
- [Structure du projet](#structure-du-projet)
- [Documentation](#documentation)
- [Licence](#licence)

## À propos

Mykanban est un tableau kanban mono-utilisateur pensé pour un usage personnel :

- 1 seul board, colonnes configurables (statuts personnalisables, réordonnables)
- Tâches avec tags obligatoires (multi-sélection, filtre AND), importance (1–5), urgence automatique selon le temps écoulé jusqu'à la deadline
- À la création d'une tâche, la deadline peut être définie via durée (1–9) + unité (jour/mois) ou via date/heure directe
- Tri manuel (drag & drop) ou automatique (smart, urgence auto, importance, deadline)
- Switch de theme clair/sombre avec memorisation locale (localStorage)
- Suppression automatique des tâches terminées depuis plus d'un mois

État actuel du modèle de données (MVP en cours) :

- Entités `User`, `Column`, `Tag`, `Task`
- Relation many-to-many `Task` ↔ `Tag` via table de jointure `task_tag`
- Index métiers en place pour le board et les archives (`column_id, position`, `deadline_at`, `archived_at`, `done_at`)
- Settings disponibles : `/settings/columns` et `/settings/tags` (CRUD + reorder drag avec persistance DB)
- CRUD tâches disponible : `/tasks/new`, `/tasks/{id}/edit`, suppression depuis le board
- Board Kanban disponible : `/board` avec filtres tags AND + tri `manual|smart|urgency|importance|deadline`
- Drag & drop tâches disponible en permanence sur le board
- Sur mobile (`< lg`) : une seule colonne affichée à la fois, navigation en swipe horizontal; double tap sur une tâche pour l'envoyer vers la colonne suivante
- Direction UI: style Airtable-like light (fond clair, texte navy, accent bleu #1b61c9, cartes arrondies et ombres douces)
- Board affiné en style Airtable: toolbar plus dense, colonnes plus compactes, hiérarchie typographique renforcée sur les cartes
- Les cartes tâches sont teintées par un dégradé basé sur la couleur de leur premier tag
- Purge automatique disponible via `app:tasks:auto-archive` (supprime les tâches done depuis plus d'un mois)

Spec fonctionnelle complète dans [`AGENTS.md`](./AGENTS.md).

## Stack

- **Backend :** PHP 8.2+, Symfony 7.4, Doctrine ORM 3
- **API :** API Platform 4 (JSON-LD / OpenAPI) exposée sous `/api/v1`
- **DB :** MySQL 8.x (installation locale / VPS — pas de Docker)
- **Frontend :** Twig, AssetMapper + Importmap, Stimulus, Turbo (Symfony UX), Tailwind CSS
- **Auth :** Symfony Security (form_login + remember_me 15j, CSRF)
- **Tests :** PHPUnit 13
- **Qualite :** GrumPHP + PHP Parallel Lint (phplint)
- **Timezone :** Europe/Paris (PHP + Twig)

## Prérequis

- PHP 8.2 ou supérieur (avec extensions `pdo_mysql`, `intl`, `ctype`, `iconv`)
- Composer 2.x
- MySQL 8.x installé en local (et sur le VPS en prod)
- Symfony CLI *(recommandé)*

> ℹ️ **Pas de Node.js requis.** Tailwind est compilé via `symfonycasts/tailwind-bundle`, qui télécharge automatiquement le binaire standalone (stocké dans `var/tailwind/`, gitignoré).

## Installation

```bash
# 1. Cloner le dépôt
git clone <url> mykanban && cd mykanban

# 2. Installer les dépendances PHP
composer install

# 3. Copier et configurer l'environnement
cp .env .env.local
# Éditer .env.local : adapter DATABASE_URL si besoin et renseigner APP_SECRET.

# 4. Créer la base MySQL locale (une fois)
# Via Symfony Console :
php bin/console doctrine:database:create

# 5. Créer le schéma
php bin/console doctrine:migrations:migrate

# 6. Créer l'utilisateur admin
php bin/console app:user:set-admin admin admin@admin.com
# (le mot de passe est demandé en interactif, caché)

# 7. Build initial de Tailwind (télécharge le binaire la 1re fois)
php bin/console tailwind:build

# 8. Lancer le serveur de dev
symfony serve -d
```

En développement, garde Tailwind en mode watch dans un second terminal :

```bash
php bin/console tailwind:build --watch
```

Si Twig remonte `Unknown "tailwind_stylesheet" function`, remplace cet appel par un lien AssetMapper standard dans le layout:

```twig
<link rel="stylesheet" href="{{ asset('styles/app.css') }}">
```

Connexion ensuite sur `https://localhost:8000/login`.

## Commandes utiles

```bash
# Doctrine
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
php bin/console doctrine:fixtures:load --no-interaction        # recharge un jeu de donnees initiales (purge la base)

# Maker
php bin/console make:entity
php bin/console make:controller
php bin/console make:form

# Tests
php bin/phpunit
php bin/phpunit --filter NomDuTest

# Qualité (GrumPHP)
vendor/bin/grumphp run

# API
php bin/console debug:router | Select-String -Pattern '/api|_api|api_'

# En APP_ENV=test, PHPUnit utilise SQLite (var/test.db) pour des tests isolés

# Cache
php bin/console cache:clear

# Sécurité
php bin/console app:user:set-admin <username> <email> [password]   # crée/met à jour l'admin

# Purge des tâches terminées (cron quotidien en prod)
php bin/console app:tasks:auto-archive

# Tailwind CSS
php bin/console tailwind:build                          # build one-shot (prod / CI)
php bin/console tailwind:build --watch                  # watch en dev
```

## Structure du projet

```text
mykanban/
├── AGENTS.md              # Spec fonctionnelle verrouillée (MVP + backlog)
├── CLAUDE.md              # Règles de collaboration avec Claude Code
├── README.md              # Ce fichier
├── docs/                  # Documentation technique détaillée
│   ├── ARCHITECTURE.md
│   └── CHANGELOG_TECHNIQUE.md
├── assets/                # Sources front (Stimulus controllers, app.js, styles/app.css Tailwind)
├── bin/console            # Point d'entrée CLI Symfony
├── config/                # Configuration Symfony
├── migrations/            # Versions Doctrine
├── public/                # Document root (index.php, assets compilés)
├── src/                   # Code applicatif (PSR-4 App\)
├── templates/             # Vues Twig
├── tests/                 # Tests PHPUnit
└── translations/          # Fichiers de traduction
```

## Documentation

- **Spec fonctionnelle :** [`AGENTS.md`](./AGENTS.md)
- **Architecture & design technique :** [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md)
- **Historique technique detaille :** [`docs/CHANGELOG_TECHNIQUE.md`](./docs/CHANGELOG_TECHNIQUE.md)
- **Règles de collaboration Claude Code :** `CLAUDE.md` *(non versionné — contient les préférences du mainteneur)*

Endpoints API principaux (authentification requise):

- `GET/POST/PATCH/DELETE /api/v1/tasks.{_format}`
- `GET/POST/PATCH/DELETE /api/v1/columns.{_format}`
- `GET/POST/PATCH/DELETE /api/v1/tags.{_format}`
- Login JWT: `POST /api/v1/login_check` (JSON `{ "username": "...", "password": "..." }`)
- Documentation OpenAPI/Hydra: `/api/v1/docs`

Exemple rapide JWT:

```bash
# 1) Obtenir un token
curl -X POST http://localhost:8000/api/v1/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin"}'

# 2) Appeler l'API avec Bearer
curl http://localhost:8000/api/v1/tasks.json \
  -H "Authorization: Bearer <TOKEN>"
```

## Licence

À définir (MIT envisagé). En attendant, tous droits réservés.
