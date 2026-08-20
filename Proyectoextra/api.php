<?php
// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

header('Content-Type: application/json');
require_once 'config.php';

$accion              = isset($_GET['accion']) ? $_GET['accion'] : '';
$id_usuario_actual   = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;
$tipo_usuario_actual = isset($_SESSION['tipo'])       ? $_SESSION['tipo']              : '';
$es_oficial_mayor    = $tipo_usuario_actual === 'oficial_mayor';
$es_admin            = $es_oficial_mayor;  // Solo oficial_mayor es admin ahora
$es_intendente       = $tipo_usuario_actual === 'intendente';
$es_maestro          = $tipo_usuario_actual === 'maestro';
$es_alumno           = $tipo_usuario_actual === 'alumno';

// ─── Helpers de respuesta ────────────────────────────────────────────────────

function responderJson($payload) {
    echo json_encode($payload);
    exit;
}

function responderSinSesion() {
    responderJson(["exito" => false, "mensaje" => "Sesión no iniciada"]);
}

function iniciarSesionApi($usuario) {
    session_regenerate_id(true);
    $_SESSION['id_usuario'] = intval($usuario['id']);
    $_SESSION['tipo']       = $usuario['tipo'];
    $_SESSION['nombre']     = $usuario['nombre'];
}

function cerrarSesionApi() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ─── Limpieza automática en cada request ────────────────────────────────────
limpiarNotificacionesLeidas();

// ─── Router ──────────────────────────────────────────────────────────────────

switch ($accion) {

    // ── Login ──────────────────────────────────────────────────────────────
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        $datos    = json_decode(file_get_contents("php://input"), true);
        $email    = isset($datos['email'])    ? trim($datos['email'])       : '';
        $password = isset($datos['password']) ? (string)$datos['password'] : '';

        if (!$email || !$password) {
            responderJson(["exito" => false, "mensaje" => "Email y contraseña son requeridos"]);
        }

        $usuario = autenticarUsuarioPorCredenciales($email, $password);
        if (!$usuario) {
            responderJson(["exito" => false, "mensaje" => "Credenciales inválidas"]);
        }

        iniciarSesionApi($usuario);
        responderJson([
            "exito"   => true,
            "mensaje" => "Autenticación exitosa",
            "usuario" => [
                "id"       => intval($usuario['id']),
                "nombre"   => $usuario['nombre'],
                "apellido" => $usuario['apellido'],
                "tipo"     => $usuario['tipo'],
                "email"    => $email
            ]
        ]);
        break;

    // ── Logout ─────────────────────────────────────────────────────────────
    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        cerrarSesionApi();
        responderJson(["exito" => true, "mensaje" => "Sesión cerrada"]);
        break;

    // ── Obtener sesión ─────────────────────────────────────────────────────
    case 'obtenerSesion':
        if (!$id_usuario_actual) responderSinSesion();

        $usuario = obtenerUsuarioPorId($id_usuario_actual);
        if ($usuario) {
            responderJson([
                "exito"   => true,
                "usuario" => [
                    "id"        => intval($usuario['id']),
                    "nombre"    => $usuario['nombre'],
                    "apellido"  => $usuario['apellido'],
                    "email"     => $usuario['email'],
                    "tipo"      => $usuario['tipo'],
                    "turno"     => $usuario['turno'] ?? null,
                    "restantes" => $usuario['tipo'] === 'alumno' ? notificacionesRestantesAlumno(intval($usuario['id'])) : null
                ]
            ]);
        } else {
            responderJson(["exito" => false, "mensaje" => "Usuario no encontrado"]);
        }
        break;

    // ── Notificaciones ─────────────────────────────────────────────────────
    case 'obtenerNotificaciones':
        if (!$id_usuario_actual) responderSinSesion();

        $id_usuario = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : $id_usuario_actual;
        if ($id_usuario !== $id_usuario_actual) {
            responderJson(["exito" => false, "mensaje" => "No tienes permiso para ver estas notificaciones"]);
        }

        $notificaciones = obtenerNotificaciones($id_usuario);
        responderJson(["exito" => true, "notificaciones" => $notificaciones, "cantidad" => count($notificaciones)]);
        break;

    case 'enviarNotificacion':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        if (!$id_usuario_actual) responderSinSesion();

        $datos      = json_decode(file_get_contents("php://input"), true);
        $id_emisor  = $id_usuario_actual;
        $id_usuario = isset($datos['id_usuario']) ? $datos['id_usuario'] : null;
        $titulo     = isset($datos['titulo'])     ? trim($datos['titulo'])     : '';
        $mensaje    = isset($datos['mensaje'])    ? trim($datos['mensaje'])    : '';
        $importancia = normalizarImportancia(isset($datos['importancia']) ? trim($datos['importancia']) : 'media');

        if ($id_usuario === null || $titulo === '' || $mensaje === '') {
            responderJson(["exito" => false, "mensaje" => "Todos los campos son requeridos"]);
        }

        // Envío a todos (solo oficial_mayor)
        if ($id_usuario === 'todos' || $id_usuario === 'all' || $id_usuario === 0 || $id_usuario === '0') {
            if (!$es_oficial_mayor) {
                responderJson(["exito" => false, "mensaje" => "Solo el Oficial Mayor puede enviar notificaciones a todos"]);
            }
            responderJson(enviarNotificacionATodos($id_emisor, $titulo, $mensaje, $importancia));
        }

        // Envío por rol (formato "rol:alumno", "rol:maestro", etc.)
        if (is_string($id_usuario) && strpos($id_usuario, 'rol:') === 0) {
            $rol_destino = substr($id_usuario, 4);
            responderJson(enviarNotificacionARol($id_emisor, $rol_destino, $titulo, $mensaje, $importancia));
        }

        $id_usuario  = intval($id_usuario);
        $destinatario = obtenerUsuarioPorId($id_usuario);
        if (!$destinatario) {
            responderJson(["exito" => false, "mensaje" => "El destinatario no es válido"]);
        }

        responderJson(enviarNotificacion($id_emisor, $id_usuario, $titulo, $mensaje, $importancia));
        break;

    case 'marcarComoLeida':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        if (!$id_usuario_actual) responderSinSesion();

        $datos           = json_decode(file_get_contents("php://input"), true);
        $id_notificacion = isset($datos['id_notificacion']) ? intval($datos['id_notificacion']) : null;

        if (!$id_notificacion) {
            responderJson(["exito" => false, "mensaje" => "ID de notificación requerido"]);
        }

        if (marcarComoLeida($id_notificacion, $id_usuario_actual)) {
            responderJson(["exito" => true, "mensaje" => "Notificación marcada como leída"]);
        } else {
            responderJson(["exito" => false, "mensaje" => "No tienes permiso o ocurrió un error"]);
        }
        break;

    // ── Borrar notificaciones leídas (cualquier usuario) ───────────────────
    case 'borrarLeidas':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        if (!$id_usuario_actual) responderSinSesion();

        responderJson(borrarNotificacionesLeidas($id_usuario_actual));
        break;

    // ── Actualizar turno del maestro ───────────────────────────────────────
    case 'actualizarTurnoMaestro':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        if (!$id_usuario_actual) responderSinSesion();
        if (!$es_maestro) {
            responderJson(["exito" => false, "mensaje" => "Solo los maestros pueden cambiar su turno"]);
        }
        $datos = json_decode(file_get_contents("php://input"), true);
        $turno = isset($datos['turno']) ? trim($datos['turno']) : '';
        responderJson(actualizarTurnoMaestro($id_usuario_actual, $turno));
        break;

    // ── Usuarios ───────────────────────────────────────────────────────────
    case 'obtenerInfoEnvio':
        if (!$id_usuario_actual) responderSinSesion();
        $usuario_actual = obtenerUsuarioPorId($id_usuario_actual);
        $turno_actual   = isset($usuario_actual['turno']) ? $usuario_actual['turno'] : null;
        $puede_ahora    = dentroDeHorario($tipo_usuario_actual, $turno_actual);
        $hora_actual    = (int)date('G');
        $restantes      = $tipo_usuario_actual === 'alumno' ? notificacionesRestantesAlumno($id_usuario_actual) : null;
        responderJson([
            "exito"          => true,
            "puede_enviar"   => $puede_ahora,
            "hora_actual"    => $hora_actual,
            "turno_actual"   => obtenerTurnoActual(),
            "turno_usuario"  => $turno_actual,
            "restantes"      => $restantes
        ]);
        break;

    case 'responderNotificacion':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        if (!$id_usuario_actual) responderSinSesion();
        $datos             = json_decode(file_get_contents("php://input"), true);
        $id_notif_original = isset($datos['id_notificacion']) ? intval($datos['id_notificacion']) : 0;
        $mensaje_respuesta = isset($datos['mensaje'])         ? trim($datos['mensaje'])           : '';
        $importancia_resp  = isset($datos['importancia'])     ? trim($datos['importancia'])       : 'media';
        if (!$id_notif_original || !$mensaje_respuesta) {
            responderJson(["exito" => false, "mensaje" => "Datos incompletos para responder"]);
        }
        responderJson(responderNotificacion($id_usuario_actual, $id_notif_original, $mensaje_respuesta, $importancia_resp));
        break;

    case 'obtenerRolesPermitidos':
        if (!$id_usuario_actual) responderSinSesion();
        responderJson([
            "exito" => true,
            "roles" => rolesPermitidosParaEmisor($tipo_usuario_actual)
        ]);
        break;

    case 'obtenerUsuarios':
        if (!$id_usuario_actual) responderSinSesion();
        responderJson(["exito" => true, "usuarios" => obtenerUsuariosPermitidos($id_usuario_actual)]);
        break;

    case 'obtenerTodosLosUsuarios':
        if (!$id_usuario_actual) responderSinSesion();
        if (!$es_admin) {
            responderJson(["exito" => false, "mensaje" => "Solo el Oficial Mayor puede ver esta información"]);
        }
        responderJson(["exito" => true, "usuarios" => obtenerTodosLosUsuarios()]);
        break;

    case 'registrar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        $datos    = json_decode(file_get_contents("php://input"), true);
        $nombre   = isset($datos['nombre'])   ? trim($datos['nombre'])   : '';
        $apellido = isset($datos['apellido']) ? trim($datos['apellido']) : '';
        $email    = isset($datos['email'])    ? trim($datos['email'])    : '';
        $password = isset($datos['password']) ? $datos['password']       : '';
        $turno    = isset($datos['turno'])    ? trim($datos['turno'])    : null;

        // Campos extras de alumno
        $extras = [
            'telefono' => isset($datos['telefono']) ? trim($datos['telefono']) : null,
            'carrera'  => isset($datos['carrera'])  ? trim($datos['carrera'])  : null,
            'semestre' => isset($datos['semestre']) ? intval($datos['semestre']) : null,
            'salon'    => isset($datos['salon'])    ? trim($datos['salon'])    : null,
            'grado'    => isset($datos['grado'])    ? intval($datos['grado'])  : null,
            'grupo'    => isset($datos['grupo'])    ? trim($datos['grupo'])    : null,
        ];

        $resultado = crearUsuario($nombre, $apellido, $email, $password, 'alumno', $turno, $extras);
        if ($resultado['exito']) {
            $usuario = autenticarUsuarioPorCredenciales($email, $password);
            if ($usuario) {
                iniciarSesionApi($usuario);
                $resultado['usuario'] = [
                    "id"       => intval($usuario['id']),
                    "nombre"   => $usuario['nombre'],
                    "apellido" => $usuario['apellido'],
                    "tipo"     => $usuario['tipo'],
                    "email"    => $usuario['email']
                ];
            }
        }
        responderJson($resultado);
        break;

    case 'crearUsuario':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        if (!$es_admin) {
            responderJson(["exito" => false, "mensaje" => "Solo el Oficial Mayor puede crear usuarios"]);
        }
        $datos    = json_decode(file_get_contents("php://input"), true);
        $nombre   = isset($datos['nombre'])   ? trim($datos['nombre'])   : '';
        $apellido = isset($datos['apellido']) ? trim($datos['apellido']) : '';
        $email    = isset($datos['email'])    ? trim($datos['email'])    : '';
        $password = isset($datos['password']) ? $datos['password']       : '';
        $tipo     = isset($datos['tipo'])     ? trim($datos['tipo'])     : 'alumno';
        $turno    = isset($datos['turno'])    ? trim($datos['turno'])    : null;

        // Campos extras (aplican según el tipo)
        $extras = [
            'telefono' => isset($datos['telefono']) ? trim($datos['telefono']) : null,
            'carrera'  => isset($datos['carrera'])  ? trim($datos['carrera'])  : null,
            'semestre' => isset($datos['semestre']) ? intval($datos['semestre']) : null,
            'salon'    => isset($datos['salon'])    ? trim($datos['salon'])    : null,
            'grado'    => isset($datos['grado'])    ? intval($datos['grado'])  : null,
            'grupo'    => isset($datos['grupo'])    ? trim($datos['grupo'])    : null,
        ];

        responderJson(crearUsuario($nombre, $apellido, $email, $password, $tipo, $turno, $extras));
        break;

    case 'obtenerReportesSalon':
        if (!$id_usuario_actual) responderSinSesion();
        if (!$es_oficial_mayor) {
            responderJson(["exito" => false, "mensaje" => "Solo el Oficial Mayor puede ver reportes por salón"]);
        }
        $usuario_actual = obtenerUsuarioPorId($id_usuario_actual);
        $turno_om       = isset($usuario_actual['turno']) ? $usuario_actual['turno'] : null;
        $reportes       = obtenerReportesPorSalon($turno_om);
        responderJson(["exito" => true, "reportes" => $reportes, "turno" => $turno_om]);
        break;

    case 'actualizarUsuario':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderJson(["exito" => false, "mensaje" => "Método no permitido"]);
        }
        if (!$es_admin) {
            responderJson(["exito" => false, "mensaje" => "Solo el Oficial Mayor puede editar usuarios"]);
        }
        $datos      = json_decode(file_get_contents("php://input"), true);
        $id_usuario = isset($datos['id_usuario']) ? intval($datos['id_usuario']) : 0;
        $nombre     = isset($datos['nombre'])     ? trim($datos['nombre'])       : '';
        $apellido   = isset($datos['apellido'])   ? trim($datos['apellido'])     : '';
        $tipo       = isset($datos['tipo'])       ? trim($datos['tipo'])         : '';
        $turno      = isset($datos['turno'])      ? trim($datos['turno'])        : null;

        if (!$id_usuario || empty($nombre) || empty($apellido) || empty($tipo)) {
            responderJson(["exito" => false, "mensaje" => "ID, nombre, apellido y rol son requeridos"]);
        }
        responderJson(actualizarUsuario($id_usuario, $nombre, $apellido, $tipo, $turno));
        break;

    // ── Default ────────────────────────────────────────────────────────────
    default:
        responderJson(["exito" => false, "mensaje" => "Acción no reconocida"]);
}
?>
