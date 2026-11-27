<?php
namespace App\Models;

/**
 * Configuration globale de l'instance Ollama auto-hébergée (assistant IA — voir App\Core\Ollama),
 * renseignée une seule fois par l'administrateur système (panneau /admin). L'assistant n'apparaît
 * dans l'application qu'une fois cette configuration présente ET le module 'ai_assistant' activé
 * pour la famille (voir Family::MODULES).
 */
class OllamaSettings
{
    public static function get(): ?array
    {
        $url = AppSetting::get('ollama_url');
        $model = AppSetting::get('ollama_model');
        if (!$url || !$model) return null;

        return [
            'url'   => rtrim($url, '/'),
            'model' => $model,
        ];
    }

    public static function save(string $url, string $model): void
    {
        AppSetting::set('ollama_url', rtrim(trim($url), '/'));
        AppSetting::set('ollama_model', trim($model));
    }
}
