<?php
require_once __DIR__ . '/auth.php';

auth_bootstrap();

function login_force_open_session(array $user)
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}

	if (session_status() !== PHP_SESSION_ACTIVE) {
		return false;
	}

	$isVirtual = isset($user['__virtual']) && $user['__virtual'] === true;
	$_SESSION['user_id'] = (int)($user['Id'] ?? 0);
	$_SESSION['identifiant'] = (string)($user['Identifiant'] ?? '');
	$_SESSION['user_role'] = auth_user_role($user);
	$_SESSION['auth_virtual'] = $isVirtual ? 1 : 0;
	$_SESSION['last_activity'] = time();

	return true;
}

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    auth_logout();
    auth_redirect('loginin.php');
}

$loginError = '';
$loginInfo = '';
$identifiant = '';
$loginWaitSeconds = 0;

$current = auth_get_current_user();
if ($current) {
    if (auth_user_requires_password_change($current)) {
        auth_redirect('loginch.php');
    }
    auth_redirect('index.php');
}

if (isset($_GET['expired']) && $_GET['expired'] === '1') {
    $loginInfo = 'Session expiree apres 1 heure sans activite.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $password = (string)($_POST['motdepasse'] ?? '');
    $remainingSeconds = 0;

	if (auth_is_login_temporarily_blocked($remainingSeconds)) {
		$loginWaitSeconds = max(1, (int)$remainingSeconds);
		$loginError = 'Identifiant ou mot de passe invalide.';
	} elseif ($identifiant === '' || $password === '') {
        $loginError = 'Veuillez saisir l\'identifiant et le mot de passe.';
	} elseif (strlen($identifiant) > 100 || preg_match('/[\x00-\x1F\x7F]/', $identifiant)) {
		$loginError = 'Identifiant ou mot de passe invalide.';
    } else {
        $user = null;
        $mustChange = false;
        if (auth_verify_login($identifiant, $password, $user, $mustChange)) {
			if (auth_open_session($user) || login_force_open_session($user)) {
				if ($mustChange) {
					auth_redirect('loginch.php');
				}
				auth_redirect('index.php');
			}
			$loginError = 'Impossible d\'ouvrir la session. Rechargez la page puis reessayez.';
        } else {
			auth_register_failed_login_attempt();
			auth_is_login_temporarily_blocked($remainingSeconds);
			$waitInfo = max(1, (int)$remainingSeconds);
			$loginWaitSeconds = $waitInfo;
			$loginError = 'Identifiant ou mot de passe invalide.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Connexion</title>
	<style>
		:root {
			--bg-1: #071326;
			--bg-2: #0d2342;
			--panel: rgba(12, 28, 52, 0.78);
			--panel-border: rgba(133, 180, 255, 0.28);
			--text: #e7f0ff;
			--muted: #98acd3;
			--input-bg: rgba(8, 22, 45, 0.75);
			--input-border: rgba(140, 181, 255, 0.28);
			--focus: #58a6ff;
			--btn-1: #1a65d8;
			--btn-2: #1f83ff;
			--shadow: 0 28px 70px rgba(0, 0, 0, 0.52);
			--ok-bg: rgba(29, 78, 216, 0.26);
			--ok-border: rgba(147, 197, 253, 0.5);
			--err-bg: rgba(127, 29, 29, 0.35);
			--err-border: rgba(252, 165, 165, 0.5);
		}

		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			min-height: 100vh;
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
			color: var(--text);
			background:
				radial-gradient(1200px 680px at 85% -10%, #2a4f8e 0%, transparent 60%),
				radial-gradient(900px 560px at -10% 105%, #15407a 0%, transparent 58%),
				linear-gradient(150deg, var(--bg-1), var(--bg-2));
			overflow: hidden;
		}

		.back-shape,
		.back-shape-2 {
			position: fixed;
			border-radius: 50%;
			filter: blur(24px);
			pointer-events: none;
			opacity: 0.28;
		}

		.back-shape {
			width: 360px;
			height: 360px;
			top: -80px;
			right: -60px;
			background: #57a3ff;
		}

		.back-shape-2 {
			width: 300px;
			height: 300px;
			bottom: -90px;
			left: -70px;
			background: #2a6dd1;
		}

		.modal-overlay {
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px;
		}

		.modal {
			width: min(92vw, 430px);
			background: var(--panel);
			border: 1px solid var(--panel-border);
			border-radius: 18px;
			box-shadow: var(--shadow);
			backdrop-filter: blur(10px);
			-webkit-backdrop-filter: blur(10px);
			padding: 28px 24px 22px;
			animation: riseIn 480ms ease-out both;
		}

		.logo-wrap {
			text-align: center;
			margin-bottom: 14px;
		}

		.logo-wrap img {
			max-width: 180px;
			width: 58%;
			height: auto;
			filter: drop-shadow(0 10px 16px rgba(0, 0, 0, 0.35));
		}

		h1 {
			margin: 0 0 20px;
			text-align: center;
			font-size: 1.16rem;
			letter-spacing: 0.02em;
			color: var(--text);
			font-weight: 600;
		}

		.notice {
			margin: 0 0 14px;
			padding: 10px 12px;
			border-radius: 10px;
			font-size: 0.9rem;
			border: 1px solid transparent;
		}

		.notice.info {
			background: var(--ok-bg);
			border-color: var(--ok-border);
		}

		.notice.error {
			background: var(--err-bg);
			border-color: var(--err-border);
		}

		form {
			display: grid;
			gap: 14px;
		}

		.field {
			display: grid;
			gap: 7px;
		}

		label {
			font-size: 0.89rem;
			color: var(--muted);
		}

		input {
			width: 100%;
			border: 1px solid var(--input-border);
			background: var(--input-bg);
			color: var(--text);
			border-radius: 11px;
			padding: 12px 13px;
			font-size: 0.96rem;
			outline: none;
			transition: border-color 160ms ease, box-shadow 160ms ease;
		}

		input::placeholder {
			color: #8fa5cd;
		}

		input:focus {
			border-color: var(--focus);
			box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.2);
		}

		.btn-login {
			margin-top: 6px;
			border: 0;
			border-radius: 12px;
			color: #f3f8ff;
			font-weight: 600;
			letter-spacing: 0.01em;
			padding: 12px;
			cursor: pointer;
			background: linear-gradient(135deg, var(--btn-1), var(--btn-2));
			box-shadow: 0 10px 24px rgba(16, 77, 177, 0.45);
			transition: transform 140ms ease, filter 140ms ease;
		}

		.btn-login:hover {
			transform: translateY(-1px);
			filter: brightness(1.06);
		}

		.btn-login:active {
			transform: translateY(0);
		}

		.hint {
			margin-top: 10px;
			text-align: center;
			font-size: 0.8rem;
			color: #aac0e7;
		}

		@keyframes riseIn {
			from {
				opacity: 0;
				transform: translateY(14px) scale(0.98);
			}
			to {
				opacity: 1;
				transform: translateY(0) scale(1);
			}
		}

		@media (max-width: 520px) {
			.modal {
				padding: 22px 18px 18px;
				border-radius: 14px;
			}

			.logo-wrap img {
				width: 66%;
				max-width: 170px;
			}
		}

		.wait-overlay {
			position: fixed;
			inset: 0;
			display: none;
			align-items: center;
			justify-content: center;
			background: rgba(7, 19, 38, 0.78);
			backdrop-filter: blur(3px);
			z-index: 9999;
		}

		.wait-overlay.active {
			display: flex;
		}

		.wait-box {
			background: rgba(12, 28, 52, 0.92);
			border: 1px solid rgba(133, 180, 255, 0.32);
			border-radius: 14px;
			padding: 18px 22px;
			min-width: 280px;
			text-align: center;
			box-shadow: 0 18px 40px rgba(0, 0, 0, 0.45);
		}

		.wait-spinner {
			width: 44px;
			height: 44px;
			margin: 0 auto 12px;
			border: 4px solid rgba(160, 191, 236, 0.28);
			border-top-color: #7db4ff;
			border-radius: 50%;
			animation: spin .9s linear infinite;
		}

		.wait-title {
			font-size: 1rem;
			font-weight: 600;
			margin-bottom: 6px;
		}

		.wait-sub {
			font-size: 0.92rem;
			color: #b8cbed;
		}

		@keyframes spin {
			to { transform: rotate(360deg); }
		}
	</style>
</head>
<body>
	<div class="back-shape" aria-hidden="true"></div>
	<div class="back-shape-2" aria-hidden="true"></div>

	<main class="modal-overlay">
		<section class="modal" role="dialog" aria-modal="true" aria-labelledby="login-title">
			<div class="logo-wrap">
				<img
					src="Images/LogoKaDelta.png"
					alt="Logo KaDelta"
					onerror="this.style.display='none';"
				>
			</div>

			<h1 id="login-title">Connexion</h1>

			<?php if ($loginInfo !== ''): ?>
				<div class="notice info"><?php echo htmlspecialchars($loginInfo, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>

			<?php if ($loginError !== ''): ?>
				<div class="notice error"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>

			<form method="post" action="" autocomplete="on">
				<div class="field">
					<label for="identifiant">Identifiant</label>
					<input
						type="text"
						id="identifiant"
						name="identifiant"
						placeholder="Entrez votre identifiant"
						value="<?php echo htmlspecialchars($identifiant, ENT_QUOTES, 'UTF-8'); ?>"
						required
					>
				</div>

				<div class="field">
					<label for="motdepasse">Mot de passe</label>
					<input
						type="password"
						id="motdepasse"
						name="motdepasse"
						placeholder="Entrez votre mot de passe"
						required
					>
				</div>

				<button class="btn-login" type="submit">Se connecter</button>
			</form>

		</section>
	</main>

	<div id="wait-overlay" class="wait-overlay" aria-live="polite" aria-hidden="true">
		<div class="wait-box">
			<div class="wait-spinner"></div>
			<div class="wait-title">Veuillez patienter...</div>
			<div class="wait-sub">Nouvelle tentative possible dans <span id="wait-seconds">0</span>s</div>
		</div>
	</div>

	<script>
	(function() {
		const waitSeconds = <?php echo (int)$loginWaitSeconds; ?>;
		if (waitSeconds <= 0) return;

		const overlay = document.getElementById('wait-overlay');
		const secondsEl = document.getElementById('wait-seconds');
		const form = document.querySelector('form');
		if (!overlay || !secondsEl || !form) return;

		let remaining = waitSeconds;
		overlay.classList.add('active');
		overlay.setAttribute('aria-hidden', 'false');
		secondsEl.textContent = String(remaining);

		const fields = form.querySelectorAll('input, button');
		fields.forEach(function(el) { el.disabled = true; });

		const timer = setInterval(function() {
			remaining -= 1;
			secondsEl.textContent = String(Math.max(0, remaining));
			if (remaining <= 0) {
				clearInterval(timer);
				overlay.classList.remove('active');
				overlay.setAttribute('aria-hidden', 'true');
				fields.forEach(function(el) { el.disabled = false; });
				const ident = document.getElementById('identifiant');
				if (ident) ident.focus();
			}
		}, 1000);
	})();
	</script>
</body>
</html>
