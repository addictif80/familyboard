<?php
namespace App\Controllers;

use App\Core\ErrorReporter;
use App\Core\Session;

class ErrorReportController extends BaseController
{
    /**
     * Beacon appelé automatiquement par le JS (window.onerror / unhandledrejection
     * / console.error) dès qu'une erreur technique survient dans le navigateur.
     * Volontairement pas derrière requireAuth() : doit rester silencieux et sans
     * redirection même pour un rôle restreint (coparent) ou une session expirée.
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

    /**
     * Signalement volontaire en un clic ("Signaler un problème"), avec
     * diagnostic technique (navigateur, cache, page…) joint automatiquement —
     * pensé pour un utilisateur non technique qui ne peut/sait pas décrire
     * précisément le bug (ex : "le calendrier ne s'affiche pas").
     */
    public function reportManual(array $params): void
    {
        if (!Session::isLoggedIn()) {
            $this->json(fn() => ['success' => false, 'error' => 'Vous devez être connecté pour envoyer un signalement.']);
            return;
        }

        $this->json(function () {
            $data = $this->jsonInput();
            $description = trim((string)($data['description'] ?? ''));

            $diagnostics = is_array($data['diagnostics'] ?? null) ? $data['diagnostics'] : [];
            $safeDiagnostics = [];
            foreach ($diagnostics as $k => $v) {
                if (!is_string($k) || strlen($k) > 60) continue;
                $safeDiagnostics[$k] = is_scalar($v) ? mb_strimwidth((string)$v, 0, 300, '…') : null;
            }

            ErrorReporter::reportManual(mb_strimwidth($description, 0, 1000, '…'), $safeDiagnostics);
            return ['success' => true];
        });
    }
}
