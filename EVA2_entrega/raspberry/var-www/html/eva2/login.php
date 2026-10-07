<?php
declare(strict_types=1);
require '/var/www/eva2_private/lib.php';

exigir_metodo('POST');
$in = entrada();
$email = strtolower(texto($in['email'] ?? ''));
$clave = is_string($in['password'] ?? null) ? $in['password'] : '';

$st = db()->prepare(
    'SELECT id, nombre, rol, password_hash, activo,
            (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueado
       FROM usuarios WHERE email = ?'
);
$st->execute([$email]);
$u = $st->fetch();

if (!$u || (int)$u['activo'] !== 1) {
    // hash falso para que la respuesta tarde lo mismo exista o no el correo
    password_verify($clave, '$2y$10$NXObo52e7.JwVffOKdc9xOJy9Xduz9hPUwoAwwHtMUBdIbFfUunF.');
    bitacora(null, null, 'LOGIN_FALLIDO', 'Correo no registrado o cuenta inactiva');
    responder(401, ['ok' => false, 'mensaje' => 'Credenciales inválidas']);
}

$uid = (int)$u['id'];

if ((int)$u['bloqueado'] === 1) {
    bitacora($uid, null, 'BLOQUEO', 'Intento de login con cuenta bloqueada');
    responder(423, ['ok' => false, 'mensaje' => 'Cuenta bloqueada temporalmente. Intenta más tarde']);
}

if (!password_verify($clave, $u['password_hash'])) {
    $up = db()->prepare('UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1 WHERE id = ?');
    $up->execute([$uid]);
    $q = db()->prepare('SELECT intentos_fallidos FROM usuarios WHERE id = ?');
    $q->execute([$uid]);
    if ((int)$q->fetchColumn() >= LOGIN_MAX_INTENTOS) {
        $b = db()->prepare(
            'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NOW() + INTERVAL ? MINUTE WHERE id = ?'
        );
        $b->execute([BLOQUEO_MINUTOS, $uid]);
        bitacora($uid, null, 'BLOQUEO', 'Cuenta bloqueada por intentos fallidos');
    } else {
        bitacora($uid, null, 'LOGIN_FALLIDO', 'Contraseña incorrecta');
    }
    responder(401, ['ok' => false, 'mensaje' => 'Credenciales inválidas']);
}

db()->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?')->execute([$uid]);

$token = bin2hex(random_bytes(32));
$s = db()->prepare(
    'INSERT INTO sesiones (usuario_id, token_hash, ip, expira_en) VALUES (?, ?, ?, NOW() + INTERVAL ? HOUR)'
);
$s->execute([$uid, hash('sha256', $token), ip_cliente(), SESION_HORAS]);
bitacora($uid, null, 'LOGIN_OK', 'Inicio de sesión');

responder(200, ['ok' => true, 'token' => $token, 'nombre' => $u['nombre'], 'rol' => $u['rol']]);
