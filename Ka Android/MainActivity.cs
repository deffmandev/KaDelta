using Android.Webkit;
using Android.Views;

namespace Ka
{
    [Activity(Label = "@string/app_name", MainLauncher = true)]
    public class MainActivity : Activity
    {
        protected override void OnCreate(Bundle? savedInstanceState)
        {
            base.OnCreate(savedInstanceState);

            // Masquer la barre d'action (ActionBar)
            if (ActionBar != null)
                ActionBar.Hide();

            // Plein écran : masquer barre de statut et navigation
            Window.DecorView.SystemUiVisibility = (StatusBarVisibility)
                (SystemUiFlags.ImmersiveSticky | SystemUiFlags.Fullscreen | SystemUiFlags.HideNavigation);

            SetContentView(Resource.Layout.activity_main);

            // Configuration avancée du WebView
            var webView = FindViewById<WebView>(Resource.Id.webView);
            var settings = webView.Settings;
            settings.JavaScriptEnabled = true;
            settings.DomStorageEnabled = true;
            settings.LoadWithOverviewMode = true;
            settings.UseWideViewPort = true;
            settings.AllowFileAccess = true;
            settings.AllowContentAccess = true;
            settings.SetSupportZoom(true);
            settings.BuiltInZoomControls = true;
            settings.DisplayZoomControls = false;

            // Gestionnaire d'erreurs pour le WebView
            webView.SetWebViewClient(new CustomWebViewClient());

            webView.LoadUrl("http://192.168.137.1/index.php");
        }

        private class CustomWebViewClient : WebViewClient
        {
            public override void OnReceivedError(WebView view, IWebResourceRequest request, WebResourceError error)
            {
                view.LoadData($"<h2>Erreur de chargement : {error.Description}</h2>", "text/html", "UTF-8");
            }
        }
    }
}