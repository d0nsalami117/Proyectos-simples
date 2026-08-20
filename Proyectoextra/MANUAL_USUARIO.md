# Manual de Usuario

Este manual explica cómo instalar y utilizar la aplicación desde la perspectiva del usuario y del administrador.

## Requisitos previos

- Servidor local con PHP y MySQL (WAMP/XAMPP o similar).
- Importar la base de datos con `database.sql`.
- Configurar `config.php` con las credenciales correctas.

## Instalación rápida

1. Copia la carpeta `Proyectoextra` en la raíz del servidor web (por ejemplo `www` en WAMP).
2. Importa la base de datos:

```bash
mysql -u root < database.sql
```

3. Edita `config.php` si necesitas cambiar usuario/contraseña/host de la BD.
4. Accede desde el navegador a `http://localhost/Proyectoextra/`.

## Acceso

- Abre `index.php` y realiza el login. Si usas datos de prueba, el admin es `admin@example.com`.

## Uso para Administrador

1. Inicia sesión como administrador.
2. Carga la lista de usuarios desde el panel.
3. Selecciona un usuario o la opción "Todos los usuarios", elige la importancia, completa `titulo` y `mensaje`, y pulsa enviar.
4. Visualiza historial y estado de notificaciones (leída/no leída).
5. Marca notificaciones como leídas desde la interfaz cuando corresponda.

## Uso para Moderador

1. Inicia sesión como moderador.
2. Puede revisar sus notificaciones recibidas, pero no dispone del panel para enviar mensajes.
3. Usa los filtros para revisar leídas y no leídas.

## Uso para Usuario

1. Inicia sesión con tu cuenta.
2. Revisa la lista de notificaciones recibidas.
3. Filtra por `no leídas` o `leídas` según necesites.

## Resolución de problemas comunes

- "Conexión fallida": verifica que MySQL esté corriendo y que `config.php` tenga las credenciales correctas.
- Acceso desde móvil: usa la IP local del equipo en vez de `localhost` (ej. `http://192.168.x.y/Proyectoextra/`).
- CSS no carga: presiona `Ctrl+F5` o revisa que `style.css` exista en la carpeta.

## Buenas prácticas de uso

- No compartir credenciales de administrador.
- Probar envíos con usuarios de prueba antes de notificar a usuarios reales.
- La opción de envío masivo está pensada para avisos generales; úsala solo cuando el mensaje aplique a todos.

---

Archivo relacionado: `README.md` (índice)
