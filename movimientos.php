<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header("location: login.html");
    exit();
}

$cargoActual = strtolower(trim((string)($_SESSION['nombre_cargo'] ?? '')));
if (!in_array($cargoActual, ['dueño', 'dueno', 'administrador', 'admin', 'owner'], true)) {
    header("location: inicio.php");
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

function obtenerTablaExistente(mysqli $db, array $candidatas): ?string {
    foreach ($candidatas as $nombre) {
        $sql = "SHOW TABLES LIKE '" . $db->real_escape_string($nombre) . "'";
        $res = $db->query($sql);
        if ($res && $res->num_rows > 0) {
            $res->free();
            return $nombre;
        }
    }
    return null;
}

function obtenerColumnasTabla(mysqli $db, string $tabla): array {
    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM `$tabla`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
        $res->free();
    }
    return $cols;
}

function buscarColumna(array $columnas, array $patrones): ?string {
    foreach ($patrones as $patron) {
        foreach ($columnas as $col) {
            if (strcasecmp($col, $patron) === 0) {
                return $col;
            }
        }
    }
    foreach ($patrones as $patron) {
        foreach ($columnas as $col) {
            if (stripos($col, $patron) !== false) {
                return $col;
            }
        }
    }
    return null;
}

function limpiarNumero($valor): float {
    if (is_int($valor) || is_float($valor)) {
        return (float)$valor;
    }
    if (!is_string($valor)) {
        return 0.0;
    }
    $limpio = trim($valor);
    if ($limpio === '') {
        return 0.0;
    }
    $limpio = preg_replace('/[^0-9,.-]/', '', $limpio);
    if (preg_match('/^\-?\d{1,3}(\.\d{3})+$/', $limpio)) {
        $limpio = str_replace('.', '', $limpio);
    } elseif (substr_count($limpio, '.') > 0 && substr_count($limpio, ',') > 0) {
        $limpio = str_replace('.', '', $limpio);
        $limpio = str_replace(',', '.', $limpio);
    } elseif (preg_match('/^\-?\d+,\d{1,2}$/', $limpio)) {
        $limpio = str_replace(',', '.', $limpio);
    }
    return is_numeric($limpio) ? (float)$limpio : 0.0;
}

function formatearMonto($valor): string {
    return '$' . number_format(limpiarNumero($valor), 0, ',', '.');
}

function esTipoSalida(string $tipo): bool {
    $t = strtolower(trim($tipo));
    return (strpos($t, 'venta') !== false || strpos($t, 'orden') !== false || strpos($t, 'salida') !== false || strpos($t, 'egreso') !== false);
}

function anexarAuditoriaObs(string $obsBase, string $etiqueta, string $actor): string {
    $baseLimpia = preg_replace('/(?:<br\s*\/?>|\r?\n)*(?:EDITADO|ELIMINADO)\s+POR\s*:.*$/is', '', $obsBase);
    $baseLimpia = trim((string)$baseLimpia);
    $lineaAuditoria = $etiqueta . ": " . $actor;
    return ($baseLimpia !== '') ? ($baseLimpia . "<br>" . $lineaAuditoria) : $lineaAuditoria;
}

function formatearObservacionHTML(string $obs): string {
    $trimObs = trim($obs);
    if ($trimObs === '') {
        return 'Sin observaciones';
    }
    $normalizado = preg_replace('/<br\s*\/?>/i', "\n", $trimObs);
    $lineas = preg_split('/\r?\n/', (string)$normalizado);
    $escapadas = array_map(function ($linea) {
        return htmlspecialchars($linea, ENT_QUOTES, 'UTF-8');
    }, $lineas);
    return implode('<br>', $escapadas);
}

$tablaMov = obtenerTablaExistente($conexion, ['movimiento', 'movimientos', 'orden', 'ordenes', 'venta', 'ventas']);
$colsMov = $tablaMov ? obtenerColumnasTabla($conexion, $tablaMov) : [];
$colPkMov = $colsMov[0] ?? 'id_movimiento';
$colFecha = buscarColumna($colsMov, ['fecha_hora_movimiento', 'fecha_hora', 'fechahora', 'fecha_movimiento', 'fecha', 'created_at']);
$colTipo = buscarColumna($colsMov, ['tipo_movimiento', 'tipo', 'accion', 'operacion']);
$colUnidadesMov = buscarColumna($colsMov, ['unidades_movimiento', 'cantidad_unidades', 'unidades', 'cant']);
$colMontoMov = buscarColumna($colsMov, ['cantidad_movimiento', 'monto_movimiento', 'total_orden', 'total_venta', 'monto_total', 'total', 'monto']);
$colObs = buscarColumna($colsMov, ['observacion_movimiento', 'observaciones', 'observacion', 'descripcion_venta', 'descripcion', 'notas']);
$colProdMov = buscarColumna($colsMov, ['id_producto', 'productos', 'producto']);
$colUsrMov = buscarColumna($colsMov, ['nombre_usuario', 'usuario', 'vendedor', 'id_usuario']);
$colEstadoMov = buscarColumna($colsMov, ['estado_movimiento', 'estado', 'activo', 'vigente']);

$tablaProd = obtenerTablaExistente($conexion, ['producto', 'productos']);
$colsProd = $tablaProd ? obtenerColumnasTabla($conexion, $tablaProd) : [];
$colProdId = buscarColumna($colsProd, ['id_producto', 'id']) ?? 'id_producto';
$colProdNom = buscarColumna($colsProd, ['nombre_producto', 'nombre']) ?? 'nombre_producto';
$colProdPrecio = buscarColumna($colsProd, ['precio_producto', 'precio', 'valor']);
$colProdStock = buscarColumna($colsProd, ['stock_actual_producto', 'stock_actual', 'stock', 'cantidad']);

$mensajeFlash = '';
$tipoFlash = 'success';
$usuarioActor = trim((string)($_SESSION['nombre_usuario'] ?? 'Admin'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tablaMov && $colPkMov) {
    $accion = $_POST['accion_mov'] ?? '';
    $idObjetivo = (int)($_POST['id_movimiento'] ?? 0);

    if ($idObjetivo > 0 && ($accion === 'eliminar_movimiento' || $accion === 'editar_movimiento')) {
        $conexion->begin_transaction();
        try {
            $idEsc = (int)$idObjetivo;
            $resActual = $conexion->query("SELECT * FROM `$tablaMov` WHERE `$colPkMov` = $idEsc LIMIT 1 FOR UPDATE");
            if (!$resActual || $resActual->num_rows === 0) {
                throw new Exception("Movimiento no encontrado.");
            }
            $movActual = $resActual->fetch_assoc();
            $resActual->free();

            $estadoActual = 1;
            if ($colEstadoMov && isset($movActual[$colEstadoMov])) {
                $valEst = $movActual[$colEstadoMov];
                if (is_string($valEst) && in_array(strtolower(trim($valEst)), ['0', 'false', 'inactivo', 'anulado', 'eliminado', 'no'], true)) {
                    $estadoActual = 0;
                } else {
                    $estadoActual = ((int)$valEst === 0) ? 0 : 1;
                }
            }

            if ($accion === 'editar_movimiento' && $estadoActual === 0) {
                throw new Exception("No se puede editar un movimiento que ya está anulado.");
            }

            $tipoActual = $colTipo ? (string)($movActual[$colTipo] ?? 'Venta') : 'Venta';
            $esSalida = esTipoSalida($tipoActual);
            $idProdActual = ($colProdMov && isset($movActual[$colProdMov]) && is_numeric($movActual[$colProdMov])) ? (int)$movActual[$colProdMov] : 0;
            $obsActual = $colObs ? (string)($movActual[$colObs] ?? '') : '';

            $precioUnitario = 0.0;
            if ($tablaProd && $colProdPrecio && $idProdActual > 0) {
                $resP = $conexion->query("SELECT `$colProdPrecio` FROM `$tablaProd` WHERE `$colProdId` = $idProdActual LIMIT 1");
                if ($resP && $rowP = $resP->fetch_assoc()) {
                    $precioUnitario = limpiarNumero($rowP[$colProdPrecio] ?? 0);
                    $resP->free();
                }
            }

            $montoActual = $colMontoMov ? limpiarNumero($movActual[$colMontoMov] ?? 0) : 0.0;
            if ($colUnidadesMov && isset($movActual[$colUnidadesMov]) && trim((string)$movActual[$colUnidadesMov]) !== '') {
                $cantActual = max(0, (int)limpiarNumero($movActual[$colUnidadesMov]));
            } elseif ($precioUnitario > 0 && $montoActual > 0) {
                $cantActual = max(1, (int)round($montoActual / $precioUnitario));
            } else {
                $cantActual = 1;
            }

            if ($precioUnitario <= 0 && $cantActual > 0 && $montoActual > 0) {
                $precioUnitario = $montoActual / $cantActual;
            }

            if ($accion === 'eliminar_movimiento') {
                if ($estadoActual === 1 && $cantActual > 0 && $tablaProd && $colProdStock && $idProdActual > 0) {
                    $deltaStock = $esSalida ? $cantActual : -$cantActual;
                    $conexion->query("UPDATE `$tablaProd` SET `$colProdStock` = GREATEST(0, `$colProdStock` + ($deltaStock)) WHERE `$colProdId` = $idProdActual");
                }

                $sets = [];
                $types = "";
                $params = [];

                if ($colEstadoMov) {
                    $sets[] = "`$colEstadoMov` = 0";
                }
                if ($colObs) {
                    $obsEliminada = anexarAuditoriaObs($obsActual, 'ELIMINADO POR', $usuarioActor);
                    $sets[] = "`$colObs` = ?";
                    $types .= "s";
                    $params[] = $obsEliminada;
                }

                if (!empty($sets)) {
                    $sqlDel = "UPDATE `$tablaMov` SET " . implode(", ", $sets) . " WHERE `$colPkMov` = ?";
                    $types .= "i";
                    $params[] = $idObjetivo;

                    $stmtUpd = $conexion->prepare($sqlDel);
                    if (!empty($params)) {
                        $stmtUpd->bind_param($types, ...$params);
                    }
                    $stmtUpd->execute();
                    $stmtUpd->close();
                }

                $conexion->commit();
                $mensajeFlash = "Movimiento anulado (estado falso). Registrado como ELIMINADO POR: {$usuarioActor}.";
            } elseif ($accion === 'editar_movimiento') {
                $nuevaCant = max(1, (int)($_POST['nueva_cantidad'] ?? 1));
                $nuevaObsBase = trim((string)($_POST['nueva_observacion'] ?? ''));

                if ($estadoActual === 1 && $cantActual !== $nuevaCant && $tablaProd && $colProdStock && $idProdActual > 0) {
                    $difCant = $nuevaCant - $cantActual;
                    $deltaStock = $esSalida ? (-$difCant) : $difCant;

                    if ($deltaStock < 0) {
                        $unidadesRequeridas = abs($deltaStock);
                        $resStock = $conexion->query("SELECT `$colProdStock` FROM `$tablaProd` WHERE `$colProdId` = $idProdActual LIMIT 1 FOR UPDATE");
                        if ($resStock && $rowStock = $resStock->fetch_assoc()) {
                            $stockDisponible = (int)limpiarNumero($rowStock[$colProdStock] ?? 0);
                            $resStock->free();
                            if ($stockDisponible < $unidadesRequeridas) {
                                throw new Exception("Stock insuficiente para aumentar en {$unidadesRequeridas} unidad(es) (disponible: {$stockDisponible}).");
                            }
                        }
                    }

                    $conexion->query("UPDATE `$tablaProd` SET `$colProdStock` = GREATEST(0, `$colProdStock` + ($deltaStock)) WHERE `$colProdId` = $idProdActual");
                }

                $sets = [];
                $types = "";
                $params = [];

                if ($colUnidadesMov) {
                    $sets[] = "`$colUnidadesMov` = ?";
                    $types .= "i";
                    $params[] = $nuevaCant;
                }
                if ($colMontoMov && $precioUnitario > 0) {
                    $nuevoMonto = round($precioUnitario * $nuevaCant, 2);
                    $sets[] = "`$colMontoMov` = ?";
                    $types .= "d";
                    $params[] = $nuevoMonto;
                }
                if ($colObs) {
                    $obsEditada = anexarAuditoriaObs($nuevaObsBase, 'EDITADO POR', $usuarioActor);
                    $sets[] = "`$colObs` = ?";
                    $types .= "s";
                    $params[] = $obsEditada;
                }

                if (!empty($sets)) {
                    $sqlEdit = "UPDATE `$tablaMov` SET " . implode(", ", $sets) . " WHERE `$colPkMov` = ?";
                    $types .= "i";
                    $params[] = $idObjetivo;

                    $stmtEdit = $conexion->prepare($sqlEdit);
                    $stmtEdit->bind_param($types, ...$params);
                    $stmtEdit->execute();
                    $stmtEdit->close();
                }

                $conexion->commit();
                $mensajeFlash = "Movimiento actualizado. Registrado como EDITADO POR: {$usuarioActor}.";
            }
        } catch (Throwable $e) {
            $conexion->rollback();
            $mensajeFlash = "No se pudo completar la operación: " . $e->getMessage();
            $tipoFlash = 'danger';
        }
    }
}

$filasMov = [];
if ($tablaMov !== null) {
    $sqlMov = "SELECT * FROM `$tablaMov`";
    if ($colPkMov) {
        $sqlMov .= " ORDER BY `$colPkMov` DESC";
    }
    $resMov = $conexion->query($sqlMov);
    if ($resMov) {
        while ($r = $resMov->fetch_assoc()) {
            $filasMov[] = $r;
        }
        $resMov->free();
    }
}

$usuariosMap = [];
$tablaUsuario = obtenerTablaExistente($conexion, ['usuario', 'usuarios']);
if ($tablaUsuario !== null) {
    $colsUsr = obtenerColumnasTabla($conexion, $tablaUsuario);
    $colUsrId = buscarColumna($colsUsr, ['id_usuario', 'id']);
    $colUsrNombre = buscarColumna($colsUsr, ['nombre_usuario', 'nombre', 'correo_usuario', 'correo']);
    if ($colUsrId && $colUsrNombre) {
        $resUsr = $conexion->query("SELECT `$colUsrId`, `$colUsrNombre` FROM `$tablaUsuario`");
        if ($resUsr) {
            while ($u = $resUsr->fetch_assoc()) {
                $usuariosMap[(string)$u[$colUsrId]] = (string)$u[$colUsrNombre];
            }
            $resUsr->free();
        }
    }
}

$productosMap = [];
$preciosProductosMap = [];
if ($tablaProd !== null && !empty($colsProd)) {
    $camposSelect = "`$colProdId`, `$colProdNom`" . ($colProdPrecio ? ", `$colProdPrecio`" : "");
    $resProd = $conexion->query("SELECT $camposSelect FROM `$tablaProd`");
    if ($resProd) {
        while ($p = $resProd->fetch_assoc()) {
            $pidKey = (string)$p[$colProdId];
            $productosMap[$pidKey] = (string)$p[$colProdNom];
            if ($colProdPrecio && isset($p[$colProdPrecio])) {
                $preciosProductosMap[$pidKey] = limpiarNumero($p[$colProdPrecio]);
            }
        }
        $resProd->free();
    }
}

$registrosFormateados = [];
foreach ($filasMov as $fila) {
    $idMov = (int)($fila[$colPkMov] ?? 0);

    $activo = true;
    if ($colEstadoMov && isset($fila[$colEstadoMov])) {
        $vEst = $fila[$colEstadoMov];
        if (is_string($vEst) && in_array(strtolower(trim($vEst)), ['0', 'false', 'inactivo', 'anulado', 'eliminado', 'no'], true)) {
            $activo = false;
        } else {
            $activo = ((int)$vEst !== 0);
        }
    }

    $fechaHora = $colFecha ? trim((string)($fila[$colFecha] ?? '')) : '-';
    if ($fechaHora === '') $fechaHora = '-';

    $tipoMov = $colTipo ? trim((string)($fila[$colTipo] ?? '')) : '';
    if ($tipoMov === '') {
        $tipoMov = ($tablaMov && stripos($tablaMov, 'orden') !== false) ? 'Venta / Orden' : 'Movimiento';
    }

    $montoNum = ($colMontoMov && isset($fila[$colMontoMov])) ? limpiarNumero($fila[$colMontoMov]) : 0.0;

    $pidStr = ($colProdMov && isset($fila[$colProdMov])) ? trim((string)$fila[$colProdMov]) : '';
    $precioProd = ($pidStr !== '' && isset($preciosProductosMap[$pidStr])) ? $preciosProductosMap[$pidStr] : 0.0;

    if ($colUnidadesMov && isset($fila[$colUnidadesMov]) && trim((string)$fila[$colUnidadesMov]) !== '') {
        $cantidadNum = max(0, (int)limpiarNumero($fila[$colUnidadesMov]));
    } elseif ($precioProd > 0 && $montoNum > 0) {
        $cantidadNum = max(1, (int)round($montoNum / $precioProd));
    } else {
        $cantidadNum = 1;
    }

    if ($montoNum <= 0 && $precioProd > 0 && $cantidadNum > 0) {
        $montoNum = $precioProd * $cantidadNum;
    }

    $observacionesRaw = $colObs ? trim((string)($fila[$colObs] ?? '')) : '';
    $observacionesEditable = preg_replace('/(?:<br\s*\/?>|\r?\n)*(?:EDITADO|ELIMINADO)\s+POR\s*:.*$/is', '', $observacionesRaw);
    $observacionesEditable = trim((string)$observacionesEditable);

    if ($pidStr !== '') {
        $productosTexto = $productosMap[$pidStr] ?? $pidStr;
    } else {
        $productosTexto = '-';
    }

    if ($colUsrMov) {
        $valUsr = trim((string)($fila[$colUsrMov] ?? ''));
        $usuarioTexto = $usuariosMap[$valUsr] ?? ($valUsr !== '' ? $valUsr : 'Sistema');
    } else {
        $usuarioTexto = 'Sistema';
    }

    $registrosFormateados[] = [
        'id' => $idMov,
        'fecha_hora' => $fechaHora,
        'tipo' => $tipoMov,
        'cantidad' => $cantidadNum,
        'monto' => formatearMonto($montoNum),
        'observaciones_html' => formatearObservacionHTML($observacionesRaw),
        'observaciones_editable' => $observacionesEditable,
        'productos' => $productosTexto,
        'usuario' => $usuarioTexto,
        'activo' => $activo,
    ];
}

include 'visual/header.php';
?>

<style>
    .mov-header-suave {
        color: #5a3e99;
        font-weight: 700;
    }

    .panel-mov-suave {
        border-color: #e6dff5 !important;
        box-shadow: 0 2px 10px rgba(90, 62, 153, 0.05);
    }

    .panel-mov-suave > .panel-heading {
        background-color: #faf8fe !important;
        border-color: #e6dff5 !important;
        color: #5a3e99;
    }

    .buscador-mov-suave .input-group-addon {
        background-color: #f6f2fc;
        border-color: #dfd6f2;
        color: #6b4cb0;
        border-radius: 0;
    }

    .buscador-mov-suave .form-control {
        border-color: #dfd6f2;
        border-radius: 0;
    }

    .buscador-mov-suave .form-control:focus {
        border-color: #a487db;
        box-shadow: none;
    }

    .tabla-mov-suave > thead > tr > th {
        background-color: #f7f4fc;
        color: #53388f;
        border-color: #e6dff5 !important;
        white-space: nowrap;
    }

    .tabla-mov-suave > tbody > tr.fila-mov > td {
        border-color: #ece6f7 !important;
        vertical-align: middle;
    }

    .tabla-mov-suave > tbody > tr.fila-mov:hover {
        background-color: #fcfbfe;
    }

    .fila-mov-inactiva > td {
        opacity: 0.65;
        background-color: #faf9fc !important;
    }

    .badge-mov-admin {
        background-color: #ede4fc;
        color: #5a3e99;
        border: 1px solid #d7c5f7;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
    }

    .circulo-estado-verde {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: #28a745;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.18);
        vertical-align: middle;
    }

    .circulo-estado-falso {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: #dc3545;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.16);
        vertical-align: middle;
    }

    .btn-accion-editar {
        background-color: #f2ecfc !important;
        border-color: #d8c8f5 !important;
        color: #53388f !important;
        font-weight: 600;
        border-radius: 0;
        outline: none !important;
    }

    .btn-accion-editar:hover,
    .btn-accion-editar:focus {
        background-color: #e6dcf9 !important;
        color: #3d2573 !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .btn-accion-eliminar {
        background-color: #fde8ea !important;
        border-color: #f5c2c7 !important;
        color: #842029 !important;
        font-weight: 600;
        border-radius: 0;
        outline: none !important;
    }

    .btn-accion-eliminar:hover,
    .btn-accion-eliminar:focus {
        background-color: #fad2d6 !important;
        color: #66161d !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .btn-cancelar-rojo-suave {
        background-color: #fde8ea !important;
        border-color: #f5c2c7 !important;
        color: #842029 !important;
        font-weight: 600;
        border-radius: 0;
        outline: none !important;
    }

    .btn-cancelar-rojo-suave:hover,
    .btn-cancelar-rojo-suave:focus {
        background-color: #fad2d6 !important;
        border-color: #f1aeb5 !important;
        color: #66161d !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .modal-backdrop {
        z-index: 10050 !important;
    }

    #modalEditarMovimiento {
        z-index: 10060 !important;
    }
</style>

<br>
<div class="clearfix" style="margin-bottom: 15px;">
    <h4 class="pull-left mov-header-suave" style="margin: 5px 0 0 0;">
        <i class="fa fa-exchange"></i> Movimientos generales
    </h4>
    <span class="badge-mov-admin pull-right" style="margin-top: 4px;">
        <i class="fa fa-shield"></i> Vista Gestión (Últimos primero)
    </span>
</div>

<?php if ($mensajeFlash !== ''): ?>
    <div class="alert alert-<?php echo $tipoFlash; ?>" style="border-radius: 0;">
        <?php echo htmlspecialchars($mensajeFlash); ?>
    </div>
<?php endif; ?>

<div class="panel panel-default panel-mov-suave" style="border-radius: 0;">
    <div class="panel-heading" style="border-radius: 0;">
        <div class="form-group buscador-mov-suave" style="margin-bottom: 0;">
            <label for="buscadorMovimientos">Filtrar movimientos</label>
            <div class="input-group" style="width: 100%;">
                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                <input type="text" id="buscadorMovimientos" class="form-control" placeholder="Buscar por fecha, tipo, cantidad, monto, observación, producto o usuario..." autocomplete="off">
            </div>
        </div>
    </div>

    <div class="panel-body" style="padding: 0;">
        <div class="table-responsive" style="margin-bottom: 0;">
            <table class="table table-bordered tabla-mov-suave" id="tablaMovimientos" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Tipo de movimiento</th>
                        <th class="text-center">Cantidad</th>
                        <th>Monto</th>
                        <th>Observaciones</th>
                        <th>Productos</th>
                        <th>Usuario</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($registrosFormateados)): ?>
                        <?php foreach ($registrosFormateados as $reg): ?>
                            <tr class="fila-mov <?php echo !$reg['activo'] ? 'fila-mov-inactiva' : ''; ?>">
                                <td><?php echo htmlspecialchars($reg['fecha_hora']); ?></td>
                                <td><?php echo htmlspecialchars($reg['tipo']); ?></td>
                                <td class="text-center"><strong><?php echo (int)$reg['cantidad']; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($reg['monto']); ?></strong></td>
                                <td><?php echo $reg['observaciones_html']; ?></td>
                                <td><?php echo htmlspecialchars($reg['productos']); ?></td>
                                <td><?php echo htmlspecialchars($reg['usuario']); ?></td>
                                <td class="text-center" title="<?php echo $reg['activo'] ? 'Activo (cuenta para stock)' : 'Falso / Anulado (no cuenta para stock)'; ?>">
                                    <span class="<?php echo $reg['activo'] ? 'circulo-estado-verde' : 'circulo-estado-falso'; ?>"></span>
                                </td>
                                <td class="text-center" style="white-space: nowrap;">
                                    <?php if ($reg['activo']): ?>
                                        <button type="button"
                                                class="btn btn-xs btn-accion-editar btn-editar-mov"
                                                data-id="<?php echo (int)$reg['id']; ?>"
                                                data-cantidad="<?php echo (int)$reg['cantidad']; ?>"
                                                data-obs="<?php echo htmlspecialchars($reg['observaciones_editable'], ENT_QUOTES, 'UTF-8'); ?>"
                                                onmousedown="this.blur();"
                                                title="Editar cantidad u observación">
                                            <i class="fa fa-pencil"></i> Editar
                                        </button>

                                        <form method="post" action="movimientos.php" style="display: inline-block; margin: 0;" onsubmit="return confirm('¿Marcar este movimiento como falso/anulado y devolver su efecto en el stock?');">
                                            <input type="hidden" name="accion_mov" value="eliminar_movimiento">
                                            <input type="hidden" name="id_movimiento" value="<?php echo (int)$reg['id']; ?>">
                                            <button type="submit" class="btn btn-xs btn-accion-eliminar" onmousedown="this.blur();" title="Dejar estado_movimiento en falso y no contar en stock">
                                                <i class="fa fa-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-xs btn-default" disabled style="border-radius: 0; opacity: 0.65;" title="Movimiento anulado, no se puede editar">
                                            <i class="fa fa-pencil"></i> Editar
                                        </button>
                                        <button type="button" class="btn btn-xs btn-default" disabled style="border-radius: 0;">Anulado</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="sinResultadosMov" style="display: none;">
                            <td colspan="9" class="text-center text-muted">
                                No se encontraron movimientos con ese criterio.
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted" style="padding: 20px;">
                                No hay movimientos registrados aún.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarMovimiento" tabindex="-1" role="dialog" aria-labelledby="modalEditarMovimientoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 0;">
            <form method="post" action="movimientos.php" id="formEditarMovimiento">
                <div class="modal-header" style="background-color: #faf8fe; border-bottom-color: #e6dff5;">
                    <button type="button" class="close" id="btnCerrarModalEditarX" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="modalEditarMovimientoLabel" style="color: #53388f; font-weight: 700;">
                        <i class="fa fa-pencil"></i> Editar movimiento
                    </h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion_mov" value="editar_movimiento">
                    <input type="hidden" name="id_movimiento" id="edit_id_movimiento">

                    <div class="form-group">
                        <label for="edit_nueva_cantidad">Cantidad (unidades)</label>
                        <input type="number" min="1" step="1" class="form-control" name="nueva_cantidad" id="edit_nueva_cantidad" required style="border-radius: 0;">
                        <p class="help-block" style="font-size: 12px; color: #6f6785;">
                            Puedes subir o bajar las unidades; ajusta automáticamente el stock y el monto.
                        </p>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="edit_nueva_observacion">Observaciones</label>
                        <textarea class="form-control" rows="3" name="nueva_observacion" id="edit_nueva_observacion" style="border-radius: 0;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #faf8fe; border-top-color: #e6dff5;">
                    <button type="button" id="btnCancelarModalEditar" class="btn btn-cancelar-rojo-suave" style="border-radius: 0;" onmousedown="this.blur();">Cancelar</button>
                    <button type="submit" class="btn btn-accion-editar" onmousedown="this.blur();">
                        <i class="fa fa-check"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function iniciarMovimientos() {
    if (window.__tecnostockMovimientosInit) return;
    window.__tecnostockMovimientosInit = true;

    function limpiarBackdrops() {
        document.querySelectorAll(".modal-backdrop").forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove("modal-open");
        document.body.style.paddingRight = "";
    }

    function obtenerModal() {
        document.querySelectorAll("body > #modalEditarMovimiento").forEach(function (previo, idx) {
            if (idx > 0) previo.remove();
        });
        const modalEl = document.getElementById("modalEditarMovimiento");
        if (modalEl && modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }
        return modalEl;
    }

    function abrirModalEditar() {
        const modalEl = obtenerModal();
        if (!modalEl) return;

        limpiarBackdrops();

        if (window.jQuery && typeof jQuery.fn.modal === "function") {
            const $m = jQuery(modalEl);
            $m.off("hidden.bs.modal").on("hidden.bs.modal", limpiarBackdrops);
            $m.modal({
                backdrop: "static",
                keyboard: true,
                show: true
            });
            setTimeout(function () {
                modalEl.style.display = "block";
                modalEl.classList.add("in");
                modalEl.setAttribute("aria-hidden", "false");
            }, 10);
        } else {
            modalEl.classList.add("in");
            modalEl.style.display = "block";
            modalEl.setAttribute("aria-hidden", "false");
            document.body.classList.add("modal-open");
        }
    }

    function cerrarModalEditar() {
        const modalEl = obtenerModal();
        if (window.jQuery && typeof jQuery.fn.modal === "function" && modalEl) {
            jQuery(modalEl).modal("hide");
        }
        if (modalEl) {
            modalEl.classList.remove("in");
            modalEl.style.display = "none";
            modalEl.setAttribute("aria-hidden", "true");
        }
        limpiarBackdrops();
        if (document.activeElement && typeof document.activeElement.blur === "function") {
            document.activeElement.blur();
        }
    }

    document.addEventListener("input", function (e) {
        if (!e.target || e.target.id !== "buscadorMovimientos") return;
        const termino = e.target.value.toLowerCase().trim();
        const filas = document.querySelectorAll("#tablaMovimientos tbody tr.fila-mov");
        const sinResultados = document.getElementById("sinResultadosMov");
        let visibles = 0;

        filas.forEach(function (fila) {
            const coincide = fila.textContent.toLowerCase().includes(termino);
            fila.style.display = coincide ? "" : "none";
            if (coincide) visibles++;
        });

        if (sinResultados) {
            sinResultados.style.display = (visibles === 0 && filas.length > 0) ? "" : "none";
        }
    });

    document.addEventListener("click", function (e) {
        const btnEditar = e.target.closest ? e.target.closest(".btn-editar-mov") : null;
        if (btnEditar) {
            e.preventDefault();
            if (btnEditar.disabled || btnEditar.closest(".fila-mov-inactiva")) return;
            if (typeof btnEditar.blur === "function") btnEditar.blur();

            const idMov = btnEditar.getAttribute("data-id") || "";
            const cantMov = btnEditar.getAttribute("data-cantidad") || "1";
            const obsMov = btnEditar.getAttribute("data-obs") || "";

            const inputId = document.getElementById("edit_id_movimiento");
            const inputCant = document.getElementById("edit_nueva_cantidad");
            const inputObs = document.getElementById("edit_nueva_observacion");

            if (inputId) inputId.value = idMov;
            if (inputCant) inputCant.value = cantMov;
            if (inputObs) inputObs.value = obsMov;

            abrirModalEditar();
            return;
        }

        const btnCerrar = e.target.closest ? e.target.closest("#btnCancelarModalEditar, #btnCerrarModalEditarX") : null;
        if (btnCerrar) {
            e.preventDefault();
            cerrarModalEditar();
        }
    });
})();
</script>

<?php
$conexion->close();
include 'visual/footer.php';
?>