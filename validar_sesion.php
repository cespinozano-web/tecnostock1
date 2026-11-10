<?php
session_start();

$servidor = "localhost";
$usuario_db = "root";          
$password_db = "";              
$base_datos = "tecnostock1";

$conexion = new mysqli($servidor, $usuario_db, $password_db, $base_datos);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$correo_ingresado = $_POST['txt_usuario'];
$password_ingresada = $_POST['txt_contrasena'];

$sql = "SELECT u.id_usuario, u.nombre_usuario, u.correo_usuario, u.clave_usuario, u.estado_usuario, u.id_cargo, c.nombre_cargo 
        FROM usuario u 
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
        WHERE u.correo_usuario = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $correo_ingresado);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {

    $usuario = $resultado->fetch_assoc();

    if ($password_ingresada === $usuario['clave_usuario']) {
        
        if ($usuario['estado_usuario'] == 1) {

            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
            $_SESSION['id_cargo'] = $usuario['id_cargo'];
            $_SESSION['nombre_cargo'] = $usuario['nombre_cargo']; 

            header("location: inicio.php");
            exit();
        } else {
            echo "<script>alert('Tu usuario está inactivo.'); window.location='login.html';</script>";
        }

    } else {
        echo "<script>alert('Contraseña incorrecta.'); window.location='login.html';</script>";
    }

} else {
    echo "<script>alert('Correo no está registrado.'); window.location='login.html';</script>";
}

$stmt->close();
$conexion->close();
?>