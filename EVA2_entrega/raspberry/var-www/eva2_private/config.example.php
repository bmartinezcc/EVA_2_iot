<?php
// Copiar como /var/www/eva2_private/config.php y completar los valores.
// Este archivo queda FUERA de /var/www/html para que nadie pueda leerlo por web.
define('DB_HOST', 'database-1.XXXXXXXX.us-east-1.rds.amazonaws.com'); // endpoint de RDS
define('DB_PORT', 3306);
define('DB_NAME', 'eva2_iot');
define('DB_USER', 'eva2_app');
define('DB_PASS', 'CAMBIA_ESTA_CLAVE_LARGA_Y_UNICA');
// Certificado raíz de AWS RDS (conexión TLS a la base de datos):
// https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem
define('DB_SSL_CA', '/var/www/eva2_private/global-bundle.pem');
// Secreto para proteger las claves temporales. Generar con: openssl rand -hex 32
define('CLAVE_PEPPER', 'REEMPLAZAR_POR_64_CARACTERES_HEX');
// Parámetros de seguridad
define('SESION_HORAS', 8);
define('LOGIN_MAX_INTENTOS', 5);
define('BLOQUEO_MINUTOS', 5);
define('CLAVE_MAX_INTENTOS', 5);    // intentos fallidos de clave por usuario cada 5 min
define('PUERTA_ABIERTA_SEG', 10);   // segundos que queda "ABIERTA" la puerta simulada
