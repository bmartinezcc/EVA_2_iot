<?php
declare(strict_types=1);
require '/var/www/eva2_private/lib.php';

exigir_metodo('POST');
$usuario = autenticar();
db()->prepare('UPDATE sesiones SET revocada = 1 WHERE token_hash = ?')
    ->execute([hash('sha256', token_de_cabecera())]);
bitacora($usuario['id'], null, 'LOGOUT', 'Cierre de sesión');
responder(200, ['ok' => true, 'mensaje' => 'Sesión cerrada']);
