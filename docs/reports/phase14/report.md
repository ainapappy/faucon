# Phase 14 — Documentation & finalisation

## Summary

Dernière phase du roadmap : **documentation complète organisée par lectorat** et **nettoyage**,
sans aucune modification de comportement applicatif. La documentation naît de la demande
« une doc technique **différente** de la doc d'utilisation, les mots techniques au wiki » : deux
portes d'entrée ([usage/](../../docs/usage/README.md) produit sans jargon ·
[technical/](../../docs/technical/README.md) développeurs avec contrats et guides d'extension),
reliées par un **wiki** dont le [dictionnaire.md](../../docs/wiki/dictionnaire.md) définit ~80
termes dans le contexte de Faucon, et par un **journal de développement** (une entrée par phase,
récit subjectif à côté des rapports chiffrés). La doc technique documente l'état réel du code
(vérifié contre les sources) : y compris les 3 types du catalogue **sans handler**
(`logic.filter`, `action.message`, `action.delay` → `handler_missing`), documentés honnêtement
plutôt que tus. Nettoyage : retrait de la devDependency `laravel-echo` (jamais importée
directement ; décision utilisateur consignée en phase 13 — `vue-tsc` passe grâce à
`skipLibCheck`), et rattrapage des 5 fichiers à échecs de formatage connus (`npm run check` :
verts). Suites finales inchangées : **Pest 841/841 (3 316 assertions)**, **Vitest 208/208 (21
fichiers)**, PHPStan 0 erreur, vue-tsc 0 erreur, Pint propre, `npm run check` 0 erreur, build
client OK (borne `pusher` = 0 fichier tenue).

## Implementation

### Files Created

Documentation (28 fichiers) :

- `docs/README.md` — index général (choisir sa porte : j'utilise / je développe / je cherche un
  terme / je lis le récit)
- `docs/usage/` — 9 fichiers : `README.md` (sommaire + écrans), `prise-en-main.md`
  (installation, compte, seeders démo, premier workflow), `workflows.md` (builder, variables,
  sauvegarde transactionnelle, test-run, activation), `nodes.md` (**référence des 17 types** par
  catégorie, dont le marqueur ⏳ des 3 non exécutables), `executions.md` (déclencheurs, statuts,
  timeline, redaction, rétention), `templates.md` (galerie, use, publish), `integrations.md`
  (credentials, test de connexion, webhook URL, **brancher un fournisseur IA**), `exemples.md`
  (3 recettes pas-à-pas issues des templates système), `faq.md` (dépannage)
- `docs/technical/` — 7 fichiers : `README.md` (sommaire + **5 invariants** + table « où est la
  source de vérité »), `architecture.md` (couches, 13 modèles, scoping, front, conventions),
  `moteur-workflow.md` (runner/registre/handlers, **catalogue vs registre**, cycle de vie,
  interpolation, logs), `fournisseurs-ia.md` (contrat, structured output, 4 drivers, usage),
  `securite.md` (autorisation, SSRF, secrets, rate limits, headers), `tests-et-qualite.md`
  (chaîne, budgets de requêtes, TDD, couverture), `performance.md` (payloads, bundle, épinglages)
- `docs/technical/extension/` — 3 guides : `ajouter-un-type-de-node.md` (catalogue → handler →
  registre → icône, protocole TDD, checklist), `ajouter-un-fournisseur-ia.md` (2 cas :
  compatible OpenAI / API propre), `ajouter-une-integration.md` (enum → whitelist → tester →
  miroir front)
- `docs/wiki/` — `README.md` (convention d'usage du dictionnaire) et
  **`dictionnaire.md`** (~80 entrées, index thématique, liens U/T vers la doc)
- `docs/diary/` — `README.md` (le journal : index + ce que le projet a appris) et 14 entrées
  `phase-01.md` … `phase-14.md` (récit, décisions, leçons ; renvoi au rapport de phase)

### Files Modified

- `README.md` — roadmap : phase 14 → ✅ + mention « Roadmap complète » ; nouvelle section
  **📚 Documentation** pointant vers `docs/` (usage, technique, dictionnaire, journal, rapports)
- `package.json` / `package-lock.json` — **retrait de `laravel-echo`** (^2.5.0, devDependency
  jamais importée directement ; les `.d.ts` d'`@laravel/echo-vue` en importent les types,
  résolus grâce à `skipLibCheck: true` de `tsconfig.json`) ; `pusher-js` conservé (requis par le
  debug Reverb DEV) ; pruning vérifié dans `node_modules`
- Reformattés par `npm run check:fix` (échecs de formatage préexistants, connus du rapport
  phase 13, plus le rapport phase 13 écrit après) : `docs/reports/phase10/report.md`,
  `docs/reports/phase12/report.md`, `docs/reports/phase13/report.md`,
  `resources/js/composables/__tests__/usePasswordValidation.spec.ts`,
  `resources/js/lib/__tests__/nodeCategories.spec.ts` — **formatage uniquement**, aucun
  changement sémantique (diffs vérifiés ; suites vertes après)

### Database Changes

Aucune (documentation et nettoyage uniquement ; les tests utilisent les factories existantes).

### Routes

Aucune route modifiée — pas de `wayfinder:generate`.

### Frontend Changes

Aucun changement de code applicatif. Seuls effets observables : les deux specs reformattés
(mises en forme, pas de logique), et le bundle **identique à la phase 13** côté dépendances de
production (`laravel-echo` n'y était déjà pas grâce à l'élimination statique ; le retrait
retire seulement la dépendance de dev). Build vérifié, borne `grep -l pusher
public/build/assets/*.js` = **0 fichier**.

### Tests

Aucun test ajouté ni modifié (aucun changement de comportement à couvrir — directives
cop/docs). Suites de régression exécutées après chaque lot de nettoyage :

- **Pest : 841 tests / 3 316 assertions, 0 échec** (`php artisan test --compact`) — identique
  à la phase 13 ;
- **Vitest : 208 tests / 21 fichiers, 0 échec** (`npm run test:unit`) — après retrait
  `laravel-echo` et reformatage des specs ;
- **vue-tsc : 0 erreur** (`npm run types:check`) — exécuté immédiatement après `npm uninstall
laravel-echo` (le risque documenté en phase 13) ;
- **PHPStan : 0 erreur** (niveau 7) ; **Pint : passed** ;
- **`npm run check` : 0 erreur** (4 warnings préexistants, hors périmètre) ;
- **`npm run build` : OK**, 0 fichier `pusher` dans `public/build/assets/`.

### Commands Executed

```bash
composer show --direct                       # versions réelles pour la doc
npm run check / check:fix                    # constat puis rattrapage du formatage
npm uninstall laravel-echo                   # nettoyage (décision phase 13)
npm run types:check                          # 0 erreur après retrait
ls node_modules/laravel-echo                 # pruning vérifié
grep -rn "laravel-echo" resources/js         # 0 import direct côté projet
npm run test:unit                            # 208 / 21 fichiers
npm run build && grep -l pusher public/build/assets/*.js | wc -l   # build + borne 0
composer test                                # Pint passed / PHPStan 0 / Pest 841-841 / (timeout
                                             # Composer 300 s sur la RÉ-exécution artisan test,
                                             # cf. Gotchas)
php artisan test --compact                   # 841 / 3 316 (exécution directe, sans plafond)
npm run check                                # 0 erreur (vérification finale)
```

## Key Information

### Technical Decisions

- **Deux lectorats, deux docs** : `usage/` parle produit (jargon évité, termes techniques en
  gras renvoyant au dictionnaire) ; `technical/` parle code (chemins réels, contrats, tests
  qui épignent). Le **wiki/dictionnaire.md** est le seul lieu de définition — chaque terme est
  défini **une fois**, dans le contexte de Faucon, avec des liens vers les deux espaces
  (convention écrite dans `docs/wiki/README.md`).
- **Documenter l'état réel, pas l'intention** : l'exploration a confirmé que 3 types catalogués
  (`logic.filter`, `action.message`, `action.delay`) n'ont **aucun handler enregistré** — un
  graphe qui les utilise est refusé avec `handler_missing` (contraste catalogue 17 / registre
  14, explicité dans la doc technique comme dans la référence des nodes, marqueur ⏳).
- **Le journal comme espace séparé des rapports** : les rapports restent exhaustifs et
  factuels (format inchangé) ; `docs/diary/` porte le récit et les leçons — 4 lignes de
  contexte par phase dans le README, une entrée liée par phase.
- **Retrait de `laravel-echo` assumé après preuve** : le risque documenté en phase 13 (les
  `.d.ts` d'`@laravel/echo-vue` importent ses types) est levé par `skipLibCheck: true` —
  `vue-tsc` à 0 erreur après `npm uninstall` et pruning vérifié. `pusher-js` reste (peer
  optionnel d'`@laravel/echo-vue`, requis par le debug Reverb DEV).
- **Formatage : rattrapage final** : les 4 échecs « préexistants hors périmètre » du rapport
  phase 13 (rapports 10/12 + 2 specs) sont corrigés — un projet « finalisé » doit passer sa
  propre chaîne (`npm run check` : 0 erreur).

### Gotchas & Solutions

- **`composer test` dépasse le plafond Composer (300 s)** sur ce poste : la chaîne relance
  `artisan test` après les checks et la suite complète (~195 s) fait déborder le budget —
  l'outil de sortie JSON a déjà rapporté les trois portes **vertes** avant le timeout. Contour :
  `php artisan test --compact` en direct (sans plafond), résultat identique. À connaître pour
  la CI (`ci:check` passe sous Linux) comme en local Windows.
- **`.env.example` déjà complet** : l'hypothèse de départ « les variables `WORKFLOW_*` ne sont
  pas documentées » s'est révélée fausse à la vérification (blocs commentés par domaine,
  `config/workflows.php`) — rien à ajouter, la doc renvoie à la config comme source.
- **Le formatage `vp check` s'applique aussi aux markdown de `docs/`** : les nouveaux fichiers
  ont été normalisés par `check:fix` (tableaux réalignés, emphases) — prévoir le passage à
  chaque rédaction.
- **Couverture** : la doc tests précise que la mesure (96,2 % fin phase 12) est **ponctuelle**
  via `XDEBUG_MODE=coverage` (aucun script permanent) — pour ne pas laisser croire à une
  garde continue qui n'existe pas.

### Commands & Config

- Aucune variable `.env` ni config modifiée ; `vite.config.ts` intouché.
- Une seule dépendance changée : `- laravel-echo` (devDependencies).
- Commande de vérification de la borne Echo prod : `grep -l pusher public/build/assets/*.js`
  → 0 fichier (inchangée depuis la phase 13).

### Version Notes

- `laravel-echo` 2.5 (retiré) : n'était requis que pour ses types par les `.d.ts`
  d'`@laravel/echo-vue` 2.5 — résolus via `skipLibCheck` ; si un jour `skipLibCheck` passe à
  `false`, réintroduire la dépendance de dev (ou une déclaration locale).
- `@laravel/echo-vue` 2.5 + `pusher-js` 8.6 : inchangés, requis en DEV uniquement
  (`realtimeDebug.ts`, garde `import.meta.env.DEV`).

## Future Ideas (Not Planned)

- **Exécuter les 3 types catalogués sans handler** (`logic.filter`, `action.message`,
  `action.delay`) : le guide d'extension en fait l'exercice d'entrée ; aucune demande produit
  à ce jour.
- **CI de fraîcheur de la doc** (liens internes cassés, termes non définis au dictionnaire) :
  la convention est écrite dans `docs/wiki/README.md`, l'outillage reste à faire.
- **Publication de la doc en site statique** (VitePress ou équivalent) : le format markdown
  par lectorat s'y prête ; aucune nécessité au périmètre portfolio.
- **`docs/reports/` hors chaîne de formatage** : les rapports passent dans `vp check`
  (markdown) — les exclure du glob éviterait les reformatages de rapports, au prix d'une
  config tooling (non faite).

## Known Limitations

- **3 types de nodes non exécutables** (catalogue 17 / registre 14) : documentés avec
  l'erreur `handler_missing`, pas cachés — voir Technical Decisions.
- **Couverture non continue** : la mesure Xdebug (96,2 % fin phase 12) reste ponctuelle ; la
  CI ne mesure pas la couverture (décision phase 12 conservée).
- **`composer test` en local Windows** : plafond Composer 300 s dépassé sur la chaîne complète
  (double exécution de la suite) — passer par `php artisan test --compact` en direct, ou
  augmenter `process-timeout`. La CI (`ci:check`) n'est pas affectée.
- **4 warnings `npm run check` préexistants** (hors périmètre, dénommés au rapport phase 13) :
  non traités — 0 erreur.
- La doc reflète l'état du code à la date de la phase : sa fraîcheur dépend des mises à jour
  futures (convention : toute modification de contrat met à jour la page concernée — rappelé
  dans chaque guide d'extension).

## Next Phase

Aucune — **roadmap complète (14/14)**. Le projet passe en mode maintenance/évolution : toute
nouvelle fonctionnalité suivra les guides d'extension et les 5 invariants de la doc technique
(`docs/technical/README.md`).
