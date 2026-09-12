<?php
declare(strict_types=1);

// Mueve este archivo un nivel por encima de public_html y renómbralo
// programacion-config.php. Nunca lo publiques ni lo agregues a Git.
return [
    'database' => [
        'dsn' => 'mysql:host=localhost;dbname=NOMBRE_BASE;charset=utf8mb4',
        'user' => 'USUARIO_BASE',
        'password' => 'CONTRASEÑA_BASE_LARGA_Y_UNICA',
    ],
    // Genera con: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    'active_key_version' => 1,
    'encryption_keys' => [
        1 => 'CLAVE_BASE64_DE_32_BYTES',
    ],
    // Genera una frase aleatoria de al menos 32 caracteres y úsala una sola vez.
    'setup_token' => 'TOKEN_ALEATORIO_DE_INSTALACION',
    'timezone' => 'America/Bogota',
    'require_https' => true,
];
