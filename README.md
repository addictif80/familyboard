# FamilyBoard

Application web familiale open-source — alternative à FamilyWall, développée en PHP/MySQL.

## Fonctionnalités

| Module | Description |
|--------|-------------|
| 🏠 **Tableau de bord** | Vue d'ensemble : prochains événements, tâches en cours, solde du mois |
| 📸 **Mur familial** | Publications, photos, réactions emoji et commentaires |
| 📅 **Calendrier partagé** | Événements récurrents, import CalDAV/iCal (Google, Nextcloud, iCloud…) |
| 👶 **Garde alternée** | Planning de garde par enfant avec périodicité automatique |
| ✅ **Tâches & Courses** | Listes partagées, assignation, priorités, échéances |
| 💬 **Chat familial** | Messagerie en temps réel (long polling) |
| 💰 **Budget partagé** | Transactions, catégories, objectifs d'épargne, charges récurrentes |
| 📋 **Projets** | Gestion de projet : tâches kanban, budget, matériaux avec liens |
| 🛡️ **Garanties** | Suivi des garanties produits avec OCR des factures |
| 🗂️ **Documents** | Coffre-fort documentaire familial avec OCR et recherche plein texte |
| 🎥 **Caméras IP** | Visualisation des flux RTSP/MJPEG/HLS via proxy PHP (go2rtc) |
| 📺 **Écran mural** | Affichage plein écran pour télévision : agenda, tâches, budget, caméras en direct |
| 🧩 **Modules** | Chaque famille peut activer/désactiver les modules indépendamment |
| ✉️ **E-mails** | Notifications SMTP personnalisées avec templates modifiables |
| 👥 **Multi-familles** | Invitation par code ou par e-mail, rôles admin/membre |

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
│   └── schema.sql       Schéma SQL complet (unique source de vérité)
├── src/
│   ├── Core/            Router, Database, Session, Mail…
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
├── cron.php             Tâches planifiées
└── index.php            Point d'entrée unique
```

## Connexion bancaire (Kresus)

Dans le module Budget, chaque membre peut soit continuer en **saisie
assistée** (ajout manuel avec suggestion automatique de catégorie), soit
connecter sa propre instance auto-hébergée de [Kresus](https://kresus.org)
pour importer automatiquement ses transactions bancaires. FamilyBoard ne
stocke jamais d'identifiants bancaires — voir
[`deploy/kresus/README.md`](deploy/kresus/README.md) pour le guide de
déploiement complet (une instance Kresus par membre, reverse-proxy et
sécurisation).

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
- Templates personnalisables par famille
- Alertes de charges récurrentes (budget)
- Journal des envois

## Tâches planifiées (cron)

```bash
# Alertes budget récurrent — toutes les heures
0 * * * * php /var/www/familyboard/cron.php
```

## PWA

L'application peut être installée sur mobile et bureau via **📲 Installer
l'app** dans la barre latérale. Elle fonctionne en mode hors-ligne partiel
grâce au Service Worker.

## Sécurité

- Sessions PHP sécurisées (régénération à la connexion)
- Mots de passe hashés avec bcrypt
- Isolation stricte par famille (`family_id` vérifié à chaque requête)
- Validation des uploads (type MIME, taille maximale)
- Échappement systématique des sorties HTML (`htmlspecialchars`)
- Requêtes préparées PDO (protection injection SQL)
