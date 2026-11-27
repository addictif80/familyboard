package com.familyboard.app.ui.settings

import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.Checkbox
import androidx.compose.material3.Column
import androidx.compose.material3.Divider
import androidx.compose.material3.ListItem
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.familyboard.app.R
import com.familyboard.app.ui.theme.Theme.FamilyBoard
import dagger.hilt.android.AndroidEntryPoint
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject

@AndroidEntryPoint
class SettingsActivity : androidx.activity.ComponentActivity() {
    private val viewModel: SettingsViewModel by viewModels()

    override fun onCreate(savedInstanceState: android.os.Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    SettingsScreen(viewModel = viewModel)
                }
            }
        }
    }
}

@HiltViewModel
class SettingsViewModel @Inject constructor(
    private val repository: com.familyboard.app.data.repository.AppRepository
) : androidx.lifecycle.ViewModel() {
    // Settings logic here
}

@Composable
fun SettingsScreen(viewModel: SettingsViewModel) {
    var locationSharingEnabled by remember { mutableStateOf(true) }
    var notificationsEnabled by remember { mutableStateOf(true) }
    var autoUpdateEnabled by remember { mutableStateOf(true) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(stringResource(R.string.settings)) },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.primaryContainer
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier.padding(padding).fillMaxSize(),
            verticalArrangement = androidx.compose.foundation.layout.Arrangement.spacedBy(8.dp)
        ) {
            // Location Section
            SettingsSection(title = "Localisation") {
                SettingsRow(
                    title = "Partage de position",
                    subtitle = "Permettre aux membres de la famille de voir votre position",
                    trailing = {
                        Switch(
                            checked = locationSharingEnabled,
                            onCheckedChange = { locationSharingEnabled = it }
                        )
                    }
                )
                SettingsRow(
                    title = "Précision GPS",
                    subtitle = "Haute précision (consomme plus de batterie)",
                    trailing = {
                        Switch(
                            checked = true,
                            onCheckedChange = { }
                        )
                    }
                )
            }

            // Notifications Section
            SettingsSection(title = "Notifications") {
                SettingsRow(
                    title = "Activer les notifications",
                    subtitle = "Recevoir alertes pour RDV, documents, budget",
                    trailing = {
                        Switch(
                            checked = notificationsEnabled,
                            onCheckedChange = { notificationsEnabled = it }
                        )
                    }
                )
                SettingsRow(
                    title = "Son des notifications",
                    subtitle = "Jouer un son pour les nouvelles notifications",
                    trailing = {
                        Switch(
                            checked = true,
                            onCheckedChange = { }
                        )
                    }
                )
                SettingsRow(
                    title = "Vibration",
                    subtitle = "Vibrer à la réception",
                    trailing = {
                        Switch(
                            checked = true,
                            onCheckedChange = { }
                        )
                    }
                )
            }

            // Updates Section
            SettingsSection(title = "Mises à jour") {
                SettingsRow(
                    title = "Vérification automatique",
                    subtitle = "Vérifier les mises à jour au démarrage",
                    trailing = {
                        Switch(
                            checked = autoUpdateEnabled,
                            onCheckedChange = { autoUpdateEnabled = it }
                        )
                    }
                )
                SettingsRow(
                    title = "Version actuelle",
                    subtitle = "1.0.0 (build 1)",
                    trailing = { }
                )
                SettingsRow(
                    title = "Vérifier maintenant",
                    subtitle = "Lancer une vérification manuelle",
                    trailing = {
                        Button(onClick = { /* viewModel.checkForUpdates() */ }) {
                            Text("Vérifier")
                        }
                    }
                )
            }

            // Account Section
            SettingsSection(title = "Compte") {
                SettingsRow(
                    title = "Famille actuelle",
                    subtitle = "Famille Dupont",
                    trailing = { }
                )
                SettingsRow(
                    title = "Changer de famille",
                    subtitle = "Rejoindre ou créer une nouvelle famille",
                    trailing = {
                        Button(onClick = { }) {
                            Text("Changer")
                        }
                    }
                )
                SettingsRow(
                    title = "Déconnexion",
                    subtitle = "Se déconnecter de l'application",
                    trailing = {
                        Button(onClick = { }) {
                            Text("Déconnexion")
                        }
                    }
                )
            }

            // About Section
            SettingsSection(title = "À propos") {
                SettingsRow(
                    title = "Version de l'application",
                    subtitle = "1.0.0",
                    trailing = { }
                )
                SettingsRow(
                    title = "Licences open source",
                    subtitle = "Voir les licences des bibliothèques utilisées",
                    trailing = { }
                )
                SettingsRow(
                    title = "Confidentialité",
                    subtitle = "Politique de confidentialité",
                    trailing = { }
                )
            }
        }
    }
}

@Composable
fun SettingsSection(title: String, content: @Composable () -> Unit) {
    Column(modifier = Modifier.fillMaxWidth()) {
        Text(
            text = title,
            style = MaterialTheme.typography.labelLarge,
            color = MaterialTheme.colorScheme.primary,
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, top = 16.dp, bottom = 8.dp)
        )
        Card(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 8.dp),
            elevation = androidx.compose.material3.CardDefaults.cardElevation(defaultElevation = 1.dp)
        ) {
            Column(modifier = Modifier.padding(vertical = 8.dp)) {
                content()
            }
        }
    }
}

@Composable
fun SettingsRow(
    title: String,
    subtitle: String,
    trailing: @Composable () -> Unit
) {
    androidx.compose.foundation.layout.Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalArrangement = androidx.compose.foundation.layout.Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column {
            Text(text = title, style = MaterialTheme.typography.bodyLarge)
            Text(text = subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        trailing()
    }
    Divider(
        modifier = Modifier.padding(start = 72.dp, end = 16.dp),
        color = MaterialTheme.colorScheme.outlineVariant
    )
}