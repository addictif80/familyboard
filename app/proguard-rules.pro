# Add project specific ProGuard rules here.
# You can control the set of applied configuration files using the
# proguardFiles setting in build.gradle.

# Room
-keep class * extends androidx.room.RoomDatabase
-keep @androidx.room.Database class *
-keep @androidx.room.Entity class *
-keep @androidx.room.Dao class *

# Retrofit/Gson
-keep class com.google.gson.** { *; }
-keep class com.squareup.retrofit2.** { *; }
-keep class okhttp3.** { *; }
-keep class okio.** { *; }

# Hilt
-keep class dagger.hilt.** { *; }
-keep class * extends dagger.hilt.android.HiltAndroidApp
-keep class * extends dagger.hilt.android.HiltViewModel

# Kotlin Coroutines
-keep class kotlinx.coroutines.** { *; }

# WorkManager
-keep class androidx.work.** { *; }

# Google Play Services Location
-keep class com.google.android.gms.location.** { *; }

# Coil
-keep class coil.** { *; }

# Keep model classes
-keep class com.familyboard.app.data.model.** { *; }

# Keep Repository
-keep class com.familyboard.app.data.repository.** { *; }

# Keep ViewModels
-keep class com.familyboard.app.ui.** { *; }

# Keep Hilt generated classes
-keep class dagger.hilt.android.internal.** { *; }