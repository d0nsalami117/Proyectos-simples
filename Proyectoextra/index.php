<?php
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once 'config.php';

function cerrarSesion() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function iniciarSesionUsuario($usuario) {
    session_regenerate_id(true);
    $_SESSION['id_usuario'] = intval($usuario['id']);
    $_SESSION['tipo'] = $usuario['tipo'];
    $_SESSION['nombre'] = $usuario['nombre'];
}

function validarCredenciales($conexion, $email) {
    $stmt = $conexion->prepare("SELECT id, nombre, tipo, password FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

if (isset($_GET['logout'])) {
    cerrarSesion();
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (!$email || !$password) {
        $error = 'Email y contraseña son requeridos';
    } else {
        $user = validarCredenciales($conexion, $email);

        if ($user && !empty($user['password'])) {
            $storedPassword = (string)$user['password'];
            $passwordValida = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

            if ($passwordValida) {
                if (!password_verify($password, $storedPassword)) {
                    $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                    $update = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $update->bind_param('si', $nuevoHash, $user['id']);
                    $update->execute();
                }

                iniciarSesionUsuario($user);
                header('Location: index.php');
                exit;
            }
        }

        $error = 'Credenciales inválidas. Revisa el email y la contraseña.';
    }
}

$usuarioAutenticado = isset($_SESSION['id_usuario']);
$nombreUsuario = $usuarioAutenticado && isset($_SESSION['nombre']) ? $_SESSION['nombre'] : '';
$tipoUsuario = $usuarioAutenticado && isset($_SESSION['tipo']) ? $_SESSION['tipo'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conecta — Sistema de Notificaciones</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
    /* ═══════════════════════════════════════════════════
       VARIABLES Y RESET
    ═══════════════════════════════════════════════════ */
    :root {
        --color-primario:          #0d1117;
        --color-primario-oscuro:   #080d13;
        --color-secundario:        #38bdf8;
        --color-secundario-oscuro: #0ea5e9;
        --color-secundario-hover:  #7dd3fc;
        --color-peligro:           #f87171;
        --color-peligro-oscuro:    #ef4444;
        --color-exito:             #34d399;
        --color-advertencia:       #fbbf24;
        --color-fondo:             #060b12;
        --color-fondo-secundario:  #0d1117;
        --color-card:              #111827;
        --color-card-hover:        #162032;
        --color-borde:             #1e2d3d;
        --color-borde-hover:       #38bdf8;
        --color-texto:             #e2e8f0;
        --color-texto-secundario:  #64748b;
        --color-texto-muted:       #374151;
        --sombra:                  0 4px 24px rgba(0,0,0,.5);
        --sombra-lg:               0 8px 40px rgba(0,0,0,.6);
        --radio:                   14px;
        --radio-sm:                8px;
        --radio-lg:                20px;
        --font-principal: 'Plus Jakarta Sans', sans-serif;
        --font-mono: 'JetBrains Mono', monospace;
    }

    *, *::before, *::after {
        margin: 0; padding: 0; box-sizing: border-box;
    }

    html { scroll-behavior: smooth; }

    body {
        font-family: var(--font-principal);
        background: var(--color-fondo);
        color: var(--color-texto);
        min-height: 100vh;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
    }

    /* ═══════════════════════════════════════════════════
       LOGIN
    ═══════════════════════════════════════════════════ */
    .login-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        background:
            radial-gradient(ellipse 80% 60% at 50% -10%, rgba(56,189,248,.12) 0%, transparent 60%),
            var(--color-fondo);
    }

    .login-card {
        background: var(--color-card);
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-lg);
        padding: 2.5rem;
        width: 100%;
        max-width: 460px;
        box-shadow: var(--sombra-lg);
        animation: fadeUp .4s ease;
    }

    .login-logo {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin-bottom: 1.75rem;
    }

    .login-logo img {
        height: 42px;
        width: auto;
        border-radius: var(--radio-sm);
        object-fit: cover;
    }

    .login-logo-text {
        font-size: 1.8rem;
        font-weight: 800;
        letter-spacing: -.5px;
        color: var(--color-texto);
    }

    .login-card h1 {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--color-texto);
        margin-bottom: .4rem;
    }

    .login-card > p {
        color: var(--color-texto-secundario);
        font-size: .9rem;
        margin-bottom: 1.5rem;
    }

    .login-error {
        background: rgba(248,113,113,.1);
        color: #fca5a5;
        border: 1px solid rgba(248,113,113,.3);
        padding: .75rem 1rem;
        border-radius: var(--radio-sm);
        margin-bottom: 1rem;
        font-size: .88rem;
    }

    .login-divider {
        border: none;
        border-top: 1px solid var(--color-borde);
        margin: 1.75rem 0;
    }

    .login-card h2 {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--color-texto);
    }

    .login-demo-hint {
        margin-top: 1rem;
        font-size: .82rem;
        color: var(--color-texto-secundario);
        padding: .6rem .9rem;
        background: rgba(56,189,248,.06);
        border-radius: var(--radio-sm);
        border: 1px solid rgba(56,189,248,.12);
        font-family: var(--font-mono);
    }

    /* ═══════════════════════════════════════════════════
       FORMULARIOS GENÉRICOS
    ═══════════════════════════════════════════════════ */
    .formulario,
    .formulario-grupo {
        display: flex;
        flex-direction: column;
        gap: .5rem;
        margin-bottom: .9rem;
    }

    label {
        font-size: .82rem;
        font-weight: 600;
        color: var(--color-texto-secundario);
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    input, select, textarea {
        width: 100%;
        padding: .75rem 1rem;
        border-radius: var(--radio-sm);
        border: 1px solid var(--color-borde);
        background: rgba(255,255,255,.03);
        color: var(--color-texto);
        font-family: var(--font-principal);
        font-size: .92rem;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }

    input::placeholder, textarea::placeholder {
        color: var(--color-texto-muted);
    }

    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--color-secundario);
        box-shadow: 0 0 0 3px rgba(56,189,248,.12);
        background: rgba(56,189,248,.03);
    }

    select option {
        background: var(--color-card);
        color: var(--color-texto);
    }

    textarea { resize: vertical; min-height: 120px; }

    small {
        color: var(--color-texto-secundario);
        font-size: .78rem;
        font-family: var(--font-mono);
    }

    /* ═══════════════════════════════════════════════════
       BOTONES
    ═══════════════════════════════════════════════════ */
    .boton {
        border: none;
        border-radius: var(--radio-sm);
        padding: .75rem 1.2rem;
        cursor: pointer;
        transition: all .2s ease;
        font-weight: 600;
        font-size: .9rem;
        font-family: var(--font-principal);
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }

    .boton-primario {
        background: var(--color-secundario);
        color: #001a28;
        width: 100%;
        justify-content: center;
        font-size: .95rem;
        padding: .85rem 1.2rem;
    }

    .boton-primario:hover {
        background: var(--color-secundario-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(56,189,248,.3);
    }

    .boton-secundario {
        background: rgba(56,189,248,.12);
        color: var(--color-secundario);
        border: 1px solid rgba(56,189,248,.25);
    }

    .boton-secundario:hover {
        background: rgba(56,189,248,.2);
        border-color: var(--color-secundario);
        transform: translateY(-1px);
    }

    .boton-peligro {
        background: rgba(248,113,113,.12);
        color: var(--color-peligro);
        border: 1px solid rgba(248,113,113,.25);
    }

    .boton-peligro:hover {
        background: var(--color-peligro-oscuro);
        color: white;
        border-color: var(--color-peligro-oscuro);
    }

    .boton:disabled {
        opacity: .5;
        cursor: not-allowed;
        transform: none !important;
    }

    /* ═══════════════════════════════════════════════════
       NAVBAR / TOPBAR
    ═══════════════════════════════════════════════════ */
    .sidebar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: rgba(13,17,23,.92);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-bottom: 1px solid var(--color-borde);
        padding: .75rem 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        box-shadow: 0 2px 20px rgba(0,0,0,.4);
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        gap: .75rem;
        flex-shrink: 0;
    }

    .sidebar-header img {
        height: 36px;
        width: auto;
        border-radius: 6px;
        object-fit: cover;
    }

    .sidebar-logo-nombre {
        font-size: 1.3rem;
        font-weight: 800;
        color: var(--color-texto);
        letter-spacing: -.3px;
    }

    .sidebar-nav ul {
        list-style: none;
        display: flex;
        gap: .35rem;
        flex-wrap: wrap;
    }

    .sidebar-link {
        text-decoration: none;
        color: var(--color-texto-secundario);
        padding: .5rem .9rem;
        border-radius: var(--radio-sm);
        font-size: .88rem;
        font-weight: 600;
        transition: all .2s ease;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .sidebar-link:hover {
        color: var(--color-texto);
        background: rgba(255,255,255,.06);
    }

    .sidebar-link.active {
        color: var(--color-secundario);
        background: rgba(56,189,248,.1);
        border-color: rgba(56,189,248,.2);
    }

    .sidebar-link.logout {
        color: var(--color-texto-secundario);
    }

    .sidebar-link.logout:hover {
        color: var(--color-peligro);
        background: rgba(248,113,113,.1);
        border-color: rgba(248,113,113,.2);
    }

    .sidebar-user {
        text-align: right;
        flex-shrink: 0;
        line-height: 1.3;
    }

    .sidebar-user #usuarioActual {
        font-size: .85rem;
        font-weight: 600;
        color: var(--color-texto);
        display: block;
    }

    .sidebar-rol {
        font-size: .75rem;
        color: var(--color-texto-secundario);
        text-transform: uppercase;
        letter-spacing: .06em;
        font-family: var(--font-mono);
    }

    /* ═══════════════════════════════════════════════════
       LAYOUT PRINCIPAL
    ═══════════════════════════════════════════════════ */
    .contenedor, .sidebar-layout {
        width: 100%;
        min-height: 100vh;
    }

    .main-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }

    /* ═══════════════════════════════════════════════════
       CARDS
    ═══════════════════════════════════════════════════ */
    .card {
        background: var(--color-card);
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-lg);
        padding: 1.75rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--sombra);
        animation: fadeUp .3s ease;
    }

    .card h2 {
        font-size: 1.15rem;
        font-weight: 700;
        margin-bottom: 1.25rem;
        color: var(--color-texto);
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    /* ═══════════════════════════════════════════════════
       NOTIFICACIONES — HEADER CON FILTRO
    ═══════════════════════════════════════════════════ */
    .notificaciones-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .notificaciones-header h2 { margin-bottom: 0; }

    .filtro {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        flex-wrap: wrap;
        padding: .85rem 1.1rem;
        background: rgba(255,255,255,.02);
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-sm);
        margin-bottom: 1.25rem;
    }

    .filtro label {
        display: flex;
        align-items: center;
        gap: .5rem;
        cursor: pointer;
        font-size: .85rem;
        text-transform: none;
        letter-spacing: 0;
        color: var(--color-texto-secundario);
        font-weight: 500;
    }

    .filtro input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--color-secundario);
        cursor: pointer;
    }

    .contadores {
        display: flex;
        gap: .75rem;
        margin-left: auto;
    }

    .contador {
        font-size: .78rem;
        font-family: var(--font-mono);
        padding: .3rem .7rem;
        border-radius: 20px;
        background: rgba(255,255,255,.04);
        border: 1px solid var(--color-borde);
        color: var(--color-texto-secundario);
    }

    /* ═══════════════════════════════════════════════════
       NOTIFICACIONES — COLUMNAS
    ═══════════════════════════════════════════════════ */
    .notificaciones-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    .col-titulo {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .1em;
        color: var(--color-texto-secundario);
        margin-bottom: .9rem;
        padding-bottom: .6rem;
        border-bottom: 1px solid var(--color-borde);
        font-family: var(--font-mono);
    }

    .lista-notif {
        display: flex;
        flex-direction: column;
        gap: .75rem;
    }

    /* ═══════════════════════════════════════════════════
       TARJETA NOTIFICACIÓN
    ═══════════════════════════════════════════════════ */
    .notificacion {
        background: rgba(255,255,255,.025);
        border: 1px solid var(--color-borde);
        border-radius: var(--radio);
        padding: 1.1rem;
        cursor: pointer;
        transition: all .2s ease;
        position: relative;
        overflow: hidden;
    }

    .notificacion::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 3px;
        background: var(--color-secundario);
        opacity: 0;
        transition: opacity .2s;
    }

    .notificacion:not(.leida)::before { opacity: 1; }

    .notificacion:hover {
        border-color: rgba(56,189,248,.3);
        background: var(--color-card-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(0,0,0,.3);
    }

    .notificacion.leida {
        opacity: .65;
    }

    .notificacion.leida:hover { opacity: .9; }

    .notificacion-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .5rem;
    }

    .notificacion-titulo {
        font-size: .95rem;
        font-weight: 700;
        color: var(--color-texto);
        line-height: 1.3;
    }

    .notificacion-admin {
        font-size: .75rem;
        color: var(--color-texto-secundario);
        white-space: nowrap;
        flex-shrink: 0;
    }

    .notificacion-meta {
        margin-bottom: .6rem;
        display: flex;
        gap: .4rem;
        flex-wrap: wrap;
    }

    .notificacion-mensaje {
        font-size: .88rem;
        color: var(--color-texto-secundario);
        line-height: 1.5;
        margin-bottom: .75rem;
    }

    .notificacion-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        flex-wrap: wrap;
    }

    .notificacion-fecha small {
        font-family: var(--font-mono);
        font-size: .75rem;
        color: var(--color-texto-muted);
        display: block;
    }

    .notificacion-acciones { display: flex; gap: .4rem; }

    /* ═══════════════════════════════════════════════════
       BADGES
    ═══════════════════════════════════════════════════ */
    .badge {
        display: inline-flex;
        align-items: center;
        padding: .25rem .65rem;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        font-family: var(--font-mono);
    }

    .badge-importancia.badge-alta {
        background: rgba(248,113,113,.15);
        color: #fca5a5;
        border: 1px solid rgba(248,113,113,.25);
    }

    .badge-importancia.badge-media {
        background: rgba(251,191,36,.12);
        color: #fcd34d;
        border: 1px solid rgba(251,191,36,.2);
    }

    .badge-importancia.badge-baja {
        background: rgba(52,211,153,.1);
        color: #6ee7b7;
        border: 1px solid rgba(52,211,153,.18);
    }

    /* leída / sin leer badges */
    .badge[style*="#e74c3c"],
    .badge[style*="e74c3c"] {
        background: rgba(239,68,68,.12) !important;
        color: #fca5a5 !important;
        border: 1px solid rgba(239,68,68,.2);
    }

    /* ═══════════════════════════════════════════════════
       MENSAJES DE ESTADO
    ═══════════════════════════════════════════════════ */
    .mensaje-estado {
        min-height: 1.5rem;
        font-size: .88rem;
        font-weight: 600;
        margin-top: .75rem;
        border-radius: var(--radio-sm);
        transition: all .3s ease;
    }

    .mensaje-estado.exito {
        background: rgba(52,211,153,.1);
        color: var(--color-exito);
        border: 1px solid rgba(52,211,153,.2);
        padding: .7rem 1rem;
    }

    .mensaje-estado.error {
        background: rgba(248,113,113,.1);
        color: #fca5a5;
        border: 1px solid rgba(248,113,113,.2);
        padding: .7rem 1rem;
    }

    .mensaje-vacio {
        color: var(--color-texto-secundario);
        font-size: .88rem;
        padding: 1.5rem 0;
        text-align: center;
    }

    /* ═══════════════════════════════════════════════════
       TABLA USUARIOS
    ═══════════════════════════════════════════════════ */
    .tabla-usuarios {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        font-size: .88rem;
    }

    .tabla-usuarios thead tr {
        background: rgba(255,255,255,.04);
    }

    .tabla-usuarios th {
        padding: .85rem 1rem;
        text-align: left;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--color-texto-secundario);
        border-bottom: 1px solid var(--color-borde);
        font-family: var(--font-mono);
    }

    .tabla-usuarios td {
        padding: .8rem 1rem;
        border-bottom: 1px solid rgba(30,45,61,.7);
        color: var(--color-texto);
        vertical-align: middle;
    }

    .tabla-usuarios tr:last-child td { border-bottom: none; }

    .tabla-usuarios tr:hover td {
        background: rgba(56,189,248,.03);
    }

    .tabla-usuarios input[type="text"],
    .tabla-usuarios select {
        padding: .45rem .7rem;
        font-size: .85rem;
        border-radius: 6px;
    }

    /* ═══════════════════════════════════════════════════
       FILTRO USUARIOS
    ═══════════════════════════════════════════════════ */
    .filtro-usuarios-panel {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: flex-end;
        margin-bottom: 1.25rem;
        padding: 1rem 1.2rem;
        background: rgba(255,255,255,.02);
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-sm);
    }

    .filtro-usuario-input { flex: 1; min-width: 200px; }

    .filtro-usuario-input label {
        display: block;
        margin-bottom: .4rem;
    }

    .filtros-roles {
        display: flex;
        gap: .4rem;
        flex-wrap: wrap;
    }

    .filtro-rol {
        padding: .4rem .9rem;
        font-size: .8rem;
        border-radius: 20px;
        background: rgba(255,255,255,.04);
        color: var(--color-texto-secundario);
        border: 1px solid var(--color-borde);
        cursor: pointer;
        transition: all .2s;
        font-weight: 600;
    }

    .filtro-rol.active {
        background: rgba(56,189,248,.12);
        color: var(--color-secundario);
        border-color: rgba(56,189,248,.3);
    }

    .filtro-rol:hover {
        border-color: var(--color-secundario);
        color: var(--color-secundario);
    }

    /* ═══════════════════════════════════════════════════
       FOOTER
    ═══════════════════════════════════════════════════ */
    .footer {
        text-align: center;
        padding: 1.5rem 2rem;
        font-size: .78rem;
        color: var(--color-texto-muted);
        border-top: 1px solid var(--color-borde);
        margin-top: 2rem;
        font-family: var(--font-mono);
    }

    /* ═══════════════════════════════════════════════════
       ANIMACIONES
    ═══════════════════════════════════════════════════ */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ═══════════════════════════════════════════════════
       RESPONSIVE
    ═══════════════════════════════════════════════════ */
    @media (max-width: 900px) {
        .sidebar {
            flex-direction: column;
            gap: .75rem;
            align-items: stretch;
            padding: .75rem 1rem;
        }
        .sidebar-nav ul { gap: .25rem; }
        .sidebar-link { font-size: .82rem; padding: .45rem .75rem; }
        .sidebar-user { text-align: left; }
        .main-content { padding: 1.25rem 1rem; }
        .notificaciones-columns { grid-template-columns: 1fr; }
        .notificaciones-header { flex-direction: column; align-items: stretch; }
    }

    /* ═══════════════════════════════════════════════════
       AVISOS DE HORARIO / LÍMITE
    ═══════════════════════════════════════════════════ */
    .aviso-horario {
        background: rgba(251,191,36,.1);
        color: #fcd34d;
        border: 1px solid rgba(251,191,36,.25);
        border-radius: var(--radio-sm);
        padding: .7rem 1rem;
        font-size: .86rem;
        font-weight: 600;
        margin-bottom: .75rem;
    }

    .aviso-restantes {
        font-size: .82rem;
        font-family: var(--font-mono);
        color: var(--color-texto-secundario);
        margin-bottom: .5rem;
    }

    .aviso-restantes.sin-restantes {
        color: var(--color-peligro);
        font-weight: 700;
    }

    /* ═══════════════════════════════════════════════════
       NOTIFICACIÓN — EMISOR Y RESPUESTA
    ═══════════════════════════════════════════════════ */
    .notif-emisor {
        margin-bottom: .6rem;
    }

    .emisor-chip {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        font-size: .8rem;
        color: var(--color-texto-secundario);
        background: rgba(255,255,255,.04);
        border: 1px solid var(--color-borde);
        border-radius: 20px;
        padding: .25rem .75rem;
    }

    .emisor-chip strong {
        color: var(--color-texto);
        font-weight: 600;
    }

    .notif-respuesta-contexto {
        background: rgba(56,189,248,.05);
        border-left: 3px solid rgba(56,189,248,.3);
        border-radius: 0 var(--radio-sm) var(--radio-sm) 0;
        padding: .6rem .9rem;
        margin-bottom: .65rem;
    }

    .respuesta-etiqueta {
        display: block;
        font-size: .75rem;
        font-weight: 700;
        color: var(--color-secundario);
        margin-bottom: .3rem;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .respuesta-mensaje-orig {
        font-size: .82rem;
        color: var(--color-texto-secundario);
        font-style: italic;
        line-height: 1.4;
    }

    .boton-responder {
        background: rgba(56,189,248,.08);
        color: var(--color-secundario);
        border: 1px solid rgba(56,189,248,.2);
        border-radius: var(--radio-sm);
        padding: .35rem .75rem;
        font-size: .78rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .2s;
        font-family: var(--font-principal);
    }

    .boton-responder:hover {
        background: rgba(56,189,248,.18);
        border-color: var(--color-secundario);
        transform: translateY(-1px);
    }

    /* ═══════════════════════════════════════════════════
       MODAL DE RESPUESTA
    ═══════════════════════════════════════════════════ */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
        backdrop-filter: blur(4px);
        animation: fadeIn .2s ease;
    }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .modal-card {
        background: var(--color-card);
        border: 1px solid var(--color-borde);
        border-radius: var(--radio-lg);
        padding: 2rem;
        width: 100%;
        max-width: 520px;
        box-shadow: var(--sombra-lg);
        animation: fadeUp .25s ease;
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--color-texto);
    }

    .modal-cerrar {
        background: none;
        border: none;
        color: var(--color-texto-secundario);
        font-size: 1.1rem;
        cursor: pointer;
        padding: .25rem .5rem;
        border-radius: var(--radio-sm);
        transition: all .2s;
    }

    .modal-cerrar:hover {
        background: rgba(248,113,113,.12);
        color: var(--color-peligro);
    }

    .modal-contexto {
        font-size: .85rem;
        color: var(--color-texto-secundario);
        margin-bottom: 1.25rem;
        padding: .6rem .9rem;
        background: rgba(56,189,248,.05);
        border-radius: var(--radio-sm);
        border: 1px solid rgba(56,189,248,.12);
    }

    .modal-contexto em {
        color: var(--color-texto);
        font-style: normal;
        font-weight: 600;
    }
    
    </style>
</head>
<body>

<?php if (!$usuarioAutenticado): ?>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-logo">
                <img src="Logo.jpeg" alt="Logo Conecta">
                <span class="login-logo-text">Conecta</span>
            </div>
            <h1>Bienvenido de nuevo</h1>
            <p>Ingresa con tu email y contraseña para continuar.</p>
            <?php if ($error): ?>
                <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="index.php">
                <input type="hidden" name="login" value="1">
                <div class="formulario-grupo">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required placeholder="tu@email.com">
                </div>
                <div class="formulario-grupo">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="boton boton-primario">Entrar →</button>
            </form>
            <div class="login-demo-hint">Demo: oficial.matutino@conecta.edu / oficial123</div>
        </div>
    </div>

<?php else: ?>
    <div class="contenedor sidebar-layout">
        <nav class="sidebar">
            <div class="sidebar-header">
                <img src="Logo.jpeg" alt="Logo Conecta">
                <span class="sidebar-logo-nombre">Conecta</span>
            </div>
            <div class="sidebar-nav">
                <ul>
                    <li><a href="#" class="sidebar-link active" data-section="notificaciones">Inicio</a></li>
                    <?php if (in_array($tipoUsuario, ['oficial_mayor','intendente','maestro','alumno'], true)): ?>
                        <li><a href="#" class="sidebar-link" data-section="enviar">Enviar</a></li>
                    <?php endif; ?>
                    <?php if ($tipoUsuario === 'maestro'): ?>
                        <li><a href="#" class="sidebar-link" data-section="turno">Mi Turno</a></li>
                    <?php endif; ?>
                    <?php if ($tipoUsuario === 'oficial_mayor'): ?>
                        <li><a href="#" class="sidebar-link" data-section="crear">Crear usuario</a></li>
                        <li><a href="#" class="sidebar-link" data-section="usuarios">Gestionar usuarios</a></li>
                        <li><a href="#" class="sidebar-link" data-section="reportes">📊 Reportes</a></li>
                    <?php endif; ?>
                    <li><a href="index.php?logout=1" class="sidebar-link logout">Cerrar sesión</a></li>
                </ul>
            </div>
            <div class="sidebar-user">
                <span id="usuarioActual">Usuario: <?php echo htmlspecialchars($nombreUsuario); ?></span>
                <span class="sidebar-rol">Rol: <?php echo htmlspecialchars($tipoUsuario); ?></span>
            </div>
        </nav>

        <main class="main-content">
            <!-- NOTIFICACIONES -->
            <section id="section-notificaciones" class="section-content">
                <div class="card">
                    <div class="notificaciones-header">
                        <h2>📩 Notificaciones Recibidas</h2>
                        <div style="display:flex;gap:.6rem;align-items:center;">
                            <button id="btnBorrarLeidas" class="boton boton-peligro" title="Borrar todos los mensajes leídos">🗑️ Borrar leídas</button>
                            <button id="btnRefrescar" class="boton boton-secundario">🔄 Refrescar</button>
                        </div>
                    </div>
                    <div id="filtroNotificaciones" class="filtro">
                        <label>
                            <input type="checkbox" id="mostrarLeidas" checked>
                            Mostrar leídas
                        </label>
                        <div class="contadores">
                            <span id="contadorNoLeidas" class="contador">No leídas: 0</span>
                            <span id="contadorLeidas" class="contador">Leídas: 0</span>
                        </div>
                    </div>
                    <div id="contenedorNotificaciones">
                        <p class="mensaje-vacio">Cargando notificaciones...</p>
                    </div>
                </div>
            </section>

            <!-- ENVIAR NOTIFICACIÓN -->
            <?php if (in_array($tipoUsuario, ['oficial_mayor','intendente','maestro','alumno'], true)): ?>
            <section id="section-enviar" class="section-content" style="display:none;">
                <div class="card">
                    <h2>📤 Enviar Notificación</h2>
                    <form id="formularioNotificacion" class="formulario">
                        <div class="formulario-grupo">
                            <label for="usuarioDestino">Destinatario</label>
                            <select id="usuarioDestino" required>
                                <option value="">-- Seleccionar destinatario --</option>
                                <?php if ($tipoUsuario === 'oficial_mayor'): ?>
                                <optgroup label="── Envío masivo ──">
                                    <option value="todos">👥 Todos los usuarios</option>
                                </optgroup>
                                <?php endif; ?>
                                <optgroup id="grupoRoles" label="── Por rol ──">
                                    <!-- Se llena dinámicamente -->
                                </optgroup>
                                <optgroup id="grupoUsuarios" label="── Usuario individual ──">
                                    <!-- Se llena dinámicamente -->
                                </optgroup>
                            </select>
                        </div>
                        <div class="formulario-grupo">
                            <label for="importancia">Importancia</label>
                            <select id="importancia" required>
                                <option value="baja">Baja</option>
                                <option value="media" selected>Media</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>
                        <div class="formulario-grupo">
                            <label for="titulo">Título</label>
                            <input type="text" id="titulo" placeholder="Título de la notificación" maxlength="150" required>
                            <small id="contadorTitulo">0/150</small>
                        </div>
                        <div class="formulario-grupo">
                            <label for="mensaje">Mensaje</label>
                            <textarea id="mensaje" placeholder="Escribe el mensaje aquí..." rows="5" maxlength="1000" required></textarea>
                            <small id="contadorMensaje">0/1000</small>
                        </div>
                        <div id="avisoHorario"   class="aviso-horario"   style="display:none"></div>
                        <div id="avisoRestantes" class="aviso-restantes" style="display:none"></div>
                        <button type="submit" class="boton boton-primario">Enviar Notificación →</button>
                    </form>
                    <div id="mensajeEstado" class="mensaje-estado"></div>
                </div>
            </section>
            <?php endif; ?>

            <!-- CAMBIAR TURNO (solo maestros) -->
            <?php if ($tipoUsuario === 'maestro'): ?>
            <section id="section-turno" class="section-content" style="display:none;">
                <div class="card">
                    <h2>🕐 Mi Turno de Mensajes</h2>
                    <p style="color:var(--color-texto-suave);margin-bottom:1.2rem;">
                        Selecciona en qué turno deseas recibir y enviar mensajes. Solo podrás comunicarte con alumnos e intendentes de ese turno.
                    </p>
                    <form id="formularioTurno" class="formulario">
                        <div class="formulario-grupo">
                            <label for="selectTurnoMaestro">Turno activo</label>
                            <select id="selectTurnoMaestro" required>
                                <option value="">-- Seleccionar turno --</option>
                                <option value="matutino">🌅 Matutino (7:00 – 14:00)</option>
                                <option value="vespertino">🌆 Vespertino (14:00 – 21:00)</option>
                            </select>
                        </div>
                        <button type="submit" class="boton boton-primario">Guardar turno</button>
                    </form>
                    <div id="mensajeTurno" class="mensaje-estado"></div>
                </div>
            </section>
            <?php endif; ?>

            <!-- CREAR USUARIO -->
            <?php if ($tipoUsuario === 'oficial_mayor'): ?>
            <section id="section-crear" class="section-content" style="display:none;">
                <div class="card">
                    <h2>👤 Crear Nuevo Usuario</h2>
                    <form id="formularioCrearUsuario" class="formulario">
                        <div class="formulario-grupo">
                            <label for="nuevoNombre">Nombre</label>
                            <input type="text" id="nuevoNombre" placeholder="Nombre" required maxlength="100">
                        </div>
                        <div class="formulario-grupo">
                            <label for="nuevoApellido">Apellido</label>
                            <input type="text" id="nuevoApellido" placeholder="Apellido" required maxlength="100">
                        </div>
                        <div class="formulario-grupo">
                            <label for="nuevoEmail">Email</label>
                            <input type="email" id="nuevoEmail" placeholder="email@ejemplo.com" required>
                        </div>
                        <div class="formulario-grupo">
                            <label for="nuevoPassword">Contraseña</label>
                            <input type="password" id="nuevoPassword" placeholder="Mínimo 6 caracteres" required minlength="6">
                        </div>
                        <div class="formulario-grupo">
                            <label for="nuevoTipo">Rol</label>
                            <select id="nuevoTipo" required>
                                <option value="alumno">Alumno</option>
                                <option value="maestro">Maestro</option>
                                <option value="intendente">Intendente</option>
                                <option value="oficial_mayor">Oficial Mayor</option>
                            </select>
                        </div>
                        <div class="formulario-grupo" id="grupoNuevoTurno">
                            <label for="nuevoTurno">Turno</label>
                            <select id="nuevoTurno">
                                <option value="">-- Sin turno --</option>
                                <option value="matutino">Matutino (7-14h)</option>
                                <option value="vespertino">Vespertino (14-21h)</option>
                            </select>
                        </div>
                        <!-- Campos para maestros, intendentes y otros (no oficial mayor) -->
                        <div class="formulario-grupo" id="grupoNuevoTelefono">
                            <label for="nuevoTelefono">Teléfono</label>
                            <input type="tel" id="nuevoTelefono" placeholder="Ej. 3311234567" maxlength="15">
                        </div>
                        <!-- Campos solo para alumnos -->
                        <div id="camposAlumno" style="display:none;">
                            <div class="formulario-grupo">
                                <label for="nuevoCarrera">Carrera</label>
                                <select id="nuevoCarrera">
                                    <option value="">-- Seleccionar carrera --</option>
                                    <option value="Ingeniería en Sistemas Computacionales">Ing. en Sistemas Computacionales (ISC)</option>
                                    <option value="Ingeniería Industrial">Ingeniería Industrial (II)</option>
                                    <option value="Ingeniería Mecatrónica">Ingeniería Mecatrónica (IM)</option>
                                    <option value="Ingeniería en Electrónica">Ingeniería en Electrónica (IEL)</option>
                                    <option value="Ingeniería Civil">Ingeniería Civil (IC)</option>
                                    <option value="Administración de Empresas">Administración de Empresas (AE)</option>
                                    <option value="Contaduría Pública">Contaduría Pública (CP)</option>
                                    <option value="Médico Cirujano Partero">Médico Cirujano Partero (MCP)</option>
                                    <option value="Licenciatura en Enfermería">Licenciatura en Enfermería (LE)</option>
                                    <option value="Cirujano Dentista">Cirujano Dentista (CD)</option>
                                    <option value="Licenciatura en Nutrición">Licenciatura en Nutrición (LN)</option>
                                    <option value="Licenciatura en Psicología">Licenciatura en Psicología (LP)</option>
                                </select>
                            </div>
                            <div class="formulario-grupo">
                                <label for="nuevoSemestre">Semestre</label>
                                <select id="nuevoSemestre">
                                    <option value="">-- Seleccionar semestre --</option>
                                    <option value="1">1°</option>
                                    <option value="2">2°</option>
                                    <option value="3">3°</option>
                                    <option value="4">4°</option>
                                    <option value="5">5°</option>
                                    <option value="6">6°</option>
                                    <option value="7">7°</option>
                                    <option value="8">8°</option>
                                    <option value="9">9°</option>
                                    <option value="10">10°</option>
                                </select>
                            </div>
                            <div class="formulario-grupo">
                                <label for="nuevoGrado">Grado</label>
                                <select id="nuevoGrado">
                                    <option value="">-- Seleccionar grado --</option>
                                    <option value="1">1°</option>
                                    <option value="2">2°</option>
                                    <option value="3">3°</option>
                                    <option value="4">4°</option>
                                    <option value="5">5°</option>
                                </select>
                            </div>
                            <div class="formulario-grupo">
                                <label for="nuevoGrupo">Grupo</label>
                                <select id="nuevoGrupo">
                                    <option value="">-- Seleccionar grupo --</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                            </div>
                            <div class="formulario-grupo">
                                <label for="nuevoSalon">Salón</label>
                                <select id="nuevoSalon">
                                    <option value="">-- Seleccionar salón --</option>
                                    <option value="A1">A1</option>
                                    <option value="A2">A2</option>
                                    <option value="A3">A3</option>
                                    <option value="B1">B1</option>
                                    <option value="B2">B2</option>
                                    <option value="B3">B3</option>
                                    <option value="C1">C1</option>
                                    <option value="C2">C2</option>
                                    <option value="C3">C3</option>
                                    <option value="D1">D1</option>
                                    <option value="D2">D2</option>
                                    <option value="D3">D3</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="boton boton-primario">Crear Usuario</button>
                    </form>
                    <div id="mensajeCrearUsuario" class="mensaje-estado"></div>
                </div>
            </section>

            <!-- GESTIONAR USUARIOS -->
            <section id="section-usuarios" class="section-content" style="display:none;">
                <div class="card">
                    <h2>📋 Gestionar Usuarios</h2>
                    <div class="filtro-usuarios-panel">
                        <div class="filtro-usuario-input">
                            <label for="buscadorUsuarios">Buscar por nombre</label>
                            <input type="text" id="buscadorUsuarios" placeholder="Escribir nombre...">
                        </div>
                        <div class="filtros-roles">
                            <button type="button" class="filtro-rol active" data-rol="oficial_mayor">Oficial Mayor</button>
                            <button type="button" class="filtro-rol active" data-rol="intendente">Intendente</button>
                            <button type="button" class="filtro-rol active" data-rol="maestro">Maestro</button>
                            <button type="button" class="filtro-rol active" data-rol="alumno">Alumno</button>
                        </div>
                    </div>
                    <div id="contenedorUsuarios">
                        <p class="mensaje-vacio">Cargando usuarios...</p>
                    </div>
                </div>
            </section>

            <!-- REPORTES POR SALÓN -->
            <section id="section-reportes" class="section-content" style="display:none;">
                <div class="card">
                    <h2>📊 Reportes por Salón</h2>
                    <p style="color:var(--color-texto-suave);margin-bottom:1.2rem;">
                        Porcentaje de reportes (notificaciones enviadas por alumnos) agrupados por salón — correspondiente a tu turno.
                    </p>
                    <div id="contenedorGraficaSalon">
                        <p class="mensaje-vacio">Cargando datos...</p>
                    </div>
                    <canvas id="graficaBarrasSalon" style="display:none;margin-top:1.5rem;"></canvas>
                </div>
            </section>
            <?php endif; ?>
        </main>

        <footer class="footer">
            Sistema de Notificaciones v1.0 — Base de datos conectada ✓<?php echo $tipoUsuario ? ' — Rol: ' . htmlspecialchars($tipoUsuario) : ''; ?>
        </footer>
    </div>

<?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="script.js"></script>
</body>
</html>
