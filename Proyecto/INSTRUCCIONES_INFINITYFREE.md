ko# 📊 Guía de Instalación en InfinityFree - Gestión de Gimnasio

## 🔧 Requisitos previos
- Cuenta en **InfinityFree** (www.infinityfree.com) - **GRATIS**
- Cliente FTP como **FileZilla** (recomendado)
- Tu dominio o subdominio proporcionado por InfinityFree

---

## PASO 1️⃣: Crear cuenta en InfinityFree

1. Ve a [www.infinityfree.com](https://www.infinityfree.com)
2. Haz clic en **"Sign Up"**
3. Completa el formulario con:
   - Email
   - Contraseña
   - Aceptar términos
4. **Verifica tu email** (importante)
5. Inicia sesión en el panel de control

---

## PASO 2️⃣: Crear la Base de Datos

1. En el panel de InfinityFree, busca **"Database Manager"** o **"MySQL Manager"**
2. Haz clic en **"Create a new Database"**
3. Dale un nombre a la BD (ej: `gimnasio_db`)
4. **Copia estos datos** (los necesitarás después):
   - **DB Name**: 
   - **DB User**: 
   - **DB Password**: 

---

## PASO 3️⃣: Importar la Base de Datos

1. En el panel, busca **"phpMyAdmin"**
2. Inicia sesión con los datos de BD creados en el paso anterior
3. Haz clic en tu base de datos en el menú izquierdo
4. Haz clic en la pestaña **"Import"**
5. Haz clic en **"Choose File"** y selecciona el archivo: `gimnasio.sql`
6. Haz clic en **"Import"** al final de la página
7. ✅ ¡Listo! Tu base de datos está importada

---

## PASO 4️⃣: Configurar los archivos PHP

### Abre el archivo `conexiongym.php` que subiste y actualiza:

```php
<?php
    $servername = "localhost";
    $username = "tu_usuario_bd";      // El que copiaste en PASO 2
    $password = "tu_contraseña_bd";   // La que copiaste en PASO 2
    $database = "tu_nombre_bd";       // El nombre de la BD creada
    
    $F = mysqli_connect($servername, $username, $password, $database);
    if (!$F) die('Error de conexión: ' . mysqli_connect_error());
    mysqli_set_charset($F, "utf8mb4");

?>
```

---

## PASO 5️⃣: Subir los archivos vía FTP

### Opción A: Usando FileZilla (recomendado)

1. **Descarga FileZilla** si no lo tienes: [filezilla-project.org](https://filezilla-project.org)

2. En el panel de InfinityFree, busca **"FTP Accounts"**

3. Copia estos datos:
   - **FTP Host**: 
   - **FTP Username**: 
   - **FTP Password**: 
   - **Port**: 21

4. Abre FileZilla:
   - Ir a **File** → **Site Manager** → **New site**
   - Nombre del sitio: `InfinityFree`
   - Servidor (Host): Pega el FTP Host
   - Usuario: Pega el FTP Username
   - Contraseña: Pega la FTP Password
   - Puerto: 21
   - Haz clic en **"Connect"**

5. En la ventana izquierda (local), navega a tu carpeta de proyecto: `c:\wamp64\www\Proyecto`

6. En la ventana derecha (servidor), asegúrate de estar en la carpeta `/htdocs` o `/public_html`

7. **Selecciona todos los archivos** de tu proyecto en la izquierda:
   - conexiongym.php ✓ ACTUALIZADO
   - login.php
   - register.php
   - menu.php
   - Login.js
   - Register.js
   - Menú.js
   - gimnasio.css
   - LogoGym.png

8. **Arrastra** los archivos a la ventana derecha (o haz clic derecho → Upload)

9. Espera a que todos suban correctamente ✅

---

## PASO 6️⃣: Acceder a tu aplicación

1. Tu URL será: `http://tu-dominio-infinityfree.com/login.php`
   - Reemplaza `tu-dominio-infinityfree` con el dominio que te dieron

2. O directamente: `http://tu-dominio-infinityfree.com/`

3. Deberías ver la pantalla de login

---

## 🔐 Primeras credenciales (Usuario Admin)

Después de importar la BD, prueba con:
- **Usuario/Email**: (El que creaste en tu BD local)
- **Contraseña**: (La correspondiente)

Si necesitas un usuario admin, ejecuta esta consulta en phpMyAdmin:

```sql
INSERT INTO usuarios (Nombre, Correo, Clave, Rol, Tipo_Suscripcion) 
VALUES ('Admin', 'admin@gimnasio.com', PASSWORD('admin123'), 'admin', 'Premium');
```

---

## ⚠️ Problemas comunes

| Problema | Solución |
|----------|----------|
| Error de conexión BD | Verifica que copiaste bien los datos en `conexiongym.php` |
| Los archivos no suben | Asegúrate de estar en la carpeta `/htdocs` del servidor |
| Página en blanco | Revisa los permisos de los archivos (deben ser 644) |
| Sesiones no funcionan | Algunos servidores requieren permisos especiales en `/tmp` |

---

## 📝 Notas finales

- **Haz backup** de tu BD regularmente desde phpMyAdmin
- **Guarda tus credenciales** de FTP en un lugar seguro
- InfinityFree es gratis pero tiene limitaciones (ej: máximo 5GB)
- Si la aplicación crece mucho, considera migrar a un servidor de pago

¡Tu aplicación estará en línea en 10-15 minutos! 🚀
