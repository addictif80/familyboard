package com.familyboard.app.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Color.Companion.Black
import androidx.compose.ui.graphics.Color.Companion.White

private val DarkColorScheme = darkColorScheme(
    primary = Color(0xFFBB86FC),
    primaryContainer = Color(0xFF3700B3),
    secondary = Color(0xFF03DAC5),
    secondaryContainer = Color(0xFF018786),
    tertiary = Color(0xFFFF018786),
    background = Color(0xFF121212),
    surface = Color(0xFF1E1E1E),
    error = Color(0xFFCF6679),
    onPrimary = Black,
    onPrimaryContainer = White,
    onSecondary = Black,
    onSecondaryContainer = White,
    onBackground = White,
    onSurface = White,
    onError = Black,
)

private val LightColorScheme = lightColorScheme(
    primary = Color(0xFF6200EE),
    primaryContainer = Color(0xFFEADDFF),
    secondary = Color(0xFF018786),
    secondaryContainer = Color(0xFFA6F1EF),
    tertiary = Color(0xFF6A0B5E),
    background = Color(0xFFFFFBFE),
    surface = Color(0xFFFFFBFE),
    error = Color(0xFFB00020),
    onPrimary = White,
    onPrimaryContainer = Black,
    onSecondary = White,
    onSecondaryContainer = Black,
    onBackground = Black,
    onSurface = Black,
    onError = White,
)

@Composable
fun Theme(
    darkTheme: Boolean = androidx.compose.material3.isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme
    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        shapes = Shapes,
        content = content
    )
}

object Typography {
    // Custom typography if needed
}

object Shapes {
    // Custom shapes if needed
}