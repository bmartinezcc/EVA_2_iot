<?php
declare(strict_types=1);
require '/var/www/eva2_private/lib.php';

exigir_metodo('GET');
autenticar();
$id = (int)($_GET['dispositivo_id'] ?? 1);

// La puerta simulada se vuelve a cerrar sola pasados unos segundos
db()->prepare(
    "UPDATE dispositivos SET estado = 'CERRADO'
      WHERE id = ? AND estado = 'ABIERTO' AND actualizado_en < NOW() - INTERVAL ? SECOND"
)->execute([$id, PUERTA_ABIERTA_SEG]);

$st = db()->prepare('SELECT id, nombre, ubicacion, estado, actualizado_en FROM dispositivos WHERE id = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) {
    responder(404, ['ok' => false, 'mensaje' => 'Dispositivo no encontrado']);
}
responder(200, ['ok' => true, 'dispositivo' => $d]);
