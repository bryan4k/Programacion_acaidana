<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
$config = load_config();
begin_secure_request($config);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
    http_response_code(405);
    exit('Solicitud no permitida.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php', true, 303);
exit;
