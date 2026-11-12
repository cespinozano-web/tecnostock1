<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header("location: login.html");
    exit();
}

include 'visual/header.php';

$servidor = "localhost";
$usuario_db = "root";          
$password_db = "";              
$base_datos = "tecnostock1";

$conexion = new mysqli($servidor, $usuario_db, $password_db, $base_datos);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$id_usuario_actual = $_SESSION['id_usuario'];

$sql = "SELECT u.id_usuario, u.nombre_usuario, u.correo_usuario, u.clave_usuario, u.estado_usuario, c.nombre_cargo, c.descripcion_cargo, u.telefono_usuario
        FROM usuario u
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE u.id_usuario = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario_actual);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<h1>Tamos trabajando para mostrarte tu informacion personal xd</h1>


<?php
$stmt->close();
$conexion->close();

include 'visual/footer.php';
?>