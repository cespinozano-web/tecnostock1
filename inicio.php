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

$sql = "SELECT p.id_producto, p.codigo_producto, p.nombre_producto, p.descripcion_producto, p.precio_producto, p.stock_actual_producto, c.nombre_categoria 
        FROM producto p
        INNER JOIN categoria c ON p.id_categoria = c.id_categoria
        ORDER BY p.nombre_producto ASC";

$resultado = $conexion->query($sql);

include 'visual/header.php';
?>

<h3>Bienvenido Sr: <?php echo $_SESSION['nombre_usuario']; ?></h3> 
<h5>Cargo: <?php echo $_SESSION['nombre_cargo']; ?></h5>

<br>
<h4 style="margin-bottom: 20px;"><i class="fa fa-cubes"></i> Stock actual de productos</h4>

<div class="table-responsive">
    <table class="table table-collapse">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre del Producto</th>
                <th>Categoría</th>
                <th>Stock Actual</th>
                <th>Precio</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['codigo_producto']; ?></td>
                        <td>
                            <a data-toggle="collapse" data-target="#detalle<?php echo $row['id_producto']; ?>" aria-expanded="false" href="javascript:void(0);" style="cursor: pointer;">
                                <?php echo $row['nombre_producto']; ?>
                            </a>
                        </td>
                        <td><?php echo $row['nombre_categoria']; ?></td>
                        <td><?php echo $row['stock_actual_producto']; ?></td>
                        <td>$<?php echo number_format($row['precio_producto'], 0, ',', '.'); ?></td>
                    </tr>
                    
                    <tr>
                        <td colspan="5" class="collapse-td">
                            <div class="collapse-content collapse" id="detalle<?php echo $row['id_producto']; ?>">
                                <span class="header-6" style="font-weight: bold; color: #333;">Descripción:</span>
                                <p style="margin-top: 5px; margin-bottom: 0;">
                                    <?php echo !empty($row['descripcion_producto']) ? $row['descripcion_producto'] : 'Sin descripción registrada para este producto.'; ?>
                                </p>
                            </div>
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