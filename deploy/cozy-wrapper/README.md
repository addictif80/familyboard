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

`/etc/systemd/system/cozy-wrapper.service` — si le serveur FamilyBoard est sur le **même
tailnet** (cas recommandé), fait écouter le relais uniquement sur l'IP Tailscale de cette VM :
pas besoin de nginx, de TLS, ni de filtrage par IP devant, le tailnet s'en charge déjà (chiffré de
bout en bout, aucune autre machine ne peut l'atteindre). Récupère l'IP Tailscale de cette VM avec
`tailscale ip -4`.

```ini
[Unit]
Description=Relais de provisioning Cozy pour FamilyBoard
After=network.target tailscaled.service

[Service]
Environment=COZY_WRAPPER_TOKEN=<le jeton généré ci-dessus>
Environment=COZY_STACK_BIN=/usr/local/bin/cozy-stack
ExecStart=/usr/bin/php -S <IP Tailscale de cette VM>:9000 /opt/cozy-wrapper/index.php
Restart=always
User=cozy

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable --now cozy-wrapper
```

Côté FamilyBoard (`/admin`, onglet Notifications → section Cozy), renseigne :
- **URL du relais** : `http://<IP Tailscale de cette VM>:9000` (HTTP simple suffit : le tailnet
  chiffre déjà le transport, inutile d'ajouter un certificat TLS par-dessus)
- **Jeton** : celui généré ci-dessus

<details>
<summary>Si FamilyBoard n'est PAS sur le même tailnet (alternative avec nginx + filtrage par IP)</summary>

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
Dans ce cas, `ExecStart` écoute sur `127.0.0.1:9000` (pas l'IP Tailscale) et l'URL côté FamilyBoard
devient `https://cozy-wrapper.abhd.fr`.
</details>

## Vérification manuelle avant la première connexion d'une famille

```bash
curl -X POST http://<IP Tailscale de cette VM>:9000/create \
  -H "Authorization: Bearer <jeton>" -H "Content-Type: application/json" \
  -d '{"domain":"test.cozy.abhd.fr","email":"test@example.com"}'
```

L'instance est créée avec un mot de passe aléatoire jetable (généré par le relais, jamais montré
ni stocké) et la connexion par lien magique activée (`--magic_link`) : ouvre
`https://test.cozy.abhd.fr/` et utilise "connexion par lien magique" pour entrer sans jamais avoir
à connaître ce mot de passe. Vérifié en conditions réelles — `cozy-stack instances add` sans
`--passphrase` bloque indéfiniment en tentant de lire un mot de passe sur l'entrée standard via un
ioctl de terminal, même avec l'entrée fermée proprement ; passer `--passphrase` directement est la
seule façon fiable de créer une instance de façon non interactive.

Nettoie ensuite l'instance de test :

```bash
curl -X POST https://cozy-wrapper.abhd.fr/destroy \
  -H "Authorization: Bearer <jeton>" -H "Content-Type: application/json" \
  -d '{"domain":"test.cozy.abhd.fr"}'
```
