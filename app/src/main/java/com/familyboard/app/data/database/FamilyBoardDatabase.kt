package com.familyboard.app.data.database

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.TypeConverters
import com.familyboard.app.data.database.converters.DateConverter
import com.familyboard.app.data.database.dao.AppointmentDao
import com.familyboard.app.data.database.dao.BudgetItemDao
import com.familyboard.app.data.database.dao.DocumentDao
import com.familyboard.app.data.database.dao.FamilyMemberDao
import com.familyboard.app.data.database.dao.SettingsDao
import com.familyboard.app.data.model.Appointment
import com.familyboard.app.data.model.BudgetItem
import com.familyboard.app.data.model.Document
import com.familyboard.app.data.model.FamilyMember
import com.familyboard.app.data.model.AppSettings

@Database(
    entities = [
        Appointment::class,
        Document::class,
        BudgetItem::class,
        FamilyMember::class,
        AppSettings::class
    ],
    version = 1,
    exportSchema = false
)
@TypeConverters(DateConverter::class)
abstract class FamilyBoardDatabase : RoomDatabase() {
    abstract fun appointmentDao(): AppointmentDao
    abstract fun documentDao(): DocumentDao
    abstract fun budgetItemDao(): BudgetItemDao
    abstract fun familyMemberDao(): FamilyMemberDao
    abstract fun settingsDao(): SettingsDao

    companion object {
        @Volatile
        private var INSTANCE: FamilyBoardDatabase? = null

        fun getDatabase(context: Context): FamilyBoardDatabase {
            return INSTANCE ?: synchronized(this) {
                val instance = Room.databaseBuilder(
                    context.applicationContext,
                    FamilyBoardDatabase::class.java,
                    "familyboard_database"
                )
                    .fallbackToDestructiveMigration()
                    .build()
                INSTANCE = instance
                instance
            }
        }
    }
}