<?php
declare(strict_types=1);

const SESSION_IDLE_SECONDS = 1800;
const SESSION_ABSOLUTE_SECONDS = 28800;
const MAX_TEMPLATE_BYTES = 22_000_000;
const MAX_TEMPLATES_PER_USER = 200;
const MAX_STORAGE_BYTES_PER_USER = 100_000_000;

function is_local_development(): bool
{
    return getenv('APP_ENV') === 'development'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', ['127.0.0.1', '::1'], true);
}

function load_config(): ?array
{
    $configuredPath = getenv('PROGRAMACION_CONFIG');
    $paths = array_filter([
        is_string($configuredPath) ? $configuredPath : null,
        dirname(__DIR__, 2) . '/programacion-config.php',
    ]);

    foreach ($paths as $path) {
        if (is_file($path)) {
            $config = require $path;
            return is_array($config) ? $config : null;
        }
    }

    return null;
}

function request_is_https(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
}

function content_security_policy(string $nonce): string
{
    $upgradePolicy = request_is_https() ? '; upgrade-insecure-requests' : '';
    return "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'{$upgradePolicy}";
}

function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    $file = dirname(__DIR__) . '/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return $path . '?v=' . rawurlencode($version);
}

function begin_secure_request(?array $config): string
{
    $timezone = is_string($config['timezone'] ?? null) ? $config['timezone'] : 'America/Bogota';
    if (in_array($timezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($timezone);
    }
    if (!is_local_development() && ($config['require_https'] ?? true) && !request_is_https()) {
        http_response_code(403);
        exit('Esta aplicación requiere HTTPS.');
    }

    $nonce = base64_encode(random_bytes(18));
    header_remove('X-Powered-By');
    ini_set('display_errors', is_local_development() ? '1' : '0');
    header('Content-Security-Policy: ' . content_security_policy($nonce));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store, private');
    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    session_name('cultos_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    if (is_local_development()) {
        session_save_path(sys_get_temp_dir());
    }
    session_start();

    $now = time();
    if (isset($_SESSION['user_id'])) {
        $expired = $now - (int) ($_SESSION['last_activity'] ?? 0) > SESSION_IDLE_SECONDS
            || $now - (int) ($_SESSION['authenticated_at'] ?? 0) > SESSION_ABSOLUTE_SECONDS;
        if ($expired) {
            $_SESSION = [];
            session_regenerate_id(true);
        } else {
            $_SESSION['last_activity'] = $now;
        }
    }

    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $nonce;
}

function database(array $config): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    foreach (['dsn', 'user', 'password'] as $key) {
        if (!isset($config['database'][$key]) || !is_string($config['database'][$key])) {
            throw new RuntimeException('Configuración de base de datos incompleta.');
        }
    }

    $pdo = new PDO(
        $config['database']['dsn'],
        $config['database']['user'],
        $config['database']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]
    );
    return $pdo;
}

function active_key_version(array $config): int
{
    $version = filter_var($config['active_key_version'] ?? null, FILTER_VALIDATE_INT);
    if (!$version || $version < 1 || $version > 65535) {
        throw new RuntimeException('La versión activa de cifrado no es válida.');
    }
    return (int) $version;
}

function encryption_key(array $config, ?int $version = null): string
{
    if (!function_exists('sodium_crypto_secretbox')) {
        throw new RuntimeException('La extensión Sodium es obligatoria.');
    }
    $version ??= active_key_version($config);
    $key = base64_decode((string) ($config['encryption_keys'][$version] ?? ''), true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('La clave de cifrado es inválida. Debe contener 32 bytes codificados en base64.');
    }
    return $key;
}

function encrypt_template(array $content, array $config): string
{
    $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $nonce . sodium_crypto_secretbox($json, $nonce, encryption_key($config));
}

function decrypt_template(string $encrypted, array $config, int $keyVersion): array
{
    if (strlen($encrypted) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('Plantilla cifrada inválida.');
    }
    $nonce = substr($encrypted, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($encrypted, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, encryption_key($config, $keyVersion));
    if ($plain === false) {
        throw new RuntimeException('No fue posible descifrar la plantilla.');
    }
    $content = json_decode($plain, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($content)) {
        throw new RuntimeException('Contenido de plantilla inválido.');
    }
    return $content;
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'], $_SESSION['user_email'])) {
        return null;
    }
    return ['id' => (int) $_SESSION['user_id'], 'email' => (string) $_SESSION['user_email']];
}

function require_user(bool $json = false): array
{
    $user = current_user();
    if ($user !== null) {
        return $user;
    }
    if ($json) {
        json_response(['error' => 'Tu sesión expiró. Inicia sesión nuevamente.'], 401);
    }
    header('Location: login.php', true, 303);
    exit;
}

function verify_csrf(?string $token): void
{
    if (!is_string($token) || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
        json_response(['error' => 'La solicitud de seguridad venció. Recarga la página.'], 419);
    }
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    if (!str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        json_response(['error' => 'Tipo de contenido no permitido.'], 415);
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > MAX_TEMPLATE_BYTES + 10000) {
        json_response(['error' => 'La solicitud es demasiado grande.'], 413);
    }
    try {
        $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_response(['error' => 'Solicitud JSON inválida.'], 400);
    }
    return is_array($body) ? $body : [];
}

function clean_text(mixed $value, int $maxLength): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException('Se esperaba un texto.');
    }
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length > $maxLength) {
        throw new InvalidArgumentException('Uno de los textos supera el tamaño permitido.');
    }
    return $value;
}

function clean_image(mixed $value): string
{
    $image = clean_text($value, 2_850_000);
    if ($image !== '' && !preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#', $image)) {
        throw new InvalidArgumentException('Una de las imágenes tiene un formato no permitido.');
    }
    return $image;
}

function validate_template(mixed $input): array
{
    if (!is_array($input)) {
        throw new InvalidArgumentException('Plantilla inválida.');
    }
    $templateType = ($input['templateType'] ?? 'worship') === 'ushers' ? 'ushers' : 'worship';

    if (array_key_exists('pages', $input)) {
        if (!is_array($input['pages']) || count($input['pages']) < 1 || count($input['pages']) > 50) {
            throw new InvalidArgumentException('La plantilla debe contener entre 1 y 50 programaciones.');
        }

        $result = ['formatVersion' => 2, 'templateType' => $templateType, 'logo' => '', 'design' => [], 'pages' => []];
        foreach (array_values($input['pages']) as $index => $page) {
            if (!is_array($page)) {
                throw new InvalidArgumentException('Programación inválida.');
            }
            $validated = validate_template(array_merge($page, [
                'logo' => $index === 0 ? ($input['logo'] ?? '') : '',
                'design' => $index === 0 ? ($input['design'] ?? []) : [],
                'templateType' => $templateType,
            ]));
            if ($index === 0) {
                $result['logo'] = $validated['logo'];
                $result['design'] = $validated['design'];
            }
            $logoExtra = max(0, $result['design']['logoSize'] - 104);
            if ($templateType === 'ushers') {
                $rowHeight = max(40, $result['design']['tableBodyFontSize'] * 2.4);
                $capacity = max(1, (int) floor((1123 - 265 - $logoExtra) / $rowHeight));
            } else {
                $rowHeight = max(45, $result['design']['tableBodyFontSize'] * 2.7);
                $available = 1123 - 300 - $logoExtra - $result['design']['footerHeight'] - $result['design']['verseHeight'] - 190;
                $capacity = max(1, (int) floor($available / $rowHeight));
            }
            if (count($validated['rows']) > $capacity) {
                throw new InvalidArgumentException('Una programación contiene más filas de las que caben en una hoja A4.');
            }
            unset($validated['logo'], $validated['design']);
            $result['pages'][] = $validated;
        }
        return $result;
    }

    $result = [];
    foreach ([
        'churchName' => 200,
        'congregation' => 120,
        'programTitle' => 250,
        'period' => 120,
        'verse' => 1200,
        'reference' => 120,
        'coordinatorLabel' => 120,
        'coordinators' => 1000,
    ] as $field => $limit) {
        $result[$field] = clean_text($input[$field] ?? '', $limit);
    }

    if (!isset($input['headers']) || !is_array($input['headers']) || count($input['headers']) !== 3) {
        throw new InvalidArgumentException('El encabezado debe contener exactamente tres columnas.');
    }
    $result['headers'] = array_map(static fn ($header) => clean_text($header, 50), array_values($input['headers']));

    $result['logo'] = clean_image($input['logo'] ?? '');

    $design = is_array($input['design'] ?? null) ? $input['design'] : [];
    $result['design'] = [];
    foreach (['headerImage', 'headerLeftImage', 'headerRightImage', 'watermarkImage', 'footerImage', 'verseFrameImage'] as $field) {
        $result['design'][$field] = clean_image($design[$field] ?? '');
    }
    foreach ([
        'headerHeight' => [60, 300, 150],
        'headerOpacity' => [5, 100, 100],
        'headerLeftSize' => [60, 380, 210],
        'headerLeftOpacity' => [5, 100, 100],
        'headerRightSize' => [60, 380, 210],
        'headerRightOpacity' => [5, 100, 100],
        'watermarkSize' => [80, 700, 320],
        'watermarkOpacity' => [3, 60, 14],
        'watermarkX' => [0, 100, 50],
        'watermarkY' => [0, 100, 46],
        'footerHeight' => [60, 320, 150],
        'footerOpacity' => [5, 100, 55],
        'verseHeight' => [120, 360, 170],
        'verseReferenceX' => [10, 90, 50],
        'verseReferenceY' => [55, 92, 86],
        'logoSize' => [40, 180, 100],
        'churchNameFontSize' => [12, 42, 23],
        'congregationFontSize' => [12, 42, 22],
        'programTitleFontSize' => [10, 72, 18],
        'tableHeaderFontSize' => [9, 28, 17],
        'tableBodyFontSize' => [9, 30, 17],
        'verseFontSize' => [11, 38, 22],
        'referenceFontSize' => [9, 28, 15],
        'coordinatorLabelFontSize' => [10, 30, 18],
        'coordinatorsFontSize' => [10, 32, 18],
    ] as $field => [$minimum, $maximum, $default]) {
        $value = filter_var($design[$field] ?? $default, FILTER_VALIDATE_INT);
        if ($value === false || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException('Uno de los ajustes visuales no es válido.');
        }
        $result['design'][$field] = (int) $value;
    }

    if (!isset($input['rows']) || !is_array($input['rows']) || count($input['rows']) < 1 || count($input['rows']) > 300) {
        throw new InvalidArgumentException('La plantilla debe contener entre 1 y 300 filas.');
    }
    $result['rows'] = [];
    foreach ($input['rows'] as $row) {
        if (!is_array($row)) {
            throw new InvalidArgumentException('Fila inválida.');
        }
        $date = clean_text($row['date'] ?? '', 10);
        if ($date !== '') {
            $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Una de las fechas es inválida.');
            }
        }
        $normalizedRow = [
            'id' => preg_match('/^[a-zA-Z0-9-]{1,80}$/', (string) ($row['id'] ?? '')) ? (string) $row['id'] : bin2hex(random_bytes(8)),
            'date' => $date,
        ];
        if ($templateType === 'ushers') {
            $uniformMode = in_array($row['uniformMode'] ?? '', ['separate', 'dress'], true) ? $row['uniformMode'] : 'separate';
            $topType = in_array($row['topType'] ?? '', ['shirt', 'blouse'], true) ? $row['topType'] : 'shirt';
            $bottomType = in_array($row['bottomType'] ?? '', ['skirt', 'pants'], true) ? $row['bottomType'] : 'skirt';
            $cleanColor = static fn (mixed $color, string $fallback): string => is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color) ? strtolower($color) : $fallback;
            $normalizedRow += [
                'names' => clean_text($row['names'] ?? '', 1200),
                'uniformMode' => $uniformMode,
                'topType' => $topType,
                'topColor' => $cleanColor($row['topColor'] ?? '', '#ffffff'),
                'bottomType' => $bottomType,
                'bottomColor' => $cleanColor($row['bottomColor'] ?? '', '#9ca3af'),
                'dressColor' => $cleanColor($row['dressColor'] ?? '', '#f2a7b5'),
            ];
        } else {
            $normalizedRow += [
                'directors' => clean_text($row['directors'] ?? '', 500),
                'preacher' => clean_text($row['preacher'] ?? '', 500),
                'special' => ($row['special'] ?? false) === true,
                'specialText' => clean_text($row['specialText'] ?? '', 500),
            ];
        }
        $result['rows'][] = $normalizedRow;
    }

    return $result;
}

function render_configuration_error(string $nonce): never
{
    http_response_code(503);
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="<?= htmlspecialchars(content_security_policy($nonce), ENT_QUOTES, 'UTF-8') ?>"><title>Configuración requerida</title><link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/styles.css'), ENT_QUOTES, 'UTF-8') ?>"></head><body class="auth-page"><main class="auth-card"><span class="eyebrow">Instalación segura</span><h1>Falta configurar el servidor</h1><p>Completa el instalador de un solo uso para conectar MySQL, generar la clave de cifrado y crear la cuenta administradora.</p><a class="button button-gold auth-submit setup-link" href="setup.php">Iniciar configuración</a></main></body></html><?php
    exit;
}
