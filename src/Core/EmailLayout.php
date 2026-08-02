<?php
namespace App\Core;

/**
 * Fixed visual design shared by every outgoing email — mirrors the app's
 * panel theme (public/css/app.css palette + Fraunces/Inter fonts). This is
 * the only place email markup/CSS lives; it is not exposed to any
 * customization UI, so admins can edit text content without ever touching
 * the graphical style.
 */
class EmailLayout
{
    public static function render(string $title, string $messageHtml, ?array $cta = null, string $extraHtml = ''): string
    {
        $spotlightHtml = self::renderSpotlight();
        $ctaHtml = '';
        if ($cta && !empty($cta['url']) && !empty($cta['label'])) {
            $ctaHtml = '
            <div style="margin:28px 0 8px">
                <a href="' . htmlspecialchars($cta['url'], ENT_QUOTES, 'UTF-8') . '"
                   style="background:#3D5A8A;color:#ffffff;padding:12px 26px;border-radius:10px;
                          text-decoration:none;font-weight:600;font-family:Inter,Arial,sans-serif;
                          font-size:15px;display:inline-block">
                    ' . htmlspecialchars($cta['label'], ENT_QUOTES, 'UTF-8') . '
                </a>
            </div>';
        }

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$safeTitle}</title>
</head>
<body style="margin:0;padding:0;background:#F6F2EA;font-family:Inter,Arial,sans-serif;color:#23201B">
<div style="max-width:560px;margin:0 auto;padding:32px 16px">

    <div style="text-align:center;margin-bottom:24px">
        <span style="font-family:Georgia,'Fraunces',serif;font-size:22px;font-weight:600;color:#3D5A8A">
            🏡 FamilyBoard
        </span>
    </div>

    <div style="background:#FFFFFF;border:1px solid #E6DFD1;border-radius:14px;padding:28px 26px;box-shadow:0 2px 10px rgba(35,32,27,.06)">
        <h1 style="font-family:Georgia,'Fraunces',serif;font-size:20px;font-weight:600;color:#3D5A8A;margin:0 0 16px">
            {$safeTitle}
        </h1>
        <div style="font-size:15px;line-height:1.6;color:#23201B">
            {$messageHtml}
        </div>
        {$extraHtml}
        {$ctaHtml}
    </div>

    {$spotlightHtml}

    <div style="text-align:center;margin-top:20px;font-size:12px;color:#7C7568">
        FamilyBoard — cet email vous a été envoyé automatiquement.
    </div>
</div>
</body>
</html>
HTML;
    }

    /**
     * A fixed-style content box (used for structured details like an event
     * card or a table) — same palette as the rest of the layout.
     */
    public static function box(string $innerHtml): string
    {
        return '<div style="background:#FBF8F2;border-left:4px solid #C99A44;padding:14px 16px;'
             . 'border-radius:8px;margin:18px 0;font-size:14px;line-height:1.6;color:#23201B">'
             . $innerHtml . '</div>';
    }

    /**
     * Mise en avant ABHD dans le pied de chaque e-mail, s'il y en a une active pour cet
     * emplacement — rendue ici (centralisée) plutôt que par chaque appelant, pour qu'elle
     * apparaisse automatiquement sur tous les e-mails sans modifier chaque endroit qui
     * appelle self::render().
     */
    private static function renderSpotlight(): string
    {
        $h = \App\Models\AbhdHighlight::pickForPlacement('email');
        if (!$h) return '';

        $base = self::baseUrl();
        $url = $base . '/highlights/' . (int)$h['id'] . '/go';
        $img = $h['image_path']
            ? '<img src="' . htmlspecialchars($base . $h['image_path'], ENT_QUOTES, 'UTF-8') . '" alt="" style="max-width:100%;height:auto;max-height:60px;display:block;margin:0 auto 8px;border-radius:6px">'
            : '';

        return '
    <div style="margin-top:18px;padding:14px 16px;border:1px solid #E6DFD1;border-radius:10px;text-align:center">
        <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="text-decoration:none;color:#23201B;display:block">
            ' . $img . '
            <strong style="font-size:13px">' . htmlspecialchars($h['title'], ENT_QUOTES, 'UTF-8') . '</strong>
            ' . ($h['description'] ? '<div style="font-size:12px;color:#7C7568;margin-top:2px">' . htmlspecialchars($h['description'], ENT_QUOTES, 'UTF-8') . '</div>' : '') . '
        </a>
    </div>';
    }

    private static function baseUrl(): string
    {
        $envUrl = getenv('APP_URL');
        if ($envUrl) return rtrim($envUrl, '/') . (defined('BASE_URL') ? BASE_URL : '');
        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
            return $scheme . '://' . $_SERVER['HTTP_HOST'] . (defined('BASE_URL') ? BASE_URL : '');
        }
        // Contexte CLI (cron) sans APP_URL configuré : dernier repli, cohérent avec cron.php.
        return 'https://board.abhd.fr' . (defined('BASE_URL') ? BASE_URL : '');
    }
}
