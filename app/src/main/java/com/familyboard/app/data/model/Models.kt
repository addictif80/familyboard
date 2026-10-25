package com.familyboard.app.data.model

import androidx.room.Entity
import androidx.room.PrimaryKey
import androidx.room.TypeConverters
import com.familyboard.app.data.database.converters.DateConverter
import com.google.gson.annotations.SerializedName
import java.util.Date

@Entity(tableName = "appointments")
data class Appointment(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    
    @SerializedName("title")
    val title: String,
    
    @SerializedName("description")
    val description: String? = null,
    
    @SerializedName("start_time")
    @TypeConverters(DateConverter::class)
    val startTime: Date,
    
    @SerializedName("end_time")
    @TypeConverters(DateConverter::class)
    val endTime: Date,
    
    @SerializedName("location")
    val location: String? = null,
    
    @SerializedName("created_by")
    val createdBy: String,
    
    @SerializedName("family_id")
    val familyId: String,
    
    @SerializedName("created_at")
    @TypeConverters(DateConverter::class)
    val createdAt: Date = Date(),
    
    @SerializedName("updated_at")
    @TypeConverters(DateConverter::class)
    val updatedAt: Date = Date(),
    
    @SerializedName("synced")
    val synced: Boolean = false
)

@Entity(tableName = "documents")
data class Document(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    
    @SerializedName("title")
    val title: String,
    
    @SerializedName("description")
    val description: String? = null,
    
    @SerializedName("file_url")
    val fileUrl: String,
    
    @SerializedName("file_type")
    val fileType: String,
    
    @SerializedName("file_size")
    val fileSize: Long,
    
    @SerializedName("uploaded_by")
    val uploadedBy: String,
    
    @SerializedName("family_id")
    val familyId: String,
    
    @SerializedName("created_at")
    @TypeConverters(DateConverter::class)
    val createdAt: Date = Date(),
    
    @SerializedName("updated_at")
    @TypeConverters(DateConverter::class)
    val updatedAt: Date = Date(),
    
    @SerializedName("synced")
    val synced: Boolean = false
)

@Entity(tableName = "budget_items")
data class BudgetItem(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    
    @SerializedName("title")
    val title: String,
    
    @SerializedName("amount")
    val amount: Double,
    
    @SerializedName("category")
    val category: String,
    
    @SerializedName("type")
    val type: String, // "income" or "expense"
    
    @SerializedName("date")
    @TypeConverters(DateConverter::class)
    val date: Date,
    
    @SerializedName("created_by")
    val createdBy: String,
    
    @SerializedName("family_id")
    val familyId: String,
    
    @SerializedName("created_at")
    @TypeConverters(DateConverter::class)
    val createdAt: Date = Date(),
    
    @SerializedName("updated_at")
    @TypeConverters(DateConverter::class)
    val updatedAt: Date = Date(),
    
    @SerializedName("synced")
    val synced: Boolean = false
)

@Entity(tableName = "family_members")
data class FamilyMember(
    @PrimaryKey
    @SerializedName("user_id")
    val userId: String,
    
    @SerializedName("name")
    val name: String,
    
    @SerializedName("email")
    val email: String,
    
    @SerializedName("avatar_url")
    val avatarUrl: String? = null,
    
    @SerializedName("role")
    val role: String, // "admin", "member"
    
    @SerializedName("last_location_lat")
    val lastLocationLat: Double? = null,
    
    @SerializedName("last_location_lng")
    val lastLocationLng: Double? = null,
    
    @SerializedName("last_location_update")
    @TypeConverters(DateConverter::class)
    val lastLocationUpdate: Date? = null,
    
    @SerializedName("location_sharing_enabled")
    val locationSharingEnabled: Boolean = true
)

@Entity(tableName = "app_settings")
data class AppSettings(
    @PrimaryKey
    val key: String,
    
    val value: String
)

data class ApiResponse<T>(
    @SerializedName("success")
    val success: Boolean,
    
    @SerializedName("data")
    val data: T? = null,
    
    @SerializedName("error")
    val error: String? = null,
    
    @SerializedName("message")
    val message: String? = null
)

data class VersionInfo(
    @SerializedName("version_code")
    val versionCode: Int,
    
    @SerializedName("version_name")
    val versionName: String,
    
    @SerializedName("download_url")
    val downloadUrl: String,
    
    @SerializedName("release_notes")
    val releaseNotes: String,
    
    @SerializedName("force_update")
    val forceUpdate: Boolean = false,
    
    @SerializedName("min_supported_version")
    val minSupportedVersion: Int = 1
)

data class LocationUpdate(
    @SerializedName("user_id")
    val userId: String,
    
    @SerializedName("latitude")
    val latitude: Double,
    
    @SerializedName("longitude")
    val longitude: Double,
    
    @SerializedName("accuracy")
    val accuracy: Float,
    
    @SerializedName("timestamp")
    @TypeConverters(DateConverter::class)
    val timestamp: Date,
    
    @SerializedName("battery_level")
    val batteryLevel: Int? = null
)