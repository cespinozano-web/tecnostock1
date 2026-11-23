<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header("location: login.html");
    exit();
}

$cargoBodega = strtolower(trim((string)($_SESSION['nombre_cargo'] ?? '')));
$puedeIngresarStock = in_array($cargoBodega, ['dueño', 'dueno', 'administrador', 'admin', 'owner'], true);

$servidor = "localhost";
$usuario_db = "root";          
$password_db = "";              
$base_datos = "tecnostock1";

$conexion = new mysqli($servidor, $usuario_db, $password_db, $base_datos);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");

$sql = "SELECT p.id_producto, p.codigo_producto, p.nombre_producto, p.precio_producto, p.stock_actual_producto, p.stock_minimo_producto AS stock_critico_producto, c.nombre_categoria 
        FROM producto p
        INNER JOIN categoria c ON p.id_categoria = c.id_categoria
        ORDER BY p.nombre_producto ASC";

$resultado = $conexion->query($sql);

include 'visual/header.php';
?>

<style>
    .bodega-titulo { color: #1f1b2e; font-weight: 700; margin: 0 0 4px 0; }
    .bodega-subtitulo { color: #6f6785; font-size: 13.5px; margin: 0 0 18px 0; }
    .btn-nuevo-stock-bodega { background-color: #f2ecfc !important; border: 1px solid #d8c8f5 !important; color: #4d2c91 !important; font-weight: 600; border-radius: 7px; padding: 8px 15px; outline: none !important; box-shadow: 0 2px 6px rgba(92, 61, 166, 0.05); }
    .btn-nuevo-stock-bodega:hover, .btn-nuevo-stock-bodega:focus { background-color: #e6dcf9 !important; border-color: #c7b1f0 !important; color: #3a1f73 !important; outline: none !important; box-shadow: none !important; }
    .card-bodega-morada { background-color: #ffffff; border: 1px solid #e7e1f5; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(92, 61, 166, 0.04); margin-bottom: 20px; }
    .bodega-toolbar { background-color: #faf8fe; border-bottom: 1px solid #ece6f7; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
    .bodega-leyenda { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 12.5px; color: #554d6b; }
    .leyenda-item { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
    .leyenda-swatch { width: 14px; height: 14px; border-radius: 3px; display: inline-block; border: 1px solid rgba(0, 0, 0, 0.08); }
    .tabla-bodega-morada { margin-bottom: 0 !important; }
    .tabla-bodega-morada > thead > tr > th { background-color: #f5f1fc; color: #4d2c91; border-bottom: 2px solid #dfd5f5 !important; border-top: none !important; border-color: #ece6f7; font-weight: 600; padding: 12px 14px; }
    .tabla-bodega-morada > tbody > tr > td { border-color: #efeaf9 !important; padding: 11px 14px; vertical-align: middle; color: #2b253b; }
    .tabla-bodega-morada > tbody > tr:hover > td:not(.celda-stock-estado) { background-color: #fcfbfe; }
    .buscador-bodega .input-group-addon { background-color: #f5f1fc; border-color: #dfd7f0; color: #673ab7; border-radius: 7px 0 0 7px; }
    .buscador-bodega .form-control { border-color: #dfd7f0; border-radius: 0 7px 7px 0; height: 36px; box-shadow: none; }
    .buscador-bodega .form-control:focus { border-color: #7c52cc; box-shadow: 0 0 0 3px rgba(124, 82, 204, 0.12); }
</style>

<br>
<h3 class="bodega-titulo"><i class="fa fa-cubes" style="color: #673ab7; margin-right: 6px;"></i> Bodega TecnoStock</h3>
<p class="bodega-subtitulo">Control de existencias y nivel de stock crítico por producto.</p>

<?php if ($puedeIngresarStock): ?>
    <div class="clearfix" style="margin-bottom: 12px;">
        <a href="nuevo_stock.php" class="btn btn-nuevo-stock-bodega" onmousedown="this.blur();">
            <i class="fa fa-plus-circle"></i> Ingresar nuevo stock
        </a>
    </div>
<?php endif; ?>

<div class="card-bodega-morada">
    <div class="bodega-toolbar">
        <div class="bodega-leyenda">
            <span class="leyenda-item">
                <span class="leyenda-swatch" style="background-color: #f2dede;"></span> Crítico / Agotado
            </span>
            <span class="leyenda-item">
                <span class="leyenda-swatch" style="background-color: #fcf8e3;"></span> Próximo a crítico
            </span>
            <span class="leyenda-item">
                <span class="leyenda-swatch" style="background-color: #dff0d8;"></span> Stock normal
            </span>
        </div>

        <div class="input-group buscador-bodega" style="width: 280px; max-width: 100%;">
            <span class="input-group-addon"><i class="fa fa-search"></i></span>
            <input type="text" id="buscadorBodega" class="form-control" placeholder="Filtrar producto o categoría..." autocomplete="off">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered tabla-bodega-morada" id="tablaBodega">
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
                <?php if ($resultado && $resultado->num_rows > 0) { ?>
                    <?php while ($row = $resultado->fetch_assoc()) { ?>
                        <?php 
                            $actual = (int)$row['stock_actual_producto'];
                            $critico = (int)$row['stock_critico_producto'];
                            $diferencia = $actual - $critico;
                            
                            if ($actual <= $critico) {
                                $bg_color = "#f2dede";
                                $color_texto = "#a94442";
                            } elseif ($diferencia <= 3) {
                                $bg_color = "#fcf8e3";
                                $color_texto = "#8a6d3b";
                            } else {
                                $bg_color = "#dff0d8";
                                $color_texto = "#3c763d";
                            }
                        ?>
                        <tr class="fila-bodega">
                            <td><?php echo htmlspecialchars($row['codigo_producto']); ?></td>
                            <td style="font-weight: 500;"><?php echo htmlspecialchars($row['nombre_producto']); ?></td>
                            <td><?php echo htmlspecialchars($row['nombre_categoria']); ?></td>
                            <td><?php echo $critico; ?></td>
                            <td class="celda-stock-estado" style="background-color: <?php echo $bg_color; ?>; color: <?php echo $color_texto; ?>; font-weight: bold;">
                                <?php echo $actual; ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr id="sinResultadosBodega" style="display: none;">
                        <td colspan="5" class="text-center text-muted">No se encontraron productos que coincidan con el filtro.</td>
                    </tr>
                <?php } else { ?>
                    <tr>
                        <td colspan="5" class="text-center">No hay productos registrados en el inventario.</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function iniciarBodega() {
    if (window.__tecnostockBodegaInit) return;
    window.__tecnostockBodegaInit = true;

    document.addEventListener("input", function (e) {
        if (!e.target || e.target.id !== "buscadorBodega") return;
        const termino = e.target.value.toLowerCase().trim();
        const filas = document.querySelectorAll("#tablaBodega tbody tr.fila-bodega");
        const filaVacia = document.getElementById("sinResultadosBodega");
        let visibles = 0;

        filas.forEach(function (fila) {
            const coincide = fila.textContent.toLowerCase().includes(termino);
            fila.style.display = coincide ? "" : "none";
            if (coincide) visibles++;
        });

        if (filaVacia) {
            filaVacia.style.display = (visibles === 0 && filas.length > 0) ? "" : "none";
        }
    });
})();
</script>

<?php 
if (isset($resultado) && is_object($resultado)) {
    $resultado->free();
}
$conexion->close();
include 'visual/footer.php';
?>