<div align="center">

# 🦅 Faucon

**AI Workflow Hub** — créez, exécutez et observez des workflows automatisés propulsés par l'IA

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?logo=vuedotjs&logoColor=white)
![Inertia](https://img.shields.io/badge/Inertia-v3-9553E9?logo=inertia&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?logo=typescript&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![Pest](https://img.shields.io/badge/Tests-Pest_5-14B8A6)
![License](https://img.shields.io/badge/License-MIT-green)

</div>

---

## 💼 Pourquoi ce dépôt existe

Faucon est un **projet personnel créé spécialement pour alimenter mon portfolio GitHub**.

Ayant toujours travaillé sur des projets professionnels **confidentiels**, je n'ai presque rien de
montrable publiquement de mon expérience. Faucon est ma vitrine : il présente, en open source, ma
façon de **concevoir, architecturer, sécuriser, tester et documenter** une application complète —
avec les mêmes exigences que sur un projet professionnel.

**Ce que ce projet démontre concrètement :**

- 🏛️ **Architecture** — monolithe Laravel modulaire (Actions, Services, DTOs, Policies), frontières claires backend / frontend
- 🧪 **Qualité** — TDD avec Pest, analyse statique (PHPStan / Larastan), formatage (Pint), tests front (Vitest)
- 🔐 **Sécurité** — autorisation par Policies team-scoped, credentials chiffrés en base, garde-fous SSRF, masquage des secrets dans les logs
- ⚙️ **Conception de moteur** — graphe de workflow, registre de handlers extensible, exécution asynchrone (queues, retries, cancellation)
- 🔌 **Découplage IA** — abstraction multi-providers (OpenAI, Anthropic, fake de test) : changer de fournisseur sans réécrire la logique métier
- 🎨 **Front moderne** — Inertia v3, Vue 3, TypeScript, Tailwind v4, états UX complets, responsive

## ✨ Le produit

Faucon permet de construire des automatisations de ce type :

```text
Webhook              Schedule             Form Submission
   ↓                    ↓                     ↓
AI Analysis          Fetch API            AI Classification
   ↓                    ↓                     ↓
Generate Response    AI Summary           Condition
   ↓                    ↓                  ├── Lead → CRM
Send Email           Save Result           └── Spam → Ignore
```

Un workflow est un **graphe dirigé** éditable visuellement : des **nodes** (étapes) reliés par des
**edges** (flux de données). Chaque node a une responsabilité unique, et les résultats se propagent
de node en node via un système de variables `{{ trigger.email }}`, `{{ ai.result }}`…

### Types de nodes prévus

| Catégorie    | Nodes                                                        |
| ------------ | ------------------------------------------------------------ |
| **Triggers** | Manuel · Webhook (idempotent, rate-limité) · Planifié (cron) |
| **Data**     | Input · Output · Transform · HTTP Request                    |
| **Logic**    | Condition (branches true/false) · Filter · Merge             |
| **AI**       | Prompt · Classification · Extraction · Résumé · Génération   |
| **Actions**  | Email · HTTP · Webhook                                       |

L'architecture est **extensible par conception** : ajouter un type de node = une classe handler +
un enregistrement au registre, sans modifier le moteur.

### Fonctionnalités clés visées

- 🧩 Éditeur visuel de workflows — graphe interactif, inspecteur de nodes, sauvegarde transactionnelle
- ⚡ Moteur d'exécution — validation du graphe, traversal, branches conditionnelles, erreurs explicites (node + raison)
- 🤖 Nodes IA multi-providers — réponses structurées (JSON validé, retry automatique), usage en tokens
- 🔗 Intégrations — credentials chiffrés en base, jamais exposés au frontend ni dans les logs
- 📬 Webhooks publics — idempotents (déduplication par request ID), rate-limités
- 🔄 Exécutions asynchrones — persistées, queue, retries, timeout, cancellation
- 📜 Logs d'exécution — auditables node par node (durées, input/output, erreurs), secrets masqués automatiquement
- 🗂️ Templates — workflows système et d'équipe, duplication, galerie avec aperçu
- 📊 Dashboard — KPIs par équipe, exécutions récentes, notifications in-app
- 👥 Travail en équipe — équipes, invitations, permissions, sur une base Fortify (2FA TOTP, passkeys)

## 🗺️ Feuille de route — 14 phases

Le développement progresse **phase par phase** : chaque phase validée laisse le projet dans un état
cohérent et committable.

| #   | Phase                           | Contenu                                                                    | Statut |
| --- | ------------------------------- | -------------------------------------------------------------------------- | :----: |
| 1   | Architecture & fondations       | Audit du squelette, conventions, scoping équipe, chaîne qualité            |   ✅   |
| 2   | Authentification & utilisateurs | Consolidation Fortify, policies, base d'autorisation                       |   ✅   |
| 3   | Workflow Builder                | Modèle graphe (nodes / edges), éditeur visuel, catalogue de types          |   ✅   |
| 4   | Workflow Engine                 | Validation, registre de handlers, variables, traversal, test run           |   ✅   |
| 5   | Actions & intégrations          | Nodes HTTP / Email, webhook idempotent, credentials chiffrés, SSRF         |   ✅   |
| 6   | AI Provider & AI Nodes          | Abstraction providers (OpenAI / Anthropic), 5 nodes IA, structured output  |   ✅   |
| 7   | Exécution & queue               | Exécutions persistées, jobs, retries, timeout, cancellation, planification |   ✅   |
| 8   | Logs & monitoring               | Logs par node, redaction des secrets, timeline d'exécution, rétention      |   ✅   |
| 9   | Templates                       | Templates système / équipe, duplication, publication, galerie              |   ✅   |
| 10  | Dashboard & UX                  | KPIs, notifications in-app, passe UX et responsive                         |   ✅   |
| 11  | Sécurité & durcissement         | Audit complet : autorisation, SSRF, rate limiting, XSS, secrets            |   ✅   |
| 12  | Tests                           | Couverture des zones critiques, edge cases, tests d'intégration            |   ✅   |
| 13  | Optimisation & qualité          | N+1, payloads Inertia, code-splitting, mesures avant / après               |   ✅   |
| 14  | Documentation & finalisation    | docs/, guides d'extension, exemples, nettoyage                             |   ⏳   |

> **Socle déjà en place** : squelette Laravel 13 + Inertia v3, authentification Fortify complète
> (login, enregistrement, 2FA TOTP, passkeys), gestion des équipes et invitations, pages settings,
> kit UI shadcn-vue, chaîne qualité outillée (Pest, PHPStan / Larastan, Vitest).

Légende : ⏳ à venir · 🚧 en cours · ✅ terminé

## 🏗️ Architecture

Monolithe **Laravel + Inertia** : pas d'API REST séparée, Inertia est la couche de communication
principale entre le frontend et le backend.

```text
Navigateur
   ↓
Vue 3 (pages & composants)
   ↓
Inertia v3
   ↓
Contrôleurs Laravel
   ↓
Actions / Services (logique métier)
   ↓
Base de données · Queues · Providers IA · Services externes
```

### Structure backend (direction)

```text
app/
├── Actions/
├── Data/              # DTOs
├── Enums/
├── Http/
│   ├── Controllers/
│   ├── Requests/      # validation
│   └── Resources/
├── Models/
├── Policies/
├── Services/
│   ├── Ai/            # abstraction providers IA
│   ├── Integration/   # client HTTP sécurisé, credentials
│   └── Workflow/      # moteur : runner, validator, handlers
└── Support/
```

### Structure frontend (direction)

```text
resources/js/
├── Components/
├── Composables/
├── Layouts/
├── Pages/
├── Types/
└── Utils/
```

### Modèle de données principal

`User` · `Team` · `Workflow` · `WorkflowNode` · `WorkflowEdge` · `WorkflowExecution` ·
`WorkflowExecutionLog` · `Integration` · `WorkflowTemplate`

Les ressources métier sont **team-scoped** : elles appartiennent à une équipe et l'accès est
contrôlé par des Policies.

## 🛠️ Stack technique

| Couche          | Technologies                                                                       |
| --------------- | ---------------------------------------------------------------------------------- |
| Backend         | Laravel 13 · PHP 8.4                                                               |
| Auth            | Fortify (login, 2FA TOTP, passkeys) · Sanctum                                      |
| Frontend        | Vue 3.5 · Inertia v3 · TypeScript · Tailwind CSS v4 · shadcn-vue (reka-ui, lucide) |
| Build & routing | Vite 8 · Wayfinder (routes typées générées)                                        |
| Données         | **MariaDB / MySQL par défaut** (SQLite et PostgreSQL possibles) · queue `database` |
| Qualité         | Pest 5 · PHPStan (Larastan) · Pint · Vitest                                        |

## 🚀 Démarrage rapide

Prérequis : **PHP 8.4**, **Composer**, **Node.js 22+**, **MariaDB ou MySQL**.

```bash
# après clonage du dépôt
composer setup    # dépendances PHP + .env + clé d'app + migrations + npm + build
composer dev      # lance l'app, Vite, la queue et le scheduler
```

Créez au préalable la base `faucon` sur votre serveur (`CREATE DATABASE faucon;`). Les identifiants se règlent dans `.env`. SQLite reste disponible : définissez `DB_CONNECTION=sqlite` dans `.env` avant `composer setup` (aucun serveur requis). Les tests tournent toujours sur sqlite `:memory:` — aucune incidence sur votre base.

## 🧪 Tests & qualité

```bash
composer test     # Pint + PHPStan + suite Pest complète
npm run build     # build de production
```

## 📄 Licence

MIT
