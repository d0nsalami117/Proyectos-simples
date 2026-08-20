<?php
session_start();
require 'conexiongym.php';

if (!isset($_SESSION['sesion']) || $_SESSION['sesion'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_role'])) {
    if (!$isAdmin) {
        header('Location: menu.php');
        exit;
    }

    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $new_role = $_POST['user_role'] ?? '';
    $allowed_roles = ['usuario', 'admin'];

    if ($user_id <= 0) {
        $error = 'Usuario inválido.';
    } elseif (!in_array($new_role, $allowed_roles, true)) {
        $error = 'Rol inválido.';
    } else {
        $stmt = mysqli_prepare($F, 'UPDATE usuarios SET Rol = ? WHERE id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $new_role, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Rol actualizado correctamente.';
            } else {
                $error = 'No se pudo actualizar el rol.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = 'No se pudo preparar la consulta.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_routine'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    $name = $_POST['routineName'] ?? '';
    $description = $_POST['routineDescription'] ?? '';
    $level = $_POST['routineLevel'] ?? '';

    $stmt = mysqli_prepare($F, 'INSERT INTO rutinas (nombre, descripcion, nivel) VALUES (?, ?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sss', $name, $description, $level);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_attendance'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    $user_id = $isAdmin ? (int) $_POST['attendanceUser'] : (int) $_SESSION['user_id'];
    $date = $_POST['attendanceDate'] ?? '';

    $stmt = mysqli_prepare($F, 'INSERT INTO asistencia (usuario_id, fecha) VALUES (?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $user_id, $date);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_routine'])) {
    if (!$isAdmin) {
        header('Location: menu.php');
        exit;
    }

    $user_id = isset($_POST['assignUser']) ? (int) $_POST['assignUser'] : 0;
    $routine_id = isset($_POST['assignRoutine']) ? (int) $_POST['assignRoutine'] : 0;

    $stmt = mysqli_prepare($F, 'INSERT INTO rutinas_asignadas (usuario_id, rutina_id) VALUES (?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $routine_id);
        if (!mysqli_stmt_execute($stmt)) {
            $error = 'No se pudo asignar la rutina: ' . mysqli_error($F);
        } else {
            $success = 'Rutina asignada correctamente.';
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = 'No se pudo preparar la consulta.';
    }
}

$active_section = $_GET['section'] ?? 'usuarios';

$users_query = "SELECT id, Nombre, Correo, Tipo_Suscripcion, Rol FROM usuarios";
$users_result = mysqli_query($F, $users_query);
if (!$users_result) {
    $error = 'Error consultando usuarios: ' . mysqli_error($F);
}

$routines_query = "SELECT id, nombre, descripcion, nivel FROM rutinas";
$routines_result = mysqli_query($F, $routines_query);
if (!$routines_result) {
    $error = 'Error consultando rutinas: ' . mysqli_error($F);
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($isAdmin) {
    $assigned_routines_query = "SELECT ra.id, u.Nombre AS usuario_nombre, r.nombre AS rutina_nombre, r.descripcion, r.nivel, ra.asignada_en FROM rutinas_asignadas ra JOIN usuarios u ON ra.usuario_id = u.id JOIN rutinas r ON ra.rutina_id = r.id ORDER BY ra.asignada_en DESC";
    $assigned_routines_result = mysqli_query($F, $assigned_routines_query);
} else {
    $assigned_routines_stmt = mysqli_prepare($F, "SELECT ra.id, r.nombre AS rutina_nombre, r.descripcion, r.nivel, ra.asignada_en FROM rutinas_asignadas ra JOIN rutinas r ON ra.rutina_id = r.id WHERE ra.usuario_id = ? ORDER BY ra.asignada_en DESC");
    if ($assigned_routines_stmt) {
        mysqli_stmt_bind_param($assigned_routines_stmt, 'i', $user_id);
        mysqli_stmt_execute($assigned_routines_stmt);
        $assigned_routines_result = mysqli_stmt_get_result($assigned_routines_stmt);
        mysqli_stmt_close($assigned_routines_stmt);
    } else {
        $assigned_routines_result = false;
    }
}
if (!$assigned_routines_result) {
    $error = 'Error consultando rutinas asignadas: ' . mysqli_error($F);
}

if ($isAdmin) {
    $attendance_query = "SELECT a.fecha, u.Nombre FROM asistencia a JOIN usuarios u ON a.usuario_id = u.id ORDER BY a.fecha DESC";
    $attendance_result = mysqli_query($F, $attendance_query);
} else {
    $attendance_stmt = mysqli_prepare($F, "SELECT a.fecha, u.Nombre FROM asistencia a JOIN usuarios u ON a.usuario_id = u.id WHERE a.usuario_id = ? ORDER BY a.fecha DESC");
    if ($attendance_stmt) {
        mysqli_stmt_bind_param($attendance_stmt, 'i', $user_id);
        mysqli_stmt_execute($attendance_stmt);
        $attendance_result = mysqli_stmt_get_result($attendance_stmt);
        mysqli_stmt_close($attendance_stmt);
    } else {
        $attendance_result = false;
    }
}
if (!$attendance_result) {
    $error = 'Error consultando asistencia: ' . mysqli_error($F);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administración de Gimnasio</title>
  <style> :root {
      --bg: #f4f7fc;
      --surface: #ffffff;
      --primary: #2e1f82;
      --primary-dark: #2e1f82;
      --danger: #d3c612;
      --success: #198754;
      --text: #2c3e50;
      --muted: #6c757d;
      --border: #dde2eb;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: monospace;
      background: var(--bg);
      color: var(--text);
    }

    header {
      background: var(--primary);
      color: #c8c2ff;
      padding: 24px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;

      background-image: url("LogoGym.png");
      background-size: 90px;
      background-position: left center;
      background-repeat: no-repeat;
      padding-left: 100px;
    }

    header h1 {
      margin: 0;
      font-size: 1.8rem;
    }

    .header-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      align-items: center;
    }

    .header-actions p {
      margin: 0;
    }

    a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
    }

    a:hover {
      text-decoration: underline;
    }

    header .status {
      margin-top: 8px;
      font-size: 0.95rem;
      color: #e2e9ff;
    }

    .container {
      max-width: 1180px;
      margin: 24px auto;
      padding: 0 20px 40px;
    }

    .nav-buttons {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 24px;
    }

    .nav-buttons a {
      padding: 14px 18px;
      border: 1px solid transparent;
      border-radius: 10px;
      background: white;
      cursor: pointer;
      transition: all 0.2s ease;
      font-weight: 600;
      display: block;
      text-align: center;
      text-decoration: none;
      color: var(--text);
    }

    .nav-buttons a.active,
    .nav-buttons a:hover {
      border-color: var(--primary);
      background: #eef4ff;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 10px 35px rgba(56, 76, 120, 0.06);
    }

    .card h2 {
      margin-top: 0;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 20px;
    }

    form {
      display: grid;
      gap: 16px;
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 6px;
    }

    input, select, button, textarea {
      width: 100%;
      border-radius: 12px;
      border: 1px solid var(--border);
      padding: 14px 16px;
      font-size: 1rem;
    }

    textarea {
      resize: vertical;
      min-height: 100px;
    }

    button.primary {
      background: var(--primary);
      color: white;
      border: none;
    }

    button.secondary {
      background: #f8f9fa;
      border: 1px solid var(--border);
    }

    button.danger {
      background: var(--danger);
      color: white;
      border: none;
    }

    button.success {
      background: var(--success);
      color: white;
      border: none;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 12px;
    }

    th, td {
      padding: 12px 10px;
      border-bottom: 1px solid var(--border);
      text-align: left;
    }

    th {
      background: #f8f9fa;
      font-weight: 700;
    }

    .section {
      display: none;
    }

    .section.active {
      display: block;
    }

    .note {
      color: var(--muted);
      margin-top: 6px;
    }

    .routines-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
    }

    .routine-card {
      background: #f8fbff;
      border: 1px solid #d8e5ff;
      border-radius: 16px;
      padding: 18px;
    }

    .routine-card h3 {
      margin: 0 0 10px;
      font-size: 1.1rem;
    }

    .badge {
      display: inline-block;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 0.85rem;
      background: #e7f3ff;
      color: var(--primary);
    }

    @media (max-width: 860px) {
      .grid-2 {
        grid-template-columns: 1fr;
      }

      .nav-buttons {
        grid-template-columns: 1fr;
      }
    }</style>
</head>
<body>
  <header>
    <section>
      <h1>Administración de Gimnasio</h1>
      <p class="status">Panel para usuarios, rutinas, asistencia y administración de suscripciones.</p>
    </section>
    <section class="header-actions">
      <?php if (isset($_SESSION['user_name'])): ?>
        <p id="userStatus">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (<?php echo htmlspecialchars($_SESSION['user_role'] ?? 'usuario'); ?>)</p>
        <p><a href="?logout=1" style="color: #ffcccc; text-decoration: underline;">Cerrar sesión</a></p>
      <?php else: ?>
        <p id="userStatus">No has iniciado sesión.</p>
        <button id="loginPageButton" class="secondary">Ir a inicio de sesión</button>
      <?php endif; ?>
    </section>
  </header>

  <main class="container">
    <nav class="nav-buttons">
      <a href="?section=usuarios" class="<?php echo $active_section === 'usuarios' ? 'active' : ''; ?>">Usuarios</a>
      <a href="?section=rutinas" class="<?php echo $active_section === 'rutinas' ? 'active' : ''; ?>">Rutinas</a>
      <a href="?section=asistencia" class="<?php echo $active_section === 'asistencia' ? 'active' : ''; ?>">Asistencia</a>
      <?php if ($isAdmin): ?>
      <a href="?section=admin" class="<?php echo $active_section === 'admin' ? 'active' : ''; ?>">Panel Admin</a>
      <?php endif; ?>
    </nav>

    <section id="usuarios" class="section <?php echo $active_section === 'usuarios' ? 'active' : ''; ?> card">
      <h2>Usuarios registrados</h2>
      <section class="card" style="margin-top: 24px;">
        <h3>Lista de usuarios</h3>
        <table>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Email</th>
              <th>Suscripción</th>
              <th>Rol</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
              <?php mysqli_data_seek($users_result, 0); ?>
              <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
              <tr>
                <td><?php echo htmlspecialchars($user['Nombre']); ?></td>
                <td><?php echo htmlspecialchars($user['Correo']); ?></td>
                <td><?php echo htmlspecialchars($user['Tipo_Suscripcion']); ?></td>
                <td><?php echo htmlspecialchars($user['Rol']); ?></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="4">No hay usuarios registrados.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </section>

    <section id="rutinas" class="section <?php echo $active_section === 'rutinas' ? 'active' : ''; ?> card">
      <h2>Visualización de Rutinas</h2>
      <?php if ($isAdmin): ?>
      <section class="routines-grid" id="routineCards">
        <?php if ($routines_result): ?>
          <?php while ($routine = mysqli_fetch_assoc($routines_result)): ?>
          <article class="routine-card">
            <h3><?php echo htmlspecialchars($routine['nombre']); ?></h3>
            <p><?php echo htmlspecialchars($routine['descripcion']); ?></p>
            <p>Nivel: <?php echo htmlspecialchars($routine['nivel']); ?></p>
          </article>
          <?php endwhile; ?>
        <?php else: ?>
          <p>No hay rutinas disponibles.</p>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <section class="card" style="margin-top: 24px;">
        <h3><?php echo $isAdmin ? 'Rutinas asignadas a usuarios' : 'Mis rutinas asignadas'; ?></h3>
        <table>
          <thead>
            <tr>
              <?php if ($isAdmin): ?>
                <th>Usuario</th>
              <?php endif; ?>
              <th>Rutina</th>
              <th>Descripción</th>
              <th>Nivel</th>
              <th>Asignada en</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($assigned_routines_result && mysqli_num_rows($assigned_routines_result) > 0): ?>
              <?php while ($assigned = mysqli_fetch_assoc($assigned_routines_result)): ?>
              <tr>
                <?php if ($isAdmin): ?>
                  <td><?php echo htmlspecialchars($assigned['usuario_nombre']); ?></td>
                <?php endif; ?>
                <td><?php echo htmlspecialchars($assigned['rutina_nombre']); ?></td>
                <td><?php echo htmlspecialchars($assigned['descripcion']); ?></td>
                <td><?php echo htmlspecialchars($assigned['nivel']); ?></td>
                <td><?php echo htmlspecialchars($assigned['asignada_en']); ?></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="<?php echo $isAdmin ? '5' : '4'; ?>">No hay rutinas asignadas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <?php if ($isAdmin): ?>
      <section class="card" style="margin-top: 24px;">
        <h3>Agregar rutina nueva</h3>
        <form id="routineForm" method="POST">
          <label for="routineName">Nombre de la rutina</label>
          <input id="routineName" name="routineName" type="text" placeholder="Full body" required>

          <label for="routineDescription">Descripción</label>
          <textarea id="routineDescription" name="routineDescription" placeholder="Descripción de ejercicios..."></textarea>

          <label for="routineLevel">Nivel</label>
          <select id="routineLevel" name="routineLevel">
            <option value="Principiante">Principiante</option>
            <option value="Intermedio">Intermedio</option>
            <option value="Avanzado">Avanzado</option>
          </select>

          <button type="submit" name="add_routine" class="primary">Agregar rutina</button>
        </form>
      </section>
      <?php endif; ?>
    </section>


    <section id="asistencia" class="section <?php echo $active_section === 'asistencia' ? 'active' : ''; ?> card">
      <h2>Registro de Asistencia</h2>
      <form id="attendanceForm" method="POST" class="grid-2">
        <section>
          <label for="attendanceUser">Usuario</label>
          <?php if ($isAdmin): ?>
          <select id="attendanceUser" name="attendanceUser" required>
            <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
              <?php mysqli_data_seek($users_result, 0); ?>
              <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
              <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['Nombre']); ?></option>
              <?php endwhile; ?>
            <?php else: ?>
              <option value="">No hay usuarios disponibles</option>
            <?php endif; ?>
          </select>
          <?php else: ?>
          <input type="text" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" readonly>
          <input type="hidden" name="attendanceUser" value="<?php echo $_SESSION['user_id']; ?>">
          <?php endif; ?>
        </section>
        <section>
          <label for="attendanceDate">Fecha</label>
          <input id="attendanceDate" name="attendanceDate" type="date" required>
        </section>
        <section style="grid-column: 1 / -1; display: flex; gap: 10px; align-items: flex-end;">
          <button type="submit" name="add_attendance" class="primary">Registrar asistencia</button>
          <button type="button" id="clearAttendance" class="secondary">Limpiar</button>
        </section>
      </form>

      <section class="card" style="margin-top: 24px;">
        <h3>Historial de asistencia</h3>
        <table>
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody id="attendanceTableBody">
            <?php if ($attendance_result): ?>
              <?php while ($att = mysqli_fetch_assoc($attendance_result)): ?>
              <tr>
                <td><?php echo htmlspecialchars($att['Nombre']); ?></td>
                <td><?php echo htmlspecialchars($att['fecha']); ?></td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="2">No hay registros de asistencia.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </section>

    <section id="admin" class="section <?php echo $active_section === 'admin' ? 'active' : ''; ?> card">
      <h2>Panel de Administración</h2>
      <?php if (!$isAdmin): ?>
        <p>No tienes permiso para ver el panel de administración.</p>
      <?php else: ?>
        <?php if (isset($success)): ?>
          <p class="note" style="color: green;"><?php echo $success; ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
          <p class="note" style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>

        <section class="card" style="margin-top: 24px;">
          <h3>Asignar rol a usuario</h3>
          <form method="POST">
            <label for="user_id">Selecciona un usuario</label>
            <select id="user_id" name="user_id" required>
              <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
                <?php mysqli_data_seek($users_result, 0); ?>
                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['Nombre'] . ' (' . $user['Correo'] . ') - ' . $user['Rol']); ?></option>
                <?php endwhile; ?>
              <?php else: ?>
                <option value="">No hay usuarios disponibles</option>
              <?php endif; ?>
            </select>

            <label for="user_role">Rol</label>
            <select id="user_role" name="user_role" required>
              <option value="usuario">Usuario</option>
              <option value="admin">Admin</option>
            </select>

            <button type="submit" name="update_role" class="primary">Actualizar rol</button>
          </form>
        </section>

        <section class="card" style="margin-top: 24px;">
          <h3>Asignar rutina a un usuario</h3>
          <form method="POST">
            <label for="assignUser">Usuario</label>
            <select id="assignUser" name="assignUser" required>
              <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
                <?php mysqli_data_seek($users_result, 0); ?>
                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['Nombre'] . ' (' . $user['Correo'] . ')'); ?></option>
                <?php endwhile; ?>
              <?php else: ?>
                <option value="">No hay usuarios disponibles</option>
              <?php endif; ?>
            </select>

            <label for="assignRoutine">Rutina</label>
            <select id="assignRoutine" name="assignRoutine" required>
              <?php if ($routines_result && mysqli_num_rows($routines_result) > 0): ?>
                <?php mysqli_data_seek($routines_result, 0); ?>
                <?php while ($routine = mysqli_fetch_assoc($routines_result)): ?>
                <option value="<?php echo $routine['id']; ?>"><?php echo htmlspecialchars($routine['nombre'] . ' - ' . $routine['nivel']); ?></option>
                <?php endwhile; ?>
              <?php else: ?>
                <option value="">No hay rutinas disponibles</option>
              <?php endif; ?>
            </select>

            <button type="submit" name="assign_routine" class="primary">Asignar rutina</button>
          </form>
        </section>
      <?php endif; ?>
    </section>
  </main>

  <script>
    const loginPageButton = document.getElementById('loginPageButton');
    if (loginPageButton) {
      loginPageButton.addEventListener('click', () => {
        window.location.href = 'login.php';
      });
    }
  </script>
</body>
</html>