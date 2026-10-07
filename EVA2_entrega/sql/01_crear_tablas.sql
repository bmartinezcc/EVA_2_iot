-- =====================================================================
-- EVA 2 - TI3042 - Aplicaciones Móviles para IoT
-- Esquema MySQL para AWS RDS (MySQL 8.x)
-- Ejecutar en MySQL Workbench conectado a tu instancia RDS.
-- Si tu base de datos ya existe con otro nombre, cambia "eva2_iot"
-- por ese nombre (en CREATE DATABASE y en USE).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS eva2_iot
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eva2_iot;

-- ---------------------------------------------------------------------
-- 1) USUARIOS: registro e inicio de sesión
--    * password_hash guarda un hash bcrypt (NUNCA la clave en texto plano)
--    * rol: ADMIN genera claves temporales, USUARIO solo las ingresa
--    * intentos_fallidos / bloqueado_hasta: bloqueo ante fuerza bruta
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(80)  NOT NULL,
  email             VARCHAR(120) NOT NULL,
  password_hash     VARCHAR(255) NOT NULL,
  rol               ENUM('ADMIN','USUARIO') NOT NULL DEFAULT 'USUARIO',
  activo            TINYINT(1)   NOT NULL DEFAULT 1,
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta   DATETIME     NULL,
  creado_en         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2) SESIONES: token de sesión (se guarda solo su hash SHA-256)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sesiones (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  ip          VARCHAR(45)  NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_en   DATETIME     NOT NULL,
  revocada    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sesiones_token (token_hash),
  KEY ix_sesiones_usuario (usuario_id),
  CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3) DISPOSITIVOS: el dispositivo IoT (Raspberry virtualizada) que se
--    controla y monitorea. "estado" simula una cerradura/puerta.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dispositivos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(80)  NOT NULL,
  tipo           VARCHAR(40)  NOT NULL DEFAULT 'RASPBERRY_PI',
  ubicacion      VARCHAR(100) NULL,
  estado         ENUM('CERRADO','ABIERTO') NOT NULL DEFAULT 'CERRADO',
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4) CLAVES_TEMPORALES: claves de 6 dígitos con vencimiento y de un
--    solo uso. Se guarda un HMAC-SHA256 de la clave, no la clave.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS claves_temporales (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dispositivo_id INT UNSIGNED NOT NULL,
  creada_por     INT UNSIGNED NOT NULL,
  clave_hash     CHAR(64)     NOT NULL,
  creada_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_en      DATETIME     NOT NULL,
  usada          TINYINT(1)   NOT NULL DEFAULT 0,
  usada_por      INT UNSIGNED NULL,
  usada_en       DATETIME     NULL,
  PRIMARY KEY (id),
  KEY ix_claves_busqueda (dispositivo_id, clave_hash, usada, expira_en),
  CONSTRAINT fk_claves_dispositivo FOREIGN KEY (dispositivo_id)
    REFERENCES dispositivos (id) ON DELETE CASCADE,
  CONSTRAINT fk_claves_creada_por FOREIGN KEY (creada_por)
    REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_claves_usada_por FOREIGN KEY (usada_por)
    REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5) BITACORA: registro de auditoría de todo lo que ocurre en la app
--    (registro, logins, claves generadas/usadas, bloqueos). Nunca se
--    guardan contraseñas ni claves temporales.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bitacora (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id     INT UNSIGNED NULL,
  dispositivo_id INT UNSIGNED NULL,
  evento         ENUM('REGISTRO','LOGIN_OK','LOGIN_FALLIDO','LOGOUT',
                      'CLAVE_GENERADA','CLAVE_OK','CLAVE_FALLIDA','BLOQUEO')
                 NOT NULL,
  detalle        VARCHAR(200) NULL,
  ip             VARCHAR(45)  NULL,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_bitacora_usuario (usuario_id, creado_en),
  KEY ix_bitacora_fecha (creado_en),
  CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_bitacora_dispositivo FOREIGN KEY (dispositivo_id)
    REFERENCES dispositivos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Dato inicial: el dispositivo IoT simulado (id = 1)
-- ---------------------------------------------------------------------
INSERT INTO dispositivos (nombre, tipo, ubicacion)
SELECT 'Raspbian-IoT', 'RASPBERRY_PI', 'Puerta principal (simulada)'
WHERE NOT EXISTS (SELECT 1 FROM dispositivos);

-- ---------------------------------------------------------------------
-- Convertir a un usuario en ADMIN (después de registrarlo desde la app):
-- UPDATE usuarios SET rol = 'ADMIN' WHERE email = 'tu_correo@ejemplo.cl';
-- ---------------------------------------------------------------------
