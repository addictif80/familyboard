package com.familyboard.app.ui.navigation

import androidx.compose.runtime.Composable
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.familyboard.app.ui.main.MainScreen
import com.familyboard.app.ui.main.MainViewModel
import com.familyboard.app.ui.settings.SettingsScreen
import com.familyboard.app.ui.settings.SettingsViewModel

@Composable
fun AppNavHost() {
    val navController = rememberNavController()
    val startRoute = "main"
    NavHost(navController, startRoute) {
        composable("main") {
            MainScreen(
                viewModel = hiltViewModel<MainViewModel>(),
                onAddAppointment = { /* navigate to appointment */ },
                onAddDocument = { /* navigate to document */ },
                onAddBudget = { /* navigate to budget */ },
                onViewLocation = { /* navigate to location */ }
            )
        }
        composable("settings") {
            SettingsScreen(viewModel = hiltViewModel<SettingsViewModel>())
        }
        composable("appointments") {
            // AppointmentsScreen(viewModel = hiltViewModel<AppointmentsViewModel>())
        }
        composable("documents") {
            // DocumentsScreen(viewModel = hiltViewModel<DocumentsViewModel>())
        }
        composable("budget") {
            // BudgetScreen(viewModel = hiltViewModel<BudgetViewModel>())
        }
        composable("location") {
            // LocationScreen(viewModel = hiltViewModel<LocationViewModel>())
        }
    }
}