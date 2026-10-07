-- =====================================================================
-- Usuario de BD exclusivo para la API (principio de mínimo privilegio,
-- ISO/IEC 27400). La API NO debe conectarse con el usuario "admin" de RDS.
-- Ejecutar con el usuario administrador de RDS. Cambia la contraseña.
-- =====================================================================
CREATE USER IF NOT EXISTS 'eva2_app'@'%'
  IDENTIFIED BY 'CAMBIA_ESTA_CLAVE_LARGA_Y_UNICA'
  REQUIRE SSL;                       -- solo conexiones cifradas con TLS

GRANT SELECT, INSERT, UPDATE ON eva2_iot.* TO 'eva2_app'@'%';
FLUSH PRIVILEGES;
