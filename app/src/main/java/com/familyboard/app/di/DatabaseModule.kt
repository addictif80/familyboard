package com.familyboard.app.di

import android.content.Context
import com.familyboard.app.data.database.FamilyBoardDatabase
import com.familyboard.app.data.repository.AppRepository
import com.familyboard.app.data.repository.AppRepositoryImpl
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object DatabaseModule {

    @Provides
    @Singleton
    fun provideDatabase(@ApplicationContext context: Context): FamilyBoardDatabase {
        return FamilyBoardDatabase.getDatabase(context)
    }

    @Provides
    @Singleton
    fun provideAppRepository(impl: AppRepositoryImpl): AppRepository {
        return impl
    }
}