# 📬 Sistema de Notificaciones

Un sistema sencillo para que un administrador envíe notificaciones a usuarios. Este README ahora contiene dos manuales: uno de usuario (cómo usar la aplicación) y otro técnico (cómo está construido y cómo mantenerlo).

## 📚 Índice

- [Manual de Usuario](MANUAL_USUARIO.md)
- [Manual Técnico](MANUAL_TECNICO.md)
- Datos de prueba
- Contacto y versión
---

**Manual de Usuario**

### Requisitos previos

- Tener instalado un servidor local (WAMP/XAMPP) con PHP y MySQL.
- Importar la base de datos usando `database.sql`.
- Configurar conexión en `config.php`.

### Instalación rápida

1. Coloca la carpeta `Proyectoextra` en la raíz de tu servidor web (ej. `www` en WAMP).
2. Importa la base de datos:

```bash
mysql -u root < database.sql
```

3. Edita `config.php` y ajusta credenciales de la BD si es necesario.
4. Abre en el navegador: `http://localhost/Proyectoextra/`

### Acceso

- Entra en `index.php`. Si usas los datos de prueba, accede con el usuario admin (admin@example.com).

### Uso (flujo para un admin)

1. Inicia sesión como administrador.
2. En el panel principal carga la lista de usuarios.
3. Para enviar una notificación: selecciona el usuario, escribe `titulo` y `mensaje`, y pulsa enviar.
4. El usuario receptor podrá ver la notificación en su lista; el admin puede ver el historial.
5. Marcar notificaciones como leídas se hace desde la interfaz de notificaciones.

### Uso (flujo para un usuario)

1. Inicia sesión con tu cuenta.
2. En la vista de notificaciones verás las entradas recibidas.
3. Usa filtros para ver solo las no leídas o las leídas.

### Consejos y resolución de problemas comunes

- Si aparece "Conexión fallida", revisa `config.php` y que MySQL esté corriendo.
- Si la app no se conecta desde el móvil, usa la IP local de la PC en vez de `localhost` y abre `http://<TU_IP>/Proyectoextra/`.
- Si no se ven estilos, presiona `Ctrl+F5` para forzar recarga.

---

**Manual Técnico**

### Visión general de la arquitectura

- Frontend: HTML/CSS/JavaScript (archivo principal `index.php`, `style.css`, `script.js`).
- Backend: PHP (archivo principal `api.php` maneja la API REST y acciones).
- Persistencia: MySQL, script de creación en `database.sql`.
- Autenticación: sistema de sesiones simple (tabla `sesiones`).

### Estructura de proyecto

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

### Base de datos (modelo)

- `usuarios`: almacena cuentas, tipos y credenciales (hash simple/plan en versión inicial).
- `notificaciones`: referencia al emisor (admin) y receptor (usuario), título, cuerpo, prioridad `importancia`, fecha y estado `leida`.
- `sesiones`: tabla pensada para autenticación por token en futuras versiones; hoy el backend usa sesiones PHP estándar.

Puedes ver el esquema completo en `database.sql`.

### Endpoints principales (implementación)

- `GET /api.php?accion=obtenerNotificaciones&id_usuario={id}` — devuelve JSON con las notificaciones.
- `GET /api.php?accion=obtenerUsuarios` — lista usuarios.
- `POST /api.php?accion=enviarNotificacion` — parámetros: `id_usuario`, `titulo`, `mensaje`, `importancia`.
- `POST /api.php?accion=marcarComoLeida` — parámetro: `id_notificacion`.

Notas de implementación:
- `api.php` valida la sesión PHP antes de procesar acciones.
- Responde siempre JSON con estructura uniforme: `{ exito: bool, mensaje: "..." }` u objetos similares.

### Configuración y despliegue

1. Asegúrate de que `config.php` contenga las credenciales correctas.
2. En producción, cambia `display_errors` off y configura TLS (HTTPS).
3. Recomendado: migrar a contraseñas hasheadas (bcrypt) y usar tokens JWT para la API.

### Seguridad (puntos críticos a mejorar)

- Validación y saneamiento de entradas (prevenir SQL Injection). Usar consultas preparadas.
- Protección CSRF para formularios POST.
- Hash de contraseñas (password_hash / password_verify en PHP).
- Control de acceso por roles (verificar `tipo` en acciones que requieren admin).

### Desarrollo y pruebas

- Para probar la API puedes usar `curl` o Postman. Ejemplo:

```bash
curl "http://localhost/Proyectoextra/api.php?accion=obtenerUsuarios"
```

- Añade registros de prueba en `database.sql` o usa el panel de administración (si lo creas).

### Cómo contribuir

- Fork del repositorio → crear una rama `feature/mi-cambio` → abrir Pull Request.
- Escribe cambios pequeños y documenta las migraciones de BD.

---

## 🗂️ Datos de prueba (referencia rápida)

| ID | Nombre | Email | Tipo |
|:--:|--------|-------|------|
| 1 | Admin Principal | admin@example.com | admin |
| 2 | Juan Pérez | juan@example.com | usuario |
| 3 | María García | maria@example.com | usuario |
| 4 | Carlos López | carlos@example.com | usuario |

## 📧 Contacto

Proyecto en desarrollo. Para dudas o contribuciones abre un issue o contacta al autor del proyecto.

---

**v1.1** - 20 de mayo de 2026
