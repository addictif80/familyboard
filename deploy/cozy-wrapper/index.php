<?php
/**
 * Relais HTTP minimal pour la création/suppression d'une instance Cozy Cloud — à déployer SUR LE
 * SERVEUR COZY STACK (jamais sur le serveur FamilyBoard). Nécessaire car l'API admin HTTP native
 * de Cozy Stack n'expose PAS la création/suppression d'instance (seule la commande CLI
 * `cozy-stack instances add|destroy`, exécutée localement, le permet) — ce script exécute cette
 * commande pour le compte de FamilyBoard, qui l'appelle par HTTP au lieu d'exiger un accès SSH.
 *
 * Aucune dépendance, un seul fichier. Voir README.md de ce dossier pour le déploiement
 * (nginx/php-fpm ou `php -S`, restriction réseau, jeton partagé).
 *
 * Variables d'environnement requises :
 *   COZY_WRAPPER_TOKEN  jeton partagé (doit correspondre à celui saisi dans /admin côté FamilyBoard)
 *   COZY_STACK_BIN      chemin de l'exécutable cozy-stack (défaut: "cozy-stack", doit être dans le PATH)
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

    $command = escapeshellarg($cozyStackBin) . ' instances add '
        . escapeshellarg($domain) . ' --email ' . escapeshellarg($email)
        . ' --locale fr --tz Europe/Paris 2>&1';
    [$code, $output] = runCommand($command);
    if ($code !== 0) {
        respond(500, ['error' => "Échec de la création de l'instance : " . trim($output)]);
    }

    // Le format exact de la sortie n'a pas pu être vérifié contre une instance réelle avant
    // déploiement (voir README.md) — on tente d'extraire une URL/un jeton d'onboarding si la
    // commande en affiche un ("registerToken=..." dans une URL), sinon registerToken reste absent
    // et FamilyBoard redirige simplement vers https://$domain/ (Cozy affichera alors son propre
    // écran d'onboarding si l'instance n'a pas de mot de passe).
    $registerToken = null;
    if (preg_match('/registerToken=([a-f0-9]+)/i', $output, $m)) {
        $registerToken = $m[1];
    }
    respond(200, ['domain' => $domain, 'registerToken' => $registerToken, 'raw' => $output]);
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
