buildscript {
    ext {
        compose_version = "1.6.10"
        kotlin_version = "2.0.0"
        agp_version = "8.4.2"
        lifecycle_version = "2.7.0"
        room_version = "2.6.1"
        retrofit_version = "2.11.0"
        okhttp_version = "4.12.0"
        coroutines_version = "1.8.0"
        material_version = "1.12.0"
        activity_version = "1.9.0"
        workmanager_version = "2.9.0"
        notification_version = "1.8.0"
        hilt_version = "2.48"
        navigation_version = "2.7.7"
    }
    dependencies {
        classpath("com.android.tools.build:gradle:$agp_version")
        classpath("org.jetbrains.kotlin:kotlin-gradle-plugin:$kotlin_version")
        classpath("com.google.dagger:hilt-android-gradle-plugin:$hilt_version")
    }
}
allprojects {
    repositories {
        google()
        mavenCentral()
    }
}