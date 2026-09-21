# Prise en main

De l'installation à votre premier workflow exécuté, en une dizaine de minutes.

## 1. Installer

Prérequis : **PHP 8.4**, **Composer**, **Node.js 22+**, **MariaDB ou MySQL** en service.

```bash
composer setup    # dépendances + .env + clé d'app + migrations + npm + build
composer dev      # lance l'app, Vite, la queue et le scheduler
```

Créez au préalable la base `faucon` sur votre serveur (`CREATE DATABASE faucon;`) et réglez les
identifiants dans `.env`. Sans serveur de base de données ? Définissez `DB_CONNECTION=sqlite`
dans `.env` **avant** `composer setup`.

Ouvrez l'URL affichée par `composer dev` (par défaut <http://localhost:8000>).

## 2. Créer un compte

- **S'inscrire** : le parcours exige la vérification de l'adresse e-mail avant l'accès
  (en local, les e-mails partent dans le fichier de log — voir `.env`, `MAIL_MAILER=log`).
- À l'inscription, une **équipe personnelle** est créée : c'est votre espace privé. Vous pouvez
  ensuite créer ou rejoindre des équipes (Réglages → Équipes) — workflows, intégrations et
  dashboard sont partagés **par équipe**.
- Recommandé dès maintenant : activer la **double authentification** (code à durée limitée) et
  enregistrer une **passkey** depuis Réglages → Sécurité.

## 3. Charger les données de démo (recommandé)

```bash
php artisan db:seed
```

Le seeder crée (sans aucun secret réel) :

- un **compte de démonstration** affiché en fin de commande ;
- **trois workflows prêts à exécuter**, avec un historique d'exécutions réaliste ;
- deux **intégrations** d'exemple (HTTP générique, SMTP local) ;
- trois **templates système** visibles dans la galerie.

> Le runner IA tourne par défaut sur le **provider fake** : les nodes IA répondent de façon
> déterministe, sans clé d'API. Pour brancher un vrai fournisseur, voir
> [Intégrations & IA](integrations.md#brancher-un-fournisseur-ia).

## 4. Votre premier workflow

1. **Workflows → Nouveau** : donnez un nom, vous arrivez dans le **builder**.
2. **Tirez un node « Manuel »** (catégorie Triggers) depuis la palette : c'est le
   déclencheur obligatoire du graphe.
3. **Ajoutez un node « Entrée »** (Data) puis un node « Sortie » (Data), et reliez-les :
   cliquez-glissez depuis le point de sortie d'un node vers l'entrée du suivant.
4. **Enregistrez** (bouton en barre supérieure) — la sauvegarde est transactionnelle : le
   graphe est validé en entier, rien n'est à moitié écrit.
5. **Tester** : la modale de test accepte un échantillon JSON d'entrée, exécute le graphe pour
   de vrai (sans créer d'exécution dans l'historique) et montre le résultat node par node.
6. **Activez** le workflow (bascule dans la liste ou le builder) : il doit être **exécutable**
   — un seul trigger, aucune étape cassée, pas de boucle.

## 5. Où regarder ensuite

- [La référence des nodes](nodes.md) pour savoir ce que chaque étape sait faire ;
- [Les exécutions](executions.md) pour déclencher en vrai (webhook, planification) et lire les
  logs ;
- Les [recettes](exemples.md) pour trois workflows complets, dont ceux des templates système.
