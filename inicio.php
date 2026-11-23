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

<style>
    .inicio-banner {
        background: linear-gradient(135deg, #f6f3fb 0%, #ede5fb 100%);
        border-left: 4px solid #7c52cc;
        padding: 16px 20px;
        margin-bottom: 20px;
        border-radius: 0;
    }

    .inicio-banner h3 {
        margin: 0 0 4px 0;
        color: #45238c;
        font-weight: 600;
    }

    .inicio-banner h5 {
        margin: 0;
        color: #6b5b8c;
    }

    .titulo-seccion-morado {
        color: #4d2899;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .btn-morado {
        background-color: #7c52cc;
        border-color: #6941b8;
        color: #ffffff !important;
        border-radius: 0;
        outline: none !important;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }

    .btn-morado:hover,
    .btn-morado:focus,
    .btn-morado:active {
        background-color: #643ab5 !important;
        border-color: #532d9c !important;
        color: #ffffff !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .buscador-morado .input-group-addon {
        background-color: #f3ecfc;
        border-color: #d8c8f2;
        color: #6941b8;
        border-radius: 0;
    }

    .buscador-morado .form-control {
        border-color: #d8c8f2;
        border-radius: 0;
    }

    .buscador-morado .form-control:focus {
        border-color: #8c62db;
        box-shadow: 0 0 0 2px rgba(124, 82, 204, 0.15);
    }

    .tabla-morada > thead > tr > th {
        background-color: #f4effc;
        color: #4a2794;
        border-bottom: 2px solid #d6c5f2;
        border-top: 1px solid #e3d7f7;
    }

    .tabla-morada > tbody > tr.fila-producto:hover {
        background-color: #faf7fe;
    }

    .tabla-morada > tbody > tr.fila-producto a,
    .tabla-morada > tbody > tr.fila-producto a:hover,
    .tabla-morada > tbody > tr.fila-producto a:focus,
    .tabla-morada > tbody > tr.fila-producto a:active {
        color: #5b32b0;
        font-weight: 500;
        outline: 0 !important;
        outline-style: none !important;
        outline-width: 0 !important;
        box-shadow: none !important;
        -webkit-tap-highlight-color: transparent;
    }

    .tabla-morada > tbody > tr.fila-producto a:hover {
        color: #3d1d82;
        text-decoration: underline;
    }

    .tabla-morada > tbody > tr.fila-detalle > td {
        background-color: #fbf9fe !important;
        color: #39464e !important;
        padding: 0 !important;
        border-top: none !important;
    }

    .collapse-content {
        background-color: #fbf9fe !important;
        color: #39464e !important;
        border-left: 3px solid #9d76e8;
        padding: 12px 16px;
        margin: 4px 0 8px 0;
    }
</style>

<?php if (isset($_GET['venta']) && $_GET['venta'] === 'ok') { ?>
    <div id="notificacionVenta" style="position: fixed; bottom: 20px; right: 20px; z-index: 10060; min-width: 290px; max-width: 380px; background-color: #28a745; color: #ffffff; padding: 14px 18px; border-radius: 0; box-shadow: 0 6px 18px rgba(0,0,0,0.25);">
        <button type="button" onclick="this.parentElement.remove();" style="background: transparent; border: none; color: #ffffff; float: right; font-size: 18px; line-height: 1; cursor: pointer; margin-left: 10px;">&times;</button>
        <strong><i class="fa fa-check-circle"></i> Venta registrada</strong>
        <div style="margin-top: 4px; font-size: 13px;">La venta se realizó con éxito y el stock fue actualizado.</div>
    </div>
    <script>
        setTimeout(function () {
            var n = document.getElementById("notificacionVenta");
            if (n) n.style.display = "none";
        }, 4500);
    </script>
<?php } ?>

<div class="inicio-banner">
    <h3>Bienvenido TecnoStock</h3>
    <h5>Cargo: <?php echo htmlspecialchars($_SESSION['nombre_cargo']); ?></h5>
</div>

<h4 class="titulo-seccion-morado"><i class="fa fa-cubes"></i> Stock actual de productos</h4>

<div class="clearfix" style="margin-bottom: 10px;">
    <a href="nueva_orden.php" class="btn btn-morado pull-right">
        <i class="fa fa-plus-circle"></i> Nuevo movimiento
    </a>
</div>

<div class="form-group buscador-morado" style="margin-bottom: 20px;">
    <div class="input-group" style="width: 100%;">
        <span class="input-group-addon"><i class="fa fa-search"></i></span>
        <input type="text" id="buscadorProductos" class="form-control" placeholder="Buscar por código, nombre, categoría..." autocomplete="off">
    </div>
</div>

<div class="table-responsive">
    <table class="table tabla-morada" id="tablaProductos">
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
            <?php if ($resultado && $resultado->num_rows > 0) { ?>
                <?php while ($row = $resultado->fetch_assoc()) { ?>
                    <tr class="fila-producto">
                        <td><?php echo htmlspecialchars($row['codigo_producto']); ?></td>
                        <td>
                            <a data-toggle="collapse" data-target="#detalle<?php echo $row['id_producto']; ?>" aria-expanded="false" href="javascript:void(0);" onmousedown="this.blur();" style="cursor: pointer;">
                                <?php echo htmlspecialchars($row['nombre_producto']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($row['nombre_categoria']); ?></td>
                        <td><?php echo htmlspecialchars($row['stock_actual_producto']); ?></td>
                        <td>$<?php echo number_format($row['precio_producto'], 0, ',', '.'); ?></td>
                    </tr>
                    
                    <tr class="fila-detalle">
                        <td colspan="5">
                            <div class="collapse" id="detalle<?php echo $row['id_producto']; ?>">
                                <div class="collapse-content">
                                    <span style="font-weight: bold; color: #4a2794;">Descripción:</span>
                                    <p style="margin-top: 5px; margin-bottom: 0; color: #39464e;">
                                        <?php echo !empty($row['descripcion_producto']) ? htmlspecialchars($row['descripcion_producto']) : 'Sin descripción registrada para este producto.'; ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                <tr id="sinResultadosBusqueda" style="display: none;">
                    <td colspan="5" class="text-center text-muted">No se encontraron productos que coincidan con la búsqueda.</td>
                </tr>
            <?php } else { ?>
                <tr>
                    <td colspan="5" class="text-center">No hay productos registrados en el inventario.</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const inputBuscador = document.getElementById("buscadorProductos");
    const filasProducto = document.querySelectorAll("#tablaProductos tbody tr.fila-producto");
    const filaSinResultados = document.getElementById("sinResultadosBusqueda");

    if (!inputBuscador) return;

    inputBuscador.addEventListener("input", function () {
        const termino = this.value.toLowerCase().trim();
        let visibles = 0;

        filasProducto.forEach(function (filaProd) {
            const filaDet = filaProd.nextElementSibling;
            const textoFila = filaProd.textContent.toLowerCase();
            const coincide = textoFila.includes(termino);

            if (coincide) {
                filaProd.style.display = "";
                if (filaDet && filaDet.classList.contains("fila-detalle")) {
                    filaDet.style.display = "";
                }
                visibles++;
            } else {
                filaProd.style.display = "none";
                if (filaDet && filaDet.classList.contains("fila-detalle")) {
                    filaDet.style.display = "none";
                }
            }
        });

        if (filaSinResultados) {
            filaSinResultados.style.display = (visibles === 0 && filasProducto.length > 0) ? "" : "none";
        }
    });
});
</script>

<?php 
if (isset($resultado) && is_object($resultado)) {
    $resultado->free();
}
$conexion->close();
include 'visual/footer.php';
?>