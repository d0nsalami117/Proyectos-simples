<?php
session_start();
require 'conexiongym.php';

if (isset($_SESSION['sesion']) && $_SESSION['sesion'] === true) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userLogin = $_POST['user'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($userLogin) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $query = "SELECT id, Nombre, Rol, Clave FROM usuarios WHERE Nombre = ? OR Correo = ? LIMIT 1";
        $stmt = mysqli_prepare($F, $query);

        if (!$stmt) {
            $error = 'Error de consulta: ' . mysqli_error($F);
        } else {
            mysqli_stmt_bind_param($stmt, 'ss', $userLogin, $userLogin);
            
            if (!mysqli_stmt_execute($stmt)) {
                $error = 'Error ejecutando consulta: ' . mysqli_stmt_error($stmt);
            } else {
                $result = mysqli_stmt_get_result($stmt);

                if ($result && $user = mysqli_fetch_assoc($result)) {
                    $passwordMatch = false;
                    
                    // Verificar si la contraseña es hash
                    if (password_verify($password, $user['Clave'])) {
                        $passwordMatch = true;
                    } elseif ($password === $user['Clave']) {
                        // Contraseña en texto plano - hashearla y actualizarla
                        $passwordMatch = true;
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $updateQuery = "UPDATE usuarios SET Clave = ? WHERE id = ?";
                        $updateStmt = mysqli_prepare($F, $updateQuery);
                        if ($updateStmt) {
                            mysqli_stmt_bind_param($updateStmt, 'si', $newHash, $user['id']);
                            mysqli_stmt_execute($updateStmt);
                            mysqli_stmt_close($updateStmt);
                        }
                    }
                    
                    if ($passwordMatch) {
                        session_regenerate_id(true);
                        $_SESSION['sesion'] = true;
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['Nombre'];
                        $_SESSION['user_role'] = $user['Rol'];

                        if ($user['Rol'] === 'admin') {
                            header('Location: index.php?section=admin');
                        } else {
                            header('Location: index.php');
                        }
                        mysqli_stmt_close($stmt);
                        exit;
                    } else {
                        $error = 'Nombre, correo o contraseña incorrectos.';
                    }
                } else {
                    $error = 'Nombre, correo o contraseña incorrectos.';
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inicio de sesión - Gimnasio</title>
  <link rel="stylesheet" href="gimnasio.css">
</head>
<body>
  <header>
    <section>
      <h1>Iniciar sesión</h1>
      <p class="status">Accede a tu cuenta para ver el panel del gimnasio.</p>
    </section>
  </header>

  <main class="container">
    <section class="card">
      <h2>Login</h2>
      <form method="POST">
        <label for="user">Correo electrónico o nombre</label>
        <input id="user" name="user" type="text" placeholder="juan@mail.com o juan" required>

        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" placeholder="********" required>

        <button type="submit" class="primary">Entrar</button>
      </form>

      <p class="note">¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
      <?php if (isset($error)): ?>
        <p class="note" style="color: red;"><?php echo $error; ?></p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>