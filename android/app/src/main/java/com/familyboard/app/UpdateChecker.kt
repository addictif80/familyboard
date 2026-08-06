package com.familyboard.app

import android.app.AlertDialog
import android.app.DownloadManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.provider.Settings
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.FileProvider
import org.json.JSONObject
import java.io.File
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.Executors

/**
 * Vérifie et installe les mises à jour de l'app en dehors de tout store : compare le
 * versionCode installé au version.json republié à chaque build sur la release GitHub
 * "android-latest" (voir .github/workflows/android-release.yml), propose le téléchargement
 * puis l'installation de l'APK correspondant.
 */
object UpdateChecker {

    private const val VERSION_URL =
        "https://github.com/addictif80/familyboard/releases/download/android-latest/version.json"
    private const val APK_URL =
        "https://github.com/addictif80/familyboard/releases/download/android-latest/familyboard.apk"
    private const val PREFS_NAME = "familyboard_prefs"
    private const val LAST_CHECK_KEY = "last_update_check"
    private const val CHECK_INTERVAL_MS = 24L * 60 * 60 * 1000

    private val executor = Executors.newSingleThreadExecutor()

    /** silent = true : n'affiche rien en l'absence de mise à jour ou en cas d'erreur réseau
     *  (vérification automatique au démarrage, au plus une fois par jour). */
    fun check(activity: AppCompatActivity, silent: Boolean) {
        if (silent) {
            val prefs = activity.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val last = prefs.getLong(LAST_CHECK_KEY, 0)
            if (System.currentTimeMillis() - last < CHECK_INTERVAL_MS) return
            prefs.edit().putLong(LAST_CHECK_KEY, System.currentTimeMillis()).apply()
        }
        executor.execute {
            try {
                val json = fetchVersionJson()
                val remoteVersionCode = json.getInt("versionCode")
                val remoteVersionName = json.optString("version", "?")
                val currentCode = currentVersionCode(activity)
                if (remoteVersionCode > currentCode) {
                    activity.runOnUiThread {
                        if (!activity.isFinishing) promptUpdate(activity, remoteVersionName)
                    }
                } else if (!silent) {
                    activity.runOnUiThread {
                        if (!activity.isFinishing) {
                            Toast.makeText(activity, "Vous avez déjà la dernière version.", Toast.LENGTH_SHORT).show()
                        }
                    }
                }
            } catch (e: Exception) {
                if (!silent) {
                    activity.runOnUiThread {
                        if (!activity.isFinishing) {
                            Toast.makeText(activity, "Impossible de vérifier les mises à jour.", Toast.LENGTH_SHORT).show()
                        }
                    }
                }
            }
        }
    }

    private fun fetchVersionJson(): JSONObject {
        val conn = URL(VERSION_URL).openConnection() as HttpURLConnection
        conn.connectTimeout = 8000
        conn.readTimeout = 8000
        conn.instanceFollowRedirects = true
        conn.inputStream.use { stream ->
            return JSONObject(stream.bufferedReader().readText())
        }
    }

    private fun currentVersionCode(context: Context): Long {
        val info = context.packageManager.getPackageInfo(context.packageName, 0)
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            info.longVersionCode
        } else {
            @Suppress("DEPRECATION")
            info.versionCode.toLong()
        }
    }

    private fun promptUpdate(activity: AppCompatActivity, versionName: String) {
        AlertDialog.Builder(activity)
            .setTitle("Mise à jour disponible")
            .setMessage("La version $versionName de FamilyBoard est disponible. La télécharger et l'installer maintenant ?")
            .setPositiveButton("Télécharger") { _, _ -> startDownload(activity) }
            .setNegativeButton("Plus tard", null)
            .show()
    }

    private fun startDownload(activity: AppCompatActivity) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !activity.packageManager.canRequestPackageInstalls()) {
            Toast.makeText(
                activity,
                "Autorisez l'installation d'applications inconnues pour FamilyBoard, puis relancez la mise à jour depuis le menu ⋮.",
                Toast.LENGTH_LONG
            ).show()
            activity.startActivity(
                Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:" + activity.packageName))
            )
            return
        }

        val destDir = activity.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS) ?: activity.cacheDir
        val destFile = File(destDir, "familyboard-update.apk")
        if (destFile.exists()) destFile.delete()

        val request = DownloadManager.Request(Uri.parse(APK_URL))
            .setTitle("Mise à jour FamilyBoard")
            .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            .setDestinationUri(Uri.fromFile(destFile))

        val dm = activity.getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
        val downloadId = dm.enqueue(request)

        val receiver = object : BroadcastReceiver() {
            override fun onReceive(context: Context, intent: Intent) {
                val id = intent.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1)
                if (id != downloadId) return
                try {
                    activity.unregisterReceiver(this)
                } catch (e: Exception) {
                    // Déjà désenregistré (ex. activité recréée) : sans conséquence.
                }
                installApk(activity, destFile)
            }
        }
        val filter = IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            activity.registerReceiver(receiver, filter, Context.RECEIVER_NOT_EXPORTED)
        } else {
            activity.registerReceiver(receiver, filter)
        }
        Toast.makeText(activity, "Téléchargement de la mise à jour…", Toast.LENGTH_SHORT).show()
    }

    private fun installApk(activity: AppCompatActivity, file: File) {
        val uri = FileProvider.getUriForFile(activity, activity.packageName + ".fileprovider", file)
        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(uri, "application/vnd.android.package-archive")
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        activity.startActivity(intent)
    }
}
