<?php
namespace App\Models;

/**
 * Configuration globale du fournisseur IA externe 1min.ai (https://1min.ai — API compatible
 * OpenAI), alternative à un serveur Ollama auto-hébergé (voir OllamaSettings) pour les familles
 * qui préfèrent un service géré plutôt que d'héberger leur propre modèle. Renseignée une seule
 * fois par l'administrateur système (panneau /admin).
 */
class OneMinAiSettings
{
    public const DEFAULT_MODEL = 'gpt-4o-mini';

    public static function get(): ?array
    {
        $apiKey = AppSetting::get('onemin_api_key');
        $model = AppSetting::get('onemin_model');
        if (!$apiKey) return null;

        return [
            'api_key' => $apiKey,
            'model'   => $model ?: self::DEFAULT_MODEL,
        ];
    }

    public static function save(string $apiKey, string $model): void
    {
        AppSetting::set('onemin_api_key', trim($apiKey));
        AppSetting::set('onemin_model', trim($model) ?: self::DEFAULT_MODEL);
    }
}
