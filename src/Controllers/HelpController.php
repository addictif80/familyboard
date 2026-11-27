<?php
namespace App\Controllers;

use App\Core\HelpGuide;
use App\Core\Session;
use App\Models\Family;

/** Guide d'utilisation : accueil (/aide), une page par chapitre ou module (/aide/:slug) et la
 *  recherche plein texte (/api/help/search). Réservé aux utilisateurs connectés, contrairement
 *  à la FAQ publique qui reste une présentation générale. Le contenu est filtré selon le rôle
 *  (administrateur, membre, ado, accès co-parent) et les modules activés par la famille. */
class HelpController extends BaseController
{
    private function context(): array
    {
        $user = Session::user();
        $disabled = [];
        if (($user['role'] ?? null) !== 'coparent') {
            $family = Family::findById($user['family_id']);
            $disabled = Family::getDisabledModules($family ?? []);
        }
        return [$user, $disabled];
    }

    public function index(array $params): void
    {
        $this->requireAuth(true);
        [$user, $disabled] = $this->context();
        $groups = HelpGuide::visibleGroups($user, $disabled);
        $currentSlug = null;
        require BASE_PATH . '/templates/help/index.php';
    }

    public function chapter(array $params): void
    {
        $this->requireAuth(true);
        [$user, $disabled] = $this->context();
        $slug = (string)($params['slug'] ?? '');
        if (!HelpGuide::exists($slug) || !HelpGuide::isVisible($slug, $user, $disabled)) {
            http_response_code(404);
            require BASE_PATH . '/templates/404.php';
            return;
        }
        $chapter = HelpGuide::load($slug, (string)($user['role'] ?? 'member'));
        $meta = HelpGuide::meta($slug);
        $groups = HelpGuide::visibleGroups($user, $disabled);
        $currentSlug = $slug;
        $isPremium = HelpGuide::isPremium($slug);

        // Chapitre précédent / suivant dans l'ordre du sommaire
        $flat = [];
        foreach ($groups as $g) foreach ($g['items'] as $s) $flat[] = $s;
        $i = array_search($slug, $flat, true);
        $prev = $i > 0 ? $flat[$i - 1] : null;
        $next = ($i !== false && $i < count($flat) - 1) ? $flat[$i + 1] : null;

        require BASE_PATH . '/templates/help/chapter.php';
    }

    public function search(array $params): void
    {
        $this->requireAuth(true);
        [$user, $disabled] = $this->context();
        $q = (string)($_GET['q'] ?? '');
        $this->json(fn () => ['results' => HelpGuide::search($q, $user, $disabled)]);
    }
}
