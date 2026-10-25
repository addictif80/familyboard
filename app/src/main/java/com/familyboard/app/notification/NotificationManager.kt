package com.familyboard.app.notification

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.familyboard.app.R
import com.familyboard.app.data.model.Appointment
import com.familyboard.app.data.model.BudgetItem
import com.familyboard.app.data.model.Document
import com.familyboard.app.data.model.VersionInfo
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class FamilyNotificationManager(private val application: Application) {

    private val notificationManager = application.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
    private val compatManager = NotificationManagerCompat.from(application)

    private val CHANNEL_GENERAL = "general_channel"
    private val CHANNEL_LOCATION = "location_channel"
    private val CHANNEL_UPDATES = "updates_channel"

    private val NOTIFICATION_GENERAL = 1000
    private val NOTIFICATION_APPOINTMENT = 1001
    private val NOTIFICATION_DOCUMENT = 1002
    private val NOTIFICATION_BUDGET = 1003
    private val NOTIFICATION_UPDATE = 1004

    init {
        createChannels()
    }

    private fun createChannels() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channels = listOf(
                NotificationChannel(
                    CHANNEL_GENERAL,
                    application.getString(R.string.notification_channel_general),
                    NotificationManager.IMPORTANCE_DEFAULT
                ).apply {
                    description = "General notifications"
                    enableVibration(true)
                },
                NotificationChannel(
                    CHANNEL_LOCATION,
                    application.getString(R.string.notification_channel_location),
                    NotificationManager.IMPORTANCE_LOW
                ).apply {
                    description = "Location sharing notifications"
                    setShowBadge(false)
                },
                NotificationChannel(
                    CHANNEL_UPDATES,
                    application.getString(R.string.notification_channel_updates),
                    NotificationManager.IMPORTANCE_HIGH
                ).apply {
                    description = "App update notifications"
                    enableVibration(true)
                }
            )
            notificationManager.createNotificationChannels(channels)
        }
    }

    fun showAppointmentNotification(appointment: Appointment) {
        val notification = NotificationCompat.Builder(application, CHANNEL_GENERAL)
            .setSmallIcon(R.drawable.ic_calendar_plus)
            .setContentTitle("Nouveau rendez-vous: ${appointment.title}")
            .setContentText(appointment.description ?: "À ${formatDate(appointment.startTime)}")
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setCategory(NotificationCompat.CATEGORY_REMINDER)
            .setAutoCancel(true)
            .build()
        compatManager.notify(NOTIFICATION_APPOINTMENT, notification)
    }

    fun showDocumentNotification(document: Document) {
        val notification = NotificationCompat.Builder(application, CHANNEL_GENERAL)
            .setSmallIcon(R.drawable.ic_document_plus)
            .setContentTitle("Nouveau document: ${document.title}")
            .setContentText(document.description ?: document.fileType)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setCategory(NotificationCompat.CATEGORY_REMINDER)
            .setAutoCancel(true)
            .build()
        compatManager.notify(NOTIFICATION_DOCUMENT, notification)
    }

    fun showBudgetNotification(item: BudgetItem) {
        val typeText = if (item.type == "income") "Revenu" else "Dépense"
        val notification = NotificationCompat.Builder(application, CHANNEL_GENERAL)
            .setSmallIcon(R.drawable.ic_budget_plus)
            .setContentTitle("Nouvel élément budget: ${item.title}")
            .setContentText("$typeText de ${String.format("%.2f", item.amount)} € - ${item.category}")
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setCategory(NotificationCompat.CATEGORY_REMINDER)
            .setAutoCancel(true)
            .build()
        compatManager.notify(NOTIFICATION_BUDGET, notification)
    }

    fun showUpdateNotification(versionInfo: VersionInfo, onDownload: () -> Unit, onDismiss: () -> Unit) {
        // For update notifications, we need to use a different approach since we can't directly
        // pass callbacks. We'll use a broadcast receiver.
        val notification = NotificationCompat.Builder(application, CHANNEL_UPDATES)
            .setSmallIcon(R.drawable.ic_download)
            .setContentTitle(application.getString(R.string.notification_update_available))
            .setContentText(application.getString(R.string.update_available, versionInfo.versionName))
            .setStyle(NotificationCompat.BigTextStyle().bigText(versionInfo.releaseNotes))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_SYSTEM_UPDATE)
            .setAutoCancel(false)
            .setOngoing(versionInfo.forceUpdate)
            .build()
        compatManager.notify(NOTIFICATION_UPDATE, notification)
    }

    fun showDownloadProgress(progress: Int) {
        val notification = NotificationCompat.Builder(application, CHANNEL_UPDATES)
            .setSmallIcon(R.drawable.ic_download)
            .setContentTitle(application.getString(R.string.notification_update_downloading))
            .setContentText("Téléchargement: $progress%")
            .setProgress(100, progress, false)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setOngoing(true)
            .build()
        compatManager.notify(NOTIFICATION_UPDATE, notification)
    }

    fun showUpdateReady(onInstall: () -> Unit) {
        val notification = NotificationCompat.Builder(application, CHANNEL_UPDATES)
            .setSmallIcon(R.drawable.ic_check)
            .setContentTitle(application.getString(R.string.notification_update_ready))
            .setContentText("Appuyez pour installer")
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .build()
        compatManager.notify(NOTIFICATION_UPDATE, notification)
    }

    fun showUpdateFailed() {
        val notification = NotificationCompat.Builder(application, CHANNEL_UPDATES)
            .setSmallIcon(R.drawable.ic_error)
            .setContentTitle(application.getString(R.string.notification_update_failed))
            .setContentText("Échec de la mise à jour. Réessayez plus tard.")
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .build()
        compatManager.notify(NOTIFICATION_UPDATE, notification)
    }

    fun cancelAll() {
        compatManager.cancelAll()
    }

    private fun formatDate(date: java.util.Date): String {
        return android.text.format.DateFormat.getMediumDateFormat(application).format(date)
    }

    companion object {
        const val ACTION_UPDATE_DOWNLOAD = "com.familyboard.app.action.UPDATE_DOWNLOAD"
        const val ACTION_UPDATE_INSTALL = "com.familyboard.app.action.UPDATE_INSTALL"
        const val ACTION_UPDATE_DISMISS = "com.familyboard.app.action.UPDATE_DISMISS"
    }
}