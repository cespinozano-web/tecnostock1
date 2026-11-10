<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
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

$id_usuario_actual = $_SESSION['id_usuario'];

$sql = "SELECT m.id_movimiento, m.fecha_hora_movimiento, m.tipo_movimiento, m.cantidad_movimiento, m.observacion_movimiento, p.nombre_producto 
        FROM movimiento m
        INNER JOIN producto p ON m.id_producto = p.id_producto
        WHERE m.id_usuario = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario_actual);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial</title>
</head>
<body>
    <h2>Historial de Movimientos de: <?php echo $_SESSION['nombre_usuario']; ?></h2>
    
    <div>
        <?php if ($resultado->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Movimiento</th>
                        <th>Fecha y Hora</th>
                        <th>Tipo</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['fecha_hora_movimiento']; ?></td>
                            <td><?php echo $row['tipo_movimiento']; ?></td>
                            <td><?php echo $row['nombre_producto']; ?></td>
                            <td><?php echo $row['cantidad_movimiento']; ?></td>
                            <td><?php echo $row['observacion_movimiento']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No tienes movimientos registrados en el sistema.</p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$stmt->close();
$conexion->close();
?>









