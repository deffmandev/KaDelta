using Android.App;
using Android.OS;
using Android.Views;
using Android.Webkit;
using Android.Widget;
using Android.Net.Http;
using System;
using System.Threading;
using Android.Content;

namespace KA_Android
{
    [Activity(Label = "@string/app_name", MainLauncher = true, Theme = "@android:style/Theme.NoTitleBar.Fullscreen")]
    public class MainActivity : Activity
    {
        private const string WebUrl = "http://192.168.137.1/index.php";
        private Timer? _retryTimer;
        private bool _isError = false;

        protected override void OnCreate(Bundle? savedInstanceState)
        {
            base.OnCreate(savedInstanceState);
            Window.AddFlags(WindowManagerFlags.Fullscreen);
            ActionBar?.Hide();
            ShowWebView();
        }

        private void ShowWebView()
        {
            SetContentView(Resource.Layout.activity_main);
            var webView = FindViewById<WebView>(Resource.Id.webView);
            webView.Settings.JavaScriptEnabled = true;
            webView.Settings.BuiltInZoomControls = false;
            webView.Settings.DisplayZoomControls = false;
            webView.Settings.SetSupportZoom(false);
            webView.Settings.LoadWithOverviewMode = true;
            webView.Settings.UseWideViewPort = true;
            webView.SetWebViewClient(new CustomWebViewClient(this));
            webView.LoadUrl(WebUrl);
        }

        public void ShowError(string reason)
        {
            if (_isError) return;
            _isError = true;
            SetContentView(Resource.Layout.error_layout);
            var errorMsg = FindViewById<TextView>(Resource.Id.errorMsg);
            if (errorMsg != null)
            {
                errorMsg.Text = $"{reason}\nNouvelle tentative dans 5 secondes...";
            }
            _retryTimer = new Timer(_ => RunOnUiThread(RestartApp), null, 5000, Timeout.Infinite);
        }

        private void RestartApp()
        {
            _retryTimer?.Dispose();
            _isError = false;
            ShowWebView();
        }

        private class CustomWebViewClient : WebViewClient
        {
            private readonly MainActivity _activity;
            public CustomWebViewClient(MainActivity activity) => _activity = activity;

            public override void OnReceivedError(WebView view, IWebResourceRequest request, WebResourceError error)
            {
                _activity.ShowError($"Erreur : {error.Description}");
            }
            public override void OnReceivedHttpError(WebView view, IWebResourceRequest request, WebResourceResponse errorResponse)
            {
                if (errorResponse.StatusCode == 404)
                {
                    string url = request?.Url?.ToString() ?? "(inconnue)";
                    _activity.ShowError($"Erreur 404 : Page non trouvée\nURL : {url}");
                }
                else
                {
                    _activity.ShowError($"Erreur HTTP : {errorResponse.StatusCode}");
                }
            }
            public override void OnReceivedSslError(WebView view, SslErrorHandler handler, SslError error)
            {
                _activity.ShowError($"Erreur SSL : {error.PrimaryError}");
            }
        }
    }
}