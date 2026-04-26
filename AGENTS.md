Regle de documentation (transverse, a respecter pour TOUTES les etapes)

A chaque modification de code, d'architecture, ou d'ajout/modification de feature, mettre a jour dans le meme changement :

- `README.md` : si installation, commandes exposees, structure ou stack changent.
- `docs/ARCHITECTURE.md` : si modele de donnees, flux, securite, frontend, ou CLI changent.
- `AGENTS.md` (ce fichier) : uniquement si le mainteneur valide explicitement un changement de spec - ce fichier est verrouille par defaut.

Details de la regle et criteres dans `CLAUDE.md` section 6 ("Regle de documentation"). Travail livre sans mise a jour de doc = travail incomplet.

---

Spec verrouillee (etat reel du projet au 2026-04-26)

Source de verite: le code du projet. Cette spec decrit ce qui est effectivement implemente aujourd'hui.

- Mode solo avec Symfony Security (form login), remember-me 15 jours, CSRF actif.
- Auth web basee sur `username` + mot de passe; commande CLI `app:user:set-admin` pour creer/mettre a jour l'admin.
- 1 board Kanban (`/board`).
- Colonnes configurables (CRUD + reorder), avec contrainte metier: au moins 1 colonne et au moins 1 colonne `isDone`.
- Tags configurables (CRUD + reorder), couleur + ordre, et suppression interdite si tag deja utilise.
- Taches:
	- champs: `title`, `description`, `tags[]` (obligatoire), `importance` (1-5, defaut 3), `urgency` legacy, `deadlineAt`, `column`, `position`, `doneAt`, `archivedAt`, `createdAt`, `updatedAt`.
	- creation via `/tasks/new`, edition `/tasks/{id}/edit`, suppression `/tasks/{id}/delete`.
	- creation deadline en 2 modes: date/heure directe, ou duree (1-9) + unite (jour/mois).
	- si aucun tag n'existe, la creation de tache est bloquee et redirigee vers les settings tags.
- Filtre tags sur le board en mode AND (la tache doit contenir tous les tags selectionnes).
- Tri board: `manual | smart | urgency | importance | deadline`, avec persistance locale du choix de tri.
- Drag & drop des taches actif (intra-colonne et inter-colonnes) via `POST /api/tasks/move` (JSON + CSRF).
- Regle `doneAt` respectee: set a l'entree d'une colonne `isDone`, clear a la sortie.
- Commande auto-archive: `app:tasks:auto-archive`.
	- etat actuel: suppression definitive des taches `doneAt <= now-1 month`.
	- la page Archives (tableau triable) n'est pas implementee a ce jour.
- API en place (hors perimetre MVP initial, mais presente en prod de code):
	- API Platform sous `/api/v1` pour `Task`, `Column`, `Tag`.
	- Auth API JWT via `POST /api/v1/login_check` + Bearer token.

Checklist minimum a garder:
- `.env.local` prod (DB, APP_SECRET, JWT_SECRET, JWT_TTL, etc.)
- `php bin/console doctrine:migrations:migrate --no-interaction`
- `php bin/console tailwind:build`
- setup cron pour `php bin/console app:tasks:auto-archive`

---

Ecarts connus entre MVP initial et implementation actuelle

- Le MVP initial parlait d'un archivage logique + page Archives; l'implementation actuelle fait une suppression definitive via commande CLI.
- L'API (`/api/v1` + JWT) existe deja, alors qu'elle n'etait pas explicite dans le plan MVP d'origine.

Toute evolution de ces points doit etre validee explicitement par Nicolas avant modification de cette spec verrouillee.
