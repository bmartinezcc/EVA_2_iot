<?php
declare(strict_types=1);
require '/var/www/eva2_private/lib.php';

exigir_metodo('POST');
$usuario = autenticar();
$in = entrada();
$dispositivo = (int)($in['dispositivo_id'] ?? 0);
$clave = texto($in['clave'] ?? '');

if (!preg_match('/^\d{6}$/', $clave)) {
    responder(422, ['ok' => false, 'mensaje' => 'La clave debe tener 6 dígitos']);
}
if (!dispositivo_valido($dispositivo)) {
    responder(404, ['ok' => false, 'mensaje' => 'Dispositivo no encontrado']);
}

// Límite de intentos fallidos (anti fuerza bruta)
$f = db()->prepare(
    "SELECT COUNT(*) FROM bitacora
      WHERE usuario_id = ? AND evento = 'CLAVE_FALLIDA' AND creado_en > NOW() - INTERVAL 5 MINUTE"
);
$f->execute([$usuario['id']]);
if ((int)$f->fetchColumn() >= CLAVE_MAX_INTENTOS) {
    bitacora($usuario['id'], $dispositivo, 'BLOQUEO', 'Demasiados intentos de clave temporal');
    responder(429, ['ok' => false, 'mensaje' => 'Demasiados intentos. Espera 5 minutos']);
}

$pdo = db();
$pdo->beginTransaction();
$st = $pdo->prepare(
    'SELECT id FROM claves_temporales
      WHERE dispositivo_id = ? AND clave_hash = ? AND usada = 0 AND expira_en > NOW()
      LIMIT 1 FOR UPDATE'
);
$st->execute([$dispositivo, hash_clave_temporal($clave)]);
$idClave = $st->fetchColumn();

if ($idClave === false) {
    $pdo->commit();
    bitacora($usuario['id'], $dispositivo, 'CLAVE_FALLIDA', 'Clave incorrecta o vencida');
    responder(403, ['ok' => false, 'mensaje' => 'Clave incorrecta o vencida']);
}

$pdo->prepare('UPDATE claves_temporales SET usada = 1, usada_por = ?, usada_en = NOW() WHERE id = ?')
    ->execute([$usuario['id'], $idClave]);
$pdo->prepare("UPDATE dispositivos SET estado = 'ABIERTO' WHERE id = ?")->execute([$dispositivo]);
$pdo->commit();

bitacora($usuario['id'], $dispositivo, 'CLAVE_OK', 'Acceso concedido');
responder(200, ['ok' => true, 'mensaje' => 'Acceso concedido: puerta abierta ' . PUERTA_ABIERTA_SEG . ' s']);
