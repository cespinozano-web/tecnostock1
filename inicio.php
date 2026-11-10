<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header("location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>
</head>
<body>

    <h3>Bienvenido sr: <?php echo $_SESSION['nombre_usuario']; ?></h3> 
    <h5>cargo: <?php echo $_SESSION['nombre_cargo']; ?></h5>
    
    <center>
        <br>
        // Barra de busqueda de la cortina
        // Cortina que muestra el stock actual de todos los producto en la sucursal A->Z.
        <a href="logout.php">Cerrar Sesión</a>
        <a href="historial.php">Historial</a>
    </center>
</body>
</html>