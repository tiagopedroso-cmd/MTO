<?php
declare(strict_types=1);
require __DIR__ . '/galeria-core.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
try {
    $action = $_GET['action'] ?? 'list';
    if (!is_string($action)) fail('Ação inválida.');
    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $state = state_read();
        respond(['photos' => array_values($state['photos'])]);
    }
    start_admin_session();
    if ($action === 'session' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        respond(['configured' => (bool)state_read()['password'], 'authenticated' => authenticated(), 'csrf' => $_SESSION['csrf']]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método não permitido.', 405);
    check_csrf();
    if ($action === 'setup' || $action === 'login') {
        attempt_guard();
        $password = $_POST['password'] ?? '';
        if (!is_string($password) || strlen($password) > 128) fail('Senha inválida.');
        if ($action === 'setup') {
            $key = $_POST['key'] ?? '';
            if (!is_string($key) || !hash_equals(MTO_SETUP_HASH, hash('sha256', $key))) fail('Chave de configuração inválida.', 403);
            if (strlen($password) < 12) fail('Crie uma senha com pelo menos 12 caracteres.');
            state_update(function (&$state) use ($password) {
                if ($state['password']) fail('O acesso já foi configurado.', 409);
                $state['password'] = password_hash($password, PASSWORD_DEFAULT); return [];
            });
        } else {
            $hash = state_read()['password'];
            if (!$hash || !password_verify($password, $hash)) fail('Senha incorreta.', 401);
        }
        attempt_guard(true); session_regenerate_id(true);
        $_SESSION['authenticated'] = time(); $_SESSION['csrf'] = bin2hex(random_bytes(32));
        respond(['ok' => true, 'csrf' => $_SESSION['csrf']]);
    }
    if (!authenticated()) fail('Entre com sua senha para continuar.', 401);
    if ($action === 'logout') { $_SESSION = []; session_destroy(); respond(['ok' => true]); }
    if ($action === 'upload') {
        $file = $_FILES['photo'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) fail('Não foi possível receber a foto. Envie um arquivo de até 8 MB.');
        if (!is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || $file['size'] > 8 * 1024 * 1024) fail('A foto deve ter até 8 MB.');
        $info = @getimagesize($file['tmp_name']);
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($types[$info[2]]) || $info[0] < 100 || $info[1] < 100 || $info[0] * $info[1] > 24000000) fail('Use uma foto JPG, PNG ou WebP entre 100 pixels e 24 megapixels.');
        $description = $_POST['description'] ?? '';
        if (!is_string($description) || strlen($description) > 640 || !preg_match('//u', $description)) fail('Descrição inválida.');
        $description = trim(strip_tags($description));
        if ($description === '') fail('Descreva brevemente o serviço mostrado na foto.');
        $id = bin2hex(random_bytes(16)); $name = $id . '.' . $types[$info[2]];
        $record = ['id' => $id, 'url' => 'uploads/galeria/' . $name, 'alt' => $description, 'width' => $info[0], 'height' => $info[1], 'created' => gmdate('c')];
        state_update(function (&$state) use ($record, $file, $name) {
            if (count($state['photos']) >= 60) fail('Limite de 60 fotos atingido. Exclua uma foto antes de enviar outra.');
            if (!move_uploaded_file($file['tmp_name'], MTO_UPLOADS . '/' . $name)) throw new RuntimeException('Falha ao salvar a foto.');
            @chmod(MTO_UPLOADS . '/' . $name, 0644);
            array_unshift($state['photos'], $record); return [];
        });
        respond(['ok' => true, 'photo' => $record]);
    }
    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) fail('Foto inválida.');
        $removed = state_update(function (&$state) use ($id) {
            foreach ($state['photos'] as $i => $photo) {
                if ($photo['id'] === $id) { array_splice($state['photos'], $i, 1); return $photo; }
            }
            fail('Foto não encontrada.', 404);
        });
        @unlink(MTO_UPLOADS . '/' . basename($removed['url'])); respond(['ok' => true]);
    }
    fail('Ação não encontrada.', 404);
} catch (Throwable $e) {
    error_log('MTO gallery: ' . $e->getMessage());
    fail('Não foi possível concluir. Verifique o armazenamento da hospedagem e tente novamente.', 500);
}
