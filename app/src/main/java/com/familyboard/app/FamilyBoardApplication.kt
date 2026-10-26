package com.familyboard.app

import android.app.Application
import android.content.Context
import androidx.work.Configuration
import androidx.work.WorkManager
import com.familyboard.app.data.database.FamilyBoardDatabase
import com.familyboard.app.data.repository.AppRepository
import com.familyboard.app.data.repository.AppRepositoryImpl
import com.familyboard.app.di.NetworkModule
import com.familyboard.app.update.UpdateCheckWorker
import dagger.hilt.android.HiltAndroidApp
import dagger.hilt.android.EntryPointAccessors
import dagger.hilt.android.components.ApplicationComponent
import javax.inject.Inject

@HiltAndroidApp
class FamilyBoardApplication : Application(), Configuration.Provider {

    @Inject
    lateinit var appRepository: AppRepository

    override fun onCreate() {
        super.onCreate()
        // Initialize WorkManager with custom configuration
        WorkManager.initialize(this, this)
        
        // Schedule periodic update check
        UpdateCheckWorker.schedulePeriodicCheck(this)
    }

    override fun getWorkManagerConfiguration(): Configuration {
        return Configuration.Builder()
            .setMinimumLoggingLevel(android.util.Log.DEBUG)
            .build()
    }

    companion object {
        fun getAppComponent(context: Context): ApplicationComponent {
            return EntryPointAccessors.fromApplication(context, ApplicationComponent::class.java)
        }
    }
}