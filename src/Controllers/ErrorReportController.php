<?php
namespace App\Controllers;

use App\Core\ErrorReporter;
use App\Core\Session;

class ErrorReportController extends BaseController
{
    /**
     * Beacon appelé automatiquement par le JS (window.onerror / unhandledrejection)
     * dès qu'une erreur technique survient dans le navigateur. Volontairement pas
     * derrière requireAuth() : doit rester silencieux et sans redirection même
     * pour un rôle restreint (coparent) ou une session expirée.
     */
    public function report(array $params): void
    {
        if (!Session::isLoggedIn()) {
            http_response_code(204);
            return;
        }

        $this->json(function () {
            $data    = $this->jsonInput();
            $message = trim((string)($data['message'] ?? ''));
            if (!$message) return ['success' => false];

            ErrorReporter::report('client', mb_strimwidth($message, 0, 500, '…'), [
                'url'  => is_string($data['url'] ?? null) ? mb_strimwidth($data['url'], 0, 300, '…') : null,
                'file' => is_string($data['file'] ?? null) ? mb_strimwidth($data['file'], 0, 300, '…') : null,
                'line' => $data['line'] ?? null,
            ]);

            return ['success' => true];
        });
    }
}
