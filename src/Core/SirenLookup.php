<?php
namespace App\Core;

/**
 * Recherche d'une entreprise par SIREN via l'API publique et gratuite du gouvernement français
 * (https://recherche-entreprises.api.gouv.fr, sans clé). Utilisée uniquement pour pré-remplir le
 * nom/l'adresse de l'employeur d'une fiche du module Suivi salarié — jamais bloquant : en cas
 * d'échec (service indisponible, réseau...), l'utilisateur saisit simplement les champs à la main.
 */
class SirenLookup
{
    private const TIMEOUT = 8;
    private const ENDPOINT = 'https://recherche-entreprises.api.gouv.fr/search';

    /** @return array{name:string,address:string}|null */
    public static function find(string $siren): ?array
    {
        $siren = preg_replace('/\D/', '', $siren);
        if (strlen($siren) !== 9) return null;
        if (!function_exists('curl_init')) return null;

        $url = self::ENDPOINT . '?' . http_build_query(['q' => $siren, 'page' => 1, 'per_page' => 1]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status !== 200) return null;

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $result = $data['results'][0] ?? null;
        if (!$result || (string)($result['siren'] ?? '') !== $siren) return null;

        $name = $result['nom_complet'] ?? $result['nom_raison_sociale'] ?? null;
        if (!$name) return null;

        $siege = $result['siege'] ?? [];
        $addressParts = array_filter([
            $siege['numero_voie'] ?? null,
            $siege['type_voie'] ?? null,
            $siege['libelle_voie'] ?? null,
        ]);
        $addressLine = trim(implode(' ', $addressParts));
        $cityParts = array_filter([$siege['code_postal'] ?? null, $siege['libelle_commune'] ?? null]);
        $address = trim(implode(', ', array_filter([$addressLine, implode(' ', $cityParts)])));

        return ['name' => $name, 'address' => $address];
    }
}
