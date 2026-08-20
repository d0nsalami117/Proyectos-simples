-- ==========================================
-- CONSULTAS ÚTILES PARA ADMINISTRACIÓN
-- Ejecutar en phpMyAdmin de InfinityFree
-- ==========================================

-- 1️⃣ CREAR USUARIO ADMIN (ejecutar después de importar gimnasio.sql)
-- Si la tabla usuarios está vacía, ejecuta esto para crear un admin:

INSERT INTO usuarios (Nombre, Correo, Clave, Rol, Tipo_Suscripcion) 
VALUES (
    'Administrador',
    'admin@gimnasio.com',
    '$2y$10$1g3vXLR6W9aVl5tHF.6T3uJ6Kx7m2p9L0qK6X8w5R2n3Y7M1B9D6y', -- Contraseña: admin123 (hasheada con PASSWORD_DEFAULT)
    'admin',
    'VIP'
);

-- 2️⃣ VER TODOS LOS USUARIOS
SELECT id, Nombre, Correo, Rol, Tipo_Suscripcion, fecha_registro FROM usuarios;

-- 3️⃣ CAMBIAR A UN USUARIO A ADMIN
UPDATE usuarios SET Rol = 'admin' WHERE Nombre = 'nombre_del_usuario';

-- 4️⃣ CAMBIAR CONTRASEÑA DE UN USUARIO (hasheada)
-- Nota: Reemplaza 'nueva_contraseña_hasheada' con el resultado del función password_hash de PHP
UPDATE usuarios SET Clave = '$2y$10$...' WHERE id = 1;

-- 5️⃣ ELIMINAR UN USUARIO
DELETE FROM usuarios WHERE id = 999;

-- 6️⃣ VER TODAS LAS RUTINAS CREADAS
SELECT * FROM rutinas ORDER BY fecha_creacion DESC;

-- 7️⃣ VER ASISTENCIA DE UN USUARIO
SELECT u.Nombre, a.fecha FROM asistencia a
JOIN usuarios u ON a.usuario_id = u.id
WHERE u.id = 4
ORDER BY a.fecha DESC;

-- 8️⃣ VACIAR LA TABLA DE ASISTENCIA (CUIDADO: esto borra todo)
-- DELETE FROM asistencia;

-- 9️⃣ RESTAURAR LA BASE DE DATOS (borrar todo y empezar de cero)
-- Ejecuta el archivo gimnasio.sql de nuevo en la opción "Import"

-- 🔟 VER ESTADÍSTICAS GENERALES
SELECT 
    'Total de Usuarios' as Estadística, COUNT(*) as Cantidad FROM usuarios
UNION ALL
SELECT 'Total de Rutinas', COUNT(*) FROM rutinas
UNION ALL
SELECT 'Total de Registros de Asistencia', COUNT(*) FROM asistencia;

-- ==========================================
-- NOTAS DE SEGURIDAD
-- ==========================================
-- 1. Las contraseñas SIEMPRE están hasheadas con PASSWORD_DEFAULT (bcrypt)
-- 2. No uses contraseñas en texto plano en la BD
-- 3. Para generar una contraseña hasheada en PHP, usa: password_hash('contraseña', PASSWORD_DEFAULT)
-- 4. Para verificar una contraseña, usa: password_verify('contraseña', hash_de_bd)

-- ==========================================
-- GENERAR HASH DE CONTRASEÑA EN PHP
-- ==========================================
-- Copia este código en un archivo PHP temporal:
/*
<?php
$password = 'tu_contraseña_aqui';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo 'Hash: ' . $hash;
?>
*/
-- Luego copia el hash generado en las consultas UPDATE
