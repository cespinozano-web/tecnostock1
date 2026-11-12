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

$sql = "SELECT p.id_producto, p.codigo_producto, p.nombre_producto, p.precio_producto, p.stock_actual_producto, p.stock_minimo_producto AS stock_critico_producto, c.nombre_categoria 
        FROM producto p
        INNER JOIN categoria c ON p.id_categoria = c.id_categoria
        ORDER BY p.nombre_producto ASC";

$resultado = $conexion->query($sql);

include 'visual/header.php';
?>

<br>
<h4 style="margin-bottom: 20px;"><i class="fa fa-cubes"></i> Bodega TecnoStock</h4>

<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre del Producto</th>
                <th>Categoría</th>
                <th>Stock Crítico</th>
                <th>Stock Actual</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <?php 
                        $actual = $row['stock_actual_producto'];
                        $critico = $row['stock_critico_producto'];
                        $diferencia = $actual - $critico;
                        
                        // Lógica de colores según el stock actual respecto al crítico
                        if ($actual <= $critico) {
                            $bg_color = "#f2dede"; // Rojo claro (bootstrap danger tint)
                            $color_texto = "#a94442";
                        } elseif ($diferencia <= 3) {
                            $bg_color = "#fcf8e3"; // Amarillo claro (bootstrap warning tint)
                            $color_texto = "#8a6d3b";
                        } else {
                            $bg_color = "#dff0d8"; // Verde claro (bootstrap success tint)
                            $color_texto = "#3c763d";
                        }
                    ?>
                    <tr>
                        <td><?php echo $row['codigo_producto']; ?></td>
                        <td><?php echo $row['nombre_producto']; ?></td>
                        <td><?php echo $row['nombre_categoria']; ?></td>
                        <td><?php echo $critico; ?></td>
                        <td style="background-color: <?php echo $bg_color; ?>; color: <?php echo $color_texto; ?>; font-weight: bold;">
                            <?php echo $actual; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">No hay productos registrados en el inventario.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
if (isset($resultado) && is_object($resultado)) {
    $resultado->free();
}
$conexion->close();
include 'visual/footer.php';
?>