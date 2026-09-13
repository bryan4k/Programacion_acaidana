<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$config = load_config();
$nonce = begin_secure_request($config);
if ($config === null) {
    render_configuration_error($nonce);
}
if (current_user() !== null) {
    header('Location: index.php', true, 303);
    exit;
}

$error = '';
$pdo = null;
$hasUsers = true;
try {
    $pdo = database($config);
    encryption_key($config);
    $hasUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (Throwable $exception) {
    error_log('Inicio Programacion: ' . $exception->getMessage());
    $error = 'No fue posible conectar de forma segura con la base de datos.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        $error = 'La sesión del formulario venció. Recarga la página.';
    } else {
        $emailInput = $_POST['email'] ?? '';
        $passwordInput = $_POST['password'] ?? '';
        if (!is_string($emailInput) || !is_string($passwordInput) || strlen($emailInput) > 760 || strlen($passwordInput) > 1024) {
            http_response_code(400);
            $error = 'Los datos enviados no son válidos.';
            $emailInput = '';
            $passwordInput = '';
        }
        $email = strtolower(trim($emailInput));
        $password = $passwordInput;
        if ($error === '' && strlen($email) > 190) {
            http_response_code(400);
            $error = 'Los datos enviados no son válidos.';
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $rateKeys = [
            ['key' => hash_hmac('sha256', 'ip|' . $ip, encryption_key($config), true), 'limit' => 20],
            ['key' => hash_hmac('sha256', 'account|' . $email, encryption_key($config), true), 'limit' => 5],
        ];
        $blocked = consume_login_attempt($pdo, $rateKeys);

        if ($error !== '') {
            // Reject malformed or oversized credentials before password hashing.
        } elseif ($blocked) {
            http_response_code(429);
            $error = 'Demasiados intentos. Espera 15 minutos antes de volver a intentar.';
        } elseif (!$hasUsers) {
            $setupToken = is_string($_POST['setup_token'] ?? null) && strlen($_POST['setup_token']) <= 1024 ? $_POST['setup_token'] : '';
            $expectedToken = (string) ($config['setup_token'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                $error = 'Escribe un correo electrónico válido.';
            } elseif (strlen($password) < 14 || strlen($password) > 1024) {
                $error = 'La contraseña debe tener al menos 14 caracteres.';
            } elseif (strlen($expectedToken) < 32 || !hash_equals($expectedToken, $setupToken)) {
                $error = 'El token de instalación no es válido.';
            } else {
                $lockName = 'programacion_first_user_' . substr(hash('sha256', encryption_key($config)), 0, 24);
                $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
                $lock->execute([$lockName]);
                if ((int) $lock->fetchColumn() !== 1) {
                    $error = 'No fue posible asegurar el proceso de instalación. Intenta nuevamente.';
                } else {
                    try {
                        if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                            $error = 'La cuenta administradora ya fue creada.';
                        } else {
                            $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                            $statement = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
                            $statement->execute([$email, password_hash($password, $algorithm)]);
                            clear_login_attempts($pdo, $rateKeys);
                            authenticate_user((int) $pdo->lastInsertId(), $email);
                        }
                    } finally {
                        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                        $release->execute([$lockName]);
                    }
                    if (current_user() !== null) {
                        header('Location: index.php', true, 303);
                        exit;
                    }
                }
            }
            if ($error !== '') usleep(random_int(250000, 600000));
        } else {
            $statement = $pdo->prepare('SELECT id, email, password_hash FROM users WHERE email = ? LIMIT 1');
            $statement->execute([$email]);
            $account = $statement->fetch();
            $fakeHash = defined('PASSWORD_ARGON2ID')
                ? '$argon2id$v=19$m=65536,t=4,p=1$WXhtOXZvbFY4Yi9UTHpVeA$YNR9TPY1opggx4db1O3AqI0455SMd+TnXb65K9aNmxc'
                : '$2y$12$U4ZQeR8V8fXvTA1qhM4yOe1m.b1h1Oj73IOq3W09A1LpgRKfSxj5q';
            $valid = password_verify($password, $account['password_hash'] ?? $fakeHash);
            if (!$account || !$valid) {
                usleep(random_int(250000, 600000));
                $error = 'Las credenciales no son válidas.';
            } else {
                $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                if (password_needs_rehash($account['password_hash'], $algorithm)) {
                    $newHash = password_hash($password, $algorithm);
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $account['id']]);
                }
                clear_login_attempts($pdo, $rateKeys);
                $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$account['id']]);
                authenticate_user((int) $account['id'], (string) $account['email']);
                header('Location: index.php', true, 303);
                exit;
            }
        }
    }
}

function authenticate_user(int $id, string $email): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['user_email'] = $email;
    $_SESSION['authenticated_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function consume_login_attempt(PDO $pdo, array $rateKeys): bool
{
    $blocked = false;
    $pdo->beginTransaction();
    try {
        $record = $pdo->prepare('INSERT INTO login_attempts (attempt_key, attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE attempts = IF(last_attempt_at < NOW() - INTERVAL 15 MINUTE, 1, LEAST(attempts + 1, 255)), last_attempt_at = IF(last_attempt_at < NOW() - INTERVAL 15 MINUTE, NOW(), last_attempt_at)');
        $check = $pdo->prepare('SELECT attempts FROM login_attempts WHERE attempt_key = ? FOR UPDATE');
        foreach ($rateKeys as $rateKey) {
            $record->bindValue(1, $rateKey['key'], PDO::PARAM_LOB);
            $record->execute();
            $check->bindValue(1, $rateKey['key'], PDO::PARAM_LOB);
            $check->execute();
            if ((int) $check->fetchColumn() > (int) $rateKey['limit']) {
                $blocked = true;
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    $pdo->exec('DELETE FROM login_attempts WHERE last_attempt_at < NOW() - INTERVAL 1 DAY LIMIT 500');
    return $blocked;
}

function clear_login_attempts(PDO $pdo, array $rateKeys): void
{
    $clear = $pdo->prepare('DELETE FROM login_attempts WHERE attempt_key = ?');
    foreach ($rateKeys as $rateKey) {
        $clear->bindValue(1, $rateKey['key'], PDO::PARAM_LOB);
        $clear->execute();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#061d42">
    <meta http-equiv="Content-Security-Policy" content="<?= htmlspecialchars(content_security_policy($nonce), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= $hasUsers ? 'Iniciar sesión' : 'Crear cuenta administradora' ?> | Programación de cultos</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-emblem">✝</div>
        <span class="eyebrow">Programación de cultos</span>
        <h1><?= $hasUsers ? 'Acceso protegido' : 'Crear cuenta administradora' ?></h1>
        <p><?= $hasUsers ? 'Ingresa tus credenciales para administrar las plantillas cifradas.' : 'Esta opción desaparece después de crear la primera cuenta.' ?></p>
        <?php if ($error !== ''): ?><div class="form-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form method="post" class="auth-form" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <label>Correo electrónico<input type="email" name="email" maxlength="190" required autocomplete="username"></label>
            <label>Contraseña<input type="password" name="password" minlength="14" maxlength="1024" required autocomplete="<?= $hasUsers ? 'current-password' : 'new-password' ?>"></label>
            <?php if (!$hasUsers): ?><label>Token de instalación<input type="password" name="setup_token" minlength="32" required autocomplete="off"></label><?php endif; ?>
            <button class="button button-gold auth-submit" type="submit"><?= $hasUsers ? 'Iniciar sesión' : 'Crear cuenta segura' ?></button>
        </form>
    </main>
</body>
</html>
