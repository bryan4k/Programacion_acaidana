<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$config = load_config();
$nonce = begin_secure_request($config);
if ($config === null) {
    if (is_local_development()) {
        json_response(['error' => 'El modo local no utiliza la API remota.'], 400);
    }
    json_response(['error' => 'Servidor no configurado.'], 503);
}

$user = require_user(true);
$action = (string) ($_GET['action'] ?? 'list');
$pdo = null;

try {
    $pdo = database($config);
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $statement = $pdo->prepare('SELECT id, name, version, updated_at FROM templates WHERE user_id = ? ORDER BY updated_at DESC');
        $statement->execute([$user['id']]);
        json_response(['templates' => $statement->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            json_response(['error' => 'Plantilla inválida.'], 400);
        }
        $statement = $pdo->prepare('SELECT id, name, encrypted_content, key_version, version, updated_at FROM templates WHERE id = ? AND user_id = ?');
        $statement->execute([$id, $user['id']]);
        $template = $statement->fetch();
        if (!$template) {
            json_response(['error' => 'Plantilla no encontrada.'], 404);
        }
        $content = decrypt_template($template['encrypted_content'], $config, (int) $template['key_version']);
        unset($template['encrypted_content']);
        $template['content'] = $content;
        json_response(['template' => $template]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        json_response(['error' => 'Método no permitido.'], 405);
    }

    $body = read_json_body();
    verify_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if ($action === 'save') {
        $name = clean_text($body['name'] ?? '', 120);
        if ($name === '') {
            json_response(['error' => 'Escribe un nombre para la plantilla.'], 422);
        }
        $content = validate_template($body['content'] ?? null);
        $encrypted = encrypt_template($content, $config);
        $keyVersion = active_key_version($config);
        $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id) {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
            $lock->execute([$user['id']]);
            $quota = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(OCTET_LENGTH(encrypted_content)), 0) AS used_bytes FROM templates WHERE user_id = ?');
            $quota->execute([$user['id']]);
            $usage = $quota->fetch();
            if ((int) $usage['total'] >= MAX_TEMPLATES_PER_USER || (int) $usage['used_bytes'] + strlen($encrypted) > MAX_STORAGE_BYTES_PER_USER) {
                $pdo->rollBack();
                json_response(['error' => 'Alcanzaste el límite de almacenamiento de plantillas.'], 413);
            }
            $statement = $pdo->prepare('INSERT INTO templates (user_id, name, encrypted_content, key_version) VALUES (?, ?, ?, ?)');
            $statement->bindValue(1, $user['id'], PDO::PARAM_INT);
            $statement->bindValue(2, $name);
            $statement->bindValue(3, $encrypted, PDO::PARAM_LOB);
            $statement->bindValue(4, $keyVersion, PDO::PARAM_INT);
            $statement->execute();
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();
            json_response(['template' => ['id' => $newId, 'name' => $name, 'version' => 1]], 201);
        }

        $version = filter_var($body['version'] ?? null, FILTER_VALIDATE_INT);
        if (!$version) {
            json_response(['error' => 'Versión de plantilla inválida.'], 400);
        }
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
        $lock->execute([$user['id']]);
        $quota = $pdo->prepare('SELECT COALESCE(SUM(OCTET_LENGTH(encrypted_content)), 0) AS used_bytes, COALESCE(MAX(CASE WHEN id = ? THEN OCTET_LENGTH(encrypted_content) END), -1) AS current_bytes FROM templates WHERE user_id = ?');
        $quota->execute([$id, $user['id']]);
        $usage = $quota->fetch();
        if ((int) $usage['current_bytes'] < 0) {
            $pdo->rollBack();
            json_response(['error' => 'Plantilla no encontrada.'], 404);
        }
        if ((int) $usage['used_bytes'] - (int) $usage['current_bytes'] + strlen($encrypted) > MAX_STORAGE_BYTES_PER_USER) {
            $pdo->rollBack();
            json_response(['error' => 'Alcanzaste el límite de almacenamiento de plantillas.'], 413);
        }
        $statement = $pdo->prepare('UPDATE templates SET name = ?, encrypted_content = ?, key_version = ?, version = version + 1 WHERE id = ? AND user_id = ? AND version = ?');
        $statement->bindValue(1, $name);
        $statement->bindValue(2, $encrypted, PDO::PARAM_LOB);
        $statement->bindValue(3, $keyVersion, PDO::PARAM_INT);
        $statement->bindValue(4, $id, PDO::PARAM_INT);
        $statement->bindValue(5, $user['id'], PDO::PARAM_INT);
        $statement->bindValue(6, $version, PDO::PARAM_INT);
        $statement->execute();
        if ($statement->rowCount() !== 1) {
            $pdo->rollBack();
            json_response(['error' => 'La plantilla cambió en otra sesión. Recárgala antes de guardar.'], 409);
        }
        $pdo->commit();
        json_response(['template' => ['id' => (int) $id, 'name' => $name, 'version' => (int) $version + 1]]);
    }

    if ($action === 'delete') {
        $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
        $version = filter_var($body['version'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || !$version) {
            json_response(['error' => 'Plantilla inválida.'], 400);
        }
        $statement = $pdo->prepare('DELETE FROM templates WHERE id = ? AND user_id = ? AND version = ?');
        $statement->execute([$id, $user['id'], $version]);
        if ($statement->rowCount() !== 1) {
            json_response(['error' => 'La plantilla cambió o ya no existe. Recarga la lista.'], 409);
        }
        json_response(['deleted' => true]);
    }

    json_response(['error' => 'Acción no encontrada.'], 404);
} catch (InvalidArgumentException $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    json_response(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('API Programacion: ' . $error->getMessage());
    json_response(['error' => 'Ocurrió un error interno. Intenta nuevamente.'], 500);
}
