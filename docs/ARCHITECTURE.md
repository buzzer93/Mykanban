# Architecture technique — Mykanban

Ce document decrit l'architecture cible et l'etat technique courant du projet.

Objectif:

- servir de reference stable pour comprendre comment le systeme fonctionne,
- decrire les invariants et decisions structurantes,
- eviter de melanger la vision architecture et le journal de developpement.

Pour l'historique detaille des changements, voir [CHANGELOG_TECHNIQUE.md](./CHANGELOG_TECHNIQUE.md).

---

## Sommaire

- [1. Vue d'ensemble](#1-vue-densemble)
- [2. Modele de donnees](#2-modele-de-donnees)
- [3. Flux applicatifs](#3-flux-applicatifs)
- [4. Securite](#4-securite)
- [5. Frontend](#5-frontend)
- [6. Commandes CLI](#6-commandes-cli)
- [7. Decisions techniques (ADR)](#7-decisions-techniques-adr)
- [8. Suivi des changements](#8-suivi-des-changements)

---

## 1. Vue d'ensemble

Application Symfony 7.4 mono-utilisateur, rendue cote serveur (Twig) avec ilots d'interactivite via Stimulus + Turbo.

- Pas d'API publique ni de SPA.
- Endpoints JSON internes utilises pour drag and drop et reorder.
- API applicative exposee via API Platform sous `/api/v1` pour preparer une future version desktop.

### Schema logique

```text
Navigateur (Twig + Stimulus)
        <-> HTTP
Symfony (Controllers + Doctrine)
        <-> SQL
MySQL

Commandes CLI Symfony (provisioning admin, purge)

Clients API (desktop/futur mobile) <-> API Platform (/api)
```

## 2. Modele de donnees

### Entites

### User

| Champ | Type | Contraintes |
| --- | --- | --- |
| id | int | PK, auto |
| username | string(50) | NOT NULL, UNIQUE |
| email | string(180) | NOT NULL, UNIQUE |
| roles | json | ROLE_USER toujours present |
| password | string(255) | NOT NULL, hash auto Symfony |

### Column

| Champ | Type | Contraintes |
| --- | --- | --- |
| id | int | PK, auto |
| name | string | NOT NULL |
| position | int | NOT NULL, index |
| isDone | bool | au moins une colonne true |

Note: table SQL mappee sur `kanban_column`.

### Tag

| Champ | Type | Contraintes |
| --- | --- | --- |
| id | int | PK, auto |
| name | string | NOT NULL |
| color | string | hex #RRGGBB |
| position | int | NOT NULL |

### Task

| Champ | Type | Contraintes |
| --- | --- | --- |
| id | int | PK, auto |
| title | string | NOT NULL |
| description | text | nullable |
| importance | smallint | 1-5, default 3 |
| urgency | smallint | legacy (non saisi en UI), urgence affichée calculée dynamiquement |
| deadlineAt | datetime | nullable |
| column | FK Column | NOT NULL |
| position | int | NOT NULL |
| doneAt | datetime | nullable |
| archivedAt | datetime | nullable |
| createdAt | datetime | auto |
| updatedAt | datetime | auto |
| tags | M2M Tag | min 1 cote validation |

M2M Task <-> Tag via table de jointure `task_tag`.

### Index

- task(column_id, position)
- task(deadline_at)
- task(archived_at)
- task(done_at)

### Invariants metier

- Au moins une colonne doit exister.
- Au moins une colonne doit etre marquee isDone.
- Une tache sans tag est invalide cote backend.
- Le filtre tags du board est un filtre AND.

## 3. Flux applicatifs

### Settings Colonnes

- CRUD sur /settings/columns
- Reorder via POST /api/settings/columns/reorder
- Reorder JS envoie CSRF stateless en double-submit (cookie + header) + `_token` JSON
- Protections:
  - impossible de supprimer la derniere colonne,
  - impossible de supprimer la derniere colonne isDone,
  - migration obligatoire des taches si colonne non vide.

### Settings Tags

- CRUD sur /settings/tags
- Reorder via POST /api/settings/tags/reorder
- Reorder JS envoie CSRF stateless en double-submit (cookie + header) + `_token` JSON
- Suppression bloquee si tag utilise.

### Taches

- Creation: /tasks/new
- Edition: /tasks/{id}/edit
- Suppression: /tasks/{id}/delete
- A la creation: choix du mode deadline: duree (1-9) + unite (jour/mois) ou date/heure directe.
- Defaults creation: importance=3, colonne initiale=position minimale.

### Board

- Route: /board
- Tri: manual, smart, urgency, importance, deadline
- Persistance du tri cote client via localStorage
- Deadline: J-x, En retard (uniquement si non terminee), Sans deadline
- Drag and drop actif en permanence
- Urgence: calculee automatiquement selon le pourcentage de temps ecoule entre createdAt et deadlineAt.

### Move task (drag and drop)

POST /api/tasks/move payload:

- taskId
- targetColumnId
- newPosition
- _token

Regles:

- recalcul des positions source/cible,
- entree dans colonne isDone => doneAt = now,
- sortie d'une colonne isDone => doneAt = null.

### Purge automatique

Commande: php bin/console app:tasks:auto-archive

Comportement courant: suppression definitive des taches done depuis plus d'un mois.

## 4. Securite

- Form login sur /login
- Ecran de connexion en "Identifiant + mot de passe" (identifiant = `username`)
- Remember-me 15 jours
- Toutes les routes (sauf login/dev assets) exigent auth
- CSRF stateless actif (formulaires + endpoints JSON)
- Routes API Platform sous `/api/v1` protegees (IS_AUTHENTICATED_FULLY)
- Auth API dediee JWT sur `/api/v1` (Bearer token, firewall stateless specifique)

Tokens utilises:

- submit
- authenticate
- logout
- reorder_columns
- reorder_tags
- move_tasks

## 5. Frontend

- Twig + Tailwind v4
- AssetMapper + Importmap (pas de bundling JS classique)
- Stimulus controllers dans assets/controllers
- SortableJS pour reorder settings et drag du board

## 5.b API Platform

- Package: `api-platform/symfony` + `api-platform/doctrine-orm`
- Prefix global: `/api/v1`
- Ressources exposees: `Task`, `Column`, `Tag`
- Formats et docs: OpenAPI/Hydra accessibles via `/api/v1/docs`
- Serialization: groupes dedies (`task:*`, `column:*`, `tag:*`) pour controler les payloads

## 5.c Auth API JWT

- Endpoint public: `POST /api/v1/login_check`
- Payload attendu: `username`, `password`
- Reponse: token JWT HMAC (`HS256`) + `expires_in`
- Protection des endpoints `/api/v1/*` par un firewall `api` stateless + authenticator Bearer custom
- Secret et TTL pilotés par variables d'environnement: `JWT_SECRET`, `JWT_TTL`

Theme:

- direction Airtable-like light
- tokens centralises dans assets/styles/app.css
- composants UI reutilisables: ui-card, ui-btn, ui-btn-primary, ui-btn-edit, ui-btn-danger, ui-chip, ui-select
- switch clair/sombre dans le header, persiste dans localStorage (`mykanban_theme`)
- initialisation anti-flash du theme dans le layout pour eviter le clignotement au chargement
- board avec composants dedies (`board-toolbar`, `board-column`, `board-task-card`) pour une densite de barre d'outils plus forte et une hierarchie typographique plus nette des cartes
- layout colonnes desktop stabilise via `grid-auto-columns: minmax(19rem, 1fr)` pour eviter l'ecrasement des colonnes
- badges cartes compactes (hauteur reduite) et statuts deadline harmonises via tokens de theme (light/dark)
- en mobile (`< 1024px` ou `pointer: coarse` jusqu'a `1279px`): une seule colonne visible a la fois, navigation horizontale native via `overflow-x` + `scroll-snap`, avec validation du changement de colonne par seuil de swipe strict (distance minimale du doigt, independante de l'inertie de scroll) pour eviter les bascules accidentelles, et boutons `←` / `→` sur chaque carte pour changer de colonne sans drag tactile
- SortableJS est reserve au desktop ; en mobile tactile, le double tap est retire, le swipe gere la navigation des colonnes, et les boutons `←` / `→` rendent le changement de colonne des taches explicite
- Les controllers front ont ete simplifies pour ce mode: `board_mobile_controller` pilote le layout mobile et l'index actif base sur le scroll natif; `board_drag_controller` pilote uniquement Sortable desktop et les deplacements explicites par boutons de carte

## 6. Commandes CLI

- app:user:set-admin [username] [email] [password]
- app:tasks:auto-archive
- doctrine:fixtures:load --no-interaction
- vendor/bin/grumphp run
- vendor/bin/parallel-lint (utilise par la tache phplint GrumPHP)
- scripts/update-vps.sh (sequence de mise a jour serveur: git pull + tailwind + asset-map + cache clear)

## 7. Decisions techniques (ADR)

### ADR-001 — AssetMapper + Importmap

Decision:

- conserver AssetMapper/Importmap pour un projet simple et sans build JS lourd.

Consequences:

- stack plus legere,
- moins de complexite de build en prod.

### ADR-002 — MySQL local, pas de Docker

Decision:

- execution locale et VPS en MySQL natif.

Consequences:

- setup plus simple,
- isolation geree par convention de base/utilisateur.

### ADR-003 — Timezone Europe/Paris forcee

Decision:

- timezone forcee dans Kernel + Twig.

Consequences:

- rendu temporel coherent entre environnements,
- vigilance si evolution vers multi-timezones.

### ADR-004 — Tailwind via symfonycasts/tailwind-bundle

Decision:

- build CSS via binaire standalone, sans Node.

Consequences:

- pipeline front plus simple,
- dependance au bundle + binaire Tailwind.

### ADR-005 — Table kanban_column + pivot implicite task_tag

Decision:

- eviter le mot reserve SQL column,
- conserver un pivot M2M simple.

Consequences:

- schema clair pour MVP,
- evolution possible vers pivot explicite si metadonnees.

### ADR-006 — Suppression tag utilise interdite

Decision:

- bloquer la suppression d'un tag associe a des taches.

Consequences:

- pas de perte semantique silencieuse.

### ADR-007 — Tri smart base sur urgence automatique

Decision:

- smartScore = urgencyAuto * 2 + importance.

Consequences:

- tri predictible et explicable,
- les priorites evoluent automatiquement dans le temps.

### ADR-008 — Drag board toujours actif

Decision:

- ne pas desactiver le drag selon tri/filtres.

Consequences:

- UX directe, ecriture continue de l'ordre manuel.

### ADR-009 — Purge done en suppression de masse

Decision:

- suppression SQL en masse des taches done > 1 mois.

Consequences:

- execution rapide pour cron quotidien.

### ADR-010 — Theme clair/sombre par variables CSS

Decision:

- piloter le theme via des variables CSS sur `:root[data-theme]`, avec bascule Stimulus et persistance locale.

Consequences:

- integration simple sans complexifier le rendu Twig,
- coherent avec AssetMapper/Importmap,
- theming centralise dans un seul fichier CSS.

## 8. Suivi des changements

Le journal detaille est dans [CHANGELOG_TECHNIQUE.md](./CHANGELOG_TECHNIQUE.md).

Regle de maintenance:

- ARCHITECTURE.md decrit l'etat du systeme.
- CHANGELOG_TECHNIQUE.md decrit la chronologie des modifications.

Derniere mise a jour structurelle:

- 2026-04-24: separation explicite architecture (reference) vs changelog (historique detaille).
- 2026-04-24: passage a une urgence automatique basee sur la progression vers la deadline.
- 2026-04-24: ajout d'un switch clair/sombre persistant (localStorage) avec tokens CSS par theme.
- 2026-04-24: refinement Airtable du board (espacements, densite toolbar, hierarchie typographique cartes).
- 2026-04-24: correction reorder colonnes/tags avec CSRF stateless complet (double-submit).
- 2026-04-24: correction layout colonnes board + harmonisation des badges deadline entre themes.
- 2026-04-24: integration API Platform pour exposer Task/Column/Tag sous `/api` (preparation client desktop).
- 2026-04-24: ajout d'une authentification API JWT dediee sur `/api/v1` pour clients desktop.
- 2026-04-24: ajout de GrumPHP (config de base + execution locale des checks qualite).
- 2026-04-26: ajout de php-parallel-lint pour corriger l'execution de la tache phplint en pre-commit.
- 2026-04-26: ajout d'une navigation mobile mono-colonne du board (swipe horizontal + fallback double tap vers la colonne de droite).
- 2026-04-27: fiabilisation des gestes mobiles du board (SortableJS `delay: 250ms`, swipe `touchmove` en `passive: false`, double tap detecte sur `touchend`, drag-to-edge pendant un drag, breakpoint mobile etendu aux tablettes tactiles via `(pointer: coarse)`, classe `board-mobile-mode` pilotee en JS pour decoupler le mode mobile des seules media queries).
- 2026-04-27: refonte UX mobile du board — suppression du swipe et du double tap (sources de friction avec le drag), ajout de boutons explicites `‹` / `›` pour naviguer entre colonnes, animation slide horizontale via `translateX` (a la place du `display: none` brutal), drag instantane sur mobile (suppression du long-press SortableJS), drag-to-edge avec reattachement automatique de la carte dans la nouvelle colonne et reveil du placeholder SortableJS via un `mousemove` synthetique.
- 2026-04-27: ajustement UX mobile board — swipe horizontal retabli sur toute la zone board (meme colonne vide) pour changer de colonne, ajout de boutons `←` / `→` directement sur les cartes pour deplacer les taches entre colonnes sans drag, suppression du texte d'aide superflu au-dessus du board et affichage d'un badge `Urgent` uniquement pour une urgence automatique 4-5.
- 2026-04-27: suppression des boutons de navigation de colonnes sur mobile (`‹` / `›`) pour conserver une navigation de colonnes uniquement par swipe, tout en gardant les boutons `←` / `→` sur les cartes pour deplacer les taches.
- 2026-04-27: correction du slide mobile du board — abandon d'un deplacement en pourcentage du track (source de decalage/colonne non affichee) au profit d'un deplacement en pixels base sur la largeur effective d'une colonne.
- 2026-04-27: refactor controllers board mobile/drag — suppression du code legacy de drag-to-edge et des evenements inter-controllers devenus inutiles, separation claire des responsabilites (swipe mobile d'un cote, drag desktop + boutons task de l'autre).
- 2026-04-27: migration du swipe mobile vers un scroll natif `scroll-snap` (abandon du swipe detecte en JS + transform), ce qui elimine les effets "swipe detecte mais colonne non affichee" observes sur mobile reel.
- 2026-04-27: tuning de sensibilite mobile — ajout d'un seuil explicite avant changement de colonne (distance minimale en px et ratio viewport) pour limiter les changements non intentionnels.
- 2026-04-27: ajout d'un script serveur `scripts/update-vps.sh` pour standardiser la sequence de deploiement manuel (pull, build tailwind, compile assets, clear cache).
