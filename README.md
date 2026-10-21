# FamilyBoard - Application Familiale Android

Application Android native (Kotlin + Jetpack Compose) pour la gestion familiale :
- 📅 Rendez-vous partagés
- 📄 Documents familiaux
- 💰 Budget commun
- 📍 Géolocalisation temps réel
- 🔔 Notifications natives
- ⚡ Mise à jour automatique via GitHub Releases

## Fonctionnalités

### 🔄 Mise à jour automatique
- Vérification au démarrage et toutes les 4h
- Téléchargement depuis GitHub Releases
- Installation silencieuse (si permission) ou guidée
- Support des mises à jour forcées

### 🔔 Notifications natives
- Nouveaux rendez-vous
- Nouveaux documents
- Éléments de budget
- Mises à jour disponibles
- Canal de notification dédié par type

### 📱 Raccourcis d'application (Appui long sur l'icône)
- **Ajouter RDV** → Ouvre formulaire rendez-vous
- **Ajouter Document** → Ouvre sélecteur de fichiers
- **Ajouter Budget** → Ouvre formulaire budget
- **Voir Localisation** → Ouvre carte famille

### 📍 Géolocalisation temps réel
- Service avant-plan (foreground service)
- Mise à jour position toutes les 10s (configurable)
- Partage position avec membres famille
- Fonctionne en arrière-plan (permission requise)
- Optimisation batterie

## Architecture

```
app/
├── data/
│   ├── database/          # Room Database
│   │   ├── dao/           # Data Access Objects
│   │   ├── converters/    # Type Converters
│   │   └── FamilyBoardDatabase.kt
│   ├── model/             # Entités & DTOs
│   ├── network/           # Retrofit API
│   └── repository/        # Repository Pattern
├── di/                    # Hilt Modules
├── location/              # Service géolocalisation
├── notification/          # Gestion notifications
├── update/                # Auto-update Worker
├── ui/
│   ├── main/              # MainActivity & ViewModel
│   └── theme/             # Material3 Theme
└── FamilyBoardApplication.kt
```

## Dépendances principales

- **Kotlin 2.0** + **Jetpack Compose**
- **Hilt** (Injection de dépendances)
- **Room** (Base de données locale)
- **Retrofit + OkHttp** (API REST)
- **WorkManager** (Tâches planifiées)
- **Google Play Services Location** (GPS)
- **Material3** (UI)

## Configuration requise

- Android 8.0+ (API 26)
- Permissions : Internet, Localisation (avant/arrière-plan), Notifications
- API REST personnalisée (voir `ApiService.kt`)

## Build & Release

### Build local
```bash
./gradlew assembleRelease
```

### GitHub Actions (CI/CD)
Le workflow `.github/workflows/release.yml` :
1. Build à chaque push sur `main`
2. Crée un Release GitHub avec l'APK
3. Publie les notes de version

### Configuration GitHub Releases
L'application attend un endpoint `/version/latest` retournant :
```json
{
  "success": true,
  "data": {
    "version_code": 2,
    "version_name": "1.1.0",
    "download_url": "https://github.com/user/repo/releases/download/v1.1.0/app-release.apk",
    "release_notes": "Nouvelles fonctionnalités...",
    "force_update": false,
    "min_supported_version": 1
  }
}
```

## Personnalisation

### Changer l'URL de l'API
Modifier `R.string.api_base_url` dans `strings.xml`

### Icônes & Thème
- `res/mipmap/` : Icônes application
- `ui/theme/Theme.kt` : Couleurs Material3
- `res/drawable/` : Icônes raccourcis

### Fréquence mise à jour
Modifier `UpdateCheckWorker.WORK_NAME` intervalle (défaut: 4h)

## Permissions détaillées

| Permission | Utilisation |
|------------|-------------|
| `INTERNET` | Appels API REST |
| `ACCESS_FINE_LOCATION` | GPS précis |
| `ACCESS_BACKGROUND_LOCATION` | Tracking continu |
| `FOREGROUND_SERVICE_LOCATION` | Service localisation |
| `POST_NOTIFICATIONS` | Notifications Android 13+ |
| `RECEIVE_BOOT_COMPLETED` | Redémarrage services |
| `REQUEST_INSTALL_PACKAGES` | Installation APK update |

## Sécurité

- Communication HTTPS uniquement
- Token d'authentification stocké sécurisé (SharedPreferences chiffré recommandé)
- Pas de logs sensibles en release
- ProGuard activé pour release

## Licence

Projet privé - Familial