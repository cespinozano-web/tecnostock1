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

$sql = "SELECT m.id_movimiento, m.fecha_hora_movimiento, m.tipo_movimiento, m.cantidad_movimiento, m.observacion_movimiento, p.nombre_producto 
        FROM movimiento m
        INNER JOIN producto p ON m.id_producto = p.id_producto
        WHERE m.id_usuario = ?
        ORDER BY m.fecha_hora_movimiento DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario_actual);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<style>
    .historial-titulo {
        color: #1f1b2e;
        font-weight: 700;
        margin: 0 0 4px 0;
    }

    .historial-subtitulo {
        color: #6f6785;
        font-size: 13.5px;
        margin: 0 0 18px 0;
    }

    .card-historial-morada {
        background-color: #ffffff;
        border: 1px solid #e7e1f5;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(92, 61, 166, 0.04);
        margin-bottom: 18px;
    }

    .historial-toolbar {
        background-color: #faf8fe;
        border-bottom: 1px solid #ece6f7;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .historial-badge-usuario {
        background-color: #f1ebfc;
        color: #5e35b1;
        border: 1px solid #dfd3f7;
        font-size: 12.5px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
    }

    .buscador-historial .input-group-addon {
        background-color: #f5f1fc;
        border-color: #dfd7f0;
        color: #673ab7;
        border-radius: 7px 0 0 7px;
    }

    .buscador-historial .form-control {
        border-color: #dfd7f0;
        border-radius: 0 7px 7px 0;
        height: 36px;
        box-shadow: none;
    }

    .buscador-historial .form-control:focus {
        border-color: #7c52cc;
        box-shadow: 0 0 0 3px rgba(124, 82, 204, 0.12);
    }

    .tabla-historial-morada {
        margin-bottom: 0 !important;
    }

    .tabla-historial-morada > thead > tr > th {
        background-color: #f5f1fc;
        color: #4d2c91;
        border-bottom: 2px solid #dfd5f5 !important;
        border-top: none !important;
        border-color: #ece6f7;
        font-weight: 600;
        padding: 12px 14px;
    }

    .tabla-historial-morada > tbody > tr > td {
        border-color: #efeaf9 !important;
        padding: 11px 14px;
        vertical-align: middle;
        color: #2b253b;
    }

    .tabla-historial-morada > tbody > tr.fila-movimiento:hover {
        background-color: #fcfbfe;
    }

    .badge-tipo-morado {
        display: inline-block;
        background-color: #f3edfc;
        color: #5a32a8;
        border: 1px solid #dfd2f5;
        font-size: 12px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
    }

    .btn-volver-morado-suave {
        background-color: #ffffff;
        border: 1px solid #dcd4ec;
        color: #3d3552 !important;
        border-radius: 7px;
        padding: 8px 16px;
        font-weight: 600;
        outline: none !important;
    }

    .btn-volver-morado-suave:hover,
    .btn-volver-morado-suave:focus {
        background-color: #f7f4fc !important;
        border-color: #c8bce3 !important;
        outline: none !important;
        box-shadow: none !important;
    }
</style>

<br>
<h3 class="historial-titulo"><i class="fa fa-history" style="color: #673ab7; margin-right: 6px;"></i> Historial de Movimientos</h3>
<p class="historial-subtitulo">Registro de movimientos realizados por <strong><?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?></strong>.</p>

<div class="card-historial-morada">
    <div class="historial-toolbar">
        <span class="historial-badge-usuario">
            <i class="fa fa-user-o" style="margin-right: 5px;"></i> Usuario: <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>
        </span>

        <div class="input-group buscador-historial" style="width: 280px; max-width: 100%;">
            <span class="input-group-addon"><i class="fa fa-search"></i></span>
            <input type="text" id="buscadorHistorial" class="form-control" placeholder="Buscar producto, tipo o nota..." autocomplete="off">
        </div>
    </div>

    <div class="table-responsive">
        <?php if ($resultado && $resultado->num_rows > 0) { ?>
            <table class="table table-bordered tabla-historial-morada" id="tablaHistorial">
                <thead>
                    <tr>
                        <th>ID Movimiento</th>
                        <th>Fecha y Hora</th>
                        <th>Tipo</th>
                        <th>Producto</th>
                        <th>Monto / Cantidad</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $resultado->fetch_assoc()) { ?>
                        <tr class="fila-movimiento">
                            <td>#<?php echo (int)$row['id_movimiento']; ?></td>
                            <td><?php echo htmlspecialchars($row['fecha_hora_movimiento']); ?></td>
                            <td><span class="badge-tipo-morado"><?php echo htmlspecialchars($row['tipo_movimiento']); ?></span></td>
                            <td style="font-weight: 500;"><?php echo htmlspecialchars($row['nombre_producto']); ?></td>
                            <td style="color: #4d2c91;"><strong>$<?php echo number_format((float)$row['cantidad_movimiento'], 0, ',', '.'); ?></strong></td>
                            <td><?php echo nl2br(htmlspecialchars($row['observacion_movimiento'])); ?></td>
                        </tr>
                    <?php } ?>
                    <tr id="sinResultadosHistorial" style="display: none;">
                        <td colspan="6" class="text-center text-muted">No se encontraron movimientos que coincidan con la búsqueda.</td>
                    </tr>
                </tbody>
            </table>
        <?php } else { ?>
            <div style="padding: 20px; text-align: center; color: #6f6785;">
                No tienes movimientos registrados en el sistema.
            </div>
        <?php } ?>
    </div>
</div>

<a href="inicio.php" class="btn btn-volver-morado-suave"><i class="fa fa-arrow-left"></i> Volver al Inicio</a>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const inputBuscador = document.getElementById("buscadorHistorial");
    const filas = document.querySelectorAll("#tablaHistorial tbody tr.fila-movimiento");
    const filaVacia = document.getElementById("sinResultadosHistorial");

    if (!inputBuscador) return;

    inputBuscador.addEventListener("input", function () {
        const termino = this.value.toLowerCase().trim();
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
});
</script>

<?php
$stmt->close();
$conexion->close();

include 'visual/footer.php';
?>