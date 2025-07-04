// See https://aka.ms/new-console-template for more information
using System;
using System.Net.Http;
using System.Threading.Tasks;
using System.Windows.Forms;

class Program
{
    private static bool allowClose = false;
    [STAThread]
    static async Task Main()
    {
        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);
        var form = new Form { Text = "Serveur KaDelta", Width = 600, Height = 400 };
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
                    var response = await httpClient.GetAsync("http://192.168.1.152/srv.php");
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
                catch (Exception ex)
                {
                    form.Invoke((Action)(() => textBox.Text = $"Erreur lors de l'appel à l'API : {ex.Message}"));
                }
            }
        });

        Application.Run(form);
    }
}
