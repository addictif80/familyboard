package com.familyboard.app.update

import android.app.Application
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import androidx.core.content.FileProvider
import androidx.hilt.work.HiltWorker
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import androidx.work.PeriodicWorkRequest
import androidx.work.WorkManager
import androidx.work.ExistingPeriodicWorkPolicy
import com.familyboard.app.data.repository.AppRepository
import com.familyboard.app.notification.FamilyNotificationManager
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.util.concurrent.TimeUnit

@AndroidEntryPoint
@HiltWorker
class UpdateCheckWorker @Inject constructor(
    context: Context,
    params: WorkerParameters,
    private val repository: AppRepository
) : CoroutineWorker(context, params) {

    private val notificationManager by lazy {
        FamilyNotificationManager(applicationContext)
    }

    companion object {
        const val WORK_NAME = "UpdateCheckWorker"
        const val UPDATE_FILE_KEY = "update_file_path"
        const val UPDATE_VERSION_KEY = "update_version_info"

        fun schedulePeriodicCheck(context: Context) {
            val workRequest = PeriodicWorkRequest.Builder(UpdateCheckWorker::class.java, 4, TimeUnit.HOURS)
                .setInitialDelay(15, TimeUnit.MINUTES)
                .build()
            WorkManager.getInstance(context)
                .enqueueUniquePeriodicWork(WORK_NAME, ExistingPeriodicWorkPolicy.KEEP, workRequest)
        }

        fun triggerDownload(context: Context) {
            val workRequest = PeriodicWorkRequest.Builder(UpdateCheckWorker::class.java, 1, TimeUnit.MINUTES)
                .build()
            WorkManager.getInstance(context).enqueue(workRequest)
        }

        fun triggerInstall(context: Context) {
            val prefs = context.getSharedPreferences("FamilyBoardPrefs", Context.MODE_PRIVATE)
            val filePath = prefs.getString(UPDATE_FILE_KEY, null)
            if (filePath != null) {
                installApk(context, filePath)
            }
        }

        private fun installApk(context: Context, filePath: String) {
            val file = File(filePath)
            if (file.exists()) {
                val uri = FileProvider.getUriForFile(
                    context,
                    "${context.packageName}.fileprovider",
                    file
                )
                val intent = Intent(Intent.ACTION_INSTALL_PACKAGE).apply {
                    data = uri
                    flags = Intent.FLAG_GRANT_READ_URI_PERMISSION
                    putExtra(Intent.EXTRA_INSTALLER_PACKAGE_NAME, context.packageName)
                }
                intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                context.startActivity(intent)
            }
        }
    }

    override suspend fun doWork(): Result {
        return withContext(Dispatchers.IO) {
            try {
                val currentVersionCode = getCurrentVersionCode()
                val versionInfo = repository.checkForUpdate().getOrNull()

                if (versionInfo != null && versionInfo.versionCode > currentVersionCode) {
                    if (versionInfo.forceUpdate) {
                        downloadAndNotify(versionInfo)
                    } else {
                        showUpdateNotification(versionInfo)
                    }
                    Result.success()
                } else {
                    Result.success()
                }
            } catch (e: Exception) {
                Result.failure()
            }
        }
    }

    private fun getCurrentVersionCode(): Int {
        return try {
            applicationContext.packageManager.getPackageInfo(applicationContext.packageName, 0).longVersionCode.toInt()
        } catch (e: PackageManager.NameNotFoundException) {
            1
        }
    }

    private suspend fun downloadAndNotify(versionInfo: com.familyboard.app.data.model.VersionInfo) {
        val downloadResult = repository.downloadUpdate(versionInfo.downloadUrl)
        if (downloadResult.isSuccess) {
            val filePath = downloadResult.getOrNull()
            if (filePath != null) {
                val prefs = applicationContext.getSharedPreferences("FamilyBoardPrefs", Context.MODE_PRIVATE)
                prefs.edit().putString(UPDATE_FILE_KEY, filePath).apply()
                notificationManager.showUpdateReady { }
            } else {
                notificationManager.showUpdateFailed()
            }
        } else {
            notificationManager.showUpdateFailed()
        }
    }

    private fun showUpdateNotification(versionInfo: com.familyboard.app.data.model.VersionInfo) {
        notificationManager.showUpdateNotification(versionInfo,
            onDownload = { },
            onDismiss = { }
        )
    }
}