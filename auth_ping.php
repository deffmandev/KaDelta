<?php
require_once __DIR__ . '/auth.php';

auth_bootstrap();

header('Content-Type: application/json; charset=utf-8');

if (!auth_touch_session_activity()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'expired' => true]);
    exit;
}

$user = auth_get_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'expired' => true]);
    exit;
}

echo json_encode([
    'ok' => true,
    'identifiant' => $user['Identifiant'],
    'lastActivity' => $_SESSION['last_activity'] ?? time(),
]);
