<?php
namespace App\Core;

/**
 * Rendu unique du "badge" utilisateur (photo si disponible, sinon initiale sur fond de
 * couleur) — centralisé après avoir constaté que chaque endroit du code réimplémentait ce
 * if/else, avec des oublis fréquents du cas "avatar présent" (mur, chat, membres...).
 */
class Avatar
{
    public static function html(?string $avatarPath, ?string $color, ?string $name, string $class = 'user-avatar', ?string $title = null): string
    {
        $class = htmlspecialchars($class);
        $colorAttr = htmlspecialchars($color ?: '#4A90D9');
        $titleAttr = $title !== null ? ' title="' . htmlspecialchars($title) . '"' : '';
        if ($avatarPath) {
            $src = htmlspecialchars(BASE_URL . $avatarPath);
            return "<div class=\"$class\" style=\"background:$colorAttr\"$titleAttr><img src=\"$src\" alt=\"\"></div>";
        }
        $initial = htmlspecialchars(mb_substr((string)$name, 0, 1));
        return "<div class=\"$class\" style=\"background:$colorAttr\"$titleAttr>$initial</div>";
    }
}
