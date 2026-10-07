<?php
declare(strict_types=1);
require '/var/www/eva2_private/lib.php';

exigir_metodo('GET');
$usuario = autenticar();

if ($usuario['rol'] === 'ADMIN') {
    $st = db()->query(
        'SELECT b.evento, b.detalle, b.ip, b.creado_en, u.nombre AS usuario
           FROM bitacora b LEFT JOIN usuarios u ON u.id = b.usuario_id
          ORDER BY b.id DESC LIMIT 30'
    );
} else {
    $st = db()->prepare(
        'SELECT b.evento, b.detalle, NULL AS ip, b.creado_en, u.nombre AS usuario
           FROM bitacora b LEFT JOIN usuarios u ON u.id = b.usuario_id
          WHERE b.usuario_id = ? ORDER BY b.id DESC LIMIT 30'
    );
    $st->execute([$usuario['id']]);
}
responder(200, ['ok' => true, 'eventos' => $st->fetchAll()]);
