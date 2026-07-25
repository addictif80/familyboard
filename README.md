# FamilyBoard

Application web familiale open-source — alternative à FamilyWall, développée
en PHP/MySQL. Auto-hébergeable, multi-familles, installable comme PWA sur
mobile et ordinateur.

## Fonctionnalités

| Module | Description |
|--------|-------------|
| 🏠 **Tableau de bord** | Vue d'ensemble : prochains événements, tâches en cours, solde du mois |
| 🖥️ **Vue famille** | Tableau de bord alternatif plein écran : météo, caméras, agenda, tâches, budget |
| 📸 **Mur familial** | Publications, photos, réactions emoji et commentaires |
| 🖼️ **Albums photo** | Albums partagés, y compris avec un co-parent à accès restreint |
| 📅 **Calendrier partagé** | Événements récurrents, import CalDAV/iCal (Google, Nextcloud, iCloud…) |
| 👶 **Garde alternée** | Planning de garde par enfant, périodicité automatique, propositions de jours, journal parental, historique d'activité horodaté |
| 🔒 **Accès co-parent** | Accès restreint pour un parent séparé (avec ou sans famille FamilyBoard à lui) — voir [Garde partagée](#garde-partagée-et-accès-co-parent) |
| ✅ **Tâches & Courses** | Listes partagées, assignation, priorités, échéances, rappels par e-mail |
| 💬 **Chat familial** | Messagerie en temps réel (long polling) |
| 💰 **Budget partagé** | Transactions, catégories, objectifs d'épargne, charges récurrentes avec alertes |
| 📋 **Projets** | Gestion de projet : tâches kanban, budget, matériaux avec liens |
| 🛡️ **Garanties** | Suivi des garanties produits avec OCR des factures |
| 🗂️ **Documents** | Coffre-fort documentaire familial avec OCR et recherche plein texte |
| 👶 **Suivi bébé** | Biberons, couches, sommeil, croissance |
| 🍽️ **Repas** | Planification des repas de la semaine |
| 📍 **Localisation** | Partage de position et lieux enregistrés |
| 📇 **Répertoire** | Contacts familiaux et professionnels partagés |
| 🚑 **Fiches urgence** | Fiches médicales/urgence consultables via un lien public (sans compte) |
| 🧑‍🍼 **Accès baby-sitter** | Lien temporaire donnant un accès limité (agenda, contacts, fiches urgence) |
| 🌩️ **Veille informationnelle** | Alertes officielles (canicule, inondation, feu de forêt…) par zone géographique |
| 🎥 **Caméras IP** | Visualisation des flux RTSP/MJPEG/HLS via proxy PHP (go2rtc) |
| 📺 **Écran mural** | Affichage plein écran pour télévision via lien kiosque : agenda, tâches, budget, caméras en direct |
| 🔔 **Notifications** | Cloche en app + notifications push (web push), avec rappels par e-mail (tâches, courses, événements) |
| 🎫 **Support** | Tickets de support envoyés à l'administrateur système, avec pièces jointes de diagnostic |
| 🧩 **Modules** | Chaque famille peut activer/désactiver les modules indépendamment |
| ✉️ **E-mails** | Notifications SMTP personnalisées avec templates modifiables par famille |
| 👥 **Multi-familles** | Invitation par code ou par e-mail, rôles admin/membre/co-parent |

## Garde partagée et accès co-parent

FamilyBoard sépare deux notions indépendantes :

- **La famille domicile** d'un compte (`family_id` + rôle `admin`/`member`) —
  exclusive : un compte appartient à une seule famille à la fois, avec un rôle
  qui s'y applique.
- **L'accès à un planning de garde** (table `custody_access`) — additif et
  indépendant de la famille : un compte peut se voir accorder l'accès à
  autant de plannings que nécessaire, appartenant à des familles différentes.

Concrètement, un même compte peut donc être :

- un parent séparé **sans famille FamilyBoard propre**, avec un compte à accès
  entièrement restreint (`role=coparent`) limité à la vue dédiée
  `/coparent` — calendrier de garde, journal parental, documents, albums et
  événements liés à l'enfant concerné, rien d'autre ;
- **ou** un utilisateur à part entière (admin ou membre) de sa propre famille,
  qui a *en plus* reçu un accès de garde partagée à un ou plusieurs enfants
  d'une (ou plusieurs) autre(s) famille(s) — il garde son tableau de bord
  complet, avec un lien "Garde partagée" en plus dans la navigation ;
- un compte à accès restreint qui décide de créer sa propre famille
  FamilyBoard complète (bouton dédié dans `/coparent`) : il devient admin de
  sa nouvelle famille tout en conservant tous ses accès de garde partagée
  existants.

Les notifications (cloche et push) suivent la même logique de cloisonnement :
un compte co-parent ne reçoit que les évènements liés à un planning auquel il
a explicitement accès (mise à jour de garde, proposition, journal parental) —
jamais les évènements génériques d'une famille (mur, chat, documents,
calendrier, projets…) auxquels il n'a pas de rapport.

## Notifications

- **En app** : cloche dans la barre du haut, historique consultable, marquage
  lu/non lu.
- **Push (web push)** : opt-in par appareil depuis *Paramètres → 🔔
  Notifications push* (ou, pour un compte co-parent, l'onglet dédié de
  `/coparent`) — fonctionne installé en PWA comme depuis un onglet navigateur
  classique (sauf iOS, voir [PWA](#pwa)).
- **Par e-mail (cron)** : rappel d'événement 24h avant, digest du lendemain,
  tâches et courses en attente depuis 7 jours et plus. Ces rappels génériques
  ne sont jamais envoyés à un compte co-parent (sauf tâche qui lui a été
  explicitement assignée), puisqu'ils ne concernent jamais un enfant en garde
  partagée précis.
- **Diffusion système** : un administrateur système peut notifier tous les
  utilisateurs de l'instance, toutes familles confondues.

## Prérequis

- PHP 8.1+
- MySQL 5.7+ ou MariaDB 10.4+ (JSON, FULLTEXT, InnoDB)
- Apache avec `mod_rewrite` activé (ou Nginx)

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/addictif80/familyboard.git
cd familyboard
```

### 2. Créer la base de données

```sql
CREATE DATABASE familyboard
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### 3. Configurer l'application

```bash
cp config/config.local.php.example config/config.local.php
# Éditer config/config.local.php avec vos paramètres DB et URL
```

Variables d'environnement acceptées en alternative :

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=familyboard
DB_USER=familyboard
DB_PASS=secret
```

### 4. Importer le schéma (installation fraîche)

```bash
mysql -u root -p familyboard < database/schema.sql
```

> **Mise à jour d'une installation existante** : le schéma est appliqué
> automatiquement au démarrage via `Database::autoMigrate()`. Toutes les
> instructions utilisent `IF NOT EXISTS` — elles sont sans effet si la
> structure existe déjà.

### 5. Installer les dépendances PHP

```bash
composer install --no-dev
```

Requis pour les notifications push (bibliothèque `minishlink/web-push`).

### 6. Permissions

```bash
chmod 755 public/uploads/
chmod 755 storage/
```

### 7. Configuration Apache

```apache
<VirtualHost *:80>
    ServerName familyboard.local
    DocumentRoot /var/www/familyboard
    <Directory /var/www/familyboard>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Un fichier `.htaccess` est déjà présent à la racine.

### Nginx (alternative)

```nginx
server {
    root /var/www/familyboard;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## Structure du projet

```
familyboard/
├── config/              Configuration (DB, constantes)
├── database/
│   ├── schema.sql       Schéma SQL complet (unique source de vérité)
│   └── add_*.sql        Migrations incrémentales (appliquées via autoMigrate)
├── src/
│   ├── Core/            Router, Database, Session, Mail, Push…
│   ├── Models/          Modèles de données
│   └── Controllers/     Contrôleurs (un par module)
├── templates/           Vues PHP
│   ├── layout.php       Layout principal (sidebar + navigation)
│   └── <module>/        Templates par module
├── public/
│   ├── css/app.css      Feuille de style principale
│   ├── js/              Scripts JavaScript
│   ├── uploads/         Fichiers uploadés
│   └── manifest.json    Manifeste PWA
├── storage/             Fichiers serveur (OCR, temp…)
├── cron.php             Tâches planifiées (rappels e-mail, alertes, sync CalDAV)
└── index.php            Point d'entrée unique
```

## Modules désactivables

Dans **Paramètres → Modules actifs** (admin uniquement), chaque famille peut
activer ou désactiver les modules qu'elle utilise. Un module désactivé
disparaît de la navigation et redirige vers le tableau de bord si l'URL est
accédée directement. Tous les modules sont activés par défaut.

## Caméras IP et flux RTSP

| Type | Description |
|------|-------------|
| `mjpeg` | Flux Motion JPEG — lecture directe dans le navigateur |
| `snapshot` | Image JPEG rafraîchie toutes les 5 secondes |
| `hls` | Flux HLS via player hls.js intégré |
| `rtsp` | Flux RTSP via **go2rtc** — proxy PHP, sans accès direct depuis le navigateur |

### Configurer go2rtc

```bash
docker run -d --network=host alexxit/go2rtc
```

Renseignez l'URL dans **Paramètres → Famille** : `http://127.0.0.1:1984`
si go2rtc tourne sur le même serveur.

Le flux passe entièrement par PHP (MJPEG pour les vignettes, HLS avec
audio pour le plein écran) — go2rtc n'a pas besoin d'être accessible
depuis le navigateur.

## Calendriers CalDAV

| Service | URL exemple |
|---------|-------------|
| Google Calendar | `https://calendar.google.com/calendar/ical/EMAIL/public/basic.ics` |
| Nextcloud | `https://nextcloud.example.com/remote.php/dav/calendars/USER/NOM/` |
| iCloud | URL de partage iCal depuis l'app Calendrier |

## SMTP et e-mails

Configurez votre serveur SMTP dans **Paramètres → SMTP** pour activer :

- Invitations par e-mail (lien valide 7 jours)
- Codes de vérification 2FA par e-mail
- Templates personnalisables par famille
- Rappels et alertes (voir [Notifications](#notifications))
- Journal des envois

## Tâches planifiées (cron)

```bash
# Rappels e-mail (événements, tâches, courses), alertes budget récurrent,
# veille informationnelle, synchronisation CalDAV — toutes les heures
0 * * * * php /var/www/familyboard/cron.php
```

## PWA

FamilyBoard est conçu pour être installé comme une application (PWA), sur
mobile comme sur ordinateur, sans passer par un store. C'est la méthode
d'installation mise en avant dans l'app — *Paramètres → 📲 Installer
l'application* propose un bouton d'installation directe (Android/Chrome,
via `beforeinstallprompt`) ainsi qu'un guide pas-à-pas pour iPhone/iPad
(Safari → Partager → Sur l'écran d'accueil). Elle fonctionne en mode
hors-ligne partiel grâce au Service Worker, et les notifications push ne
sont délivrées sur iOS que depuis l'app installée (jamais depuis un onglet
Safari classique — contrainte du système, pas de l'app).

## Application Android native (optionnelle, hors promotion in-app)

Un client Android natif (`android/`) existe en plus de la PWA — une app
WebView légère qui demande l'adresse de votre serveur au premier lancement.
Il n'est plus promu dans l'interface (la PWA couvre le même besoin sans
installation manuelle d'APK) ; il reste disponible pour qui veut le
compiler soi-même :

- **Build** : `.github/workflows/android-release.yml` compile et publie
  l'APK sur GitHub Releases (déclenchable manuellement ou via un tag
  `android-vX.Y.Z`).
- **Signature** : pour un APK installable (signé), configurez ces secrets
  du dépôt GitHub avant de lancer le workflow :
  - `FAMILYBOARD_KEYSTORE_BASE64` — un keystore encodé en base64
    (`keytool -genkeypair -v -keystore release.keystore -alias familyboard -keyalg RSA -keysize 2048 -validity 10000`
    puis `base64 -w0 release.keystore`)
  - `FAMILYBOARD_KEYSTORE_PASSWORD`, `FAMILYBOARD_KEY_ALIAS`, `FAMILYBOARD_KEY_PASSWORD`

  Sans ces secrets, le workflow compile quand même mais produit un APK non
  signé (non installable) — utile pour vérifier que le build passe.

## Sécurité

- **Isolation stricte par famille** : `family_id` vérifié à chaque requête
  pour toute ressource (documents, événements, tâches, budget…) ; les
  ressources de garde partagée passent par une vérification d'accès dédiée
  (`custody_access`) plutôt qu'une simple comparaison de famille, puisqu'un
  co-parent peut légitimement appartenir à une autre famille.
- **Double authentification (2FA)** : TOTP (application d'authentification)
  ou code par e-mail, avec politique d'activation obligatoire configurable
  par l'administrateur (délai de grâce avant blocage).
- **Invitations sécurisées** : token à usage unique, expirant après 7 jours ;
  si l'e-mail invité correspond à un compte déjà existant, le mot de passe de
  ce compte est vérifié avant toute action, et son rôle est explicitement
  remis à "membre" lors d'un changement de famille (pour qu'un administrateur
  invité ailleurs comme simple membre ne devienne pas administrateur de la
  famille qui l'invite).
- **Sessions PHP sécurisées** (régénération de l'ID à la connexion,
  protection contre la fixation de session).
- **Mots de passe hashés** avec bcrypt.
- **Validation des uploads** (type MIME, taille maximale).
- **Échappement systématique des sorties HTML** (`htmlspecialchars`).
- **Requêtes préparées PDO** (protection injection SQL).
