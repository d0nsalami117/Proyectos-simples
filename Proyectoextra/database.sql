-- Base de datos para el sistema de notificaciones
CREATE DATABASE IF NOT EXISTS notificaciones_db;
USE notificaciones_db;

-- Eliminar tablas si existen (para reimportar limpio)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notificacion_destinatarios;
DROP TABLE IF EXISTS sesiones;
DROP TABLE IF EXISTS notificaciones;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- Tabla de usuarios
-- NOTA: Se eliminó el rol 'director'. Ahora hay dos oficiales mayores,
--       uno por turno (matutino y vespertino).
--       Los maestros tienen campo turno para elegir en qué turno reciben mensajes.
--       Los alumnos tienen carrera, semestre, salon, grado, grupo y telefono.
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    tipo ENUM('oficial_mayor', 'intendente', 'maestro', 'alumno') NOT NULL DEFAULT 'alumno',
    turno ENUM('matutino','vespertino') NULL DEFAULT NULL,
    telefono VARCHAR(15) NULL DEFAULT NULL,
    -- Campos exclusivos para alumnos
    carrera VARCHAR(100) NULL DEFAULT NULL,
    semestre TINYINT UNSIGNED NULL DEFAULT NULL,
    salon VARCHAR(10) NULL DEFAULT NULL,
    grado TINYINT UNSIGNED NULL DEFAULT NULL,
    grupo VARCHAR(5) NULL DEFAULT NULL,
    -- Campos exclusivos para alumnos dados de alta por oficial
    notificaciones_hoy INT NOT NULL DEFAULT 0,
    fecha_contador DATE NULL DEFAULT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de notificaciones
CREATE TABLE notificaciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_emisor INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    importancia ENUM('baja', 'media', 'alta') NOT NULL DEFAULT 'media',
    id_respuesta_a INT NULL DEFAULT NULL,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_emisor) REFERENCES usuarios(id),
    FOREIGN KEY (id_respuesta_a) REFERENCES notificaciones(id) ON DELETE SET NULL
);

-- Tabla de destinatarios
CREATE TABLE notificacion_destinatarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_notificacion INT NOT NULL,
    id_usuario INT NOT NULL,
    leida BOOLEAN DEFAULT FALSE,
    fecha_lectura TIMESTAMP NULL,
    FOREIGN KEY (id_notificacion) REFERENCES notificaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

-- Tabla de sesiones
CREATE TABLE sesiones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    token VARCHAR(191) UNIQUE NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

-- Datos de ejemplo (contraseñas en texto plano solo para pruebas locales)
INSERT INTO usuarios (nombre, apellido, email, password, tipo, turno) VALUES
-- Oficiales Mayores (uno por turno, reemplazan al director)
('Roberto',   'Herrera',  'oficial.matutino@conecta.edu',   'oficial123',   'oficial_mayor','matutino'),
('Claudia',   'Vidal',    'oficial.vespertino@conecta.edu', 'oficial123',   'oficial_mayor','vespertino'),
-- Intendentes
('Miguel',    'Salinas',  'miguel.torres@conecta.edu',      'intendente123','intendente',   'matutino'),
('Patricia',  'Cruz',     'patricia.mendoza@conecta.edu',   'intendente123','intendente',   'vespertino'),
('Ernesto',   'Gomez',    'ernesto.gutierrez@conecta.edu',  'intendente123','intendente',   'matutino'),
('Silvia',    'Morales',  'silvia.vargas@conecta.edu',      'intendente123','intendente',   'vespertino'),
-- Maestros (con turno elegible: determina en qué horario reciben mensajes)
('Alejandro', 'Reyes',    'alejandro.castillo@conecta.edu', 'maestro123',   'maestro',      'matutino'),
('Verónica',  'Leal',     'veronica.leal@conecta.edu',      'maestro123',   'maestro',      'vespertino'),
('Fernando',  'Alvarado', 'fernando.rios@conecta.edu',      'maestro123',   'maestro',      'matutino'),
('Mónica',    'Sandoval', 'monica.ibarra@conecta.edu',      'maestro123',   'maestro',      'vespertino');

-- Alumnos con los nuevos campos
INSERT INTO usuarios (nombre, apellido, email, password, tipo, turno, telefono, carrera, semestre, salon, grado, grupo) VALUES
('Daniela', 'Herrera', 'daniela.herrera@conecta.edu', 'alumno123', 'alumno', 'matutino', '3311111111', 'Ingeniería en Sistemas Computacionales', 3, 'A1', 3, 'A'),
('Carlos',  'Soto',    'carlos.soto@conecta.edu',     'alumno123', 'alumno', 'matutino', '3312222222', 'Administración de Empresas',            1, 'A1', 1, 'B'),
('Valeria', 'Paredes', 'valeria.nunez@conecta.edu',   'alumno123', 'alumno', 'matutino', '3313333333', 'Contaduría Pública',                    2, 'B2', 2, 'A'),
('Sebas',   'Medina',  'sebastian.flores@conecta.edu','alumno123', 'alumno', 'vespertino','3314444444', 'Ingeniería Industrial',                 4, 'B2', 4, 'C'),
('Itzel',   'Mora',    'itzel.mora@conecta.edu',       'alumno123', 'alumno', 'vespertino','3315555555', 'Médico Cirujano Partero',               2, 'C3', 2, 'B'),
('Diego',   'Ruiz',    'diego.acosta@conecta.edu',     'alumno123', 'alumno', 'vespertino','3316666666', 'Licenciatura en Enfermería',            3, 'C3', 3, 'A');

-- Evento que borra notificaciones leídas hace más de 10 minutos (para todos los destinatarios)
-- (requiere: SET GLOBAL event_scheduler = ON en MySQL)
DROP EVENT IF EXISTS limpiar_notificaciones_leidas;
CREATE EVENT limpiar_notificaciones_leidas
    ON SCHEDULE EVERY 1 MINUTE
    DO
    DELETE nd, n
    FROM notificacion_destinatarios nd
    JOIN notificaciones n ON n.id = nd.id_notificacion
    WHERE nd.leida = TRUE
      AND nd.fecha_lectura IS NOT NULL
      AND nd.fecha_lectura <= NOW() - INTERVAL 10 MINUTE
      AND NOT EXISTS (
          SELECT 1 FROM notificacion_destinatarios nd2
          WHERE nd2.id_notificacion = nd.id_notificacion
            AND (nd2.leida = FALSE OR nd2.fecha_lectura IS NULL OR nd2.fecha_lectura > NOW() - INTERVAL 10 MINUTE)
      );

-- ============================================================
-- MIGRACIÓN: ejecutar este bloque si ya tienes la BD y solo
-- quieres añadir las columnas nuevas sin perder datos.
-- ============================================================
-- ALTER TABLE usuarios
--     ADD COLUMN IF NOT EXISTS telefono  VARCHAR(15)   NULL DEFAULT NULL AFTER turno,
--     ADD COLUMN IF NOT EXISTS carrera   VARCHAR(100)  NULL DEFAULT NULL AFTER telefono,
--     ADD COLUMN IF NOT EXISTS semestre  TINYINT UNSIGNED NULL DEFAULT NULL AFTER carrera,
--     ADD COLUMN IF NOT EXISTS salon     VARCHAR(10)   NULL DEFAULT NULL AFTER semestre,
--     ADD COLUMN IF NOT EXISTS grado     TINYINT UNSIGNED NULL DEFAULT NULL AFTER salon,
--     ADD COLUMN IF NOT EXISTS grupo     VARCHAR(5)    NULL DEFAULT NULL AFTER grado;
