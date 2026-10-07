<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require __DIR__ . '/config.php';

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler(function ($e) {
    error_log('EVA2 error: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'Error interno del servidor']);
});

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // sentencias preparadas reales
        ];
        if (defined('DB_SSL_CA') && DB_SSL_CA !== '') {
            $opciones[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;                 // TLS hacia RDS
            $opciones[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        try {
            $pdo->exec("SET time_zone = 'America/Santiago'");
        } catch (Throwable $e) {
            // si la BD no tiene tablas de zonas horarias se usa la hora por defecto
        }
    }
    return $pdo;
}

function exigir_metodo(string $metodo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        header('Allow: ' . $metodo);
        responder(405, ['ok' => false, 'mensaje' => 'Método no permitido']);
    }
}

function entrada(): array
{
    $crudo = file_get_contents('php://input', false, null, 0, 4096); // máx. 4 KB
    $datos = json_decode($crudo === false ? '' : $crudo, true);
    if (!is_array($datos)) {
        responder(400, ['ok' => false, 'mensaje' => 'Solicitud inválida']);
    }
    return $datos;
}

function texto($valor): string
{
    return is_string($valor) ? trim($valor) : '';
}

// Largo en caracteres sin depender de la extensión mbstring
function largo(string $s): int
{
    return (int)preg_match_all('/./us', $s);
}

function ip_cliente(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function bitacora(?int $usuarioId, ?int $dispositivoId, string $evento, string $detalle = ''): void
{
    $st = db()->prepare(
        'INSERT INTO bitacora (usuario_id, dispositivo_id, evento, detalle, ip) VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([$usuarioId, $dispositivoId, $evento, substr($detalle, 0, 200), ip_cliente()]);
}

function token_de_cabecera(): string
{
    $cab = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($cab === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $nombre => $valor) {
            if (strcasecmp($nombre, 'Authorization') === 0) {
                $cab = $valor;
            }
        }
    }
    if (preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', $cab, $m)) {
        return strtolower($m[1]);
    }
    return '';
}

// Control de acceso: valida el token de sesión y (opcional) el rol exigido.
function autenticar(array $rolesPermitidos = []): array
{
    $token = token_de_cabecera();
    if ($token === '') {
        responder(401, ['ok' => false, 'mensaje' => 'Sesión no válida']);
    }
    $st = db()->prepare(
        'SELECT u.id, u.nombre, u.rol
           FROM sesiones s JOIN usuarios u ON u.id = s.usuario_id
          WHERE s.token_hash = ? AND s.revocada = 0 AND s.expira_en > NOW() AND u.activo = 1'
    );
    $st->execute([hash('sha256', $token)]);
    $usuario = $st->fetch();
    if (!$usuario) {
        responder(401, ['ok' => false, 'mensaje' => 'Sesión expirada, inicia sesión nuevamente']);
    }
    if ($rolesPermitidos && !in_array($usuario['rol'], $rolesPermitidos, true)) {
        responder(403, ['ok' => false, 'mensaje' => 'No tienes permisos para esta acción']);
    }
    $usuario['id'] = (int)$usuario['id'];
    return $usuario;
}

function hash_clave_temporal(string $clave): string
{
    return hash_hmac('sha256', $clave, CLAVE_PEPPER);
}

function dispositivo_valido(int $id): bool
{
    $st = db()->prepare('SELECT 1 FROM dispositivos WHERE id = ?');
    $st->execute([$id]);
    return (bool)$st->fetchColumn();
}
