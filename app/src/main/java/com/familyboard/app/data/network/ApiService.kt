package com.familyboard.app.data.network

import com.familyboard.app.data.model.ApiResponse
import com.familyboard.app.data.model.Appointment
import com.familyboard.app.data.model.BudgetItem
import com.familyboard.app.data.model.Document
import com.familyboard.app.data.model.FamilyMember
import com.familyboard.app.data.model.LocationUpdate
import com.familyboard.app.data.model.VersionInfo
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Part
import retrofit2.http.Path
import retrofit2.http.Query
import retrofit2.http.Streaming
import retrofit2.http.Url
import okhttp3.ResponseBody

interface ApiService {

    @GET("version/latest")
    suspend fun getLatestVersion(
        @Header("Authorization") authToken: String
    ): ApiResponse<VersionInfo>

    // Appointments
    @GET("appointments")
    suspend fun getAppointments(
        @Header("Authorization") authToken: String,
        @Query("family_id") familyId: String,
        @Query("from") fromDate: String?,
        @Query("to") toDate: String?
    ): ApiResponse<List<Appointment>>

    @POST("appointments")
    suspend fun createAppointment(
        @Header("Authorization") authToken: String,
        @Body appointment: Appointment
    ): ApiResponse<Appointment>

    @PUT("appointments/{id}")
    suspend fun updateAppointment(
        @Header("Authorization") authToken: String,
        @Path("id") id: Long,
        @Body appointment: Appointment
    ): ApiResponse<Appointment>

    @retrofit2.http.DELETE("appointments/{id}")
    suspend fun deleteAppointment(
        @Header("Authorization") authToken: String,
        @Path("id") id: Long
    ): ApiResponse<Unit>

    // Documents
    @GET("documents")
    suspend fun getDocuments(
        @Header("Authorization") authToken: String,
        @Query("family_id") familyId: String
    ): ApiResponse<List<Document>>

    @Multipart
    @POST("documents")
    suspend fun uploadDocument(
        @Header("Authorization") authToken: String,
        @Part("title") title: RequestBody,
        @Part("description") description: RequestBody?,
        @Part("family_id") familyId: RequestBody,
        @Part file: MultipartBody.Part
    ): ApiResponse<Document>

    @retrofit2.http.DELETE("documents/{id}")
    suspend fun deleteDocument(
        @Header("Authorization") authToken: String,
        @Path("id") id: Long
    ): ApiResponse<Unit>

    // Budget
    @GET("budget")
    suspend fun getBudgetItems(
        @Header("Authorization") authToken: String,
        @Query("family_id") familyId: String,
        @Query("from") fromDate: String?,
        @Query("to") toDate: String?
    ): ApiResponse<List<BudgetItem>>

    @POST("budget")
    suspend fun createBudgetItem(
        @Header("Authorization") authToken: String,
        @Body item: BudgetItem
    ): ApiResponse<BudgetItem>

    @PUT("budget/{id}")
    suspend fun updateBudgetItem(
        @Header("Authorization") authToken: String,
        @Path("id") id: Long,
        @Body item: BudgetItem
    ): ApiResponse<BudgetItem>

    @retrofit2.http.DELETE("budget/{id}")
    suspend fun deleteBudgetItem(
        @Header("Authorization") authToken: String,
        @Path("id") id: Long
    ): ApiResponse<Unit>

    // Family Members
    @GET("family/members")
    suspend fun getFamilyMembers(
        @Header("Authorization") authToken: String,
        @Query("family_id") familyId: String
    ): ApiResponse<List<FamilyMember>>

    @POST("family/members/location")
    suspend fun updateLocation(
        @Header("Authorization") authToken: String,
        @Body location: LocationUpdate
    ): ApiResponse<Unit>

    @GET("family/members/locations")
    suspend fun getFamilyLocations(
        @Header("Authorization") authToken: String,
        @Query("family_id") familyId: String
    ): ApiResponse<List<FamilyMember>>

    @Streaming
    @GET
    suspend fun downloadApk(
        @Url url: String
    ): retrofit2.Response<ResponseBody>
}