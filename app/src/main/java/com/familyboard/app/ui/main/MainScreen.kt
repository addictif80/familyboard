package com.familyboard.app.ui.main

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.livedata.observeAsState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.unit.dp
import com.familyboard.app.R
import com.familyboard.app.data.model.Appointment
import com.familyboard.app.data.model.BudgetItem
import com.familyboard.app.data.model.Document
import com.familyboard.app.data.model.VersionInfo
import com.familyboard.app.ui.theme.Theme.FamilyBoard

@Composable
fun MainScreen(
    viewModel: MainViewModel,
    onAddAppointment: () -> Unit,
    onAddDocument: () -> Unit,
    onAddBudget: () -> Unit,
    onViewLocation: () -> Unit
) {
    val updateAvailable by viewModel.updateAvailable.observeAsState()
    val isLoading by viewModel.isLoading.observeAsState()

    Column(
        modifier = Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        TopAppBar(
            title = { Text(stringResource(R.string.app_name), fontWeight = FontWeight.Bold) },
            colors = TopAppBarDefaults.topAppBarColors(
                containerColor = MaterialTheme.colorScheme.primaryContainer,
                titleContentColor = MaterialTheme.colorScheme.onPrimaryContainer
            ),
            actions = {
                IconButton(onClick = { /* Settings */ }) {
                    Icon(
                        imageVector = androidx.compose.material.icons.Icons.Default.Settings,
                        contentDescription = stringResource(R.string.settings)
                    )
                }
            }
        )

        // Update banner
        updateAvailable?.let { versionInfo ->
            UpdateBanner(versionInfo, onDownload = { viewModel.downloadUpdate(versionInfo) }, onDismiss = { viewModel.dismissUpdate() })
        }

        // Quick Actions Grid
        QuickActionsGrid(
            onAddAppointment = onAddAppointment,
            onAddDocument = onAddDocument,
            onAddBudget = onAddBudget,
            onViewLocation = onViewLocation
        )

        // Content sections
        if (isLoading) {
            CircularProgressIndicator(modifier = Modifier.fillMaxWidth().padding(16.dp))
        } else {
            // Appointments section
            SectionCard(
                title = "Prochains rendez-vous",
                icon = androidx.compose.material.icons.Icons.Default.Event,
                onClick = { /* navigate to appointments */ }
            ) {
                // Show upcoming appointments
                Text("Aucun rendez-vous à venir", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }

            // Documents section
            SectionCard(
                title = "Derniers documents",
                icon = androidx.compose.material.icons.Icons.Default.Description,
                onClick = { /* navigate to documents */ }
            ) {
                Text("Aucun document récent", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }

            // Budget section
            SectionCard(
                title = "Budget du mois",
                icon = androidx.compose.material.icons.Icons.Default.AttachMoney,
                onClick = { /* navigate to budget */ }
            ) {
                Text("Aucune dépense/revenu ce mois", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
fun QuickActionsGrid(
    onAddAppointment: () -> Unit,
    onAddDocument: () -> Unit,
    onAddBudget: () -> Unit,
    onViewLocation: () -> Unit
) {
    androidx.compose.foundation.layout.Row(
        modifier = Modifier.fillMaxWidth().padding(16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        QuickActionButton(
            icon = androidx.compose.material.icons.Icons.Default.Add,
            label = "RDV",
            color = MaterialTheme.colorScheme.primary,
            onClick = onAddAppointment
        )
        QuickActionButton(
            icon = androidx.compose.material.icons.Icons.Default.Add,
            label = "Document",
            color = MaterialTheme.colorScheme.secondary,
            onClick = onAddDocument
        )
        QuickActionButton(
            icon = androidx.compose.material.icons.Icons.Default.Add,
            label = "Budget",
            color = MaterialTheme.colorScheme.tertiary,
            onClick = onAddBudget
        )
        QuickActionButton(
            icon = androidx.compose.material.icons.Icons.Default.LocationOn,
            label = "Position",
            color = MaterialTheme.colorScheme.error,
            onClick = onViewLocation
        )
    }
}

@Composable
fun QuickActionButton(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    color: Color,
    onClick: () -> Unit
) {
    val width = (androidx.compose.ui.unit.dp(100)).toPx()
    androidx.compose.material3.Card(
        modifier = Modifier
            .fillMaxWidth(0.23f)
            .size(100.dp)
            .padding(8.dp),
        onClick = onClick,
        elevation = androidx.compose.material3.CardDefaults.cardElevation(defaultElevation = 4.dp)
    ) {
        Column(
            modifier = Modifier.fillMaxSize().padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            androidx.compose.material3.Icon(
                imageVector = icon,
                contentDescription = null,
                tint = color,
                modifier = Modifier.size(32.dp)
            )
            Text(
                text = label,
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurface
            )
        }
    }
}

@Composable
fun SectionCard(
    title: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    onClick: () -> Unit,
    content: @Composable () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
        onClick = onClick,
        elevation = androidx.compose.material3.CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            androidx.compose.foundation.layout.Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                androidx.compose.foundation.layout.Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(imageVector = icon, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                    Text(text = title, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                }
                Icon(
                    imageVector = androidx.compose.material.icons.Icons.Default.ChevronRight,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            androidx.compose.foundation.layout.Spacer(modifier = Modifier.padding(top = 8.dp))
            content()
        }
    }
}

@Composable
fun UpdateBanner(
    versionInfo: VersionInfo,
    onDownload: () -> Unit,
    onDismiss: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth().padding(16.dp),
        colors = androidx.compose.material3.CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.primaryContainer
        )
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            androidx.compose.foundation.layout.Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text(
                        text = "Mise à jour disponible: v${versionInfo.versionName}",
                        style = MaterialTheme.typography.titleMedium,
                        color = MaterialTheme.colorScheme.onPrimaryContainer
                    )
                    Text(
                        text = versionInfo.releaseNotes,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onPrimaryContainer.copy(alpha = 0.8f)
                    )
                }
                androidx.compose.foundation.layout.Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Button(onClick = onDownload, colors = androidx.compose.material3.ButtonDefaults.buttonColors(
                        containerColor = MaterialTheme.colorScheme.primary
                    )) {
                        Text("Télécharger")
                    }
                    androidx.compose.material3.TextButton(onClick = onDismiss) {
                        Text("Plus tard")
                    }
                }
            }
        }
    }
}