# Manual Técnico

Este manual describe la arquitectura, la estructura de código, la base de datos y cómo desplegar y mantener la aplicación.

## Visión general

- Frontend: HTML5/CSS3/JavaScript (principalmente `index.php`, `style.css`, `script.js`).
- Backend: PHP (API en `api.php`).
- Base de datos: MySQL (`database.sql`).
- Autenticación: sesiones PHP del servidor; la tabla `sesiones` queda como base para una autenticación por token futura.

## Estructura del proyecto

```
Proyectoextra/
├── index.php        # Punto de entrada (login + UI)
├── api.php          # API REST: recibe `accion` y responde JSON
├── config.php       # Datos de conexión y constantes
├── script.js        # Lógica cliente: peticiones a la API, UI dinámico
├── style.css        # Estilos
├── database.sql     # Esquema inicial de la BD
└── android/         # Cliente Android (si aplica)
```

## Modelo de datos

- `usuarios` (id, nombre, email, password, tipo, fecha_registro).
- `notificaciones` (id, id_admin, id_usuario, titulo, mensaje, importancia, leida, fecha_envio, fecha_lectura).
- `sesiones` (id, id_usuario, token, fecha_creacion, fecha_expiracion) para autenticación por token futura.

Consulta `database.sql` para detalles de columnas e índices.

## Endpoints principales

- `GET /api.php?accion=obtenerNotificaciones&id_usuario={id}`
- `GET /api.php?accion=obtenerUsuarios`
- `POST /api.php?accion=enviarNotificacion` (body: `id_usuario`, `titulo`, `mensaje`, `importancia`)
- `POST /api.php?accion=marcarComoLeida` (body: `id_notificacion`)

Implementación:
- `api.php` valida la sesión PHP antes de ejecutar operaciones.
- Respuesta JSON con `{ exito: bool, mensaje: "..." }` o estructuras equivalentes según la acción.

## Configuración y despliegue

1. Configura `config.php` con las credenciales de la BD.
2. En producción, desactiva `display_errors` y habilita HTTPS.
3. Migrar a contraseñas hasheadas y considerar JWT para autenticación API.

## Seguridad (recomendaciones)

- Usar consultas preparadas para evitar SQL Injection.
- Proteger endpoints con verificación de roles (`tipo` de usuario).
- Implementar protección CSRF en formularios.
- Reemplazar almacenamiento de contraseñas en claro por `password_hash`/`password_verify`.

## Desarrollo y pruebas

- Pruebas rápidas con `curl` o Postman:

```bash
curl "http://localhost/Proyectoextra/api.php?accion=obtenerUsuarios"
```

- Añadir registros de prueba en `database.sql` o vía panel de administración.

## Cómo contribuir

- Crear rama `feature/descripcion` → realizar cambios → abrir Pull Request.
- Documentar cualquier cambio en el esquema de BD en `database.sql` y en este manual.

---

Archivo relacionado: `README.md` (índice)
