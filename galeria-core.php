<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
const MTO_SETUP_HASH = 'e7f784ff7e47536fe32a5e2b673bc06f80a70465414dc5ceb14f6f0fdca446ee';
const MTO_DATA = __DIR__ . '/.mto-data';
const MTO_UPLOADS = __DIR__ . '/uploads/galeria';
const MTO_PREFIX = "<?php http_response_code(404); exit; ?>\n";

function fail(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE); exit;
}
function respond(array $data): never { echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function ensure_storage(): void {
    foreach ([MTO_DATA, MTO_UPLOADS] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('Armazenamento indisponível.');
    }
}
function read_record(string $path, array $default): array {
    if (!file_exists($path)) return $default;
    $raw = file_get_contents($path);
    if ($raw === false || !str_starts_with($raw, MTO_PREFIX)) throw new RuntimeException('Dados indisponíveis.');
    return json_decode(substr($raw, strlen(MTO_PREFIX)), true, 512, JSON_THROW_ON_ERROR);
}
function write_record(string $path, array $data): void {
    $temp = dirname($path) . '/tmp-' . bin2hex(random_bytes(12)) . '.php';
    if (file_put_contents($temp, MTO_PREFIX . json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) throw new RuntimeException('Falha ao salvar.');
    @chmod($temp, 0600);
    if (!rename($temp, $path)) { @unlink($temp); throw new RuntimeException('Falha ao publicar.'); }
}
function state_read(): array { return read_record(MTO_DATA . '/state.php', ['password' => null, 'photos' => []]); }
function state_update(callable $callback): array {
    ensure_storage();
    $lock = fopen(MTO_DATA . '/state.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Galeria ocupada. Tente novamente.');
    try { $state = state_read(); $result = $callback($state); write_record(MTO_DATA . '/state.php', $state); return $result; }
    finally { flock($lock, LOCK_UN); fclose($lock); }
}
function start_admin_session(): void {
    ini_set('session.use_strict_mode', '1');
    session_name('mto_gallery');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function authenticated(): bool { return isset($_SESSION['authenticated']) && (int)$_SESSION['authenticated'] > time() - 8 * 3600; }
function check_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) fail('Sessão expirada. Atualize a página.', 403);
}
function attempt_guard(bool $success = false): void {
    ensure_storage();
    $key = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
    $path = MTO_DATA . '/attempts.php';
    $lock = fopen(MTO_DATA . '/attempts.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Tente novamente.');
    try {
        $all = read_record($path, []);
        $all = array_filter($all, fn($r) => $r['until'] > time());
        if ($success) unset($all[$key]);
        else {
            $row = $all[$key] ?? ['count' => 0, 'until' => time() + 900];
            if ($row['count'] >= 8) fail('Muitas tentativas. Aguarde 15 minutos.', 429);
            $row['count']++; $all[$key] = $row;
        }
        write_record($path, $all);
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
