# Changelog technique — Mykanban

Historique detaille des changements techniques.

Ce fichier contient la chronologie; la reference architecture stable est dans [ARCHITECTURE.md](./ARCHITECTURE.md).

---

## Format

### YYYY-MM-DD — Titre court

- Type: feat, fix, arch, refactor, test, breaking
- Quoi: changement concret
- Pourquoi: motivation
- Impact: fichiers/flux touches

---

### 2026-04-26 — Board mobile en navigation mono-colonne

- Type: feat
- Quoi: ajout d'un mode mobile du board affichant une seule colonne a la fois (`< lg`), avec navigation par swipe gauche/droite; ajout d'un fallback "double tap" sur une carte pour deplacer la tache vers la colonne de droite via le meme endpoint `POST /api/tasks/move`.
- Pourquoi: ameliorer l'ergonomie mobile quand le board contient plusieurs colonnes, sans perdre la logique metier de deplacement et de persistance des positions.
- Impact: `assets/controllers/board_mobile_controller.js`, `assets/controllers/board_drag_controller.js`, `assets/stimulus_bootstrap.js`, `templates/board/index.html.twig`, `assets/styles/app.css`, `README.md`, `docs/ARCHITECTURE.md`.

---

### 2026-04-26 — Release initiale v1 (premier push)

- Type: feat + arch + doc
- Quoi: livraison du MVP Mykanban — application kanban solo monoutilisateur sur Symfony 7.4 / PHP 8.2, avec :
  - **Fondations** : bootstrap Symfony, sécurité form_login + remember-me 15j + CSRF, commande `app:user:set-admin`, timezone Europe/Paris, AssetMapper + Stimulus + Turbo, Tailwind CLI.
  - **Modèle de données** : entités `User`, `Column`, `Tag`, `Task` avec contraintes de validation, indexes métier et migrations Doctrine dédiées. Champ `username` distinct de l'email pour l'authentification.
  - **CRUD métier** : gestion complète des tâches, colonnes (ordre + `isDone`) et tags ; board triable / filtrable (filtre tags en AND), reorder colonnes/tags via endpoints JSON CSRF stateless (double-submit).
  - **Drag & drop** : déplacement fiable des cartes (`POST /api/tasks/move`) protégé CSRF, drag sur toute la carte, tests fonctionnels associés.
  - **Urgence automatique** : suppression de la saisie manuelle, calcul basé sur la progression temporelle entre `createdAt` et `deadlineAt` (mode durée+unité ou date/heure à la création), tris `urgency` / `smart`, badge `Terminée` cohérent.
  - **Fixtures métier** : tags Administratif / Comptable / Code / Itylon, timelines réalistes alignées sur le moteur d'urgence auto.
  - **API webapp** : intégration API Platform (`/api/v1`) exposant `Task`, `Column`, `Tag` avec groupes de sérialisation.
  - **Auth API JWT** : firewall `api` stateless dédié, endpoint `POST /api/v1/login_check`, authenticator Bearer custom — prêt pour un client desktop futur sans dépendance à la session web.
  - **UX / Design** : refonte visuelle Airtable-like (tokens, composants `ui-card` / `ui-btn` / `ui-chip` / `ui-select`), switch thème clair/sombre persistant (`localStorage`), harmonisation des écrans hors board, polish badges et hiérarchie typographique sur les cartes.
  - **Qualité / outillage** : mise en place GrumPHP (`phplint` + `composer`) avec hook pre-commit, ajout de `php-parallel-lint/php-parallel-lint` pour fiabiliser le check.
  - **Documentation** : séparation `ARCHITECTURE.md` (référence stable) / `CHANGELOG_TECHNIQUE.md` (historique daté), `README.md` à jour côté installation / commandes / structure.
- Pourquoi: poser une base produit cohérente, fonctionnelle et documentée pour la première mise en ligne, tout en préparant l'extension desktop via une API authentifiée.
- Impact: ensemble du projet — `src/Entity/*`, `src/Controller/*`, `src/Form/*`, `src/Repository/*`, `src/Command/*`, `src/Security/*`, `src/DataFixtures/*`, `config/packages/*`, `config/routes/*`, `migrations/*`, `templates/*`, `assets/*`, `composer.json`, `composer.lock`, `grumphp.yml`, `phpunit.dist.xml`, `README.md`, `docs/ARCHITECTURE.md`.
