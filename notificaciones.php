<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header("location: login.html");
    exit();
}

$cargoActual = strtolower(trim((string)($_SESSION['nombre_cargo'] ?? '')));
$puedeVerNotificaciones = in_array($cargoActual, ['dueño', 'dueno', 'owner', 'administrador', 'admin'], true)
    || stripos($cargoActual, 'admin') !== false
    || stripos($cargoActual, 'dueñ') !== false
    || stripos($cargoActual, 'duen') !== false;

if (!$puedeVerNotificaciones) {
    header("location: bodega.php");
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

function obtenerColsNotif(mysqli $db, string $tabla): array {
    $cols = [];
    $res = @$db->query("SHOW COLUMNS FROM `$tabla`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
        $res->free();
    }
    return $cols;
}

function buscarColNotif(array $cols, array $candidatas): ?string {
    foreach ($candidatas as $cand) {
        foreach ($cols as $col) {
            if (strcasecmp($col, $cand) === 0) {
                return $col;
            }
        }
    }
    foreach ($candidatas as $cand) {
        foreach ($cols as $col) {
            if (stripos($col, $cand) !== false) {
                return $col;
            }
        }
    }
    return null;
}

$colsProd = obtenerColsNotif($conexion, 'producto');
$colProdCodigo = buscarColNotif($colsProd, ['codigo_producto', 'codigo', 'sku']);
$colProdNombre = buscarColNotif($colsProd, ['nombre_producto', 'producto', 'nombre']) ?? 'nombre_producto';
$colProdDesc = buscarColNotif($colsProd, ['descripcion_producto', 'descripcion', 'detalle']);
$colProdStock = buscarColNotif($colsProd, ['stock_actual', 'stock', 'cantidad_stock', 'cantidad', 'existencia']) ?? 'stock_actual';
$colProdCritico = buscarColNotif($colsProd, ['stock_critico', 'stock_minimo', 'minimo_stock', 'critico', 'minimo']);
$colProdPrecio = buscarColNotif($colsProd, ['precio_producto', 'precio_unitario', 'precio', 'valor']);
$colProdCatId = buscarColNotif($colsProd, ['id_categoria', 'categoria']);
$colProdEstado = buscarColNotif($colsProd, ['estado_producto', 'estado', 'activo']);

$colsCat = obtenerColsNotif($conexion, 'categoria');
$colCatId = !empty($colsCat) ? (buscarColNotif($colsCat, ['id_categoria', 'id']) ?? 'id_categoria') : null;
$colCatNom = !empty($colsCat) ? (buscarColNotif($colsCat, ['nombre_categoria', 'categoria', 'nombre']) ?? 'nombre_categoria') : null;

$joinCat = "";
$exprCat = "'Sin categoría'";
if ($colProdCatId && $colCatId && $colCatNom) {
    $joinCat = " LEFT JOIN `categoria` cat ON p.`$colProdCatId` = cat.`$colCatId` ";
    $exprCat = "COALESCE(cat.`$colCatNom`, 'Sin categoría')";
}

$exprCodigo = $colProdCodigo ? "COALESCE(p.`$colProdCodigo`, '')" : "''";
$exprDesc = $colProdDesc ? "COALESCE(p.`$colProdDesc`, '')" : "''";
$exprCritico = $colProdCritico ? "COALESCE(p.`$colProdCritico`, 5)" : "5";
$exprPrecio = $colProdPrecio ? "COALESCE(p.`$colProdPrecio`, 0)" : "0";

$whereCriticos = [];
if ($colProdEstado) {
    $whereCriticos[] = "(p.`$colProdEstado` IS NULL OR p.`$colProdEstado` != 0)";
}
$whereCriticos[] = "COALESCE(p.`$colProdStock`, 0) <= ({$exprCritico})";

$sqlCriticos = "SELECT {$exprCodigo} AS codigo_producto,
                       p.`$colProdNombre` AS nombre_producto,
                       {$exprDesc} AS descripcion_producto,
                       {$exprCat} AS nombre_categoria,
                       COALESCE(p.`$colProdStock`, 0) AS stock_actual,
                       ({$exprCritico}) AS stock_critico,
                       ({$exprPrecio}) AS precio_producto
                FROM `producto` p
                {$joinCat}
                WHERE " . implode(" AND ", $whereCriticos) . "
                ORDER BY COALESCE(p.`$colProdStock`, 0) ASC, p.`$colProdNombre` ASC";

$notificacionesCriticas = [];
$totalAgotados = 0;
$resCrit = $conexion->query($sqlCriticos);
if ($resCrit) {
    while ($row = $resCrit->fetch_assoc()) {
        $stockAct = (int)($row['stock_actual'] ?? 0);
        $stockCrit = (int)($row['stock_critico'] ?? 0);
        $agotado = ($stockAct <= 0);
        if ($agotado) {
            $totalAgotados++;
        }

        $notificacionesCriticas[] = [
            'codigo' => trim((string)($row['codigo_producto'] ?? '')),
            'nombre' => (string)($row['nombre_producto'] ?? ''),
            'descripcion' => trim((string)($row['descripcion_producto'] ?? '')),
            'categoria' => (string)($row['nombre_categoria'] ?? 'Sin categoría'),
            'stock_actual' => $stockAct,
            'stock_critico' => $stockCrit,
            'precio' => (float)($row['precio_producto'] ?? 0),
            'agotado' => $agotado,
        ];
    }
    $resCrit->free();
}

$totalNotificaciones = count($notificacionesCriticas);
$totalColumnas = $colProdPrecio ? 7 : 6;

include 'visual/header.php';
?>

<style>
    .notif-titulo { color: #1f1b2e; font-weight: 700; margin: 0 0 4px 0; }
    .notif-subtitulo { color: #6f6785; font-size: 13.5px; margin: 0; }
    .card-notif-morada { background-color: #ffffff; border: 1px solid #e7e1f5; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(92, 61, 166, 0.04); }
    .toolbar-notif-morada { background-color: #faf8fe; border-bottom: 1px solid #ece6f7; padding: 13px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
    .buscador-notif .input-group-addon { background-color: #f5f1fc; border-color: #dfd7f0; color: #673ab7; border-radius: 7px 0 0 7px; }
    .buscador-notif .form-control { border-color: #dfd7f0; border-radius: 0 7px 7px 0; height: 36px; box-shadow: none; }
    .buscador-notif .form-control:focus { border-color: #7c52cc; box-shadow: 0 0 0 3px rgba(124, 82, 204, 0.12); }
    .alerta-critica-banner { background-color: #fff4f5; border: 1px solid #f5c2c7; border-left: 4px solid #dc3545; border-radius: 9px; padding: 13px 16px; margin-bottom: 16px; color: #6e1a22; }
    .alerta-ok-banner { background-color: #eefaf3; border: 1px solid #c3ebd3; border-left: 4px solid #28a745; border-radius: 9px; padding: 13px 16px; margin-bottom: 16px; color: #1b5e34; }
    .tabla-notif-morada { margin-bottom: 0 !important; }
    .tabla-notif-morada > thead > tr > th { background-color: #f5f1fc; color: #4d2c91; border-bottom: 2px solid #dfd5f5 !important; border-top: none !important; border-color: #ece6f7; font-weight: 600; padding: 11px 12px; }
    .tabla-notif-morada > tbody > tr > td { border-color: #efeaf9 !important; padding: 10px 12px; vertical-align: middle; color: #2b253b; }
    .fila-agotado { background-color: #fff8f8; }
    .badge-critico-alerta { background-color: #fde8ea; color: #842029; border: 1px solid #f5c2c7; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 12px; display: inline-block; }
    .badge-agotado-alerta { background-color: #dc3545; color: #ffffff; padding: 4px 10px; border-radius: 12px; font-weight: 700; font-size: 12px; display: inline-block; }
</style>

<br>
<div class="clearfix" style="margin-bottom: 16px;">
    <h3 class="notif-titulo">
        <i class="fa fa-bell" style="color: #673ab7; margin-right: 6px;"></i> Notificaciones de Stock Crítico
    </h3>
    <p class="notif-subtitulo">
        Productos que han tocado o bajado del límite de stock crítico (<strong><?php echo (int)$totalNotificaciones; ?></strong> en alerta).
    </p>
</div>

<?php if ($totalNotificaciones > 0): ?>
    <div class="alerta-critica-banner">
        <strong><i class="fa fa-exclamation-triangle"></i> Atención:</strong>
        Hay <strong><?php echo (int)$totalNotificaciones; ?></strong> producto(s) en stock crítico
        <?php if ($totalAgotados > 0): ?>
            (incluyendo <strong><?php echo (int)$totalAgotados; ?></strong> sin stock / agotados)
        <?php endif; ?>.
    </div>
<?php else: ?>
    <div class="alerta-ok-banner">
        <strong><i class="fa fa-check-circle"></i> Todo en orden:</strong>
        Ningún producto ha tocado el nivel de stock crítico en este momento.
    </div>
<?php endif; ?>

<div class="card-notif-morada">
    <div class="toolbar-notif-morada">
        <span style="font-weight: 600; color: #4d2c91;">
            <i class="fa fa-list-alt"></i> Detalle de productos en stock crítico (<?php echo (int)$totalNotificaciones; ?>)
        </span>

        <div class="input-group buscador-notif" style="width: 300px; max-width: 100%;">
            <span class="input-group-addon"><i class="fa fa-search"></i></span>
            <input type="text" id="buscadorNotificaciones" class="form-control" placeholder="Buscar producto, categoría o código..." autocomplete="off">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered tabla-notif-morada" id="tablaNotificaciones">
            <thead>
                <tr>
                    <?php if ($colProdCodigo): ?>
                        <th>Código</th>
                    <?php endif; ?>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th class="text-center">Stock actual</th>
                    <th class="text-center">Stock crítico</th>
                    <?php if ($colProdPrecio): ?>
                        <th class="text-right">Precio</th>
                    <?php endif; ?>
                    <th class="text-center">Notificación</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($notificacionesCriticas)): ?>
                    <?php foreach ($notificacionesCriticas as $item): ?>
                        <tr class="fila-notif-item <?php echo $item['agotado'] ? 'fila-agotado' : ''; ?>">
                            <?php if ($colProdCodigo): ?>
                                <td><?php echo htmlspecialchars($item['codigo'] !== '' ? $item['codigo'] : '-'); ?></td>
                            <?php endif; ?>
                            <td>
                                <div style="font-weight: 700; color: #1f1b2e;"><?php echo htmlspecialchars($item['nombre']); ?></div>
                                <?php if ($item['descripcion'] !== ''): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['descripcion']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['categoria']); ?></td>
                            <td class="text-center" style="font-weight: 700; color: #842029;">
                                <?php echo (int)$item['stock_actual']; ?>
                            </td>
                            <td class="text-center" style="font-weight: 600;">
                                <?php echo (int)$item['stock_critico']; ?>
                            </td>
                            <?php if ($colProdPrecio): ?>
                                <td class="text-right">
                                    $<?php echo number_format($item['precio'], 0, ',', '.'); ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-center">
                                <?php if ($item['agotado']): ?>
                                    <span class="badge-agotado-alerta">Sin stock (Crítico)</span>
                                <?php else: ?>
                                    <span class="badge-critico-alerta">Tocó stock crítico</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="sinResultadosNotif" style="display: none;">
                        <td colspan="<?php echo (int)$totalColumnas; ?>" class="text-center text-muted">
                            No hay notificaciones que coincidan con el filtro.
                        </td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo (int)$totalColumnas; ?>" class="text-center text-muted" style="padding: 22px;">
                            No hay productos en stock crítico actualmente.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function iniciarFiltroNotificaciones() {
    if (window.__tecnoStockNotifInit) return;
    window.__tecnoStockNotifInit = true;

    document.addEventListener("input", function (e) {
        if (!e.target || e.target.id !== "buscadorNotificaciones") return;
        const q = e.target.value.toLowerCase().trim();
        const filas = document.querySelectorAll("#tablaNotificaciones tbody tr.fila-notif-item");
        const filaVacia = document.getElementById("sinResultadosNotif");
        let visibles = 0;

        filas.forEach(function (fila) {
            const coincide = fila.textContent.toLowerCase().includes(q);
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
$conexion->close();
include 'visual/footer.php';
?>