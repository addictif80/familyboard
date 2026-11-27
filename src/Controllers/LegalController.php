<?php
namespace App\Controllers;

use App\Models\LegalContent;

/** Pages publiques (accessibles sans compte) : politique de confidentialité, CGU, FAQ. */
class LegalController extends BaseController
{
    public function privacy(array $params): void
    {
        $title = 'Politique de confidentialité';
        $body = LegalContent::get('privacy');
        require BASE_PATH . '/templates/legal/page.php';
    }

    public function terms(array $params): void
    {
        $title = 'Conditions générales d\'utilisation';
        $body = LegalContent::get('terms');
        require BASE_PATH . '/templates/legal/page.php';
    }

    public function faq(array $params): void
    {
        require BASE_PATH . '/templates/legal/faq.php';
    }
}
