# EVA 2 – TI3042 Aplicaciones Móviles para IoT
App Android (Kotlin) + API en la Raspberry virtual + base de datos AWS RDS MySQL.

## Cómo funciona

```
Tablet Android (ADMIN)  ─┐
                         ├─ WiFi ─> Raspbian-IoT (Apache + PHP, API) ──TLS──> AWS RDS MySQL
PC/Emulador (USUARIO)   ─┘                │
                                          └─ dispositivo IoT simulado: puerta ABIERTA / CERRADA
```

La app **nunca se conecta directo a MySQL** (sería inseguro). Habla con la API PHP de la Raspberry
y la Raspberry guarda y consulta todo en RDS.

Flujo de la demo (dos dispositivos Android, criterio 2.1.4):
1. Dispositivo A (ADMIN): inicia sesión → "Generar clave temporal" → aparece una clave de 6 dígitos.
2. Dispositivo B (USUARIO): inicia sesión → escribe la clave en "Ingresar clave temporal".
3. La puerta simulada pasa a **ABIERTO** 10 s en AMBOS dispositivos (se actualiza solo cada 3 s) y todo queda en la bitácora de RDS.

## Contenido

| Carpeta | Qué es |
|---|---|
| `sql/01_crear_tablas.sql` | Crea las 5 tablas en RDS (usuarios, sesiones, dispositivos, claves_temporales, bitacora) |
| `sql/02_usuario_app_minimo_privilegio.sql` | Usuario de BD solo con SELECT/INSERT/UPDATE y TLS obligatorio |
| `raspberry/var-www/html/eva2/` | API PHP (registro, login, logout, estado, generar_clave, validar_clave, historial) |
| `raspberry/var-www/eva2_private/` | `lib.php` y `config.example.php` (fuera de la carpeta web) |
| `EVA_2/` | Proyecto Android Studio ya modificado |

## Paso a paso

### 1. Base de datos (MySQL Workbench → tu RDS)
1. Abre `sql/01_crear_tablas.sql` y ejecútalo completo (si tu BD ya tiene otro nombre, cambia `eva2_iot` por ese nombre).
2. Abre `sql/02_usuario_app_minimo_privilegio.sql`, cambia la contraseña y ejecútalo.
3. En AWS: Security Group de RDS → permite el puerto 3306 **solo** desde la IP desde donde sale tu Raspberry (y tu PC para Workbench), no desde 0.0.0.0/0.

### 2. Raspberry virtual (Raspbian-IoT)
```bash
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php php-mysql
```
Sube las carpetas con FileZilla (sftp, usuario de la Raspberry) a `/home/pi/` y luego:
```bash
sudo mkdir -p /var/www/eva2_private
sudo cp -r /home/pi/eva2/* /var/www/html/eva2/      # carpeta html/eva2 de este zip
sudo cp /home/pi/eva2_private/lib.php /var/www/eva2_private/
sudo cp /home/pi/eva2_private/config.example.php /var/www/eva2_private/config.php
sudo nano /var/www/eva2_private/config.php           # endpoint RDS, usuario eva2_app, clave, PEPPER
sudo wget -O /var/www/eva2_private/global-bundle.pem https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem
openssl rand -hex 32                                 # pega el resultado en CLAVE_PEPPER
sudo chown -R root:www-data /var/www/eva2_private
sudo chmod 750 /var/www/eva2_private && sudo chmod 640 /var/www/eva2_private/*
sudo systemctl restart apache2
```
Prueba desde el PC: abre `http://IP_RASPBERRY/eva2/estado.php` → debe responder `{"ok":false,"mensaje":"Sesión no válida"}` (eso significa que la API funciona).
Si falla la conexión a RDS revisa `sudo tail /var/log/apache2/error.log`.

> Cambia la contraseña por defecto del usuario de la Raspberry (la del taller es de práctica).

### 3. App Android
1. Abre la carpeta `EVA_2` en Android Studio y haz *Sync*.
2. En `Config.kt` cambia `BASE_URL` por `http://IP_DE_TU_RASPBERRY/eva2`.
3. En `res/xml/network_security_config.xml` cambia `192.168.1.50` por esa misma IP.
4. Ejecuta en el emulador **y** en la tablet (ambos deben alcanzar la IP de la Raspberry; la VM debe estar en modo adaptador puente).
5. Regístrate en cada dispositivo. Para tener un ADMIN ejecuta en Workbench:
   `UPDATE usuarios SET rol='ADMIN' WHERE email='tu_correo';`
6. Cierra sesión y vuelve a entrar para que la app lea el rol nuevo.

## Seguridad aplicada (ISO/IEC 27400 – criterio 2.1.3.10 / 2.1.4.14)

| Medida | Dónde |
|---|---|
| Contraseñas con hash bcrypt, nunca en texto plano | `registro.php`, `login.php` |
| Token de sesión aleatorio de 256 bits, en BD solo su hash SHA-256, vence a las 8 h y se revoca al cerrar sesión | `login.php`, `logout.php`, `lib.php` |
| Sesión en el teléfono cifrada AES-256 (Android Keystore); la contraseña nunca se guarda | `SessionManager.kt` |
| Claves temporales de 6 dígitos: vencen, son de un solo uso, solo una vigente por dispositivo y se guardan como HMAC con secreto en el servidor | `generar_clave.php`, `validar_clave.php` |
| Control de acceso por roles: solo ADMIN genera claves; USUARIO solo ve su historial | `autenticar()` en `lib.php` |
| Protección contra fuerza bruta: bloqueo de cuenta tras 5 logins fallidos y límite de 5 intentos de clave cada 5 min | `login.php`, `validar_clave.php` |
| Sentencias preparadas (anti inyección SQL) y validación de entradas en servidor y app | todo el backend |
| Conexión API → RDS cifrada con TLS verificando el certificado de AWS; usuario de BD con mínimo privilegio | `config.example.php`, `02_usuario_app...sql` |
| Credenciales de BD fuera de la carpeta web | `/var/www/eva2_private/` |
| Bitácora de auditoría (sin guardar contraseñas ni claves) | tabla `bitacora` |
| Permisos mínimos en la app (INTERNET y estado de red), sin backups, tráfico en claro bloqueado salvo la IP del laboratorio | `AndroidManifest.xml`, `network_security_config.xml` |

**Limitación a mencionar en la defensa:** el tramo app → Raspberry va por HTTP dentro de la red WiFi del taller
(excepción acotada a una IP). Para producción se activaría HTTPS en Apache (`a2enmod ssl`) con un certificado
y se quitaría el `<domain-config>` de `network_security_config.xml`.

## Pruebas (criterio 2.1.3.11) – checklist para el video
- Registro con correo repetido → "Ese correo ya está registrado".
- Login con contraseña mala → "Credenciales inválidas"; 5 veces → cuenta bloqueada.
- USUARIO no ve el bloque de generar clave (y si llamara a la API recibe 403).
- Clave correcta → "Acceso concedido", estado ABIERTO en ambos dispositivos; misma clave otra vez → rechazada.
- Clave vencida / incorrecta → rechazada y registrada en el historial.
- Mostrar en Workbench las filas nuevas de `usuarios`, `claves_temporales` y `bitacora`.
