<?php
namespace App\Controllers;

use App\Models\AbhdHighlight;

/**
 * Redirection avec compteur de clic pour une mise en avant ABHD — volontairement publique
 * (pas de requireAuth) : elle doit fonctionner aussi bien depuis un module de l'app que
 * depuis un client mail où le lecteur n'a pas forcément de session active. Sans risque de
 * redirection ouverte puisque l'URL cible est fixée par l'administrateur système, jamais par
 * un paramètre fourni par l'appelant.
 */
class HighlightController extends BaseController
{
    public function go(array $params): void
    {
        $highlight = AbhdHighlight::getById((int)($params['id'] ?? 0));
        if (!$highlight || !$highlight['is_active']) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        AbhdHighlight::registerClick($highlight['id']);
        header('Location: ' . $highlight['url']);
        exit;
    }
}
