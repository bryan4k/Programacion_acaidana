# Programación de cultos

Aplicación PHP para crear, guardar, editar, imprimir y exportar a Word documentos con una o varias programaciones. En producción, las plantillas se almacenan cifradas en MySQL y cada operación exige una sesión autenticada.

Cada plantilla puede contener hasta 50 programaciones independientes que comparten el logo y el diseño. La interfaz permite agregarlas, duplicarlas, ordenarlas y eliminarlas; sus títulos, fechas, tabla, versículo y coordinadores se editan por separado.

El botón **Nueva de ujieres** crea un documento con diseño propio y columnas de fecha, nombres y uniforme. Cada uniforme se configura por fila como camisa o blusa con falda o pantalón, o como vestido; los colores seleccionados se aplican directamente a los dibujos SVG mostrados en la hoja y en la impresión, sin escribir el nombre del color.

Cada programación ocupa exactamente una hoja A4. La aplicación limita las filas según el espacio disponible y bloquea el guardado, la impresión o la exportación si alguna programación lo excede. El diseño compartido conserva el fondo central, las imágenes laterales de la cabecera, la marca de agua, el pie, el marco del versículo y sus ajustes de tamaño, opacidad y posición. Las imágenes admitidas son PNG, JPEG y WebP de hasta 2 MB cada una; para marcos y adornos se recomienda PNG con transparencia.

## Requisitos

- PHP 8.2 o superior.
- MySQL 8 o MariaDB 10.5 o superior.
- Extensiones PHP `pdo_mysql` y `sodium`.
- Certificado HTTPS activo.

## Despliegue automático con Git en Hostinger

La integración Git de Hostinger despliega automáticamente cada cambio enviado a la rama `main`. No es necesario subir ni editar archivos con el administrador de archivos.

1. En hPanel selecciona PHP 8.2 o superior y confirma que estén disponibles `pdo_mysql` y `sodium`.
2. Activa el certificado SSL del dominio. Si usas Cloudflare, selecciona SSL/TLS **Full (strict)**; no uses el modo Flexible.
3. En hPanel crea una base de datos MySQL y su usuario. Guarda el servidor, nombre, usuario y contraseña que muestra Hostinger. No necesitas abrir phpMyAdmin ni importar `database.sql`.
4. Entra a **Websites → Dashboard → Advanced → Git** y pulsa **Continue with GitHub**.
5. Autoriza Hostinger para acceder al repositorio `bryan4k/Programacion_acaidana`.
6. Selecciona la rama `main`, establece **Root directory** en `public_html` y pulsa **Deploy**. El sitio debe estar vacío o respaldado porque Hostinger reemplazará el contenido del directorio.
7. Activa **Auto-deployment** para que cada `push` nuevo a `main` se publique automáticamente.
8. Abre `https://TU-DOMINIO/setup.php` inmediatamente después del primer despliegue.
9. Completa los datos de MySQL y crea la cuenta administradora. El instalador crea las tablas, genera una clave de cifrado y guarda `programacion-config.php` fuera de `public_html` con permisos privados.
10. Al terminar entrarás directamente a la aplicación. Desde ese momento `setup.php` queda bloqueado y redirige al inicio de sesión.

Los futuros despliegues Git solo reemplazan `public_html`. La configuración privada y las claves permanecen un nivel por encima, por lo que no se sobrescriben ni se publican en GitHub.

Activa el firewall o WAF disponible en Hostinger y, si utilizas Cloudflare, habilita su protección contra bots y limita solicitudes repetidas a `login.php`. La aplicación incluye límites propios, pero detener tráfico abusivo antes de ejecutar PHP protege mejor los recursos del hosting compartido.

Antes de poner el sitio en uso, confirma en hPanel que `post_max_size` sea al menos `24M`, que el límite de memoria de PHP sea al menos `128M` y que `max_allowed_packet` de MySQL acepte las plantillas que utilizarás. Los cambios de `.user.ini` pueden tardar varios minutos en aplicarse. Haz una prueba completa guardando una plantilla con varias programaciones e imágenes.

Después de desplegar, comprueba que las rutas `/.htaccess`, `/.user.ini`, `/database.sql`, `/README.md`, `/config.example.php` y `/programacion-config.php` devuelvan `403` o `404`, nunca su contenido. Revisa los logs de PHP desde hPanel si aparece un error interno.

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
