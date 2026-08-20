<?php
// Configuración de conexión a la base de datos
$servidor  = "localhost";
$usuario   = "Donsalami";
$contraseña = "6hVebwYD00?";
$base_datos = "notificaciones_db";

// Crear conexión
$conexion = new mysqli($servidor, $usuario, $contraseña, $base_datos);

// Verificar conexión
if ($conexion->connect_error) {
    die(json_encode(["exito" => false, "mensaje" => "Conexión fallida: " . $conexion->connect_error]));
}

// Establecer charset a UTF-8
$conexion->set_charset("utf8");

// ─── Helpers ────────────────────────────────────────────────────────────────

function normalizarImportancia($importancia) {
    $validos = ['baja', 'media', 'alta'];
    return in_array($importancia, $validos, true) ? $importancia : 'media';
}

// ─── Notificaciones ─────────────────────────────────────────────────────────

function obtenerNotificaciones($id_usuario) {
    global $conexion;
    $sql = "SELECT n.*,
                   u.nombre AS nombre_emisor, u.apellido AS apellido_emisor,
                   rd.leida, rd.fecha_lectura,
                   orig.titulo  AS titulo_original,
                   orig.mensaje AS mensaje_original,
                   uo.nombre    AS nombre_emisor_original,
                   uo.apellido  AS apellido_emisor_original
            FROM notificaciones n
            JOIN usuarios u  ON u.id  = n.id_emisor
            JOIN notificacion_destinatarios rd ON rd.id_notificacion = n.id
            LEFT JOIN notificaciones orig ON orig.id = n.id_respuesta_a
            LEFT JOIN usuarios uo ON uo.id = orig.id_emisor
            WHERE rd.id_usuario = ?
            ORDER BY n.fecha_envio DESC";

    $stmt = $conexion->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function obtenerNotificacionPorId($id_notificacion) {
    global $conexion;
    $sql = "SELECT n.*, u.nombre AS nombre_emisor, u.apellido AS apellido_emisor
            FROM notificaciones n
            JOIN usuarios u ON n.id_emisor = u.id
            WHERE n.id = ?
            LIMIT 1";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $id_notificacion);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function enviarNotificacion($id_emisor, $id_usuario, $titulo, $mensaje, $importancia = 'media') {
    global $conexion;
    $importancia = normalizarImportancia($importancia);

    if (!puedeEnviar($id_emisor, $id_usuario)) {
        return ["exito" => false, "mensaje" => "Permisos insuficientes para enviar a ese destinatario"];
    }

    // Validar horario
    $emisor = obtenerUsuarioPorId($id_emisor);
    $turno  = isset($emisor['turno']) ? $emisor['turno'] : null;
    if (!dentroDeHorario($emisor['tipo'], $turno)) {
        return ["exito" => false, "mensaje" => "Fuera del horario permitido para enviar mensajes"];
    }

    // Validar límite diario del alumno
    if ($emisor['tipo'] === 'alumno') {
        if (!verificarLimiteAlumno($id_emisor)) {
            return ["exito" => false, "mensaje" => "Has alcanzado el límite de 4 notificaciones diarias"];
        }
    }

    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare("INSERT INTO notificaciones (id_emisor, titulo, mensaje, importancia) VALUES (?, ?, ?, ?)");
        if (!$stmt) throw new Exception($conexion->error);
        $stmt->bind_param("isss", $id_emisor, $titulo, $mensaje, $importancia);
        if (!$stmt->execute()) throw new Exception($stmt->error);

        $id_notificacion = $stmt->insert_id;

        $stmt2 = $conexion->prepare("INSERT INTO notificacion_destinatarios (id_notificacion, id_usuario) VALUES (?, ?)");
        if (!$stmt2) throw new Exception($conexion->error);
        $stmt2->bind_param("ii", $id_notificacion, $id_usuario);
        if (!$stmt2->execute()) throw new Exception($stmt2->error);

        if ($emisor['tipo'] === 'alumno') incrementarContadorAlumno($id_emisor);

        $conexion->commit();
        return ["exito" => true, "mensaje" => "Notificación enviada correctamente"];
    } catch (Exception $e) {
        $conexion->rollback();
        return ["exito" => false, "mensaje" => "Error al enviar la notificación: " . $e->getMessage()];
    }
}

function enviarNotificacionATodos($id_emisor, $titulo, $mensaje, $importancia = 'media') {
    global $conexion;
    $importancia = normalizarImportancia($importancia);

    $emisor = obtenerUsuarioPorId($id_emisor);
    $turno  = isset($emisor['turno']) ? $emisor['turno'] : null;
    if (!dentroDeHorario($emisor['tipo'], $turno)) {
        return ["exito" => false, "mensaje" => "Fuera del horario permitido para enviar mensajes"];
    }

    $usuarios = obtenerUsuarios();

    if (empty($usuarios)) {
        return ["exito" => false, "mensaje" => "No hay usuarios disponibles para notificar"];
    }

    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare("INSERT INTO notificaciones (id_emisor, titulo, mensaje, importancia) VALUES (?, ?, ?, ?)");
        if (!$stmt) throw new Exception($conexion->error);
        $stmt->bind_param("isss", $id_emisor, $titulo, $mensaje, $importancia);
        if (!$stmt->execute()) throw new Exception($stmt->error);

        $id_notificacion = $stmt->insert_id;

        $stmt2 = $conexion->prepare("INSERT INTO notificacion_destinatarios (id_notificacion, id_usuario) VALUES (?, ?)");
        if (!$stmt2) throw new Exception($conexion->error);

        $enviados = 0;
        $omitidos = 0;

        foreach ($usuarios as $usuario) {
            $id_usuario = intval($usuario['id']);
            if ($id_usuario === intval($id_emisor)) continue;
            if (!puedeEnviar($id_emisor, $id_usuario)) { $omitidos++; continue; }
            $stmt2->bind_param("ii", $id_notificacion, $id_usuario);
            if (!$stmt2->execute()) throw new Exception($stmt2->error);
            $enviados++;
        }

        if ($enviados === 0) throw new Exception('No hay destinatarios válidos según permisos del emisor');

        $conexion->commit();
        return ["exito" => true, "mensaje" => "Notificación enviada", "enviados" => $enviados, "omitidos" => $omitidos];
    } catch (Exception $e) {
        $conexion->rollback();
        return ["exito" => false, "mensaje" => "Error al enviar la notificación masiva: " . $e->getMessage()];
    }
}

function marcarComoLeida($id_notificacion, $id_usuario) {
    global $conexion;
    $stmt = $conexion->prepare("UPDATE notificacion_destinatarios SET leida = TRUE, fecha_lectura = NOW() WHERE id_notificacion = ? AND id_usuario = ?");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $id_notificacion, $id_usuario);
    return $stmt->execute();
}

/**
 * Borra las notificaciones leídas de un usuario específico.
 * Cualquier usuario puede borrar sus propios mensajes leídos.
 * Si tras borrar la relación la notificación queda sin destinatarios, se elimina también la notificación.
 */
function borrarNotificacionesLeidas($id_usuario) {
    global $conexion;

    // Obtener IDs de notificaciones leídas por este usuario
    $stmt = $conexion->prepare(
        "SELECT id_notificacion FROM notificacion_destinatarios WHERE id_usuario = ? AND leida = TRUE"
    );
    if (!$stmt) return ["exito" => false, "mensaje" => "Error interno"];
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) {
        return ["exito" => true, "mensaje" => "No hay mensajes leídos para borrar", "borrados" => 0];
    }

    $conexion->begin_transaction();
    try {
        // Borrar las relaciones destinatario (leídas del usuario)
        $del = $conexion->prepare(
            "DELETE FROM notificacion_destinatarios WHERE id_usuario = ? AND leida = TRUE"
        );
        if (!$del) throw new Exception($conexion->error);
        $del->bind_param("i", $id_usuario);
        if (!$del->execute()) throw new Exception($del->error);
        $borrados = $del->affected_rows;

        // Limpiar notificaciones huérfanas (sin ningún destinatario)
        $conexion->query(
            "DELETE FROM notificaciones WHERE id NOT IN (SELECT DISTINCT id_notificacion FROM notificacion_destinatarios)"
        );

        $conexion->commit();
        return ["exito" => true, "mensaje" => "Mensajes leídos eliminados", "borrados" => $borrados];
    } catch (Exception $e) {
        $conexion->rollback();
        return ["exito" => false, "mensaje" => "Error al borrar: " . $e->getMessage()];
    }
}

// ─── Usuarios ────────────────────────────────────────────────────────────────

function obtenerUsuarios() {
    global $conexion;
    $resultado = $conexion->query("SELECT id, nombre, apellido, tipo, turno FROM usuarios ORDER BY nombre, apellido");
    return $resultado ? $resultado->fetch_all(MYSQLI_ASSOC) : [];
}

function obtenerTodosLosUsuarios() {
    global $conexion;
    $extras = _columnasNuevasExisten()
        ? ", telefono, carrera, semestre, salon, grado, grupo"
        : "";
    $resultado = $conexion->query("SELECT id, nombre, apellido, email, tipo, turno{$extras}, fecha_registro FROM usuarios ORDER BY nombre, apellido");
    return $resultado ? $resultado->fetch_all(MYSQLI_ASSOC) : [];
}

// Detectar si las columnas nuevas ya existen en la BD (compatibilidad con BD antigua)
function _columnasNuevasExisten() {
    global $conexion;
    static $cache = null;
    if ($cache !== null) return $cache;
    $res = $conexion->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'salon' LIMIT 1");
    $cache = ($res && $res->num_rows > 0);
    return $cache;
}

function obtenerUsuarioPorId($id_usuario) {
    global $conexion;
    $extras = _columnasNuevasExisten()
        ? ", telefono, carrera, semestre, salon, grado, grupo"
        : "";
    $stmt = $conexion->prepare("SELECT id, nombre, apellido, email, tipo, turno, notificaciones_hoy, fecha_contador{$extras} FROM usuarios WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function contarAlumnosPorSalon($salon, $turno = null) {
    global $conexion;
    if (!_columnasNuevasExisten()) return 0; // Si no hay columna salon, sin límite
    if ($turno) {
        $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'alumno' AND salon = ? AND turno = ?");
        if (!$stmt) return 0;
        $stmt->bind_param("ss", $salon, $turno);
    } else {
        $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'alumno' AND salon = ?");
        if (!$stmt) return 0;
        $stmt->bind_param("s", $salon);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return intval($row['total']);
}

function obtenerReportesPorSalon($turno = null) {
    global $conexion;
    if (!_columnasNuevasExisten()) return [];
    // Cuenta notificaciones enviadas por alumnos, agrupadas por salon
    if ($turno) {
        $sql = "SELECT u.salon, COUNT(n.id) as total_reportes,
                       COUNT(DISTINCT u.id) as total_alumnos
                FROM notificaciones n
                JOIN usuarios u ON u.id = n.id_emisor
                WHERE u.tipo = 'alumno' AND u.turno = ? AND u.salon IS NOT NULL AND u.salon != ''
                GROUP BY u.salon
                ORDER BY u.salon";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("s", $turno);
    } else {
        $sql = "SELECT u.salon, u.turno, COUNT(n.id) as total_reportes,
                       COUNT(DISTINCT u.id) as total_alumnos
                FROM notificaciones n
                JOIN usuarios u ON u.id = n.id_emisor
                WHERE u.tipo = 'alumno' AND u.salon IS NOT NULL AND u.salon != ''
                GROUP BY u.salon, u.turno
                ORDER BY u.turno, u.salon";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) return [];
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function obtenerUsuariosPermitidos($id_emisor) {
    $usuarios   = obtenerUsuarios();
    $permitidos = [];
    foreach ($usuarios as $usuario) {
        $id = intval($usuario['id']);
        if ($id === intval($id_emisor)) continue;
        if (puedeEnviar($id_emisor, $id)) $permitidos[] = $usuario;
    }
    return $permitidos;
}

function puedeEnviar($id_emisor, $id_destinatario) {
    $emisor = obtenerUsuarioPorId($id_emisor);
    $dest   = obtenerUsuarioPorId($id_destinatario);
    if (!$emisor || !$dest) return false;

    $re = $emisor['tipo'];
    $rd = $dest['tipo'];

    // oficial_mayor → puede enviar a todos
    // intendente    → puede enviar a oficial_mayor y a todos los demás
    // maestro       → puede enviar a intendentes y alumnos de su mismo turno
    // alumno        → puede enviar a maestros de su mismo turno
    if ($re === 'oficial_mayor') return true;
    if ($re === 'intendente')    return true;
    if ($re === 'maestro') {
        if (!in_array($rd, ['intendente', 'alumno'], true)) return false;
        // El maestro solo envía a usuarios de su mismo turno
        $turno_emisor = $emisor['turno'];
        $turno_dest   = $dest['turno'];
        if ($turno_emisor && $turno_dest && $turno_emisor !== $turno_dest) return false;
        return true;
    }
    if ($re === 'alumno') {
        if ($rd !== 'maestro') return false;
        // El alumno solo puede enviar al maestro de su mismo turno
        $turno_emisor = $emisor['turno'];
        $turno_dest   = $dest['turno'];
        if ($turno_emisor && $turno_dest && $turno_emisor !== $turno_dest) return false;
        return true;
    }
    return false;
}

function rolesPermitidosParaEmisor($tipo_emisor) {
    // Devuelve los roles a los que puede enviar el tipo de emisor
    switch ($tipo_emisor) {
        case 'oficial_mayor': return ['oficial_mayor', 'intendente', 'maestro', 'alumno'];
        case 'intendente':    return ['oficial_mayor', 'intendente', 'maestro', 'alumno'];
        case 'maestro':       return ['intendente', 'alumno'];
        case 'alumno':        return ['maestro'];
        default:              return [];
    }
}

function enviarNotificacionARol($id_emisor, $rol_destino, $titulo, $mensaje, $importancia = 'media') {
    global $conexion;
    $importancia = normalizarImportancia($importancia);

    $emisor = obtenerUsuarioPorId($id_emisor);
    if (!$emisor) return ["exito" => false, "mensaje" => "Emisor no encontrado"];

    $turno = isset($emisor['turno']) ? $emisor['turno'] : null;
    if (!dentroDeHorario($emisor['tipo'], $turno)) {
        return ["exito" => false, "mensaje" => "Fuera del horario permitido para enviar mensajes"];
    }

    $roles_permitidos = rolesPermitidosParaEmisor($emisor['tipo']);
    if (!in_array($rol_destino, $roles_permitidos, true)) {
        return ["exito" => false, "mensaje" => "No tienes permiso para enviar a ese rol"];
    }

    // Obtener todos los usuarios con ese rol (excepto el propio emisor)
    // Para maestros y alumnos: filtrar por turno
    if (in_array($emisor['tipo'], ['maestro', 'alumno'], true) && $turno) {
        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE tipo = ? AND id != ? AND turno = ?");
        if (!$stmt) return ["exito" => false, "mensaje" => "Error interno: " . $conexion->error];
        $stmt->bind_param("sis", $rol_destino, $id_emisor, $turno);
    } else {
        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE tipo = ? AND id != ?");
        if (!$stmt) return ["exito" => false, "mensaje" => "Error interno: " . $conexion->error];
        $stmt->bind_param("si", $rol_destino, $id_emisor);
    }
    $stmt->execute();
    $usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($usuarios)) {
        return ["exito" => false, "mensaje" => "No hay usuarios con el rol '$rol_destino'" . ($turno ? " en turno $turno" : "")];
    }

    $conexion->begin_transaction();
    try {
        $stmt = $conexion->prepare("INSERT INTO notificaciones (id_emisor, titulo, mensaje, importancia) VALUES (?, ?, ?, ?)");
        if (!$stmt) throw new Exception($conexion->error);
        $stmt->bind_param("isss", $id_emisor, $titulo, $mensaje, $importancia);
        if (!$stmt->execute()) throw new Exception($stmt->error);

        $id_notificacion = $stmt->insert_id;

        $stmt2 = $conexion->prepare("INSERT INTO notificacion_destinatarios (id_notificacion, id_usuario) VALUES (?, ?)");
        if (!$stmt2) throw new Exception($conexion->error);

        $enviados = 0;
        foreach ($usuarios as $u) {
            $id_u = intval($u['id']);
            $stmt2->bind_param("ii", $id_notificacion, $id_u);
            if (!$stmt2->execute()) throw new Exception($stmt2->error);
            $enviados++;
        }

        $conexion->commit();
        return ["exito" => true, "mensaje" => "Notificación enviada al rol '$rol_destino'", "enviados" => $enviados];
    } catch (Exception $e) {
        $conexion->rollback();
        return ["exito" => false, "mensaje" => "Error al enviar por rol: " . $e->getMessage()];
    }
}

function autenticarUsuarioPorCredenciales($email, $password) {
    global $conexion;
    $stmt = $conexion->prepare("SELECT id, nombre, apellido, tipo, password FROM usuarios WHERE email = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario || empty($usuario['password'])) return false;

    $stored = (string)$usuario['password'];
    $valida = password_verify($password, $stored) || hash_equals($stored, $password);
    if (!$valida) return false;

    // Migrar contraseña en texto plano a hash si corresponde
    if (!password_verify($password, $stored)) {
        $hash   = password_hash($password, PASSWORD_DEFAULT);
        $update = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        if ($update) { $update->bind_param('si', $hash, $usuario['id']); $update->execute(); }
    }

    return $usuario;
}

function crearUsuario($nombre, $apellido, $email, $password, $tipo = 'alumno', $turno = null, $extras = []) {
    global $conexion;

    // Validaciones básicas
    if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
        return ["exito" => false, "mensaje" => "Nombre, apellido, email y contraseña son requeridos"];
    }
    if (strlen($nombre) < 2 || strlen($apellido) < 2) {
        return ["exito" => false, "mensaje" => "Nombre y apellido deben tener al menos 2 caracteres"];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ["exito" => false, "mensaje" => "Email inválido"];
    }
    if (!in_array($tipo, ['oficial_mayor', 'intendente', 'maestro', 'alumno'], true)) {
        return ["exito" => false, "mensaje" => "Tipo de usuario inválido"];
    }

    // Email duplicado
    $check = $conexion->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    if (!$check) return ["exito" => false, "mensaje" => "Error interno: " . $conexion->error];
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        return ["exito" => false, "mensaje" => "El email ya está registrado"];
    }

    // Turno requerido para maestros y alumnos
    $turno_final = null;
    if (in_array($tipo, ['maestro', 'alumno', 'intendente', 'oficial_mayor'], true)) {
        if (in_array($turno, ['matutino', 'vespertino'], true)) {
            $turno_final = $turno;
        }
    }

    // Campos de alumnos
    $telefono  = isset($extras['telefono'])  ? trim($extras['telefono'])  : null;
    $carrera   = isset($extras['carrera'])   ? trim($extras['carrera'])   : null;
    $semestre  = isset($extras['semestre'])  ? intval($extras['semestre']): null;
    $salon     = isset($extras['salon'])     ? strtoupper(trim($extras['salon'])) : null;
    $grado     = isset($extras['grado'])     ? intval($extras['grado'])   : null;
    $grupo     = isset($extras['grupo'])     ? strtoupper(trim($extras['grupo'])) : null;

    // Validar límite de 3 alumnos por salón
    if ($tipo === 'alumno' && !empty($salon) && !empty($turno_final)) {
        $cantidad = contarAlumnosPorSalon($salon, $turno_final);
        if ($cantidad >= 3) {
            return ["exito" => false, "mensaje" => "El salón $salon ya tiene 3 alumnos registrados en el turno $turno_final. No se pueden agregar más."];
        }
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if (_columnasNuevasExisten()) {
        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, apellido, email, password, tipo, turno, telefono, carrera, semestre, salon, grado, grupo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return ["exito" => false, "mensaje" => "Error interno: " . $conexion->error];
        // s,s,s,s,s,s,s,s,i,s,i,s
        $stmt->bind_param("ssssssssisis", $nombre, $apellido, $email, $hash, $tipo, $turno_final, $telefono, $carrera, $semestre, $salon, $grado, $grupo);
    } else {
        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, apellido, email, password, tipo, turno) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) return ["exito" => false, "mensaje" => "Error interno: " . $conexion->error];
        $stmt->bind_param("ssssss", $nombre, $apellido, $email, $hash, $tipo, $turno_final);
    }

    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        return [
            "exito"   => true,
            "mensaje" => "Usuario creado correctamente",
            "id"      => $id,
            "usuario" => ["id" => $id, "nombre" => $nombre, "apellido" => $apellido, "email" => $email, "tipo" => $tipo, "turno" => $turno_final]
        ];
    }
    return ["exito" => false, "mensaje" => "Error al crear el usuario: " . $stmt->error];
}

function actualizarUsuario($id_usuario, $nombre, $apellido, $tipo, $turno = null) {
    global $conexion;

    if (empty($nombre) || empty($apellido) || empty($tipo)) {
        return ["exito" => false, "mensaje" => "Nombre, apellido y rol son requeridos"];
    }
    if (!in_array($tipo, ['oficial_mayor', 'intendente', 'maestro', 'alumno'], true)) {
        return ["exito" => false, "mensaje" => "Rol inválido"];
    }
    if ($turno !== null && $turno !== '' && !in_array($turno, ['matutino', 'vespertino'], true)) {
        return ["exito" => false, "mensaje" => "Turno inválido"];
    }

    // Maestros, alumnos, intendentes y oficiales guardan turno
    $turno_final = in_array($turno, ['matutino', 'vespertino'], true) ? $turno : null;

    $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, tipo = ?, turno = ? WHERE id = ?");
    if (!$stmt) return ["exito" => false, "mensaje" => "Error interno: " . $conexion->error];
    $stmt->bind_param("ssssi", $nombre, $apellido, $tipo, $turno_final, $id_usuario);
    return $stmt->execute()
        ? ["exito" => true,  "mensaje" => "Usuario actualizado correctamente"]
        : ["exito" => false, "mensaje" => "Error al actualizar: " . $stmt->error];
}

/**
 * Permite a un maestro actualizar únicamente su propio turno de recepción de mensajes.
 */
function actualizarTurnoMaestro($id_usuario, $turno) {
    global $conexion;
    if (!in_array($turno, ['matutino', 'vespertino'], true)) {
        return ["exito" => false, "mensaje" => "Turno inválido. Debe ser 'matutino' o 'vespertino'"];
    }
    $stmt = $conexion->prepare("UPDATE usuarios SET turno = ? WHERE id = ? AND tipo = 'maestro'");
    if (!$stmt) return ["exito" => false, "mensaje" => "Error interno"];
    $stmt->bind_param("si", $turno, $id_usuario);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        return ["exito" => true, "mensaje" => "Turno actualizado a '$turno'"];
    }
    return ["exito" => false, "mensaje" => "No se pudo actualizar el turno"];
}

// ─── Horario y límites ───────────────────────────────────────────────────────

function dentroDeHorario($tipo_usuario = null, $turno = null) {
    $hora = (int)date('G');
    if ($hora < 7 || $hora >= 21) return false;
    // Alumnos y maestros están restringidos a su turno
    if (in_array($tipo_usuario, ['alumno', 'maestro'], true)) {
        if (!$turno) return false;
        if ($turno === 'matutino'   && ($hora < 7  || $hora >= 14)) return false;
        if ($turno === 'vespertino' && ($hora < 14 || $hora >= 21)) return false;
    }
    return true;
}

function obtenerTurnoActual() {
    $hora = (int)date('G');
    if ($hora >= 7  && $hora < 14) return 'matutino';
    if ($hora >= 14 && $hora < 21) return 'vespertino';
    return null;
}

function verificarLimiteAlumno($id_usuario) {
    global $conexion;
    $hoy  = date('Y-m-d');
    $stmt = $conexion->prepare("SELECT notificaciones_hoy, fecha_contador FROM usuarios WHERE id = ? LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return false;
    if ($row['fecha_contador'] !== $hoy) {
        $upd = $conexion->prepare("UPDATE usuarios SET notificaciones_hoy = 0, fecha_contador = ? WHERE id = ?");
        $upd->bind_param("si", $hoy, $id_usuario);
        $upd->execute();
        return true;
    }
    return intval($row['notificaciones_hoy']) < 4;
}

function incrementarContadorAlumno($id_usuario) {
    global $conexion;
    $hoy = date('Y-m-d');
    $stmt = $conexion->prepare("UPDATE usuarios SET notificaciones_hoy = notificaciones_hoy + 1, fecha_contador = ? WHERE id = ?");
    if (!$stmt) return;
    $stmt->bind_param("si", $hoy, $id_usuario);
    $stmt->execute();
}

function notificacionesRestantesAlumno($id_usuario) {
    global $conexion;
    $hoy  = date('Y-m-d');
    $stmt = $conexion->prepare("SELECT notificaciones_hoy, fecha_contador FROM usuarios WHERE id = ? LIMIT 1");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return 0;
    if ($row['fecha_contador'] !== $hoy) return 4;
    return max(0, 4 - intval($row['notificaciones_hoy']));
}

// ─── Responder notificación ──────────────────────────────────────────────────

function responderNotificacion($id_emisor, $id_notificacion_original, $mensaje_respuesta, $importancia = 'media') {
    global $conexion;
    $sql  = "SELECT n.id, n.titulo, n.mensaje, n.id_emisor FROM notificaciones n WHERE n.id = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) return ["exito" => false, "mensaje" => "Error interno"];
    $stmt->bind_param("i", $id_notificacion_original);
    $stmt->execute();
    $original = $stmt->get_result()->fetch_assoc();
    if (!$original) return ["exito" => false, "mensaje" => "Notificación original no encontrada"];

    $id_destinatario = intval($original['id_emisor']);
    if ($id_destinatario === intval($id_emisor)) {
        return ["exito" => false, "mensaje" => "No puedes responderte a ti mismo"];
    }
    if (!puedeEnviar($id_emisor, $id_destinatario)) {
        return ["exito" => false, "mensaje" => "No tienes permiso para responder a ese usuario"];
    }

    $emisor = obtenerUsuarioPorId($id_emisor);
    $turno  = isset($emisor['turno']) ? $emisor['turno'] : null;
    if (!dentroDeHorario($emisor['tipo'], $turno)) {
        return ["exito" => false, "mensaje" => "Fuera del horario permitido para enviar mensajes"];
    }
    if ($emisor['tipo'] === 'alumno') {
        if (!verificarLimiteAlumno($id_emisor)) {
            return ["exito" => false, "mensaje" => "Has alcanzado el límite de 4 notificaciones diarias"];
        }
    }

    $titulo_respuesta = "Re: " . $original['titulo'];
    $importancia      = normalizarImportancia($importancia);
    $id_orig          = intval($id_notificacion_original);

    $conexion->begin_transaction();
    try {
        $ins = $conexion->prepare("INSERT INTO notificaciones (id_emisor, titulo, mensaje, importancia, id_respuesta_a) VALUES (?, ?, ?, ?, ?)");
        if (!$ins) throw new Exception($conexion->error);
        $ins->bind_param("isssi", $id_emisor, $titulo_respuesta, $mensaje_respuesta, $importancia, $id_orig);
        if (!$ins->execute()) throw new Exception($ins->error);
        $id_nueva = $ins->insert_id;

        $ins2 = $conexion->prepare("INSERT INTO notificacion_destinatarios (id_notificacion, id_usuario) VALUES (?, ?)");
        if (!$ins2) throw new Exception($conexion->error);
        $ins2->bind_param("ii", $id_nueva, $id_destinatario);
        if (!$ins2->execute()) throw new Exception($ins2->error);

        if ($emisor['tipo'] === 'alumno') incrementarContadorAlumno($id_emisor);
        $conexion->commit();
        return ["exito" => true, "mensaje" => "Respuesta enviada correctamente"];
    } catch (Exception $e) {
        $conexion->rollback();
        return ["exito" => false, "mensaje" => "Error al enviar respuesta: " . $e->getMessage()];
    }
}

// ─── Limpieza automática ─────────────────────────────────────────────────────

function limpiarNotificacionesLeidas() {
    global $conexion;
    $conexion->query("
        DELETE n FROM notificaciones n
        WHERE NOT EXISTS (
            SELECT 1 FROM notificacion_destinatarios nd
            WHERE nd.id_notificacion = n.id
              AND (nd.leida = FALSE OR nd.fecha_lectura IS NULL
                   OR nd.fecha_lectura > NOW() - INTERVAL 10 MINUTE)
        )
    ");
}
