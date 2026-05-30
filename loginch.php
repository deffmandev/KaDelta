<?php
require_once __DIR__ . '/auth.php';

auth_bootstrap();
$user = auth_require_active_session();

$info = '';
$error = '';
$identifiant = isset($user['Identifiant']) ? (string)$user['Identifiant'] : '';
$canChangePassword = !auth_is_non_changeable_user($user);

if (!$canChangePassword) {
	$error = 'Le compte administrateur ne peut pas changer son mot de passe.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!$canChangePassword) {
		auth_redirect('index.php');
	}

	$nouveau = (string)($_POST['nouveaumotdepasse'] ?? '');
	$confirm = (string)($_POST['confmotdepasse'] ?? '');

	if ($nouveau === '' || $confirm === '') {
		$error = 'Veuillez remplir tous les champs.';
	} elseif ($nouveau !== $confirm) {
		$error = 'La confirmation ne correspond pas au nouveau mot de passe.';
	} elseif (strlen($nouveau) < 5) {
		$error = 'Le mot de passe doit contenir au moins 5 caracteres.';
	} else {
		if (auth_change_password((int)$user['Id'], $nouveau)) {
			$_SESSION['last_activity'] = time();
			auth_redirect('index.php?pwdchanged=1');
		} else {
			$error = 'Impossible de mettre a jour le mot de passe.';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Changement de mot de passe</title>
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
		}

		.notice {
			margin: 0 0 14px;
			padding: 10px 12px;
			border-radius: 10px;
			font-size: 0.9rem;
			border: 1px solid transparent;
		}

		.notice.info {
			background: rgba(29, 78, 216, 0.26);
			border-color: rgba(147, 197, 253, 0.5);
		}

		.notice.error {
			background: rgba(127, 29, 29, 0.35);
			border-color: rgba(252, 165, 165, 0.5);
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
			width: min(92vw, 460px);
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
			margin: 0 0 8px;
			text-align: center;
			font-size: 1.16rem;
			letter-spacing: 0.02em;
			color: var(--text);
			font-weight: 600;
		}

		.subtitle {
			margin: 0 0 18px;
			text-align: center;
			font-size: 0.9rem;
			color: var(--muted);
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

		.btn-submit {
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

		.btn-submit:hover {
			transform: translateY(-1px);
			filter: brightness(1.06);
		}

		.btn-submit:active {
			transform: translateY(0);
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
	</style>
</head>
<body>
	<div class="back-shape" aria-hidden="true"></div>
	<div class="back-shape-2" aria-hidden="true"></div>

	<main class="modal-overlay">
		<section class="modal" role="dialog" aria-modal="true" aria-labelledby="change-title">
			<div class="logo-wrap">
				<img
					src="image/LogoKaDelta.png"
					alt="Logo KaDelta"
					onerror="this.onerror=null;this.src='Images/LogoKaDelta.png';"
				>
			</div>

			<h1 id="change-title">Changement de mot de passe</h1>
			<p class="subtitle">Mettez a jour vos informations de connexion</p>

			<?php if ($info !== ''): ?>
				<div class="notice info"><?php echo htmlspecialchars($info, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>

			<?php if ($error !== ''): ?>
				<div class="notice error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
			<?php endif; ?>

			<form method="post" action="" autocomplete="on">
				<div class="field">
					<label for="identifiant">Identifiant</label>
					<input
						type="text"
						id="identifiant"
						name="identifiant"
						value="<?php echo htmlspecialchars($identifiant, ENT_QUOTES, 'UTF-8'); ?>"
						readonly
						required
					>
				</div>

				<div class="field">
					<label for="nouveaumotdepasse">Nouveau mot de passe</label>
					<input
						type="password"
						id="nouveaumotdepasse"
						name="nouveaumotdepasse"
						placeholder="Entrez le nouveau mot de passe"
						required
					>
				</div>

				<div class="field">
					<label for="confmotdepasse">Confirmer le nouveau mot de passe</label>
					<input
						type="password"
						id="confmotdepasse"
						name="confmotdepasse"
						placeholder="Confirmez le nouveau mot de passe"
						required
					>
				</div>

				<button class="btn-submit" type="submit" <?php echo $canChangePassword ? '' : 'disabled'; ?>>Changer le mot de passe</button>
			</form>
		</section>
	</main>
</body>
</html>
