# ✅ Checklist de Despliegue - InfinityFree

## Antes de subir

- [ ] Cuenta de InfinityFree creada y verificada
- [ ] Base de datos creada en InfinityFree
- [ ] Archivo `gimnasio.sql` importado en phpMyAdmin
- [ ] FileZilla descargado e instalado
- [ ] Datos de FTP copiados del panel de InfinityFree
- [ ] Archivo `conexiongym.php` actualizado con credenciales correctas

## Durante la subida

- [ ] Conectado a FTP con FileZilla
- [ ] Estoy en la carpeta `/htdocs` o `/public_html`
- [ ] Todos los archivos PHP están subidos:
  - [ ] conexiongym.php
  - [ ] login.php
  - [ ] register.php
  - [ ] menu.php
- [ ] Todos los archivos JavaScript están subidos:
  - [ ] Login.js
  - [ ] Register.js
  - [ ] Menú.js
- [ ] Archivo CSS está subido:
  - [ ] gimnasio.css
- [ ] Imágenes están subidas:
  - [ ] LogoGym.png
- [ ] Transferencia completada sin errores

## Después de subir

- [ ] Puedo acceder a `http://tu-dominio/login.php`
- [ ] La página carga correctamente
- [ ] Las imágenes se ven
- [ ] El CSS se aplicó correctamente
- [ ] Puedo registrar un usuario nuevo
- [ ] Puedo hacer login con un usuario
- [ ] El menú carga después de login
- [ ] Las funciones del sistema funcionan

## URLs útiles para InfinityFree

```
Panel de Control: https://cpanel.infinityfree.com
Gestor de BD: https://cpanel.infinityfree.com/database
phpMyAdmin: https://cpanel.infinityfree.com/phpmyadmin
Gestor FTP: https://cpanel.infinityfree.com/ftp
```

---

## 🆘 Si algo no funciona

1. **Error 500 Internal Server Error**
   - Verifica que `conexiongym.php` tenga las credenciales correctas
   - Abre phpMyAdmin y confirma que la BD existe

2. **No se conecta a la base de datos**
   - Copia exactamente las credenciales de BD desde InfinityFree
   - Verifica que el servidor sea `localhost`

3. **Las sesiones se pierden**
   - Esto es normal en algunos servidores gratuitos
   - Intenta actualizar la página

4. **Archivos no se suben**
   - Verifica que tengas permisos de escritura
   - Intenta en la carpeta `/htdocs` directamente
   - Cambia el modo a binario en FileZilla

---

**Creado:** 25 de mayo de 2026
**Proyecto:** Gestión de Gimnasio
**Versión de PHP requerida:** 7.4+
**Versión de MySQL requerida:** 5.7+
