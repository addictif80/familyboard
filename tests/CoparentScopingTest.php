<?php
/**
 * Vérifie le point le plus sensible trouvé lors de l'audit sécurité co-parent (voir
 * CoparentController) : un enregistrement Document/Health/Contact rattaché à un planning de
 * garde A ne doit JAMAIS apparaître dans *ForSchedules() quand on interroge avec un autre
 * planning B — c'est exactement la classe de bug (IDOR par scoping incomplet) trouvée et
 * corrigée cette session. Même convention que tests/StorageBillingTest.php (pas de framework de
 * test dans ce projet) : assertions directes, code de sortie non nul en cas d'échec, nettoyage
 * des données de test après coup.
 *
 * Usage : php tests/CoparentScopingTest.php
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../vendor/autoload.php';
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\Database;
use App\Models\Contact;
use App\Models\Custody;
use App\Models\Document;
use App\Models\Health;

$failures = 0;
$pass = 0;

function check(string $label, $actual, $expected): void
{
    global $failures, $pass;
    if ($actual === $expected) {
        $pass++;
    } else {
        $failures++;
        echo "FAIL  $label — attendu " . var_export($expected, true) . ", obtenu " . var_export($actual, true) . "\n";
    }
}

$familyId = (int)(Database::fetch('SELECT id FROM families LIMIT 1')['id'] ?? 0);
if ($familyId === 0) {
    echo "SKIP  aucune famille en base pour exécuter ce test.\n";
    exit(0);
}

$suffix = bin2hex(random_bytes(4));
$userId = (int)Database::insert(
    'INSERT INTO users (family_id, name, email, password, role, color) VALUES (?,?,?,?,?,?)',
    [$familyId, 'Test Scoping', "test-scoping-$suffix@x.invalid", 'x', 'member', '#000']
);

$scheduleA = Custody::createSchedule($familyId, 'Enfant Test A ' . $suffix);
$scheduleB = Custody::createSchedule($familyId, 'Enfant Test B ' . $suffix);

// ── Document ────────────────────────────────────────────────────
$docId = Document::create($familyId, $userId, [
    'title' => 'Doc test scoping ' . $suffix,
    'doc_type' => 'other',
    'custody_schedule_ids' => [$scheduleA],
]);
$viaA = array_column(Document::getForSchedules([$scheduleA]), 'id');
$viaB = array_column(Document::getForSchedules([$scheduleB]), 'id');
check('Document : visible via son propre planning (A)', in_array($docId, $viaA, true), true);
check('Document : invisible via un autre planning (B)', in_array($docId, $viaB, true), false);

// ── Health (carnet) ─────────────────────────────────────────────
$entryId = Health::addEntry($familyId, $userId, [
    'subject_type' => 'user', 'subject_id' => $userId, 'entry_type' => 'autre',
    'title' => 'Entrée test scoping ' . $suffix, 'description' => null,
    'entry_date' => date('Y-m-d'), 'reminder_date' => null,
    'custody_schedule_ids' => [$scheduleA],
]);
$viaA = array_column(Health::getEntriesForSchedules([$scheduleA]), 'id');
$viaB = array_column(Health::getEntriesForSchedules([$scheduleB]), 'id');
check('Health entry : visible via son propre planning (A)', in_array($entryId, $viaA, true), true);
check('Health entry : invisible via un autre planning (B)', in_array($entryId, $viaB, true), false);

// ── Contact ─────────────────────────────────────────────────────
$contactId = Contact::create($familyId, $userId, [
    'first_name' => 'Contact test scoping ' . $suffix,
    'custody_schedule_ids' => [$scheduleA],
]);
$viaA = array_column(Contact::getForSchedules([$scheduleA]), 'id');
$viaB = array_column(Contact::getForSchedules([$scheduleB]), 'id');
check('Contact : visible via son propre planning (A)', in_array($contactId, $viaA, true), true);
check('Contact : invisible via un autre planning (B)', in_array($contactId, $viaB, true), false);

// ── Nettoyage ─────────────────────────────────────────────────────
Database::execute('DELETE FROM documents WHERE id=?', [$docId]);
Database::execute('DELETE FROM health_entries WHERE id=?', [$entryId]);
Database::execute('DELETE FROM contacts WHERE id=?', [$contactId]);
Database::execute('DELETE FROM custody_schedules WHERE id IN (?,?)', [$scheduleA, $scheduleB]);
Database::execute('DELETE FROM users WHERE id=?', [$userId]);

echo "\n$pass test(s) réussi(s), $failures échec(s).\n";
exit($failures > 0 ? 1 : 0);
