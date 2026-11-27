package com.familyboard.app.data.repository

import com.familyboard.app.data.model.Appointment
import com.familyboard.app.data.model.BudgetItem
import com.familyboard.app.data.model.Document
import com.familyboard.app.data.model.FamilyMember
import com.familyboard.app.data.model.LocationUpdate
import com.familyboard.app.data.model.VersionInfo
import kotlinx.coroutines.flow.Flow

interface AppRepository {
    // Version/Update
    suspend fun checkForUpdate(): Result<VersionInfo>
    suspend fun downloadUpdate(downloadUrl: String): Result<String> // Returns local file path

    // Appointments
    fun getAppointments(familyId: String): Flow<List<Appointment>>
    suspend fun createAppointment(appointment: Appointment): Result<Long>
    suspend fun updateAppointment(appointment: Appointment): Result<Unit>
    suspend fun deleteAppointment(id: Long): Result<Unit>
    suspend fun syncAppointments(familyId: String): Result<Unit>

    // Documents
    fun getDocuments(familyId: String): Flow<List<Document>>
    suspend fun uploadDocument(title: String, description: String?, familyId: String, filePath: String): Result<Document>
    suspend fun deleteDocument(id: Long): Result<Unit>
    suspend fun syncDocuments(familyId: String): Result<Unit>

    // Budget
    fun getBudgetItems(familyId: String): Flow<List<BudgetItem>>
    suspend fun createBudgetItem(item: BudgetItem): Result<Long>
    suspend fun updateBudgetItem(item: BudgetItem): Result<Unit>
    suspend fun deleteBudgetItem(id: Long): Result<Unit>
    suspend fun getBudgetSummary(familyId: String, fromDate: Long, toDate: Long): Result<BudgetItemDao.BudgetSummary>
    suspend fun syncBudgetItems(familyId: String): Result<Unit>

    // Family Members
    fun getFamilyMembers(): Flow<List<FamilyMember>>
    suspend fun updateMemberLocation(location: LocationUpdate): Result<Unit>
    suspend fun getFamilyLocations(familyId: String): Result<List<FamilyMember>>
    suspend fun setLocationSharing(userId: String, enabled: Boolean): Result<Unit>

    // Settings
    suspend fun getSetting(key: String): String?
    suspend fun saveSetting(key: String, value: String): Result<Unit>
    suspend fun getAuthToken(): String?
    suspend fun saveAuthToken(token: String): Result<Unit>
    suspend fun clearAuthToken(): Result<Unit>

    // Current user/family
    suspend fun getCurrentFamilyId(): String?
    suspend fun setCurrentFamilyId(familyId: String): Result<Unit>
    suspend fun getCurrentUserId(): String?
}