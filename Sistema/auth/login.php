<?php
session_start();
if (isset($_SESSION["usuario"])) {
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container p-5">
    <div class="card col-md-4 mx-auto">
        <div class="card-header text-center bg-primary text-white">
            Iniciar Sesión
        </div>
        <div class="card-body">
            <form action="validar_login.php" method="POST">
                <label>Usuario</label>
                <input type="text" name="usuario" class="form-control" required>

                <label class="mt-3">Contraseña</label>
                <input type="password" name="password" class="form-control" required>

                <button class="btn btn-primary w-100 mt-3">Entrar</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
