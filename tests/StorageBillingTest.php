<?php
/**
 * Tests de la logique critique du stockage perso payant (règles de majorité, agrégation de
 * quota) — aucun framework de test n'existe dans ce projet (pas de PHPUnit), donc ce script
 * autonome suit la même convention que les vérifications ad hoc déjà utilisées pendant le
 * développement : assertions directes, code de sortie non nul en cas d'échec.
 *
 * Usage : php tests/StorageBillingTest.php
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
use App\Core\Majority;
use App\Core\StorageQuota;
use App\Models\MemberStorageSubscription;

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

// ── Majority::isAdult ───────────────────────────────────────────

check('majorité : né il y a 30 ans', Majority::isAdult((new DateTime('-30 years'))->format('Y-m-d')), true);
check('majorité : 17 ans 11 mois', Majority::isAdult((new DateTime('-17 years -11 months'))->format('Y-m-d')), false);
check('majorité : 18 ans - 1 jour (encore mineur)', Majority::isAdult((new DateTime('-18 years +1 day'))->format('Y-m-d')), false);
check('majorité : 18 ans + 1 jour (majeur)', Majority::isAdult((new DateTime('-18 years -1 day'))->format('Y-m-d')), true);
check('majorité : date de naissance absente (null) → bloqué', Majority::isAdult(null), false);
check('majorité : date de naissance vide → bloqué', Majority::isAdult(''), false);
check('majorité : date de naissance invalide → bloqué', Majority::isAdult('pas une date'), false);
check('majorité : hasKnownBirthday absente', Majority::hasKnownBirthday(null), false);
check('majorité : hasKnownBirthday renseignée', Majority::hasKnownBirthday('2000-01-01'), true);

// ── MemberStorageSubscription::totalAddonGbForBeneficiary (DB réelle, nettoyé après coup) ──

$familyId = (int)(Database::fetch('SELECT id FROM families LIMIT 1')['id'] ?? 0);
if ($familyId > 0) {
    $suffix = bin2hex(random_bytes(4));
    $payerId = Database::insert(
        'INSERT INTO users (family_id, name, email, password, role, color) VALUES (?,?,?,?,?,?)',
        [$familyId, 'Test Payeur', "test-payer-$suffix@x.invalid", 'x', 'member', '#000']
    );
    $benId = Database::insert(
        'INSERT INTO users (family_id, name, email, password, role, color) VALUES (?,?,?,?,?,?)',
        [$familyId, 'Test Bénéficiaire', "test-benef-$suffix@x.invalid", 'x', 'member', '#000']
    );

    $products = Database::fetchAll('SELECT id, quota_gb FROM storage_products ORDER BY id LIMIT 3');
    if (count($products) >= 3) {
        $s1 = MemberStorageSubscription::create($benId, $payerId, (int)$products[0]['id']);
        $s2 = MemberStorageSubscription::create($benId, $payerId, (int)$products[1]['id']);
        $s3 = MemberStorageSubscription::create($benId, $payerId, (int)$products[2]['id']);

        $expectedAllActive = (int)$products[0]['quota_gb'] + (int)$products[1]['quota_gb'] + (int)$products[2]['quota_gb'];
        check('quota : 3 abonnements actifs s\'additionnent', MemberStorageSubscription::totalAddonGbForBeneficiary($benId), $expectedAllActive);

        MemberStorageSubscription::cancel($s3);
        $expectedAfterCancel = (int)$products[0]['quota_gb'] + (int)$products[1]['quota_gb'];
        check('quota : un abonnement résilié ne compte plus', MemberStorageSubscription::totalAddonGbForBeneficiary($benId), $expectedAfterCancel);

        MemberStorageSubscription::updateStatus($s1, 'grace', (new DateTime('+30 days'))->format('Y-m-d H:i:s'));
        check('quota : un abonnement en grâce compte toujours', MemberStorageSubscription::totalAddonGbForBeneficiary($benId), $expectedAfterCancel);

        check('quota total membre = gratuit + add-ons', StorageQuota::totalQuotaGbForMember($benId), StorageQuota::freeQuotaGb() + $expectedAfterCancel);

        Database::execute('DELETE FROM member_storage_subscriptions WHERE beneficiary_id = ?', [$benId]);
    } else {
        echo "SKIP  tests de quota (moins de 3 paliers de stockage en base — exécutez add_storage_billing.sql)\n";
    }

    Database::execute('DELETE FROM users WHERE id IN (?,?)', [$payerId, $benId]);
} else {
    echo "SKIP  tests de quota (aucune famille en base pour créer des utilisateurs de test)\n";
}

echo "\n$pass test(s) réussi(s), $failures échec(s).\n";
exit($failures > 0 ? 1 : 0);
