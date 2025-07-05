// See https://aka.ms/new-console-template for more information
using System;
using System.Net.Http;
using System.Threading.Tasks;
using System.Windows.Forms;
using System.Drawing;
using System.Reflection;
using System.IO;

class Program
{
    private static bool allowClose = false;
    [STAThread]
    static async Task Main()
    {
        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);
        // Charger l'icône depuis les ressources embarquées
        Icon icon = null;
        try
        {
            var assembly = typeof(Program).Assembly;
            foreach (var res in assembly.GetManifestResourceNames())
            {
                if (res.EndsWith("App.ico", StringComparison.OrdinalIgnoreCase))
                {
                    using (var stream = assembly.GetManifestResourceStream(res))
                    {
                        if (stream != null)
                            icon = new Icon(stream);
                    }
                    break;
                }
            }
        }
        catch { }
        var form = new Form {
            Text = "Serveur KaDelta",
            Width = 750,
            Height = 500,
            StartPosition = FormStartPosition.CenterScreen,
            FormBorderStyle = FormBorderStyle.FixedDialog,
            MaximizeBox = false,
            MinimizeBox = true,
            Icon = icon ?? SystemIcons.Application
        };
        var textBox = new TextBox { Multiline = true, Dock = DockStyle.Fill, ReadOnly = true, ScrollBars = ScrollBars.Both };
        form.Controls.Add(textBox);

        // Empêcher la fermeture de la fenêtre (même Alt+F4 ou croix), sauf si allowClose
        form.FormClosing += (s, e) =>
        {
            if (!allowClose)
                e.Cancel = true;
        };

        _ = Task.Run(async () =>
        {
            using var httpClient = new HttpClient();
            while (true)
            {
                try
                {
                    var response = await httpClient.GetAsync("http://localhost/srv.php");
                    response.EnsureSuccessStatusCode();
                    var content = await response.Content.ReadAsStringAsync();
                    form.Invoke((Action)(() => textBox.Text = content));
                    if (content.Contains("KilleServeur", StringComparison.OrdinalIgnoreCase))
                    {
                        allowClose = true;
                        form.Invoke((Action)(() => Application.Exit()));
                        break;
                    }
                    if (content.Contains("RebootCompletServeur", StringComparison.OrdinalIgnoreCase))
                    {
                        await Task.Delay(500);
                        System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo
                        {
                            FileName = "shutdown",
                            Arguments = "/r /t 0",
                            CreateNoWindow = true,
                            UseShellExecute = false
                        });
                        allowClose = true;
                        form.Invoke((Action)(() => Application.Exit()));
                        break;
                    }
                }
                catch (HttpRequestException ex)
                {
                    await LogError($"Erreur réseau HTTP : {ex.Message}");
                    form.Invoke((Action)(() => textBox.Text = $"Erreur réseau HTTP : {ex.Message}"));
                    await Task.Delay(10000); // Pause de 10s en cas d'erreur réseau
                }
                catch (TaskCanceledException ex)
                {
                    await LogError($"Délai d'attente dépassé : {ex.Message}");
                    form.Invoke((Action)(() => textBox.Text = $"Délai d'attente dépassé : {ex.Message}"));
                    await Task.Delay(10000); // Pause de 10s en cas d'erreur réseau
                }
                catch (Exception ex)
                {
                    await LogError($"Erreur inattendue : {ex.Message}");
                    form.Invoke((Action)(() => textBox.Text = $"Erreur inattendue : {ex.Message}"));
                    await Task.Delay(10000); // Pause de 10s en cas d'erreur réseau
                }
                // Suppression du délai entre les requêtes
            }
        });

        // Fonction de log
        static async Task LogError(string message)
        {
            try
            {
                var logPath = System.IO.Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "AServeur.log");
                await System.IO.File.AppendAllTextAsync(logPath, $"[{DateTime.Now:dd-MM-yyyy HH:mm:ss}] {message}\r\n");
            }
            catch { /* Ignorer les erreurs de log */ }
        }

        var quitButton = new Button
        {
            Text = "Quitter",
            Width = 120,
            Height = 35,
            Anchor = AnchorStyles.Bottom | AnchorStyles.Right
        };
        quitButton.Click += (s, e) =>
        {
            var result = MessageBox.Show("Voulez-vous vraiment quitter le Serveur KaDelta ?", "Confirmation", MessageBoxButtons.YesNo, MessageBoxIcon.Question);
            if (result == DialogResult.Yes)
            {
                allowClose = true;
                form.Close();
            }
        };

        var logButton = new Button
        {
            Text = "Afficher le journal",
            Width = 120,
            Height = 35,
            Anchor = AnchorStyles.Bottom | AnchorStyles.Right
        };
        logButton.Click += (s, e) =>
        {
            var logPath = System.IO.Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "AServeur.log");
            var logForm = new Form
            {
                Text = "Journal des erreurs",
                Width = 1200,
                Height = 500,
                StartPosition = FormStartPosition.CenterScreen,
                FormBorderStyle = FormBorderStyle.FixedDialog,
                MaximizeBox = false,
                MinimizeBox = true
            };
            var logTextBox = new TextBox
            {
                Multiline = true,
                ReadOnly = true,
                Dock = DockStyle.Fill,
                ScrollBars = ScrollBars.Both,
                Font = new System.Drawing.Font("Consolas", 8)
            };
            logForm.Controls.Add(logTextBox);

            string lastLog = null;
            void LoadLog()
            {
                if (System.IO.File.Exists(logPath))
                {
                    var text = System.IO.File.ReadAllText(logPath);
                    if (text != lastLog)
                    {
                        logTextBox.Text = text;
                        logTextBox.SelectionStart = logTextBox.Text.Length;
                        logTextBox.ScrollToCaret();
                        lastLog = text;
                    }
                }
                else
                {
                    logTextBox.Text = "Aucun journal disponible.";
                    lastLog = null;
                }
            }
            var timer = new System.Windows.Forms.Timer { Interval = 1000 };
            timer.Tick += (sender2, e2) =>
            {
                if (!logForm.IsDisposed && logForm.Visible)
                    LoadLog();
            };
            timer.Start();
            logForm.FormClosed += (sender2, e2) => timer.Stop();

            LoadLog();
            logForm.ShowDialog(form);
        };

        var effaceLogButton = new Button
        {
            Text = "Effacer le journal",
            Width = 120,
            Height = 35,
            Anchor = AnchorStyles.Bottom | AnchorStyles.Right
        };
        effaceLogButton.Click += (s, e) =>
        {
            var logPath = System.IO.Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "AServeur.log");
            var result = MessageBox.Show(form, "Voulez-vous vraiment effacer le journal ?", "Confirmation", MessageBoxButtons.YesNo, MessageBoxIcon.Warning);
            if (result == DialogResult.Yes)
            {
                try
                {
                    if (System.IO.File.Exists(logPath))
                        System.IO.File.Delete(logPath);
                }
                catch (Exception ex)
                {
                    MessageBox.Show(form, $"Erreur lors de l'effacement : {ex.Message}", "Erreur", MessageBoxButtons.OK, MessageBoxIcon.Error);
                }
            }
        };

        var creditButton = new Button
        {
            Text = "Crédits",
            Width = 120,
            Height = 35,
            Anchor = AnchorStyles.Bottom | AnchorStyles.Right
        };
        creditButton.Click += (s, e) =>
        {
            var creditForm = new Form
            {
                Text = "Crédits",
                Width = 520,
                Height = 240,
                StartPosition = FormStartPosition.CenterParent,
                FormBorderStyle = FormBorderStyle.FixedDialog,
                MaximizeBox = false,
                MinimizeBox = false
            };
            var mainPanel = new Panel { Dock = DockStyle.Fill };
            PictureBox logo = null;
            try
            {
                var assembly = typeof(Program).Assembly;
                foreach (var res in assembly.GetManifestResourceNames())
                {
                    if (res.EndsWith("LogoKaDelta.png", StringComparison.OrdinalIgnoreCase))
                    {
                        using (var stream = assembly.GetManifestResourceStream(res))
                        {
                            if (stream != null)
                                logo = new PictureBox
                                {
                                    Image = Image.FromStream(stream),
                                    SizeMode = PictureBoxSizeMode.Zoom,
                                    Width = 100,
                                    Height = 100,
                                    Anchor = AnchorStyles.Left
                                };
                        }
                        break;
                    }
                }
            }
            catch { }
            var label = new Label
            {
                Text = "Développement Serveur KaDelta x64\nFrédéric DUSSERRE\nVersion 1.0.1 du 05/07/2025",
                AutoSize = false,
                TextAlign = ContentAlignment.MiddleCenter,
                Dock = DockStyle.Fill,
                Font = new Font("Segoe UI", 12, FontStyle.Bold)
            };
            var contentPanel = new Panel { Dock = DockStyle.Fill };
            if (logo != null)
            {
                logo.Location = new Point(20, (contentPanel.Height - logo.Height) / 2);
                logo.Anchor = AnchorStyles.Left;
                contentPanel.Controls.Add(logo);
                label.Padding = new Padding(140, 0, 0, 0);
            }
            contentPanel.Controls.Add(label);
            contentPanel.Resize += (s2, e2) =>
            {
                if (logo != null)
                    logo.Location = new Point(20, (contentPanel.Height - logo.Height) / 2);
            };
            var closeButton = new Button
            {
                Text = "Fermer",
                Width = 100,
                Height = 40,
                Anchor = AnchorStyles.None
            };
            closeButton.Click += (s2, e2) => creditForm.Close();
            var panelBtn = new Panel { Height = 60, Dock = DockStyle.Bottom };
            panelBtn.Controls.Add(closeButton);
            closeButton.Location = new Point((panelBtn.Width - closeButton.Width) / 2, 10);
            panelBtn.Resize += (s2, e2) =>
            {
                closeButton.Location = new Point((panelBtn.Width - closeButton.Width) / 2, 10);
            };
            mainPanel.Controls.Add(contentPanel);
            mainPanel.Controls.Add(panelBtn);
            creditForm.Controls.Add(mainPanel);
            creditForm.ShowDialog(form);
        };

        var panel = new Panel
        {
            Dock = DockStyle.Bottom,
            Height = 50
        };
        panel.Controls.Add(quitButton);
        panel.Controls.Add(logButton);
        panel.Controls.Add(effaceLogButton);
        panel.Controls.Add(creditButton);
        // Positionner les boutons côte à côte en bas à droite
        quitButton.Location = new System.Drawing.Point(panel.Width - quitButton.Width - 20, 7);
        creditButton.Location = new System.Drawing.Point(panel.Width - quitButton.Width - creditButton.Width - 30, 7);
        logButton.Location = new System.Drawing.Point(panel.Width - quitButton.Width - creditButton.Width - logButton.Width - 40, 7);
        effaceLogButton.Location = new System.Drawing.Point(panel.Width - quitButton.Width - creditButton.Width - logButton.Width - effaceLogButton.Width - 50, 7);
        panel.Resize += (s, e) =>
        {
            quitButton.Location = new System.Drawing.Point(panel.Width - quitButton.Width - 20, 7);
            creditButton.Location = new System.Drawing.Point(panel.Width - quitButton.Width - creditButton.Width - 30, 7);
            logButton.Location = new System.Drawing.Point(panel.Width - quitButton.Width - creditButton.Width - logButton.Width - 40, 7);
            effaceLogButton.Location = new System.Drawing.Point(panel.Width - quitButton.Width - creditButton.Width - logButton.Width - effaceLogButton.Width - 50, 7);
        };
        form.Controls.Add(panel);

        // Ajout systray
        var notifyIcon = new NotifyIcon
        {
            Icon = icon ?? SystemIcons.Application,
            Visible = true,
            Text = "Serveur KaDelta"
        };
        notifyIcon.DoubleClick += (s, e2) =>
        {
            if (!form.Visible)
                form.Show();
            if (form.WindowState == FormWindowState.Minimized)
                form.WindowState = FormWindowState.Normal;
            form.Activate();
        };
        form.Resize += (s, e2) =>
        {
            if (form.WindowState == FormWindowState.Minimized)
            {
                form.Hide();
                notifyIcon.BalloonTipTitle = "Serveur KaDelta";
                notifyIcon.BalloonTipText = "L'application est réduite dans la zone de notification.";
                notifyIcon.ShowBalloonTip(1000);
            }
        };
        form.FormClosing += (s, e) =>
        {
            notifyIcon.Visible = false;
        };

        Application.Run(form);
    }
}
