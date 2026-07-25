<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Family;
use App\Models\OfficialAlert;

class AlertController extends BaseController
{
    public function active(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $family = Family::findById((int)$user['family_id']);
            $city = trim((string)($family['weather_city'] ?? '')) ?: null;
            $alerts = OfficialAlert::getActiveForFamily($city);

            return ['alerts' => array_map(function ($a) {
                $meta = OfficialAlert::CATEGORIES[$a['category']] ?? ['label' => $a['category'], 'icon' => '⚠️'];
                return [
                    'id'          => (int)$a['id'],
                    'category'    => $a['category'],
                    'label'       => $meta['label'],
                    'icon'        => $meta['icon'],
                    'title'       => $a['title'],
                    'summary'     => $a['summary'],
                    'source_name' => $a['source_name'],
                    'source_url'  => $a['source_url'],
                    'created_at'  => $a['created_at'],
                ];
            }, $alerts)];
        });
    }

    /** Page dédiée listant l'historique d'une seule catégorie, ouverte via "S'informer" du bandeau. */
    public function category(array $params): void
    {
        $this->requireAuth(true);
        $category = (string)($params['category'] ?? '');
        $meta = OfficialAlert::CATEGORIES[$category] ?? null;
        if (!$meta) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }

        $user = Session::user();
        $family = Family::findById((int)$user['family_id']);
        $city = trim((string)($family['weather_city'] ?? '')) ?: null;
        $alerts = OfficialAlert::getActiveForFamilyByCategory($category, $city);

        require BASE_PATH . '/templates/alerts/category.php';
    }
}
