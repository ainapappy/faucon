# Phase 10 — Dashboard & UX

## Summary

Livraison du dashboard utile, des notifications in-app et de la passe UX transversale. Backend :
service `DashboardMetrics` (KPIs team-scoped en requêtes agrégées, analytics volume quotidien 30 j
en série contiguë, top workflows), `DashboardController` enrichi (stats / recentExecutions /
recentWorkflows sync, `analytics` en `Inertia::defer()`), notification d'échec d'exécution par
listener sur l'événement existant `WorkflowExecutionFailed` (destinataire : auteur du run,
décision utilisateur), endpoints notifications (index paginé, read idempotent, read-all), prop
racine `notifications` (unreadCount + 5 non-lues). Frontend : page Dashboard fidèle à la maquette
**corrigée au préalable** (4 widgets sans données réelles corrigés), graphe SVG maison avec tabs
7/14/30 et skeletons sur widgets différés, cloche de notifications dans le shell (badge, mark-read

- deep-link, tout marquer lu), page historique `notifications/Index`, entrée « Exécutions » de
  sidebar ajoutée, audit 7 états × responsive écran par écran (trous corrigés : un seul trouvé).
  Suites finales : **Pest 770/770 (3 016 assertions)**, **Vitest 170/170**, PHPStan 0 erreur,
  vue-tsc 0 erreur, Pint propre, `npm run build` OK.

## Implementation

### Files Created

Backend (11) :

- `app/Data/Workflow/DashboardStats.php` — DTO readonly, 7 propriétés
- `app/Data/Workflow/DashboardAnalytics.php` — DTO readonly (daily 30 j + topWorkflows)
- `app/Services/Workflow/DashboardMetrics.php` — stats() 2 requêtes agrégées, analytics() 2 requêtes, gap-fill PHP, top 5
- `app/Services/Workflow/ExecutionPresenter.php` — projection liste d'exécutions, extraite du contrôleur Exécutions (partagée dashboard/exécutions)
- `app/Notifications/ExecutionFailedNotification.php` — database channel, `toDatabase()` (jamais `toArray()`), payload 8 clés, message tronqué 200, aucun input/result
- `app/Listeners/Workflows/NotifyAuthorOfExecutionFailure.php` — notifie l'auteur du run seulement ; skip silencieux si `user_id` null ; try/catch best-effort
- `app/Services/NotificationPresenter.php` — `item()` : type court (`execution_failed` | `generic`), jamais le FQCN
- `app/Http/Controllers/Notifications/NotificationController.php` — index paginé 15/page, paginator aplati (`through()` + `withQueryString()`)
- `app/Http/Controllers/Notifications/MarkNotificationReadController.php` — invokable, scoping `$request->user()->notifications()->whereKey()` (sans route model binding), idempotent
- `app/Http/Controllers/Notifications/MarkAllNotificationsReadController.php` — invokable, mass update des non-lues
- `database/migrations/2026_09_20_184344_create_notifications_table.php` — table Laravel standard

Frontend (19) :

- `resources/js/types/dashboard.ts`, `resources/js/types/notifications.ts` — types miroirs des DTO/props backend
- `resources/js/lib/dashboardChart.ts` — pur : `sliceChartRange` (7/14/30), `buildChartLayout` (viewBox 660×240, polyline + aire, y-ticks, points), `nearestPointIndex`, formats FR
- `resources/js/lib/dashboardFormat.ts` — `buildStatCards` (4 KPIs, sous-textes réels), `topWorkflowBars`, formats
- `resources/js/lib/notificationFormat.ts` — `readNotificationsSummary` : lecture défensive de la prop (contournement du collision de nom, cf. Gotchas)
- `resources/js/lib/__tests__/dashboardChart.spec.ts`, `dashboardFormat.spec.ts`, `notificationFormat.spec.ts`
- `resources/js/composables/useDashboard.ts`, `useNotifications.ts` (+ `__tests__/useNotifications.spec.ts`)
- `resources/js/components/dashboard/` : `StatCard.vue`, `ExecutionsChart.vue` (SVG maison, tabs ui/tabs, crosshair + tooltip), `TopWorkflowsCard.vue`, `RecentWorkflowsCard.vue`, `RecentExecutionsTable.vue`, `DashboardSkeleton.vue`
- `resources/js/components/notifications/NotificationsBell.vue` — dropdown, badge, mark-read + navigation
- `resources/js/pages/notifications/Index.vue` — historique paginé (paginator aplati, lues grisées, read-all, deep-link)

Maquette (source de vérité, corrigée AVANT le lot D) :

- `.knowledge/design/dashboard.html` + `.knowledge/design/css/app.css` — cf. Frontend Changes

### Files Modified

- `app/Http/Controllers/DashboardController.php` — `__invoke(Request, Team $currentTeam)` ; props `stats`, `recentExecutions` (8, via ExecutionPresenter), `recentWorkflows` (6, sous-requêtes scalaires), `analytics` en `Inertia::defer()`, `permissions` ; `pendingInvitations` intacte
- `app/Http/Controllers/Workflows/WorkflowExecution/WorkflowExecutionController.php` — `toListItem()` supprimé, délégué à `ExecutionPresenter` (tests existants restés verts)
- `app/Http/Middleware/HandleInertiaRequests.php` — prop racine `notifications` = `{unreadCount, recent[≤5]}` ou null si invité
- `routes/web.php` — 3 routes notifications dans le groupe `auth` (user-scoped)
- `database/seeders/DemoWorkflowExecutionSeeder.php` — 3 runs failed **manuels** de l'auteur démo → 2 notifications non lues + 1 lue (cohérence décision A1) ; `seedRun()` retourne l'exécution et accepte `author` ; idempotent
- `tests/Feature/DashboardTest.php` (+7), `tests/Feature/DatabaseSeederTest.php` (+2)
- `resources/js/pages/Dashboard.vue` — réécrite en assembleur (~95 lignes)
- `resources/js/components/AppHeader.vue`, `AppSidebar.vue`, `AppSidebarHeader.vue` — cloche montée (desktop + sheet mobile), entrée « Exécutions » de nav ajoutée
- `resources/js/types/global.d.ts`, `types/index.ts` — props partagées
- `.knowledge/memory/maps/map-front-files.json` — registre mis à jour (+ 4 modules ui manquants)

### Database Changes

- **Une seule migration** : `notifications` (uuid PK, `type`, morphs `notifiable`, `data`, `read_at` nullable, timestamps). `php artisan migrate` exécuté sur la base dev.
- Aucun autre changement : les KPIs se calculent sur les tables existantes (pas d'index `team_id` sur `workflow_executions` — rétention 90 j + règle « mesure d'abord »).
- Directive livrables : seeder mis à jour (`migrate:fresh --seed` produit une base de démo cohérente avec la décision A1, prouvé par `DatabaseSeederTest`, 8 tests verts).

### Routes

| Méthode | URI                            | Nom                      | Notes                                |
| ------- | ------------------------------ | ------------------------ | ------------------------------------ |
| GET     | `notifications`                | `notifications.index`    | paginé 15/page, paginator aplati     |
| PATCH   | `notifications/{notification}` | `notifications.read`     | `whereUuid`, user-scoped, idempotent |
| POST    | `notifications/read-all`       | `notifications.read-all` | mass update des non-lues             |

Routes user-scoped dans le groupe `auth` existant (arbitrage A7 : le notifiable est
l'utilisateur, la cloche vit dans le header global ; la payload porte `teamSlug` pour le
deep-link). `wayfinder:generate --with-form` exécuté (voir Gotchas pour le `--with-form`).

### Frontend Changes

**Corrections maquette d'abord** (validées par l'utilisateur, appliquées avant le lot D) :

- KPI 4 « Tokens IA · mois » → « Durée moyenne · 7 j » (A4 — aucun compteur de tokens au schéma)
- Retrait des 4 sparklines + tendances fictives → sous-textes réels `.kpi-sub` (A5)
- « Activité en direct » (feed aléatoire) → « Vos workflows » (accès rapide) (A3)
- Bouton « Exporter » décoratif → raccourci « Templates » (gated)

**Lot D — Dashboard** : 4 `StatCard` (valeur + libellé + sous-texte réel), `ExecutionsChart`
(SVG maison — aire + polyline sur token `--brand`, tabs 7/14/30, tooltip enrichi
« N exécutions · M réussies · K échecs », states dataviz), `TopWorkflowsCard`,
`RecentWorkflowsCard` (dot Actif/En pause — icône + libellé, jamais la couleur seule),
`RecentExecutionsTable` (projection identique à la page Exécutions, lignes cliquables
deep-link `?execution=`, « Tout voir »), bandeau raccourcis gated par `permissions`,
`<Deferred data="analytics">` + skeletons à grille identique (zéro saut de layout).

**Lot E — Notifications** : `NotificationsBell` dans le shell (badge dot + aria-label,
dropdown des 5 non-lues, ouverture = PATCH mark-read preserveScroll PUIS navigation vers
l'exécution — navigation prioritaire si le PATCH échoue ; « Tout marquer lu » disabled à 0 +
processing ; empty state ; présente dans `AppSidebarHeader` et `AppHeader`/sheet mobile) ;
page `notifications/Index` (historique paginé, lues grisées + badge « Non lue », read-all,
pagination `only: ['notifications']`, empty state).

**Lot F — Passe UX** : audit écran par écran, checklist des 7 états du master §20 :

| Écran               | loading                      | success                | error                                  | empty                   | validation       | disabled/processing | responsive                             |
| ------------------- | ---------------------------- | ---------------------- | -------------------------------------- | ----------------------- | ---------------- | ------------------- | -------------------------------------- |
| Dashboard           | props sync + skeletons DEFER | —                      | empty states ×4 + CTA                  | n/a                     | n/a              | CTA gated           | KPIs 1→2→4 col, table overflow-x       |
| notifications/Index | paginator                    | processing read-all    | 404 silencieux (scoping back)          | illustré                | n/a              | read-all en vol     | header flex-col sous sm                |
| Cloche              | aucune requête à l'ouverture | —                      | navigation prioritaire si PATCH échoue | « Aucune notification » | n/a              | read-all processing | max-w viewport, sheet mobile           |
| workflows/Index     | sync                         | toasts ×5              | toasts + repli optimiste               | + CTA gated             | dialog 422       | switch/dialog busy  | auto-fill ≥300px                       |
| workflows/Edit      | saving                       | « Enregistré · HH:MM » | error + toasts 422                     | hints                   | JSON inspecteur  | Tester/Exécuter     | desktop-first assumé, fallback lisible |
| executions/Index    | polling + live               | toasts                 | toasts                                 | filtre vide             | n/a              | Annuler en vol      | filtres empilés, sheet plein écran     |
| templates/Index     | processing « Utiliser »      | flash                  | AlertError                             | ×3                      | n/a              | boutons gated       | auto-fill ≥290px                       |
| integrations        | testing par carte            | toast + reload         | métier ET réseau                       | + CTA gated             | live miroir back | Tester en vol       | auto-fill ≥280px                       |
| teams/settings      | reloads                      | reloads                | 422 Form                               | sections v-if           | Form errors      | dialogs             | lignes wrap (non modifié)              |
| profile/security    | status/flash                 | idem                   | Form errors                            | n/a                     | passwordRules    | submits             | OK natif                               |
| auth/*              | n/a                          | —                      | AlertError + shake                     | n/a                     | par champ        | submits             | form seule sous 1100px                 |

**Trou corrigé : un seul** — entrée « Exécutions » manquante de la sidebar/header (ajoutée,
icône `Activity`, sans le badge décoratif de la maquette). Tous les écrans P1 géraient déjà
les 7 états (travail des phases 5–9). Builder : desktop-first assumé conformément à la spec,
non refondu. Thème clair/sombre OK par construction (tokens uniquement).

### Tests

- **Backend — 44 nouveaux** : `tests/Unit/Workflow/DashboardMetricsTest.php` (14 — KPIs exacts via factories, bornes 24 h/7 j à la seconde, cancelled hors dénominateur, durée moyenne sur completed, isolation équipe, série contiguë 30 j, top workflows ex æquo déterministes, soft-deleted exclus) ; `DashboardTest.php` +7 (props exactes, limites 8/6, projection identique Exécutions, isolation, defer annoncé puis résolu via `loadDeferredProps()`, **query count métier épinglé ≤ 6**) ; `ExecutionFailedNotificationTest.php` (12 — e2e : auteur seul avec assertions négatives autres membres/équipes, webhook/planifié → rien, completed/cancelled → rien, clés payload, troncation, retry → 1 seule, `failed()` brutal, exception d'envoi rapportée et non propagée) ; `NotificationsEndpointsTest.php` (9 — paginator aplati, 15/page, isolation 2 users croisées, read idempotent, uuid d'autrui → 404, read-all, prop racine exacte, invités → login) ; `DatabaseSeederTest.php` +2.
- **Frontend — 23 nouveaux** (Vitest) : dashboardChart (12 — troncature, plancher, contiguïté, ticks, série plate, 1 point, aire fermée), dashboardFormat (9), notificationFormat (7), useNotifications (5, couche `visit` injectée).
- **Suites finales** : Pest **770/770, 3 016 assertions** ; Vitest **170/170** ; PHPStan 0 ; vue-tsc 0 ; Pint passé ; `npm run build` OK.

### Commands Executed

`search-docs` (database channel/markAsRead/mass update ; event discovery L13 ; deferred props +
helper de test Inertia) ; `database-schema` ; `php artisan list` + `--help` des make:* ;
générateurs `--no-interaction` (`make:notifications-table`, `make:notification`,
`make:listener`, `make:controller`, `make:class`, `make:test --pest`) ; `event:list` (listener
auto-découvert, aucun enregistrement manuel) ; `route:list --except-vendor` ; `php artisan
migrate` ; `php artisan wayfinder:generate --with-form` ; `vendor/bin/pint --dirty --format
agent` après chaque lot ; `composer types:check` ; `php artisan test --compact` ;
`npm run test:unit` / `types:check` / `check` / `build`.

## Key Information

### Technical Decisions

- **A1 — Destinataires** : décision utilisateur tranchée contre la recommandation de
  l'architecte (« toute l'équipe ») : **auteur du run seulement** (`$execution->user` non
  null), skip silencieux pour webhook/cron (`user_id` null). Le listener est nommé
  `NotifyAuthorOfExecutionFailure` en conséquence.
- **A2 — Graphe** : implémenté — volume quotidien 30 j, tabs 7/14/30, SVG statique maison
  (helper pur `dashboardChart.ts` + Vitest), skill dataviz respectée ; le taux de succès
  reste le KPI numérique.
- **A3/A4/A5 + « Exporter »** : maquette corrigée AVANT le lot D (règle design-first,
  validation utilisateur) puis implémentée fidèlement.
- **A6 — Cache** : aucun cache des KPIs (rétention 90 j, 4 requêtes agrégées/vue) ;
  `Cache::remember 60 s` consigné pour la phase 13.
- **A7 — Routes user-scoped** dans le groupe `auth` (pas sous `{current_team}`) : le
  notifiable est l'utilisateur ; la payload porte `teamSlug` pour naviguer vers la bonne équipe.
- **A8** — 3ᵉ route `notifications.read` (PATCH) ajoutée aux 2 demandées : le prompt exige
  « ouverture marque lu individuellement ».
- **Réutilisation / pas de duplication** : aucun service d'agrégats n'existait en phase 8
  (phase 8 = services de logs) — `DashboardMetrics` créé dans `app/Services/Workflow/` ;
  la projection des listes d'exécutions est **partagée** dashboard/exécutions via
  `ExecutionPresenter` (extraction du contrôleur existant, tests existants restés verts) ;
  notification branchée par listener sur l'événement **existant** `WorkflowExecutionFailed` —
  `RunWorkflowJob` 100 % intact.
- **Performance** : `recentWorkflows` via sous-requêtes scalaires (1 requête), query count
  métier du dashboard **épinglé par test (≤ 6)** ; `analytics` en `Inertia::defer()` avec
  skeletons ; série quotidienne rendue contiguë côté service (le front ne comble jamais de trous).
- **Piège de collision de prop** : sur `notifications/Index`, la prop de PAGE `notifications`
  (paginator) écrase la prop racine du même nom — contournement par lecture défensive de la
  forme (`readNotificationsSummary`, documenté dans `global.d.ts`) ; alternative back
  (renommer la prop de page) consignée en Future Ideas.

### Gotchas & Solutions

- **`catch (Throwable)` non qualifié ne matche pas** dans un listener namespacé sur cet
  environnement (prouvé par instrumentation : le catch ne s'exécutait jamais). Solution :
  `use Throwable;` + type importé. Gotcha majeur à retenir.
- **Wayfinder : `--with-form` obligatoire** (convention phase 3) — la génération du lot C
  sans l'option cassait `types:check` sur 19 fichiers préexistants (`.form()`) ; relancée
  avec l'option, sorties jamais éditées à la main (git-ignorées).
- **Closure `Inertia::defer()`** : annoter `: array` quand le service retourne un DTO →
  TypeError 500 sur la requête partielle (détecté par le test defer) ; retirer le type.
- **Tests des deferred props** : utiliser le helper officiel Inertia 3.x
  `loadDeferredProps()` plutôt que les headers `X-Inertia-Partial-*` manuels.
- **`tests/Unit` n'hérite pas de `RefreshDatabase`** (Pest.php ne l'applique qu'à Feature) :
  `uses(RefreshDatabase::class)` local dans `DashboardMetricsTest`.
- **Temps figé** : `Carbon::setTestNow` + reset `afterEach` (convention projet) ;
  `travelTo()` global indisponible dans ce setup.
- **Colonnes `addSelect`** : lire via `getAttribute()` et agréger via `toBase()` pour
  l'inférence PHPStan/Larastan (12 erreurs corrigées pendant le lot A).

### Commands & Config

- Aucune variable `.env` ajoutée/modifiée ; aucune valeur de config nouvelle.
- `php artisan migrate` requis à la mise à jour (table `notifications`) ;
  `php artisan migrate:fresh --seed` produit une base de démo exploitable (notifications de
  démo sur des échecs de runs manuels de l'auteur démo — cohérence A1).

### Version Notes

- **Laravel 13** : auto-discovery des listeners dans `app/Listeners` confirmée (`event:list`)
  — aucun enregistrement manuel du listener.
- **Inertia v3** : `loadDeferredProps()` (helper de test officiel) ; `Inertia::defer()` en
  contrôleur ; paginator aplati côté front (`.data`/`.total`, pas de `.meta`).
- **Notifications** : database channel via `toDatabase()` (jamais `toArray()`) ; id de
  notification = uuid **string** côté TS ; scoping par `$user->notifications()->whereKey()`
  (pas de route model binding sur `DatabaseNotification`).

## Future Ideas (Not Planned)

- **Étendre les notifications d'échec à toute l'équipe** — l'auteur seul laisse les
  déclencheurs automatiques (webhook/cron) sans destinataire ; réévaluer si un retour
  utilisateur le demande (permission dédiée ou broadcast équipe).
- **Contournement propre du collision de prop** : renommer la prop de page
  (`notifications` → `history`) ou exposer `unreadCount` dans le paginator — supprimerait la
  lecture défensive et rendrait le compteur global disponible sur la page d'historique.
- **Contrôleur dédié de redirection cloche** (PATCH + redirect en 1 visite au lieu de 2).
- **Confirmation avant suppression de workflow** — fidèle à la maquette actuelle
  (suppression directe), friction à évaluer.
- **Badge notifications temps réel** (polling/broadcast) — aujourd'hui mis à jour aux
  navigations complètes seulement.
- **Cache KPIs 60 s** (phase 13, sur mesure) ; **digest email hebdomadaire, préférences de
  notification par utilisateur, command palette, PWA, i18n, plein écran éditeur** — veille
  du prompt de phase.

## Known Limitations

- Les déclencheurs automatiques (webhook, planifié) **ne notifient personne** (décision A1).
- Le badge/cloche ne se rafraîchit qu'aux navigations complètes (prop racine, ni polling ni
  push) ; la cloche liste au plus les 5 dernières non-lues.
- Sur `notifications/Index`, la prop racine est écrasée par le paginator : la cloche y rend
  son état vide (lecture défensive) et la page n'affiche pas le compteur global non lu.
- Pas de cache des KPIs (A6, volontaire).
- Builder : desktop-first avec fallback lisible (assumé, non refondu) ; badge « 3 »
  décoratif de la sidebar de la maquette non reporté dans le code.
- Parcours manuel `migrate:fresh --seed` + scénario UI non exécuté sur la base dev
  (destructif) — comportement prouvé par les tests (seeder : 8 tests verts).

## Next Phase

Phase 11 — **Sécurité & durcissement** : audit complet — autorisation, SSRF, rate limiting,
XSS, secrets (prompt : `.knowledge/prompts/phase11.md`).
