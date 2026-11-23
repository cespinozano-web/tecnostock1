<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("location: login.html");
    exit();
}

$servidor = "localhost";
$usuario_db = "root";
$password_db = "";
$base_datos = "tecnostock1";

$conexion = new mysqli($servidor, $usuario_db, $password_db, $base_datos);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");

$correo_ingresado = trim((string)($_POST['txt_usuario'] ?? ''));
$password_ingresada = (string)($_POST['txt_contrasena'] ?? '');

$sql = "SELECT u.id_usuario, u.nombre_usuario, u.correo_usuario, u.clave_usuario, u.estado_usuario, u.id_cargo, c.nombre_cargo 
        FROM usuario u 
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo 
        WHERE LOWER(TRIM(u.correo_usuario)) = LOWER(?)
        LIMIT 1";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $correo_ingresado);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado && $resultado->num_rows === 1) {

    $usuario = $resultado->fetch_assoc();
    $claveGuardada = (string)($usuario['clave_usuario'] ?? '');

    $claveValida = false;
    $debeMigrarHash = false;

    if ($claveGuardada !== '') {
        if (password_verify($password_ingresada, $claveGuardada)) {
            $claveValida = true;
            if (password_needs_rehash($claveGuardada, PASSWORD_DEFAULT)) {
                $debeMigrarHash = true;
            }
        } elseif (hash_equals($claveGuardada, $password_ingresada)) {
            // Fallback para cuentas antiguas que tenían clave en texto plano
            $claveValida = true;
            $debeMigrarHash = true;
        }
    }

    if ($claveValida) {

        if ((int)$usuario['estado_usuario'] === 1) {

            if ($debeMigrarHash) {
                $nuevoHash = password_hash($password_ingresada, PASSWORD_DEFAULT);
                $idUsr = (int)$usuario['id_usuario'];
                $stmtUpd = $conexion->prepare("UPDATE usuario SET clave_usuario = ? WHERE id_usuario = ?");
                $stmtUpd->bind_param("si", $nuevoHash, $idUsr);
                $stmtUpd->execute();
                $stmtUpd->close();
            }

            session_regenerate_id(true);
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
            $_SESSION['id_cargo'] = $usuario['id_cargo'];
            $_SESSION['nombre_cargo'] = $usuario['nombre_cargo'];

            $stmt->close();
            $conexion->close();
            header("location: inicio.php");
            exit();
        } else {
            $stmt->close();
            $conexion->close();
            echo "<script>alert('Tu usuario está inactivo.'); window.location='login.html';</script>";
            exit();
        }

    } else {
        $stmt->close();
        $conexion->close();
        echo "<script>alert('Contraseña incorrecta.'); window.location='login.html';</script>";
        exit();
    }

} else {
    $stmt->close();
    $conexion->close();
    echo "<script>alert('Correo no está registrado.'); window.location='login.html';</script>";
    exit();
}
?>