<?php
declare(strict_types=1);
require '/var/www/eva2_private/lib.php';

exigir_metodo('POST');
$admin = autenticar(['ADMIN']);          // solo el rol ADMIN
$in = entrada();
$dispositivo = (int)($in['dispositivo_id'] ?? 0);
$minutos = (int)($in['minutos'] ?? 5);

if (!dispositivo_valido($dispositivo)) {
    responder(404, ['ok' => false, 'mensaje' => 'Dispositivo no encontrado']);
}
if ($minutos < 1 || $minutos > 60) {
    responder(422, ['ok' => false, 'mensaje' => 'Los minutos de vigencia deben estar entre 1 y 60']);
}

$clave = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$pdo = db();
$pdo->beginTransaction();
// Solo una clave vigente por dispositivo: se vencen las anteriores
$pdo->prepare('UPDATE claves_temporales SET expira_en = NOW() WHERE dispositivo_id = ? AND usada = 0 AND expira_en > NOW()')
    ->execute([$dispositivo]);
$pdo->prepare(
    'INSERT INTO claves_temporales (dispositivo_id, creada_por, clave_hash, expira_en)
     VALUES (?, ?, ?, NOW() + INTERVAL ? MINUTE)'
)->execute([$dispositivo, $admin['id'], hash_clave_temporal($clave), $minutos]);
$idClave = (int)$pdo->lastInsertId();
$q = $pdo->prepare('SELECT expira_en FROM claves_temporales WHERE id = ?');
$q->execute([$idClave]);
$expira = (string)$q->fetchColumn();
$pdo->commit();

bitacora($admin['id'], $dispositivo, 'CLAVE_GENERADA', "Vigencia $minutos min");   // la clave NO se registra
responder(201, ['ok' => true, 'clave' => $clave, 'expira_en' => $expira, 'minutos' => $minutos]);
