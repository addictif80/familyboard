# FamilyBoard

Alternative open-source à FamilyWall — application web famille en PHP/MySQL.

## Fonctionnalités

- 🏠 **Tableau de bord** — vue d'ensemble de la famille
- 📸 **Mur familial** — publications, photos, réactions et commentaires
- 📅 **Calendrier partagé** — événements, récurrences, import CalDAV/iCal
- 👶 **Garde alternée** — planning de garde par enfant, avec heures d'arrivée/départ
- ✅ **Tâches & Courses** — listes partagées, assignation, priorités, échéances
- 💬 **Chat familial** — messagerie en temps réel (polling)
- 💰 **Budget partagé** — transactions, catégories, objectifs d'épargne
- 📋 **Projets** — gestion de projet avec tâches (kanban) et budget dédié
- ✉️ **Notifications email** — via serveur SMTP personnalisé
- 👥 **Multi-familles** — invitation par code

## Installation

### Prérequis
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.4+
- Apache avec `mod_rewrite` activé

### Étapes

1. **Cloner le dépôt** dans votre répertoire web
2. **Créer la base de données** :
   ```sql
   CREATE DATABASE familyboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. **Importer le schéma** :
   ```bash
   mysql -u root -p familyboard < sql/schema.sql
   ```
4. **Configurer la base de données** :
   ```bash
   cp config/config.local.php.example config/config.local.php
   # Éditez config/config.local.php avec vos paramètres DB
   ```
5. **Permissions uploads** :
   ```bash
   chmod 755 public/uploads/
   ```
6. **Activer mod_rewrite** (Apache) et accéder à l'URL du projet

### Configuration Apache

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/familyboard
    DirectoryIndex index.php
    <Directory /var/www/familyboard>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Variables d'environnement (alternative à config.local.php)

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=familyboard
DB_USER=familyboard
DB_PASS=secret
```

## Structure

```
familyboard/
├── config/         Configuration
├── src/
│   ├── Core/       Router, Database, Session, Mail
│   ├── Models/     Modèles de données
│   └── Controllers/ Contrôleurs
├── templates/      Vues PHP
├── public/
│   ├── css/        Feuilles de style
│   ├── js/         JavaScript
│   └── uploads/    Fichiers uploadés
├── sql/            Schéma de base de données
└── index.php       Point d'entrée
```

## Calendriers CalDAV

FamilyBoard peut importer des calendriers depuis n'importe quelle URL iCal/CalDAV :
- Google Calendar : `https://calendar.google.com/calendar/ical/EMAIL/public/basic.ics`
- Nextcloud : `https://nextcloud.example.com/remote.php/dav/calendars/USER/CALENDAR/`
- iCloud : via les URL de partage iCal

## SMTP

Configurez votre serveur SMTP dans **Paramètres → SMTP** pour recevoir des notifications email pour :
- Nouveaux événements, posts, tâches assignées
- Mises à jour de la garde alternée

## Sécurité

- Authentification par session PHP
- Mots de passe hashés (bcrypt)
- Isolation des familles (chaque famille ne voit que ses données)
- Protection CSRF via vérification de méthode HTTP
- Validation des uploads (type MIME, taille)
