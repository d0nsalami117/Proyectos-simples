<?php
session_start();
require 'conexiongym.php';

if (isset($_SESSION['sesion']) && $_SESSION['sesion'] === true) {
    header('Location: menu.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $plan = trim($_POST['plan'] ?? '');
    $role = 'usuario';
    
    $check_stmt = mysqli_prepare($F, 'SELECT id FROM usuarios WHERE Correo = ?');
    if (!$check_stmt) {
        $error = 'Error en consulta: ' . mysqli_error($F);
    } else {
        mysqli_stmt_bind_param($check_stmt, 's', $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = 'El correo electrónico ya está registrado.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = mysqli_prepare($F, 'INSERT INTO usuarios (Nombre, Correo, Clave, Tipo_Suscripcion, Rol) VALUES (?, ?, ?, ?, ?)');
            if ($insert_stmt) {
                mysqli_stmt_bind_param($insert_stmt, 'sssss', $name, $email, $passwordHash, $plan, $role);
                if (mysqli_stmt_execute($insert_stmt)) {
                    $success = 'Registro exitoso. <a href="login.php">Inicia sesión</a>';
                } else {
                    $error = 'Error en el registro: ' . mysqli_error($F);
                }
                mysqli_stmt_close($insert_stmt);
            } else {
                $error = 'Error en el registro: ' . mysqli_error($F);
            }
        }
        mysqli_stmt_close($check_stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro - Gimnasio</title>
  <link rel="stylesheet" href="gimnasio.css">
</head>
<body>
  <header>
    <section>
      <h1>Registro</h1>
      <p class="status">Crea tu cuenta y luego inicia sesión para acceder al panel.</p>
    </section>
  </header>

  <main class="container">
    <section class="card">
      <h2>Registrarse</h2>
      <form method="POST">
        <label for="name">Nombre</label>
        <input id="name" name="name" type="text" placeholder="Juan Pérez" required>

        <label for="email">Correo electrónico</label>
        <input id="email" name="email" type="email" placeholder="juan@mail.com" required>

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" placeholder="********" required>

        <label for="plan">Suscripción</label>
        <select id="plan" name="plan">
          <option value="Básico">Básico</option>
          <option value="Premium">Premium</option>
          <option value="VIP">VIP</option>
        </select>

        <button type="submit" class="primary">Registrarse</button>
      </form>

      <p class="note">¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
      <?php if (isset($error)): ?>
        <p class="note" style="color: red;"><?php echo $error; ?></p>
      <?php endif; ?>
      <?php if (isset($success)): ?>
        <p class="note" style="color: green;"><?php echo $success; ?></p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>