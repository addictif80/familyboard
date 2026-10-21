package com.familyboard.app.ui.main

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.familyboard.app.data.model.VersionInfo
import com.familyboard.app.data.repository.AppRepository
import com.familyboard.app.update.UpdateCheckWorker
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class MainViewModel @Inject constructor(
    private val repository: AppRepository
) : ViewModel() {

    private val _updateAvailable = MutableStateFlow<VersionInfo?>(null)
    val updateAvailable = _updateAvailable.asStateFlow()

    private val _isLoading = MutableStateFlow(false)
    val isLoading = _isLoading.asStateFlow()

    fun checkForUpdates() {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                val result = repository.checkForUpdate()
                if (result.isSuccess) {
                    val versionInfo = result.getOrNull()
                    if (versionInfo != null) {
                        val currentVersion = getCurrentVersionCode()
                        if (versionInfo.versionCode > currentVersion) {
                            _updateAvailable.value = versionInfo
                        }
                    }
                }
            } catch (e: Exception) {
                // Handle error
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun downloadUpdate(versionInfo: VersionInfo) {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                val result = repository.downloadUpdate(versionInfo.downloadUrl)
                if (result.isSuccess) {
                    val filePath = result.getOrNull()
                    filePath?.let {
                        // Save for install
                        UpdateCheckWorker.triggerInstall(applicationContext)
                    }
                }
            } catch (e: Exception) {
                // Handle error
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun dismissUpdate() {
        _updateAvailable.value = null
    }

    private fun getCurrentVersionCode(): Int {
        return try {
            applicationContext.packageManager.getPackageInfo(applicationContext.packageName, 0).longVersionCode.toInt()
        } catch (e: Exception) {
            1
        }
    }

    private val applicationContext: android.content.Context
        get() = repository.getClass().classLoader.loadClass("com.familyboard.app.FamilyBoardApplication")
            .getDeclaredField("instance").get(null) as android.content.Context
}