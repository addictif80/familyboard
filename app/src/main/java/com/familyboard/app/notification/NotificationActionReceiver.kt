package com.familyboard.app.notification

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import androidx.core.content.ContextCompat
import com.familyboard.app.location.LocationForegroundService
import com.familyboard.app.update.UpdateCheckWorker

class NotificationActionReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action ?: return
        when (action) {
            FamilyNotificationManager.ACTION_UPDATE_DOWNLOAD -> {
                // Trigger update download
                UpdateCheckWorker.triggerDownload(context)
            }
            FamilyNotificationManager.ACTION_UPDATE_INSTALL -> {
                // Trigger update install
                UpdateCheckWorker.triggerInstall(context)
            }
            FamilyNotificationManager.ACTION_UPDATE_DISMISS -> {
                // Dismiss update notification
                // NotificationManager.cancel(NOTIFICATION_UPDATE)
            }
            else -> {
                // Handle other actions
            }
        }
    }
}

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action ?: return
        when (action) {
            Intent.ACTION_BOOT_COMPLETED, Intent.ACTION_MY_PACKAGE_REPLACED -> {
                // Restart location service if it was running
                val prefs = context.getSharedPreferences("FamilyBoardPrefs", Context.MODE_PRIVATE)
                if (prefs.getBoolean("location_service_running", false)) {
                    val serviceIntent = Intent(context, LocationForegroundService::class.java)
                    serviceIntent.action = LocationForegroundService.ACTION_START
                    ContextCompat.startForegroundService(context, serviceIntent)
                }
                // Schedule periodic update check
                UpdateCheckWorker.schedulePeriodicCheck(context)
            }
        }
    }
}