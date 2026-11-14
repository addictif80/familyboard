<?php
/**
 * Relais HTTP minimal pour la création/suppression d'une instance Cozy Cloud et la génération de
 * jeton OAuth — à déployer SUR LE SERVEUR COZY STACK (jamais sur le serveur FamilyBoard).
 * Nécessaire car l'API admin HTTP native de Cozy Stack n'expose PAS la création/suppression
 * d'instance ni la génération de jeton (seules les commandes CLI `cozy-stack instances
 * add|destroy|token-oauth`, exécutées localement, le permettent) — ce script les exécute pour le
 * compte de FamilyBoard, qui les appelle par HTTP au lieu d'exiger un accès SSH. La génération de
 * jeton via token-oauth évite tout parcours navigateur (lien magique + consentement OAuth) côté
 * famille : la connexion de FamilyBoard à l'instance est entièrement automatique.
 *
 * Aucune dépendance, un seul fichier. Voir README.md de ce dossier pour le déploiement
 * (nginx/php-fpm ou `php -S`, restriction réseau, jeton partagé).
 *
 * Variables d'environnement requises :
 *   COZY_WRAPPER_TOKEN   jeton partagé (doit correspondre à celui saisi dans /admin côté FamilyBoard)
 *   COZY_STACK_BIN       chemin de l'exécutable cozy-stack (défaut: "cozy-stack", doit être dans le PATH)
 *   COZY_ADMIN_PASSWORD  phrase de passe admin cozy-stack (celle choisie à l'installation du paquet) —
 *                        requise pour /magic-link, qui appelle l'API admin HTTP plutôt que la CLI
 *   COZY_ADMIN_URL       base de l'API admin HTTP (défaut: "http://127.0.0.1:6060")
 */

header('Content-Type: application/json');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

$expectedToken = getenv('COZY_WRAPPER_TOKEN');
if (!$expectedToken) {
    respond(500, ['error' => "COZY_WRAPPER_TOKEN n'est pas configuré côté serveur."]);
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . trim((string)$path, '/');

if ($path === '/health') {
    // Pas d'authentification sur /health : utilisé par "Tester la connexion" côté admin
    // FamilyBoard uniquement pour vérifier que le relais répond — mais on exige quand même le
    // jeton, pour détecter une mauvaise config sans ambiguïté plutôt qu'un faux "ok".
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
if (!hash_equals('Bearer ' . $expectedToken, (string)$authHeader)) {
    respond(401, ['error' => 'Jeton invalide.']);
}

if ($path === '/health') {
    respond(200, ['ok' => true]);
}

/**
 * Exécute une commande déjà entièrement échappée et renvoie [code de sortie, stdout, stderr].
 * Entrée standard explicitement fermée tout de suite (EOF immédiat) : sans --passphrase,
 * `cozy-stack instances add` essaie de lire un mot de passe sur stdin via un ioctl de terminal —
 * sans ce pipe fermé proprement, l'appel plante avec "inappropriate ioctl for device" au lieu de
 * recevoir l'EOF qui le fait basculer sur la génération d'un jeton d'inscription.
 */
function runCommand(string $command): array
{
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) return [1, '', 'Impossible de lancer la commande.'];

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return [$code, (string)$stdout, (string)$stderr];
}

/** Un sous-domaine d'instance Cozy ne doit contenir que des caractères DNS valides — rejette
 *  tout le reste avant même d'échapper pour le shell (défense en profondeur). */
function isValidDomain(string $domain): bool
{
    return (bool)preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$cozyStackBin = getenv('COZY_STACK_BIN') ?: 'cozy-stack';

if ($path === '/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = strtolower(trim((string)($input['domain'] ?? '')));
    $email = trim((string)($input['email'] ?? ''));
    if (!isValidDomain($domain) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(400, ['error' => 'domain ou email invalide.']);
    }

    // Sans --passphrase, la commande attend un mot de passe sur l'entrée standard via un ioctl
    // de terminal — impossible à satisfaire depuis un script (constaté en conditions réelles,
    // même avec stdin explicitement fermé : la bibliothèque utilisée exige un vrai TTY, pas
    // seulement une entrée vide). On fournit donc un mot de passe aléatoire jetable, jamais
    // montré ni stocké nulle part, et --magic_link pour que la famille puisse se connecter sans
    // jamais avoir à le connaître (lien de connexion envoyé par e-mail à sa première visite).
    $throwawayPassphrase = bin2hex(random_bytes(16));
    $command = escapeshellarg($cozyStackBin) . ' instances add '
        . escapeshellarg($domain) . ' --email ' . escapeshellarg($email)
        . ' --passphrase ' . escapeshellarg($throwawayPassphrase) . ' --magic_link'
        . ' --locale fr --tz Europe/Paris 2>&1';
    [$code, $output] = runCommand($command);
    if ($code !== 0) {
        respond(500, ['error' => "Échec de la création de l'instance : " . trim($output)]);
    }
    respond(200, ['domain' => $domain, 'raw' => $output]);
}

if ($path === '/token' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = strtolower(trim((string)($input['domain'] ?? '')));
    $clientId = trim((string)($input['client_id'] ?? ''));
    $scope = trim((string)($input['scope'] ?? ''));
    if (!isValidDomain($domain) || $clientId === '' || $scope === '') {
        respond(400, ['error' => 'domain, client_id ou scope invalide.']);
    }

    // Génère le jeton directement côté serveur (CLI dédiée) — jamais via le parcours
    // navigateur /auth/authorize, qui exigerait que la famille s'authentifie interactivement
    // (lien magique + consentement). Durée volontairement longue (10 ans) : pas de notion de
    // refresh token avec cette commande, on en régénère simplement un nouveau si besoin plutôt
    // que de gérer un cycle de rafraîchissement.
    $command = escapeshellarg($cozyStackBin) . ' instances token-oauth '
        . escapeshellarg($domain) . ' ' . escapeshellarg($clientId) . ' ' . escapeshellarg($scope)
        . ' --expire 87600h 2>&1';
    [$code, $output] = runCommand($command);
    if ($code !== 0) {
        respond(500, ['error' => "Échec de la génération du jeton : " . trim($output)]);
    }
    respond(200, ['access_token' => trim($output)]);
}

if ($path === '/magic-link' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = strtolower(trim((string)($input['domain'] ?? '')));
    if (!isValidDomain($domain)) {
        respond(400, ['error' => 'domain invalide.']);
    }

    // Passe par l'API admin HTTP (pas la CLI) : POST /instances/:domain/magic_link génère un
    // code à usage unique, consommé ensuite par GET /auth/magic_link?code=... sur l'instance
    // elle-même — permet d'ouvrir un bouton "Ouvrir mon espace Cozy" connecté d'un seul clic,
    // sans relever un e-mail. Authentification Basic : n'importe quel nom d'utilisateur, seul
    // le mot de passe (COZY_ADMIN_PASSWORD) compte (vérifié dans le code source de cozy-stack).
    $adminPassword = getenv('COZY_ADMIN_PASSWORD');
    if (!$adminPassword) {
        respond(500, ['error' => "COZY_ADMIN_PASSWORD n'est pas configuré côté serveur."]);
    }
    $adminUrl = getenv('COZY_ADMIN_URL') ?: 'http://127.0.0.1:6060';

    $ch = curl_init(rtrim($adminUrl, '/') . '/instances/' . rawurlencode($domain) . '/magic_link');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_USERPWD        => 'admin:' . $adminPassword,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string)$response, true);
    if ($status !== 200 || !is_array($data) || empty($data['code'])) {
        respond(500, ['error' => "Échec de la génération du lien magique (HTTP $status) : " . trim((string)$response)]);
    }
    respond(200, ['login_url' => 'https://' . $domain . '/auth/magic_link?code=' . urlencode($data['code'])]);
}

if ($path === '/destroy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain = strtolower(trim((string)($input['domain'] ?? '')));
    if (!isValidDomain($domain)) {
        respond(400, ['error' => 'domain invalide.']);
    }

    $command = escapeshellarg($cozyStackBin) . ' instances destroy ' . escapeshellarg($domain) . ' --force 2>&1';
    [$code, $output] = runCommand($command);
    if ($code !== 0) {
        respond(500, ['error' => "Échec de la suppression de l'instance : " . trim($output)]);
    }
    respond(200, ['ok' => true]);
}

respond(404, ['error' => 'Route inconnue.']);
