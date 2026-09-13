<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$config = load_config();
$nonce = begin_secure_request($config);
if ($config !== null) {
    header('Location: login.php', true, 303);
    exit;
}

$error = '';
$values = [
    'database_host' => 'localhost',
    'database_port' => '3306',
    'database_name' => '',
    'database_user' => '',
    'email' => '',
    'timezone' => 'America/Bogota',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $field) {
        $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    $databasePassword = is_string($_POST['database_password'] ?? null) ? $_POST['database_password'] : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $passwordConfirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';

    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        $error = 'La sesión del instalador venció. Recarga la página.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{1,253}$/', $values['database_host'])) {
        $error = 'El servidor de MySQL no es válido.';
    } elseif (!ctype_digit($values['database_port']) || (int) $values['database_port'] < 1 || (int) $values['database_port'] > 65535) {
        $error = 'El puerto de MySQL no es válido.';
    } elseif (!preg_match('/^[a-zA-Z0-9_$.-]{1,64}$/', $values['database_name']) || !preg_match('/^[a-zA-Z0-9_$.-]{1,64}$/', $values['database_user'])) {
        $error = 'El nombre de la base de datos o el usuario no es válido.';
    } elseif ($databasePassword === '' || strlen($databasePassword) > 1024) {
        $error = 'Escribe la contraseña de la base de datos.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190) {
        $error = 'Escribe un correo electrónico válido.';
    } elseif (strlen($password) < 14 || strlen($password) > 1024) {
        $error = 'La contraseña administradora debe tener al menos 14 caracteres.';
    } elseif (!hash_equals($password, $passwordConfirmation)) {
        $error = 'Las contraseñas administradoras no coinciden.';
    } elseif (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
        $error = 'La zona horaria no es válida.';
    } elseif (!function_exists('sodium_crypto_secretbox')) {
        $error = 'Hostinger debe tener habilitada la extensión Sodium de PHP.';
    } else {
        $configPath = dirname(__DIR__) . '/programacion-config.php';
        $temporaryPath = $configPath . '.tmp-' . bin2hex(random_bytes(6));
        $pdo = null;
        $configActivated = false;
        try {
            if (file_exists($configPath)) {
                throw new RuntimeException('Ya existe un archivo de configuración que debe revisarse antes de continuar.');
            }
            if (!is_writable(dirname($configPath))) {
                throw new RuntimeException('PHP no tiene permiso para guardar la configuración privada fuera de public_html.');
            }

            $newConfig = [
                'database' => [
                    'dsn' => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $values['database_host'], (int) $values['database_port'], $values['database_name']),
                    'user' => $values['database_user'],
                    'password' => $databasePassword,
                ],
                'active_key_version' => 1,
                'encryption_keys' => [1 => base64_encode(random_bytes(32))],
                'setup_token' => '',
                'timezone' => $values['timezone'],
                'require_https' => true,
            ];
            $configContents = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($newConfig, true) . ";\n";
            if (file_put_contents($temporaryPath, $configContents, LOCK_EX) === false) {
                throw new RuntimeException('No fue posible preparar la configuración privada.');
            }
            chmod($temporaryPath, 0600);

            $pdo = new PDO($newConfig['database']['dsn'], $newConfig['database']['user'], $newConfig['database']['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $schema = file_get_contents(__DIR__ . '/database.sql');
            if ($schema === false) {
                throw new RuntimeException('No fue posible leer el esquema de la base de datos.');
            }
            foreach (preg_split('/;\s*(?=CREATE TABLE)/i', trim($schema)) ?: [] as $statement) {
                if (trim($statement) !== '') $pdo->exec(rtrim(trim($statement), ';') . ';');
            }
            if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                throw new RuntimeException('La base de datos ya contiene una cuenta administradora.');
            }

            $pdo->beginTransaction();
            $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            $insert = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
            $insert->execute([strtolower($values['email']), password_hash($password, $algorithm)]);
            $userId = (int) $pdo->lastInsertId();
            if (!rename($temporaryPath, $configPath)) {
                throw new RuntimeException('No fue posible activar la configuración privada.');
            }
            $configActivated = true;
            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = strtolower($values['email']);
            $_SESSION['authenticated_at'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: index.php', true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
            if (is_file($temporaryPath)) unlink($temporaryPath);
            if ($configActivated && is_file($configPath)) unlink($configPath);
            error_log('Instalacion Programacion: ' . $exception->getMessage());
            $error = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'No fue posible completar la instalación. Verifica los datos de MySQL y los permisos.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#061d42">
    <title>Instalar | Programación de cultos</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="auth-page setup-page">
    <main class="auth-card setup-card">
        <div class="auth-emblem">✝</div>
        <span class="eyebrow">Instalación inicial segura</span>
        <h1>Conectar la aplicación</h1>
        <p>Completa una sola vez los datos creados en Hostinger. La clave de cifrado se genera automáticamente y la configuración se guarda fuera de <code>public_html</code>.</p>
        <?php if ($error !== ''): ?><div class="form-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form method="post" class="auth-form setup-form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <fieldset>
                <legend>Base de datos Hostinger</legend>
                <label>Servidor MySQL<input name="database_host" maxlength="253" required value="<?= htmlspecialchars($values['database_host'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Puerto<input name="database_port" inputmode="numeric" maxlength="5" required value="<?= htmlspecialchars($values['database_port'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Nombre de la base<input name="database_name" maxlength="64" required value="<?= htmlspecialchars($values['database_name'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Usuario MySQL<input name="database_user" maxlength="64" required value="<?= htmlspecialchars($values['database_user'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Contraseña MySQL<input type="password" name="database_password" maxlength="1024" required></label>
            </fieldset>
            <fieldset>
                <legend>Cuenta administradora</legend>
                <label>Correo electrónico<input type="email" name="email" maxlength="190" required value="<?= htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8') ?>"></label>
                <label>Contraseña<input type="password" name="password" minlength="14" maxlength="1024" required autocomplete="new-password"></label>
                <label>Confirmar contraseña<input type="password" name="password_confirmation" minlength="14" maxlength="1024" required autocomplete="new-password"></label>
                <label>Zona horaria<input name="timezone" maxlength="80" required value="<?= htmlspecialchars($values['timezone'], ENT_QUOTES, 'UTF-8') ?>"></label>
            </fieldset>
            <button class="button button-gold auth-submit" type="submit">Instalar y entrar</button>
        </form>
    </main>
</body>
</html>
