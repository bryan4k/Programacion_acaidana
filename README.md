# Programación de cultos

Aplicación PHP para crear, guardar, editar, imprimir y exportar a Word documentos con una o varias programaciones. En producción, las plantillas se almacenan cifradas en MySQL y cada operación exige una sesión autenticada.

Cada plantilla puede contener hasta 50 programaciones independientes que comparten el logo y el diseño. La interfaz permite agregarlas, duplicarlas, ordenarlas y eliminarlas; sus títulos, fechas, tabla, versículo y coordinadores se editan por separado.

Cada programación ocupa exactamente una hoja A4. La aplicación limita las filas según el espacio disponible y bloquea el guardado, la impresión o la exportación si alguna programación lo excede. El diseño compartido conserva el fondo central, las imágenes laterales de la cabecera, la marca de agua, el pie, el marco del versículo y sus ajustes de tamaño, opacidad y posición. Las imágenes admitidas son PNG, JPEG y WebP de hasta 2 MB cada una; para marcos y adornos se recomienda PNG con transparencia.

## Requisitos

- PHP 8.2 o superior.
- MySQL 8 o MariaDB 10.5 o superior.
- Extensiones PHP `pdo_mysql` y `sodium`.
- Certificado HTTPS activo.

## Instalación segura en Hostinger

1. Selecciona PHP 8.2 o superior en hPanel y confirma que estén disponibles `pdo_mysql` y `sodium`.
2. Crea una base de datos y un usuario de base de datos desde hPanel. Usa una contraseña larga, aleatoria y exclusiva.
3. Abre phpMyAdmin, selecciona la base de datos e importa `database.sql` una sola vez.
4. Sube `index.php`, `login.php`, `logout.php`, `api.php`, `.htaccess`, `.user.ini` y las carpetas `app` y `assets` directamente dentro de `public_html`. Verifica que los archivos ocultos `.htaccess` y `.user.ini` también se hayan subido.
5. Copia el contenido de `config.example.php` a un archivo llamado `programacion-config.php` ubicado **un nivel por encima de `public_html`**. No lo coloques dentro del directorio público. Esta ruta automática presupone que la aplicación está en la raíz de `public_html`; para una subcarpeta define la variable de entorno `PROGRAMACION_CONFIG` con la ruta absoluta.
6. Completa el DSN, usuario, contraseña de MySQL y `timezone` en ese archivo.
7. Genera las dos claves desde la terminal de Hostinger o desde un equipo confiable. Nunca uses generadores web:

```bash
openssl rand -base64 32
openssl rand -hex 32
```

8. Coloca el primer resultado en `encryption_keys[1]` y el segundo en `setup_token`.
9. Comprueba que el dominio abre exclusivamente mediante HTTPS y visita la aplicación. Si usas Cloudflare, selecciona SSL/TLS **Full (strict)**; no uses el modo Flexible.
10. Como todavía no existen usuarios, aparecerá el formulario para crear la primera cuenta. Usa una contraseña única de al menos 14 caracteres y escribe el `setup_token`.
11. Después de crear la cuenta, elimina el valor de `setup_token` del archivo de configuración. El alta de cuentas también queda desactivada automáticamente al existir el primer usuario.

Activa el firewall o WAF disponible en Hostinger y, si utilizas Cloudflare, habilita su protección contra bots y limita solicitudes repetidas a `login.php`. La aplicación incluye límites propios, pero detener tráfico abusivo antes de ejecutar PHP protege mejor los recursos del hosting compartido.

Antes de poner el sitio en uso, confirma en hPanel que `post_max_size` sea al menos `24M`, que el límite de memoria de PHP sea al menos `128M` y que `max_allowed_packet` de MySQL acepte las plantillas que utilizarás. Los cambios de `.user.ini` pueden tardar varios minutos en aplicarse. Haz una prueba completa guardando una plantilla con varias programaciones e imágenes.

Después de desplegar, comprueba que las rutas `/.htaccess`, `/.user.ini`, `/database.sql`, `/README.md` y `/config.example.php` devuelvan `403` o `404`, nunca su contenido. Revisa los logs de PHP desde hPanel si aparece un error interno.

Si tienes terminal, protege adicionalmente el archivo privado:

```bash
chmod 600 ~/programacion-config.php
```

## Protección implementada

- Cifrado autenticado Secretbox mediante Sodium para el contenido y los logos de las plantillas. Los nombres y fechas de modificación permanecen como metadatos visibles en MySQL.
- Clave de cifrado almacenada fuera de `public_html` y separada de MySQL.
- Contraseñas con Argon2id cuando está disponible, usando el algoritmo seguro predeterminado de PHP como respaldo.
- Consultas PDO preparadas y separación obligatoria de plantillas por usuario.
- Tokens CSRF de 256 bits para guardar, eliminar y cerrar sesión.
- Cookies de sesión `HttpOnly`, `SameSite=Strict` y `Secure` bajo HTTPS.
- Rotación del identificador de sesión, expiración por 30 minutos de inactividad y límite absoluto de 8 horas.
- Limitación independiente por cuenta e IP durante 15 minutos y mensajes que no revelan si una cuenta existe.
- Política CSP restrictiva, bloqueo de iframes, HSTS, protección MIME y ausencia de CORS.
- Validación en servidor de fechas, textos, filas, encabezados y formatos de imagen.
- Control de versiones para evitar sobrescrituras desde dos pestañas o dispositivos.
- Cuota de 200 plantillas y 100 MB cifrados por usuario, comprobada de forma transaccional.
- Aplicación bloqueada por defecto cuando falta configuración o HTTPS.

## Respaldo indispensable

Conserva una copia privada de `programacion-config.php`, especialmente de `encryption_keys`. Una copia de MySQL sin esas claves no puede descifrarse. No las envíes por correo, mensajería ni las guardes dentro de `public_html`.

Para respaldar la información necesitas dos elementos separados:

1. Una exportación de la base de datos desde phpMyAdmin.
2. Una copia cifrada o almacenada en un gestor de contraseñas de todas las `encryption_keys`.

Para rotar la clave, conserva la entrada anterior, agrega una versión nueva y cambia `active_key_version`. Las plantillas se volverán a cifrar con la versión activa la próxima vez que se guarden. No elimines una clave anterior mientras existan plantillas asociadas a ella.

## Desarrollo local

El modo local debe habilitarse explícitamente y solo acepta conexiones desde `127.0.0.1` o `::1`:

```bash
APP_ENV=development php -S 127.0.0.1:8080
```

En este modo las plantillas se guardan únicamente en el navegador para no exigir MySQL durante el diseño. El modo de producción nunca usa este mecanismo como respaldo silencioso.
