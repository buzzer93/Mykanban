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

### 2026-04-27 — Navigation colonnes mobile en swipe uniquement

- Type: fix
- Quoi:
  - Suppression des boutons de navigation de colonnes `‹` / `›` sur mobile.
  - Navigation des colonnes conservee uniquement par swipe horizontal.
  - Boutons `←` / `→` sur les cartes conserves pour deplacer les taches entre colonnes.
- Pourquoi: aligner le comportement avec la decision UX validee (swipe pour colonnes, boutons sur les tasks).
- Impact: `templates/board/index.html.twig`, `assets/controllers/board_mobile_controller.js`, `assets/styles/app.css`, `README.md`, `docs/ARCHITECTURE.md`.

---

### 2026-04-27 — Ajustement UX mobile: swipe colonnes + boutons deplacement taches

- Type: fix + feat
- Quoi:
  - Swipe horizontal retabli pour changer de colonne sur mobile, avec capture du geste sur toute la zone board (y compris quand la colonne active est vide).
  - Ajout de boutons `←` / `→` sur chaque carte (mobile uniquement) pour deplacer une tache vers la colonne precedente/suivante sans drag.
  - Suppression du texte d'aide superflu au-dessus des colonnes.
  - Badge urgence standard retire des cartes; un badge `Urgent` est affiche uniquement pour `autoUrgencyLevel` 4 ou 5.
  - Ajout d'un controller Stimulus `flash-dismiss`: fermeture manuelle et auto-disparition des messages flash apres 4 secondes.
  - Simplification du bouton theme (version compacte, labels accessibilite geres dynamiquement).
- Pourquoi: corriger la regression mobile, rendre la navigation et le deplacement de tache plus intuitifs, et reduire le bruit visuel.
- Impact: `assets/controllers/board_mobile_controller.js`, `assets/controllers/board_drag_controller.js`, `assets/controllers/flash_dismiss_controller.js`, `assets/controllers/theme_controller.js`, `assets/stimulus_bootstrap.js`, `assets/styles/app.css`, `templates/board/index.html.twig`, `templates/base.html.twig`, `templates/_app_header.html.twig`, `README.md`, `docs/ARCHITECTURE.md`.

---

### 2026-04-27 — Refonte UX mobile du board (drag instantane + boutons de nav)

- Type: feat + refactor
- Quoi:
  - Suppression du **swipe horizontal** et du **double tap** : ils entraient en conflit avec le drag (intent ambigue) et n'etaient pas decouvrables.
  - Ajout de boutons explicites `‹` / `›` autour du compteur "1/N" pour naviguer entre colonnes (cibles de tap fiables, fonctionnent meme quand la colonne active est vide).
  - Drag instantane sur mobile : suppression du long-press SortableJS (`delay`, `delayOnTouchOnly`, `touchStartThreshold`). On touche une carte, on la deplace, comme sur desktop.
  - Animation slide horizontale entre colonnes via `transform: translateX(-N * 100%)` sur une track `display: flex / overflow: hidden` (a la place du `display: none` qui etait brutal).
  - Drag-to-edge fluide : pendant un drag, doigt dans les 60 px du bord pendant 350 ms → la colonne slide ET la carte en cours de drag est reattachee (`appendChild`) dans la nouvelle liste visible, puis un `mousemove` synthetique reveille le placeholder/preview de SortableJS pour ne plus avoir a bouger le doigt.
  - Nouveaux targets Stimulus: `track`, `navBar`, `prevButton`, `nextButton`. Methodes publiques: `previous()`, `next()`, `goTo(index)`.
  - Nouvel evenement interne: `board:column-changed` (dispatchee par board-mobile, ecoutee par board-drag pour reattacher la carte en drag).
- Pourquoi: l'UX precedente cumulait trop de gestes implicites (swipe + double tap + long-press + drag-to-edge), avec des conflits d'intent (swipe/drag) et un retour visuel brutal au changement de colonne. La refonte privilegie la decouvrabilite (boutons), l'instantaneite (drag direct), et la continuite visuelle (animation slide + placeholder qui survit au changement de colonne).
- Impact: `assets/controllers/board_mobile_controller.js`, `assets/controllers/board_drag_controller.js`, `assets/styles/app.css`, `templates/board/index.html.twig`, `docs/ARCHITECTURE.md`, `README.md`.

---

### 2026-04-27 — Fiabilisation des gestes mobiles du board

- Type: fix + feat
- Quoi:
  - SortableJS configure avec `delay: 250ms`, `delayOnTouchOnly: true`, `touchStartThreshold: 5` pour que le drag d'une carte mobile demande un long-press, et n'absorbe plus les gestes de swipe horizontal et de double tap.
  - Swipe entre colonnes: `touchmove` passe en `passive: false` avec `preventDefault` quand le geste est horizontal, ce qui empeche le scroll vertical de la page de capter le geste.
  - Double tap fiabilise: detection deplacee de `click` vers `touchend` (un `click` n'est pas emis si le doigt bouge, ou peut etre annule par SortableJS).
  - Drag-to-edge: pendant un drag SortableJS sur mobile, maintenir le doigt 400 ms dans les 60 px du bord gauche/droit fait defiler la colonne visible (event `board:column-advance`).
  - Breakpoint mobile etendu: en plus de `max-width: 1023px`, le mode mobile s'active aussi sur `(pointer: coarse) and (max-width: 1279px)` pour couvrir les tablettes tactiles.
  - Classe `board-mobile-mode` posee en JS sur la section du board, utilisee comme filet de securite CSS pour forcer le mode mono-colonne meme si les media queries ne matchent pas.
- Pourquoi: la version precedente (commit `03f3590`) n'etait pas operationnelle: SortableJS volait tous les touchs, le swipe etait converti en drag involontaire, le double tap n'etait pas dispatche, et les tablettes voyaient le scroll horizontal desktop.
- Impact: `assets/controllers/board_drag_controller.js`, `assets/controllers/board_mobile_controller.js`, `assets/styles/app.css`, `docs/ARCHITECTURE.md`.

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
