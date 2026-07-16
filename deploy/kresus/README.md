# Connexion bancaire (Kresus) — guide de déploiement

FamilyBoard peut agréger les transactions bancaires de chaque membre qui le
souhaite, en s'appuyant sur [Kresus](https://kresus.org), un gestionnaire de
finances personnelles auto-hébergé. FamilyBoard ne stocke **jamais** les
identifiants bancaires : ceux-ci restent uniquement dans l'instance Kresus du
membre concerné. FamilyBoard ne connaît que l'URL de cette instance et ses
identifiants Basic Auth (protection d'accès au reverse-proxy, pas la banque).

Les membres qui ne souhaitent pas connecter leur banque peuvent continuer à
utiliser la **saisie assistée** (ajout manuel de transactions avec suggestion
automatique de catégorie) directement dans FamilyBoard — aucune installation
n'est requise pour eux.

## Pourquoi une instance par membre ?

Kresus ne propose pas (encore) de gestion multi-utilisateurs isolée au sein
d'une même instance — chaque instance a un seul jeu de comptes bancaires,
protégé par un Basic Auth posé en amont (reverse-proxy). Pour que chaque
membre de la famille garde ses comptes séparés, il faut donc **un conteneur
Kresus par membre qui se connecte**, chacun avec son propre sous-domaine et
ses propres identifiants.

## 1. Prérequis sur la VM Proxmox

Docker et Docker Compose doivent être installés sur la VM qui héberge déjà
FamilyBoard/CyberPanel :

```bash
curl -fsSL https://get.docker.com | sh
```

## 2. Déployer les instances Kresus

```bash
cd /home/user/familyboard/deploy/kresus
cp config/member.ini.example config/kresus.ini
docker compose up -d
```

Pour chaque nouveau membre : dupliquer un bloc de service dans
`docker-compose.yml` (nouveau nom de container, nouveau port hôte
`127.0.0.1:987X`, nouveaux dossiers `data/`, `woob/`, nouveau fichier
`config/<prenom>.ini`), puis relancer `docker compose up -d`.

Les ports ne sont exposés que sur `127.0.0.1` — ils ne sont accessibles que
via le reverse-proxy configuré à l'étape suivante, jamais directement depuis
Internet.

## 3. Reverse-proxy + sous-domaine par membre (CyberPanel)

Pour chaque membre (exemple avec Alice sur `kresus-alice.mondomaine.tld`) :

1. **Créer le sous-domaine** dans CyberPanel : Websites → Create Website (ou
   Manage → Create Child Domain selon la version), domaine
   `kresus-alice.mondomaine.tld`.
2. **Déclarer une External App** de type *Web Server* pointant vers
   `127.0.0.1:9871` (le port du conteneur d'Alice).
3. Sur le vhost du sous-domaine, ajouter une règle de réécriture qui proxy
   tout le trafic vers cette External App (menu *Rewrite Rules* du site, ou
   directement dans la configuration OpenLiteSpeed du vhost — voir le tuto
   CyberPanel *"How to use OpenLiteSpeed as Reverse Proxy server"* sur leur
   forum communautaire pour le détail des règles).
4. **Activer le SSL** (Let's Encrypt) depuis CyberPanel pour ce sous-domaine
   — FamilyBoard exige une URL en `https://` pour se connecter (vérification
   TLS stricte, pas de certificat auto-signé accepté).
5. **Protéger l'accès par Basic Auth** : dans la configuration de sécurité du
   vhost OpenLiteSpeed (onglet *Security* → *Realm*/*Context*), définir un
   fichier `htpasswd` (`htpasswd -c /usr/local/lsws/conf/vhosts/<vhost>/htpasswd alice`)
   et l'attacher au contexte `/`. Ce sont ces identifiants (login +
   mot de passe du htpasswd, pas ceux de la banque) qu'Alice renseignera
   côté FamilyBoard.

Répéter pour chaque membre avec son propre sous-domaine, son propre port et
son propre fichier `htpasswd`.

## 4. Chaque membre connecte sa banque dans Kresus

Chaque membre se rend sur `https://kresus-<prenom>.mondomaine.tld`
(identifiants Basic Auth requis), puis ajoute sa banque via l'interface
Kresus (identifiants bancaires saisis **uniquement** dans Kresus).

## 5. Connecter FamilyBoard à l'instance

Dans FamilyBoard → Budget → *Connexion bancaire*, chaque membre renseigne :
- l'URL de son instance (`https://kresus-alice.mondomaine.tld`) ;
- l'identifiant et le mot de passe **Basic Auth** (ceux du `htpasswd`, pas
  ceux de sa banque).

FamilyBoard teste la connexion immédiatement, puis synchronise les nouvelles
transactions automatiquement via `cron.php` (voir la tâche cron déjà en place
dans le README principal). Un bouton "Synchroniser maintenant" permet aussi
un déclenchement manuel.

## Limite connue

L'API utilisée (`/api/v1/all`) n'est pas documentée officiellement par
Kresus et peut évoluer sans préavis d'une version à l'autre. Si la
synchronisation automatique échoue après une mise à jour de l'image
`bnjbvr/kresus`, le statut d'erreur s'affiche dans FamilyBoard (Budget →
Connexion bancaire) et dans les logs du cron — le membre concerné peut
toujours saisir ses transactions manuellement en attendant un correctif.
