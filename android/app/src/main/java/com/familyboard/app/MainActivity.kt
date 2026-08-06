package com.familyboard.app

import android.annotation.SuppressLint
import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.net.http.SslError
import android.os.Bundle
import android.view.Menu
import android.view.MenuItem
import android.webkit.CookieManager
import android.webkit.SslErrorHandler
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Button
import android.widget.EditText
import android.widget.ProgressBar
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import androidx.appcompat.widget.Toolbar
import androidx.activity.result.contract.ActivityResultContracts
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout

class MainActivity : AppCompatActivity() {

    private lateinit var prefs: android.content.SharedPreferences
    private lateinit var webView: WebView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private lateinit var progressBar: ProgressBar
    private var filePathCallback: ValueCallback<Array<Uri>>? = null

    private val fileChooserLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        val callback = filePathCallback
        filePathCallback = null
        if (callback == null) return@registerForActivityResult
        val data = result.data
        if (result.resultCode == RESULT_OK && data != null) {
            val uri = data.data
            callback.onReceiveValue(if (uri != null) arrayOf(uri) else null)
        } else {
            callback.onReceiveValue(null)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        prefs = getSharedPreferences("familyboard_prefs", Context.MODE_PRIVATE)

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (::webView.isInitialized && webView.canGoBack()) {
                    webView.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                    isEnabled = true
                }
            }
        })

        val savedUrl = prefs.getString("server_url", null)
        if (savedUrl.isNullOrBlank()) {
            showSetupScreen()
        } else {
            showMainScreen(savedUrl)
        }

        UpdateChecker.check(this, silent = true)
    }

    private fun showSetupScreen() {
        findViewById<android.view.View>(R.id.setupScreen).visibility = android.view.View.VISIBLE
        findViewById<android.view.View>(R.id.mainScreen).visibility = android.view.View.GONE

        val input = findViewById<EditText>(R.id.serverUrlInput)
        val button = findViewById<Button>(R.id.connectButton)
        val submit = {
            val url = normalizeUrl(input.text.toString())
            if (url == null) {
                Toast.makeText(this, R.string.error_invalid_url, Toast.LENGTH_LONG).show()
            } else {
                prefs.edit().putString("server_url", url).apply()
                showMainScreen(url)
            }
        }
        button.setOnClickListener { submit() }
    }

    private fun normalizeUrl(raw: String): String? {
        var url = raw.trim()
        if (url.isEmpty()) return null
        if (!url.startsWith("http://") && !url.startsWith("https://")) {
            url = "https://$url"
        }
        return try {
            val uri = Uri.parse(url)
            if (uri.host.isNullOrBlank()) null else url.trimEnd('/')
        } catch (e: Exception) {
            null
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun showMainScreen(url: String) {
        findViewById<android.view.View>(R.id.setupScreen).visibility = android.view.View.GONE
        findViewById<android.view.View>(R.id.mainScreen).visibility = android.view.View.VISIBLE

        val toolbar = findViewById<Toolbar>(R.id.toolbar)
        setSupportActionBar(toolbar)

        webView = findViewById(R.id.webView)
        swipeRefresh = findViewById(R.id.swipeRefresh)
        progressBar = findViewById(R.id.progressBar)

        val settings: WebSettings = webView.settings
        settings.javaScriptEnabled = true
        settings.domStorageEnabled = true
        settings.databaseEnabled = true
        settings.mixedContentMode = WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE
        settings.mediaPlaybackRequiresUserGesture = false
        settings.cacheMode = WebSettings.LOAD_DEFAULT

        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        val host = Uri.parse(url).host

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val requestHost = request.url.host
                return if (requestHost != null && requestHost != host) {
                    // Lien externe (aide, CalDAV, mailto...) : ouvrir hors de l'appli
                    startActivity(Intent(Intent.ACTION_VIEW, request.url))
                    true
                } else {
                    false
                }
            }

            override fun onPageFinished(view: WebView, url: String?) {
                swipeRefresh.isRefreshing = false
            }

            override fun onReceivedSslError(view: WebView, handler: SslErrorHandler, error: SslError) {
                // Certificats auto-signés fréquents sur les instances auto-hébergées en LAN.
                handler.proceed()
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView, newProgress: Int) {
                progressBar.visibility = if (newProgress in 1..99) android.view.View.VISIBLE else android.view.View.GONE
                progressBar.progress = newProgress
            }

            override fun onShowFileChooser(
                view: WebView,
                callback: ValueCallback<Array<Uri>>,
                params: FileChooserParams
            ): Boolean {
                filePathCallback = callback
                val intent = params.createIntent()
                return try {
                    fileChooserLauncher.launch(intent)
                    true
                } catch (e: Exception) {
                    filePathCallback = null
                    false
                }
            }
        }

        webView.setDownloadListener { downloadUrl, userAgent, contentDisposition, mimeType, _ ->
            try {
                val request = DownloadManager.Request(Uri.parse(downloadUrl))
                request.addRequestHeader("Cookie", CookieManager.getInstance().getCookie(downloadUrl))
                request.addRequestHeader("User-Agent", userAgent)
                request.setMimeType(mimeType)
                val filename = android.webkit.URLUtil.guessFileName(downloadUrl, contentDisposition, mimeType)
                request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                request.setDestinationInExternalPublicDir(android.os.Environment.DIRECTORY_DOWNLOADS, filename)
                val dm = getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
                dm.enqueue(request)
                Toast.makeText(this, "Téléchargement en cours…", Toast.LENGTH_SHORT).show()
            } catch (e: Exception) {
                Toast.makeText(this, "Impossible de télécharger le fichier.", Toast.LENGTH_SHORT).show()
            }
        }

        swipeRefresh.setOnRefreshListener { webView.reload() }

        webView.loadUrl(url)
    }

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        if (!::webView.isInitialized) return false
        menuInflater.inflate(R.menu.main_menu, menu)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            R.id.action_reload -> {
                webView.reload()
                true
            }
            R.id.action_change_server -> {
                prefs.edit().remove("server_url").apply()
                webView.loadUrl("about:blank")
                invalidateOptionsMenu()
                showSetupScreen()
                true
            }
            R.id.action_check_update -> {
                UpdateChecker.check(this, silent = false)
                true
            }
            else -> super.onOptionsItemSelected(item)
        }
    }
}
