<?php
namespace App\Controllers;

/** Page publique de téléchargement de l'app Android (APK, hors store) et redirection vers la
 *  dernière version publiée sur GitHub Releases (release "android-latest", republiée à chaque
 *  build — voir .github/workflows/android-release.yml). */
class AndroidController extends BaseController
{
    private function apkUrl(): string
    {
        return 'https://github.com/' . GITHUB_REPO . '/releases/download/android-latest/familyboard.apk';
    }

    public function page(array $params): void
    {
        $apkUrl = $this->apkUrl();
        require BASE_PATH . '/templates/android/index.php';
    }

    public function download(array $params): void
    {
        header('Location: ' . $this->apkUrl(), true, 302);
        exit;
    }
}
