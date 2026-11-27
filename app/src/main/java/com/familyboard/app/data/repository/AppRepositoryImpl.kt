package com.familyboard.app.data.repository

import android.content.Context
import androidx.lifecycle.LiveData
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import com.familyboard.app.data.database.FamilyBoardDatabase
import com.familyboard.app.data.database.dao.AppointmentDao
import com.familyboard.app.data.database.dao.BudgetItemDao
import com.familyboard.app.data.database.dao.DocumentDao
import com.familyboard.app.data.database.dao.FamilyMemberDao
import com.familyboard.app.data.database.dao.SettingsDao
import com.familyboard.app.data.model.Appointment
import com.familyboard.app.data.model.ApiResponse
import com.familyboard.app.data.model.BudgetItem
import com.familyboard.app.data.model.Document
import com.familyboard.app.data.model.FamilyMember
import com.familyboard.app.data.model.LocationUpdate
import com.familyboard.app.data.model.VersionInfo
import com.familyboard.app.data.network.ApiService
import com.familyboard.app.update.UpdateCheckWorker
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.tasks.await
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File
import java.io.FileOutputStream
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AppRepositoryImpl @Inject constructor(
    private val context: Context,
    private val apiService: ApiService,
    private val database: FamilyBoardDatabase
) : AppRepository {

    private val appointmentDao = database.appointmentDao()
    private val documentDao = database.documentDao()
    private val budgetItemDao = database.budgetItemDao()
    private val familyMemberDao = database.familyMemberDao()
    private val settingsDao = database.settingsDao()

    private val dateFormat = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", Locale.getDefault())

    override suspend fun checkForUpdate(): Result<VersionInfo> {
        return withContext(Dispatchers.IO) {
            try {
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.getLatestVersion("Bearer $token").await()
                if (response.success && response.data != null) {
                    Result.success(response.data!!)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to check update"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun downloadUpdate(downloadUrl: String): Result<String> {
        return withContext(Dispatchers.IO) {
            try {
                val response = apiService.downloadApk(downloadUrl).await()
                if (response.isSuccessful && response.body() != null) {
                    val file = File(context.cacheDir, "familyboard_update_${System.currentTimeMillis()}.apk")
                    val fileOutputStream = FileOutputStream(file)
                    response.body()?.byteStream()?.use { input ->
                        input.copyTo(fileOutputStream)
                    }
                    Result.success(file.absolutePath)
                } else {
                    Result.failure(Exception("Download failed: ${response.code()}"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    // Appointments
    override fun getAppointments(familyId: String): Flow<List<Appointment>> {
        return appointmentDao.getAppointmentsByFamily(familyId)
    }

    override suspend fun createAppointment(appointment: Appointment): Result<Long> {
        return withContext(Dispatchers.IO) {
            try {
                val id = appointmentDao.insert(appointment)
                // Sync to server
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.createAppointment("Bearer $token", appointment).await()
                if (response.success && response.data != null) {
                    appointmentDao.update(response.data!!.copy(synced = true))
                    Result.success(id)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to create appointment"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun updateAppointment(appointment: Appointment): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                appointmentDao.update(appointment)
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.updateAppointment("Bearer $token", appointment.id, appointment).await()
                if (response.success) {
                    appointmentDao.update(appointment.copy(synced = true))
                    Result.success(Unit)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to update appointment"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun deleteAppointment(id: Long): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                appointmentDao.delete(id)
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.deleteAppointment("Bearer $token", id).await()
                if (response.success) {
                    Result.success(Unit)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to delete appointment"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun syncAppointments(familyId: String): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                val unsynced = appointmentDao.getUnsyncedAppointments(familyId)
                if (unsynced.isNotEmpty()) {
                    val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                    for (appt in unsynced) {
                        val response = apiService.createAppointment("Bearer $token", appt).await()
                        if (response.success && response.data != null) {
                            appointmentDao.update(response.data!!.copy(synced = true))
                        }
                    }
                }
                // Pull from server
                val response = apiService.getAppointments("Bearer $token", familyId, null, null).await()
                if (response.success && response.data != null) {
                    appointmentDao.insertAll(response.data!!)
                }
                Result.success(Unit)
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    // Documents
    override fun getDocuments(familyId: String): Flow<List<Document>> {
        return documentDao.getDocumentsByFamily(familyId)
    }

    override suspend fun uploadDocument(title: String, description: String?, familyId: String, filePath: String): Result<Document> {
        return withContext(Dispatchers.IO) {
            try {
                val file = File(filePath)
                val requestFile = file.asRequestBody("application/octet-stream".toMediaType())
                val body = MultipartBody.Part.createFormData("file", file.name, requestFile)
                val titleBody = title.toRequestBody("text/plain".toMediaType())
                val descBody = description?.toRequestBody("text/plain".toMediaType())
                val familyBody = familyId.toRequestBody("text/plain".toMediaType())

                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.uploadDocument("Bearer $token", titleBody, descBody, familyBody, body).await()
                if (response.success && response.data != null) {
                    documentDao.insert(response.data!!)
                    Result.success(response.data!!)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to upload document"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun deleteDocument(id: Long): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                documentDao.delete(id)
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.deleteDocument("Bearer $token", id).await()
                if (response.success) {
                    Result.success(Unit)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to delete document"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun syncDocuments(familyId: String): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.getDocuments("Bearer $token", familyId).await()
                if (response.success && response.data != null) {
                    documentDao.insertAll(response.data!!)
                }
                Result.success(Unit)
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    // Budget
    override fun getBudgetItems(familyId: String): Flow<List<BudgetItem>> {
        return budgetItemDao.getBudgetItemsByFamily(familyId)
    }

    override suspend fun createBudgetItem(item: BudgetItem): Result<Long> {
        return withContext(Dispatchers.IO) {
            try {
                val id = budgetItemDao.insert(item)
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.createBudgetItem("Bearer $token", item).await()
                if (response.success && response.data != null) {
                    budgetItemDao.update(response.data!!.copy(synced = true))
                    Result.success(id)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to create budget item"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun updateBudgetItem(item: BudgetItem): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                budgetItemDao.update(item)
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.updateBudgetItem("Bearer $token", item.id, item).await()
                if (response.success) {
                    budgetItemDao.update(item.copy(synced = true))
                    Result.success(Unit)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to update budget item"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun deleteBudgetItem(id: Long): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                budgetItemDao.delete(id)
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.deleteBudgetItem("Bearer $token", id).await()
                if (response.success) {
                    Result.success(Unit)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to delete budget item"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun getBudgetSummary(familyId: String, fromDate: Long, toDate: Long): Result<BudgetItemDao.BudgetSummary> {
        return withContext(Dispatchers.IO) {
            try {
                val summary = budgetItemDao.getBudgetSummary(familyId, fromDate, toDate)
                Result.success(summary ?: BudgetItemDao.BudgetSummary(0.0, 0.0))
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun syncBudgetItems(familyId: String): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.getBudgetItems("Bearer $token", familyId, null, null).await()
                if (response.success && response.data != null) {
                    budgetItemDao.insertAll(response.data!!)
                }
                Result.success(Unit)
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    // Family Members
    override fun getFamilyMembers(): Flow<List<FamilyMember>> {
        return familyMemberDao.getAllMembers()
    }

    override suspend fun updateMemberLocation(location: LocationUpdate): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                familyMemberDao.updateLocation(location.userId, location.latitude, location.longitude, location.timestamp.time)
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.updateLocation("Bearer $token", location).await()
                if (response.success) {
                    Result.success(Unit)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to update location"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun getFamilyLocations(familyId: String): Result<List<FamilyMember>> {
        return withContext(Dispatchers.IO) {
            try {
                val token = getAuthTokenSync() ?: return@withContext Result.failure(Exception("No auth token"))
                val response = apiService.getFamilyLocations("Bearer $token", familyId).await()
                if (response.success && response.data != null) {
                    familyMemberDao.insertAll(response.data!!)
                    Result.success(response.data!!)
                } else {
                    Result.failure(Exception(response.error ?: "Failed to get locations"))
                }
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun setLocationSharing(userId: String, enabled: Boolean): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                familyMemberDao.setLocationSharing(userId, enabled)
                Result.success(Unit)
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    // Settings
    override suspend fun getSetting(key: String): String? {
        return withContext(Dispatchers.IO) {
            settingsDao.getValue(key)
        }
    }

    override suspend fun saveSetting(key: String, value: String): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                settingsDao.insert(AppSettings(key, value))
                Result.success(Unit)
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun getAuthToken(): String? {
        return withContext(Dispatchers.IO) {
            settingsDao.getValue("auth_token")
        }
    }

    override suspend fun saveAuthToken(token: String): Result<Unit> {
        return saveSetting("auth_token", token)
    }

    override suspend fun clearAuthToken(): Result<Unit> {
        return withContext(Dispatchers.IO) {
            try {
                settingsDao.delete("auth_token")
                Result.success(Unit)
            } catch (e: Exception) {
                Result.failure(e)
            }
        }
    }

    override suspend fun getCurrentFamilyId(): String? {
        return withContext(Dispatchers.IO) {
            settingsDao.getValue("current_family_id")
        }
    }

    override suspend fun setCurrentFamilyId(familyId: String): Result<Unit> {
        return saveSetting("current_family_id", familyId)
    }

    override suspend fun getCurrentUserId(): String? {
        return withContext(Dispatchers.IO) {
            settingsDao.getValue("current_user_id")
        }
    }

    private suspend fun getAuthTokenSync(): String? {
        return withContext(Dispatchers.IO) {
            settingsDao.getValue("auth_token")
        }
    }
}

// Need to add downloadApk to ApiService
// This will be added in the ApiService file