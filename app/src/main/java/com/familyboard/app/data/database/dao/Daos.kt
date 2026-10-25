package com.familyboard.app.data.database.dao

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update
import com.familyboard.app.data.model.Appointment
import com.familyboard.app.data.model.BudgetItem
import com.familyboard.app.data.model.Document
import com.familyboard.app.data.model.FamilyMember
import com.familyboard.app.data.model.AppSettings
import kotlinx.coroutines.flow.Flow

@Dao
interface AppointmentDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(appointments: List<Appointment>): List<Long>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(appointment: Appointment): Long

    @Update
    suspend fun update(appointment: Appointment): Int

    @Query("DELETE FROM appointments WHERE id = :id")
    suspend fun delete(id: Long): Int

    @Query("SELECT * FROM appointments WHERE family_id = :familyId ORDER BY start_time ASC")
    fun getAppointmentsByFamily(familyId: String): Flow<List<Appointment>>

    @Query("SELECT * FROM appointments WHERE family_id = :familyId AND start_time >= :fromDate AND start_time <= :toDate ORDER BY start_time ASC")
    fun getAppointmentsByDateRange(familyId: String, fromDate: Long, toDate: Long): Flow<List<Appointment>>

    @Query("SELECT * FROM appointments WHERE id = :id")
    suspend fun getById(id: Long): Appointment?

    @Query("SELECT * FROM appointments WHERE synced = 0 AND family_id = :familyId")
    suspend fun getUnsyncedAppointments(familyId: String): List<Appointment>

    @Query("UPDATE appointments SET synced = 1 WHERE id IN (:ids)")
    suspend fun markAsSynced(ids: List<Long>): Int
}

@Dao
interface DocumentDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(documents: List<Document>): List<Long>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(document: Document): Long

    @Update
    suspend fun update(document: Document): Int

    @Query("DELETE FROM documents WHERE id = :id")
    suspend fun delete(id: Long): Int

    @Query("SELECT * FROM documents WHERE family_id = :familyId ORDER BY created_at DESC")
    fun getDocumentsByFamily(familyId: String): Flow<List<Document>>

    @Query("SELECT * FROM documents WHERE id = :id")
    suspend fun getById(id: Long): Document?

    @Query("SELECT * FROM documents WHERE synced = 0 AND family_id = :familyId")
    suspend fun getUnsyncedDocuments(familyId: String): List<Document>

    @Query("UPDATE documents SET synced = 1 WHERE id IN (:ids)")
    suspend fun markAsSynced(ids: List<Long>): Int
}

@Dao
interface BudgetItemDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(items: List<BudgetItem>): List<Long>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(item: BudgetItem): Long

    @Update
    suspend fun update(item: BudgetItem): Int

    @Query("DELETE FROM budget_items WHERE id = :id")
    suspend fun delete(id: Long): Int

    @Query("SELECT * FROM budget_items WHERE family_id = :familyId ORDER BY date DESC")
    fun getBudgetItemsByFamily(familyId: String): Flow<List<BudgetItem>>

    @Query("SELECT * FROM budget_items WHERE family_id = :familyId AND date >= :fromDate AND date <= :toDate ORDER BY date DESC")
    fun getBudgetItemsByDateRange(familyId: String, fromDate: Long, toDate: Long): Flow<List<BudgetItem>>

    @Query("SELECT * FROM budget_items WHERE id = :id")
    suspend fun getById(id: Long): BudgetItem?

    @Query("SELECT SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income, SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense FROM budget_items WHERE family_id = :familyId AND date >= :fromDate AND date <= :toDate")
    suspend fun getBudgetSummary(familyId: String, fromDate: Long, toDate: Long): BudgetSummary?

    @Query("SELECT * FROM budget_items WHERE synced = 0 AND family_id = :familyId")
    suspend fun getUnsyncedItems(familyId: String): List<BudgetItem>

    @Query("UPDATE budget_items SET synced = 1 WHERE id IN (:ids)")
    suspend fun markAsSynced(ids: List<Long>): Int

    data class BudgetSummary(
        val income: Double?,
        val expense: Double?
    )
}

@Dao
interface FamilyMemberDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(members: List<FamilyMember>): List<Long>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(member: FamilyMember): Long

    @Update
    suspend fun update(member: FamilyMember): Int

    @Query("SELECT * FROM family_members WHERE user_id = :userId")
    suspend fun getById(userId: String): FamilyMember?

    @Query("SELECT * FROM family_members")
    fun getAllMembers(): Flow<List<FamilyMember>>

    @Query("UPDATE family_members SET last_location_lat = :lat, last_location_lng = :lng, last_location_update = :timestamp WHERE user_id = :userId")
    suspend fun updateLocation(userId: String, lat: Double, lng: Double, timestamp: Long): Int

    @Query("UPDATE family_members SET location_sharing_enabled = :enabled WHERE user_id = :userId")
    suspend fun setLocationSharing(userId: String, enabled: Boolean): Int
}

@Dao
interface SettingsDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(setting: AppSettings): Long

    @Query("SELECT * FROM app_settings WHERE key = :key")
    suspend fun get(key: String): AppSettings?

    @Query("SELECT value FROM app_settings WHERE key = :key")
    suspend fun getValue(key: String): String?

    @Query("DELETE FROM app_settings WHERE key = :key")
    suspend fun delete(key: String): Int
}