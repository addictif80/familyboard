<?php
namespace App\Core;

use App\Models\Family;
use App\Models\MailcowSettings;
use App\Models\User;

/**
 * Client minimal pour l'API d'administration d'une instance Mailcow auto-hébergée
 * (https://github.com/mailcow/mailcow-dockerized), utilisé uniquement pour créer/maintenir un
 * alias e-mail par famille (<slug-famille>@<domaine configuré>) redirigeant vers les adresses
 * e-mail personnelles de ses membres — jamais pour lire des e-mails ou gérer des boîtes.
 *
 * Authentification par clé API (X-API-Key), générée dans Mailcow (Configuration > Access >
 * API). Cette API n'étant pas garantie stable d'une version à l'autre de Mailcow, le bouton
 * "Tester la connexion" du panneau /admin sert à vérifier que ça fonctionne toujours contre
 * l'instance réelle avant de s'y fier pour la synchronisation automatique.
 */
class Mailcow
{
    private const TIMEOUT = 15;

    private static function request(string $method, string $path, ?array $body = null): array
    {
        $settings = MailcowSettings::get();
        if (!$settings) {
            return ['ok' => false, 'status' => 0, 'body' => null];
        }

        $ch = curl_init($settings['url'] . '/api/v1' . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => ['X-API-Key: ' . $settings['api_key'], 'Content-Type: application/json'],
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
        ];
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) return ['ok' => false, 'status' => 0, 'body' => null];
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => json_decode($response, true)];
    }

    /** Vérifie que l'URL/clé API sont valides ET que le domaine configuré existe sur cette
     *  instance (un alias sur un domaine non hébergé par Mailcow échouerait silencieusement). */
    public static function testConnection(): array
    {
        $settings = MailcowSettings::get();
        if (!$settings) {
            return ['ok' => false, 'error' => 'URL, clé API et domaine requis.'];
        }
        $r = self::request('GET', '/get/domain/' . urlencode($settings['domain']));
        if (!$r['ok']) {
            return ['ok' => false, 'error' => "Connexion refusée (HTTP {$r['status']}) — vérifiez l'URL et la clé API."];
        }
        // Mailcow répond souvent en HTTP 200 même pour un domaine introuvable, avec un corps
        // vide ou {"type":"error", ...} plutôt qu'un vrai 404 — les deux sont donc à vérifier.
        $body = $r['body'];
        $isError = empty($body) || (is_array($body) && ($body['type'] ?? null) === 'error');
        if ($isError) {
            return ['ok' => false, 'error' => "Le domaine « {$settings['domain']} » n'est pas configuré sur cette instance Mailcow."];
        }
        return ['ok' => true, 'error' => ''];
    }

    private static function findAliasByAddress(string $address): ?array
    {
        $r = self::request('GET', '/get/alias/all');
        if (!$r['ok'] || !is_array($r['body'])) return null;
        foreach ($r['body'] as $alias) {
            if (($alias['address'] ?? null) === $address) return $alias;
        }
        return null;
    }

    private static function deleteAliasAtAddress(string $address): void
    {
        $existing = self::findAliasByAddress($address);
        if ($existing && !empty($existing['id'])) {
            self::request('POST', '/delete/alias', [(int)$existing['id']]);
        }
    }

    /**
     * Recalcule et pousse la liste de destinataires de l'alias d'une famille à partir de ses
     * membres actuels (co-parents exclus : ils ne partagent pas le foyer). Sans effet si Mailcow
     * n'est pas configuré — appelé partout où la composition d'une famille change, sans
     * conditionner ces flux à la présence de cette intégration. Best-effort : les erreurs réseau
     * ne remontent jamais à l'appelant, seulement au journal.
     */
    public static function syncFamily(int $familyId): void
    {
        try {
            $settings = MailcowSettings::get();
            if (!$settings) return;

            $slug = Family::ensureMailAliasSlug($familyId);
            if (!$slug) return;
            $address = $slug . '@' . $settings['domain'];

            $emails = array_values(array_unique(array_map(
                fn($u) => $u['email'],
                array_filter(User::getByFamily($familyId), fn($u) => $u['role'] !== 'coparent')
            )));

            if (empty($emails)) {
                self::deleteAliasAtAddress($address);
                return;
            }
            $goto = implode(',', $emails);

            $existing = self::findAliasByAddress($address);
            if ($existing && !empty($existing['id'])) {
                self::request('POST', '/edit/alias', ['items' => [(int)$existing['id']], 'attr' => ['goto' => $goto, 'active' => '1']]);
            } else {
                self::request('POST', '/add/alias', ['address' => $address, 'goto' => $goto, 'active' => '1']);
            }
        } catch (\Throwable $e) {
            error_log('Mailcow::syncFamily failed: ' . $e->getMessage());
        }
    }

    /** Supprime l'alias d'une famille (famille supprimée) — best-effort. */
    public static function deleteFamilyAlias(?string $slug): void
    {
        if (!$slug) return;
        try {
            $settings = MailcowSettings::get();
            if (!$settings) return;
            self::deleteAliasAtAddress($slug . '@' . $settings['domain']);
        } catch (\Throwable $e) {
            error_log('Mailcow::deleteFamilyAlias failed: ' . $e->getMessage());
        }
    }

    /**
     * Reconstruit l'alias de toutes les familles contre le domaine actuellement configuré —
     * appelé après l'enregistrement des réglages admin (première configuration, ou changement de
     * domaine : $oldDomain permet de supprimer les anciens alias devenus orphelins). Opération
     * admin peu fréquente, exécutée de façon synchrone (pas de file d'attente dans cette appli).
     */
    public static function resyncAllFamilies(?string $oldDomain = null): void
    {
        $families = \App\Core\Database::fetchAll('SELECT id, mail_alias_slug FROM families');
        foreach ($families as $family) {
            if ($oldDomain && !empty($family['mail_alias_slug'])) {
                self::deleteAliasAtAddress($family['mail_alias_slug'] . '@' . $oldDomain);
            }
            self::syncFamily((int)$family['id']);
        }
    }
}
