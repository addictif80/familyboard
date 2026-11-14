# Relais de provisioning Cozy

À déployer **sur le serveur/conteneur qui héberge Cozy Stack** (jamais sur le serveur
FamilyBoard) — voir `index.php` pour le détail du pourquoi.

## Déploiement rapide (php-fpm + nginx déjà présents, cas standard)

```bash
mkdir -p /opt/cozy-wrapper
cp index.php /opt/cozy-wrapper/
```

Génère un jeton aléatoire et configure les variables d'environnement (à adapter à ton gestionnaire
de service — ici avec un service systemd dédié) :

```bash
openssl rand -hex 32   # → le jeton à coller aussi dans /admin côté FamilyBoard
```

`/etc/systemd/system/cozy-wrapper.service` :

```ini
[Unit]
Description=Relais de provisioning Cozy pour FamilyBoard
After=network.target

[Service]
Environment=COZY_WRAPPER_TOKEN=<le jeton généré ci-dessus>
Environment=COZY_STACK_BIN=/usr/local/bin/cozy-stack
ExecStart=/usr/bin/php -S 127.0.0.1:9000 /opt/cozy-wrapper/index.php
Restart=always
User=cozy

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now cozy-wrapper
```

Puis un reverse-proxy nginx (TLS) qui ne redirige vers `127.0.0.1:9000` QUE pour l'IP du serveur
FamilyBoard (en plus du jeton, défense en profondeur — ce relais peut créer/supprimer des
instances, à ne jamais exposer largement) :

```nginx
server {
    listen 443 ssl;
    server_name cozy-wrapper.abhd.fr;
    # ... certificats TLS ...

    allow <IP du serveur FamilyBoard>;
    deny all;

    location / {
        proxy_pass http://127.0.0.1:9000;
    }
}
```

Côté FamilyBoard (`/admin`, onglet Notifications → section Cozy), renseigne :
- **URL du relais** : `https://cozy-wrapper.abhd.fr`
- **Jeton** : celui généré ci-dessus

## Vérification manuelle avant la première connexion d'une famille

```bash
curl -X POST https://cozy-wrapper.abhd.fr/create \
  -H "Authorization: Bearer <jeton>" -H "Content-Type: application/json" \
  -d '{"domain":"test.cozy.abhd.fr","email":"test@example.com"}'
```

**Important** : le format exact de ce que `cozy-stack instances add` affiche sur sa sortie
standard (notamment un éventuel lien/jeton d'onboarding `registerToken=...`) n'a pas pu être
vérifié contre une instance réelle avant ce déploiement — regarde le champ `raw` de la réponse
JSON pour voir la sortie brute de la commande, et ajuste la regex dans `index.php`
(`registerToken=([a-f0-9]+)`) si le format diffère. Si aucun jeton n'est trouvé, FamilyBoard
redirige simplement vers `https://test.cozy.abhd.fr/`, où Cozy proposera de toute façon son
propre écran d'onboarding si l'instance n'a pas encore de mot de passe.

Nettoie ensuite l'instance de test :

```bash
curl -X POST https://cozy-wrapper.abhd.fr/destroy \
  -H "Authorization: Bearer <jeton>" -H "Content-Type: application/json" \
  -d '{"domain":"test.cozy.abhd.fr"}'
```
