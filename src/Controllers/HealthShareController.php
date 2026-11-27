<?php
namespace App\Controllers;

use App\Models\Family;
use App\Models\FamilyChild;
use App\Models\Health;
use App\Models\HealthShareLink;
use App\Models\User;

/**
 * Page publique (sans compte), lecture seule : le carnet de santé d'UN sujet, pour un lien
 * transmis à un médecin — jamais la famille entière, jamais d'action possible (pas d'édition,
 * pas de suppression). Expire toujours (voir HealthShareLink) ; une fois expiré ou révoqué, le
 * lien affiche la même page "invalide" qu'un token inconnu — aucune information sur la raison,
 * pour ne pas confirmer à un tiers qu'un lien a existé.
 */
class HealthShareController extends BaseController
{
    public function view(array $params): void
    {
        $token = $params['token'] ?? '';
        $link = HealthShareLink::findByToken($token);
        $active = $link && HealthShareLink::isActive($link);

        if (!$active) {
            http_response_code(404);
            require BASE_PATH . '/templates/health/share.php';
            return;
        }

        $familyId = (int)$link['family_id'];
        $family = Family::findById($familyId);
        $subject = $link['subject_type'] === 'child'
            ? FamilyChild::getById((int)$link['subject_id'])
            : User::findById((int)$link['subject_id']);
        $subjectName = $subject['name'] ?? '?';

        $entries = array_values(array_filter(
            Health::getEntries($familyId),
            fn($e) => $e['subject_type'] === $link['subject_type'] && (int)$e['subject_id'] === (int)$link['subject_id']
        ));
        $growth = $link['subject_type'] === 'child' ? Health::getGrowth($familyId, (int)$link['subject_id']) : [];
        $doctors = array_values(array_filter(
            Health::getDoctors($familyId),
            fn($d) => $d['subject_type'] === $link['subject_type'] && (int)$d['subject_id'] === (int)$link['subject_id']
        ));

        require BASE_PATH . '/templates/health/share.php';
    }
}
