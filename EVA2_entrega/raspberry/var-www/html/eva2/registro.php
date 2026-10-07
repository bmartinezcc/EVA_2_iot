<?php
declare(strict_types=1);
require '/var/www/eva2_private/lib.php';

exigir_metodo('POST');
$in = entrada();
$nombre = texto($in['nombre'] ?? '');
$email  = strtolower(texto($in['email'] ?? ''));
$clave  = is_string($in['password'] ?? null) ? $in['password'] : '';

if (largo($nombre) < 2 || largo($nombre) > 80) {
    responder(422, ['ok' => false, 'mensaje' => 'El nombre debe tener entre 2 y 80 caracteres']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    responder(422, ['ok' => false, 'mensaje' => 'Correo electrónico inválido']);
}
if (strlen($clave) < 8 || strlen($clave) > 72 || !preg_match('/[A-Za-z]/', $clave) || !preg_match('/\d/', $clave)) {
    responder(422, ['ok' => false, 'mensaje' => 'La contraseña debe tener 8 a 72 caracteres, con letras y números']);
}

try {
    $st = db()->prepare('INSERT INTO usuarios (nombre, email, password_hash) VALUES (?, ?, ?)');
    $st->execute([$nombre, $email, password_hash($clave, PASSWORD_DEFAULT)]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        responder(409, ['ok' => false, 'mensaje' => 'Ese correo ya está registrado']);
    }
    throw $e;
}
$id = (int)db()->lastInsertId();
bitacora($id, null, 'REGISTRO', 'Usuario registrado');
responder(201, ['ok' => true, 'mensaje' => 'Registro exitoso, ahora inicia sesión']);
