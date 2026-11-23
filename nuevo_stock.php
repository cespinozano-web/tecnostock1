<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header("location: login.html");
    exit();
}

$cargoActual = strtolower(trim((string)($_SESSION['nombre_cargo'] ?? '')));
if (!in_array($cargoActual, ['dueño', 'dueno', 'administrador', 'admin', 'owner'], true)) {
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

function obtenerColumnas(mysqli $db, string $tabla): array {
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

function buscarCol(array $cols, array $candidatas): ?string {
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

function limpiarNumeroStock($valor): float {
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

function registrarMovimientoIngreso(
    mysqli $db,
    int $idProducto,
    int $unidades,
    float $monto,
    string $observacion,
    int $idUsuario,
    string $nombreUsuario
): void {
    if ($idProducto <= 0 || $unidades <= 0) {
        return;
    }
    $colsMov = obtenerColumnas($db, 'movimiento');
    if (empty($colsMov)) {
        return;
    }

    $colMovProd = buscarCol($colsMov, ['id_producto', 'producto']);
    $colMovUsr = buscarCol($colsMov, ['id_usuario', 'nombre_usuario', 'usuario']);
    $colMovTipo = buscarCol($colsMov, ['tipo_movimiento', 'tipo']);
    $colMovUni = buscarCol($colsMov, ['unidades_movimiento', 'unidades']);
    $colMovCant = buscarCol($colsMov, ['cantidad_movimiento', 'monto_movimiento', 'monto']);
    $colMovObs = buscarCol($colsMov, ['observacion_movimiento', 'observacion', 'observaciones']);
    $colMovEst = buscarCol($colsMov, ['estado_movimiento', 'estado']);
    $colMovFec = buscarCol($colsMov, ['fecha_hora_movimiento', 'fecha_hora', 'fecha']);

    $camposIns = [];
    $placeholders = [];
    $tiposBind = "";
    $valoresBind = [];

    if ($colMovProd) {
        $camposIns[] = "`$colMovProd`";
        $placeholders[] = "?";
        $tiposBind .= "i";
        $valoresBind[] = $idProducto;
    }
    if ($colMovUsr) {
        $camposIns[] = "`$colMovUsr`";
        $placeholders[] = "?";
        if (stripos($colMovUsr, 'id_') === 0) {
            $tiposBind .= "i";
            $valoresBind[] = $idUsuario;
        } else {
            $tiposBind .= "s";
            $valoresBind[] = $nombreUsuario;
        }
    }
    if ($colMovTipo) {
        $camposIns[] = "`$colMovTipo`";
        $placeholders[] = "?";
        $tiposBind .= "s";
        $valoresBind[] = "Ingreso";
    }
    if ($colMovUni) {
        $camposIns[] = "`$colMovUni`";
        $placeholders[] = "?";
        $tiposBind .= "i";
        $valoresBind[] = $unidades;
    }
    if ($colMovCant) {
        $camposIns[] = "`$colMovCant`";
        $placeholders[] = "?";
        $tiposBind .= "d";
        $valoresBind[] = round($monto, 2);
    }
    if ($colMovObs) {
        $camposIns[] = "`$colMovObs`";
        $placeholders[] = "?";
        $tiposBind .= "s";
        $valoresBind[] = $observacion;
    }
    if ($colMovEst) {
        $camposIns[] = "`$colMovEst`";
        $placeholders[] = "?";
        $tiposBind .= "i";
        $valoresBind[] = 1;
    }
    if ($colMovFec) {
        $camposIns[] = "`$colMovFec`";
        $placeholders[] = "NOW()";
    }

    if (!empty($camposIns)) {
        $sqlMov = "INSERT INTO `movimiento` (" . implode(", ", $camposIns) . ") VALUES (" . implode(", ", $placeholders) . ")";
        $stmtMov = $db->prepare($sqlMov);
        if (!empty($valoresBind)) {
            $stmtMov->bind_param($tiposBind, ...$valoresBind);
        }
        $stmtMov->execute();
        $stmtMov->close();
    }
}

$colsProd = obtenerColumnas($conexion, 'producto');
$colProdId = buscarCol($colsProd, ['id_producto']) ?? 'id_producto';
$colProdCod = buscarCol($colsProd, ['codigo_producto']) ?? 'codigo_producto';
$colProdNom = buscarCol($colsProd, ['nombre_producto']) ?? 'nombre_producto';
$colProdDesc = buscarCol($colsProd, ['descripcion_producto', 'descripcion']);
$colProdPre = buscarCol($colsProd, ['precio_producto', 'precio']) ?? 'precio_producto';
$colProdStk = buscarCol($colsProd, ['stock_actual_producto']) ?? 'stock_actual_producto';
$colProdMin = buscarCol($colsProd, ['stock_minimo_producto', 'stock_critico_producto', 'stock_minimo']);
$colProdCat = buscarCol($colsProd, ['id_categoria']);

$colsCat = obtenerColumnas($conexion, 'categoria');
$colCatId = buscarCol($colsCat, ['id_categoria']) ?? 'id_categoria';
$colCatNom = buscarCol($colsCat, ['nombre_categoria']) ?? 'nombre_categoria';
$colCatDesc = buscarCol($colsCat, ['descripcion_categoria', 'descripcion']);

$mensaje = "";
$tipoNotificacion = "ok";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accionPost = $_POST['accion_post'] ?? 'guardar_ingreso';
    $idUsuarioActual = (int)($_SESSION['id_usuario'] ?? 0);
    $nombreUsuarioActual = trim((string)($_SESSION['nombre_usuario'] ?? ''));

    if ($accionPost === 'crear_categoria') {
        $nombreCategoriaNueva = trim((string)($_POST['nombre_categoria'] ?? ''));
        $descCategoriaNueva = trim((string)($_POST['descripcion_categoria'] ?? ''));

        if ($nombreCategoriaNueva === '') {
            $mensaje = "No se pudo crear la categoría: ingresa un nombre para la categoría.";
            $tipoNotificacion = "error";
        } else {
            $conexion->begin_transaction();
            try {
                $camposC = ["`$colCatNom`"];
                $placeC = ["?"];
                $typesC = "s";
                $valsC = [$nombreCategoriaNueva];

                if ($colCatDesc) {
                    $camposC[] = "`$colCatDesc`";
                    $placeC[] = "?";
                    $typesC .= "s";
                    $valsC[] = $descCategoriaNueva;
                }

                $sqlInsC = "INSERT INTO `categoria` (" . implode(", ", $camposC) . ") VALUES (" . implode(", ", $placeC) . ")";
                $stmtInsC = $conexion->prepare($sqlInsC);
                $stmtInsC->bind_param($typesC, ...$valsC);
                $stmtInsC->execute();
                $stmtInsC->close();

                $conexion->commit();
                $mensaje = "Categoría \"{$nombreCategoriaNueva}\" creada correctamente.";
                $tipoNotificacion = "ok";
            } catch (Throwable $e) {
                $conexion->rollback();
                $mensaje = "No se pudo crear la categoría: " . $e->getMessage();
                $tipoNotificacion = "error";
            }
        }
    } elseif ($accionPost === 'crear_producto') {
        $nuevoCodigo = trim((string)($_POST['nuevo_codigo'] ?? ''));
        $nuevoNombre = trim((string)($_POST['nuevo_nombre'] ?? ''));
        $nuevaDescripcion = trim((string)($_POST['nueva_descripcion'] ?? ''));
        $nuevoPrecio = max(0, limpiarNumeroStock($_POST['nuevo_precio'] ?? 0));
        $nuevoStockInicial = max(0, (int)($_POST['nuevo_stock_inicial'] ?? 0));
        $nuevoStockMin = max(0, (int)($_POST['nuevo_stock_minimo'] ?? 0));
        $nuevaCategoria = (int)($_POST['nueva_categoria'] ?? 0);

        if ($nuevoNombre === '' || $nuevoCodigo === '') {
            $mensaje = "No se pudo crear el producto: ingresa código y nombre del producto.";
            $tipoNotificacion = "error";
        } else {
            $conexion->begin_transaction();
            try {
                $camposP = ["`$colProdCod`", "`$colProdNom`", "`$colProdPre`", "`$colProdStk`"];
                $placeP = ["?", "?", "?", "?"];
                $typesP = "ssdi";
                $valsP = [$nuevoCodigo, $nuevoNombre, $nuevoPrecio, $nuevoStockInicial];

                if ($colProdDesc) {
                    $camposP[] = "`$colProdDesc`";
                    $placeP[] = "?";
                    $typesP .= "s";
                    $valsP[] = $nuevaDescripcion;
                }
                if ($colProdMin) {
                    $camposP[] = "`$colProdMin`";
                    $placeP[] = "?";
                    $typesP .= "i";
                    $valsP[] = $nuevoStockMin;
                }
                if ($colProdCat && $nuevaCategoria > 0) {
                    $camposP[] = "`$colProdCat`";
                    $placeP[] = "?";
                    $typesP .= "i";
                    $valsP[] = $nuevaCategoria;
                }

                $sqlInsP = "INSERT INTO `producto` (" . implode(", ", $camposP) . ") VALUES (" . implode(", ", $placeP) . ")";
                $stmtInsP = $conexion->prepare($sqlInsP);
                $stmtInsP->bind_param($typesP, ...$valsP);
                $stmtInsP->execute();
                $nuevoIdProducto = (int)$conexion->insert_id;
                $stmtInsP->close();

                if ($nuevoIdProducto > 0 && $nuevoStockInicial > 0) {
                    $montoInicial = round($nuevoPrecio * $nuevoStockInicial, 2);
                    registrarMovimientoIngreso(
                        $conexion,
                        $nuevoIdProducto,
                        $nuevoStockInicial,
                        $montoInicial,
                        "Stock inicial al crear producto",
                        $idUsuarioActual,
                        $nombreUsuarioActual
                    );
                }

                $conexion->commit();
                $mensaje = "Producto \"{$nuevoNombre}\" creado correctamente" . ($nuevoStockInicial > 0 ? " (+{$nuevoStockInicial} ud. registradas como Ingreso)." : ".");
                $tipoNotificacion = "ok";
            } catch (Throwable $e) {
                $conexion->rollback();
                $mensaje = "No se pudo crear el producto: " . $e->getMessage();
                $tipoNotificacion = "error";
            }
        }
    } else {
        $itemsJson = (string)($_POST['items_json'] ?? '[]');
        $observacionGeneral = trim((string)($_POST['observacion_general'] ?? ''));

        $itemsDecodificados = json_decode($itemsJson, true);
        $itemsNormalizados = [];

        if (is_array($itemsDecodificados)) {
            foreach ($itemsDecodificados as $it) {
                $pid = (int)($it['id_producto'] ?? 0);
                $uni = (int)($it['unidades'] ?? 0);
                if ($pid > 0 && $uni > 0) {
                    if (!isset($itemsNormalizados[$pid])) {
                        $itemsNormalizados[$pid] = 0;
                    }
                    $itemsNormalizados[$pid] += $uni;
                }
            }
        }

        if (empty($itemsNormalizados)) {
            $mensaje = "No se pudo ingresar el stock: selecciona unidades mayores a 0 en al menos un producto.";
            $tipoNotificacion = "error";
        } else {
            $conexion->begin_transaction();
            try {
                $totalUnidades = 0;

                foreach ($itemsNormalizados as $pid => $unidadesSumar) {
                    $stmtProd = $conexion->prepare("SELECT `$colProdNom`, `$colProdPre` FROM `producto` WHERE `$colProdId` = ? LIMIT 1 FOR UPDATE");
                    $stmtProd->bind_param("i", $pid);
                    $stmtProd->execute();
                    $resP = $stmtProd->get_result();
                    $prodRow = $resP ? $resP->fetch_assoc() : null;
                    $stmtProd->close();

                    if (!$prodRow) {
                        throw new Exception("Producto ID #{$pid} no encontrado.");
                    }

                    $precioUnit = limpiarNumeroStock($prodRow[$colProdPre] ?? 0);
                    $montoMovimiento = round($precioUnit * $unidadesSumar, 2);

                    $stmtUpd = $conexion->prepare("UPDATE `producto` SET `$colProdStk` = COALESCE(`$colProdStk`, 0) + ? WHERE `$colProdId` = ?");
                    $stmtUpd->bind_param("ii", $unidadesSumar, $pid);
                    $stmtUpd->execute();
                    $stmtUpd->close();

                    registrarMovimientoIngreso(
                        $conexion,
                        $pid,
                        $unidadesSumar,
                        $montoMovimiento,
                        $observacionGeneral,
                        $idUsuarioActual,
                        $nombreUsuarioActual
                    );

                    $totalUnidades += $unidadesSumar;
                }

                $conexion->commit();
                $mensaje = "Stock ingresado correctamente: +{$totalUnidades} unidad(es) registradas como Ingreso.";
                $tipoNotificacion = "ok";
            } catch (Throwable $e) {
                $conexion->rollback();
                $mensaje = "No se pudo ingresar el stock: " . $e->getMessage();
                $tipoNotificacion = "error";
            }
        }
    }
}

$categorias = [];
$resCat = $conexion->query("SELECT `$colCatId` AS id_categoria, `$colCatNom` AS nombre_categoria FROM `categoria` ORDER BY `$colCatNom` ASC");
if ($resCat) {
    while ($c = $resCat->fetch_assoc()) {
        $categorias[] = $c;
    }
    $resCat->free();
}

$productos = [];
$campoDescSel = $colProdDesc ? "p.`$colProdDesc`" : "'' AS descripcion_producto";
if ($colProdCat) {
    $sqlLista = "SELECT p.`$colProdId`, p.`$colProdCod`, p.`$colProdNom`, {$campoDescSel}, p.`$colProdPre`, p.`$colProdStk`, c.`$colCatNom` AS nombre_categoria
                 FROM `producto` p
                 LEFT JOIN `categoria` c ON p.`$colProdCat` = c.`$colCatId`
                 ORDER BY p.`$colProdNom` ASC";
} else {
    $sqlLista = "SELECT `$colProdId`, `$colProdCod`, `$colProdNom`, " . ($colProdDesc ? "`$colProdDesc`" : "''") . " AS descripcion_producto, `$colProdPre`, `$colProdStk`, '' AS nombre_categoria
                 FROM `producto`
                 ORDER BY `$colProdNom` ASC";
}

$resLista = $conexion->query($sqlLista);
if ($resLista) {
    while ($p = $resLista->fetch_assoc()) {
        $productos[] = [
            'id' => (int)$p[$colProdId],
            'codigo' => (string)($p[$colProdCod] ?? ''),
            'nombre' => (string)($p[$colProdNom] ?? ''),
            'descripcion' => (string)($p['descripcion_producto'] ?? ''),
            'precio' => limpiarNumeroStock($p[$colProdPre] ?? 0),
            'stock' => (int)($p[$colProdStk] ?? 0),
            'categoria' => (string)($p['nombre_categoria'] ?? 'General'),
        ];
    }
    $resLista->free();
}

include 'visual/header.php';
?>

<style>
    .nuevo-stock-titulo { color: #1f1b2e; font-weight: 700; margin: 0 0 4px 0; }
    .nuevo-stock-subtitulo { color: #6f6785; font-size: 13.5px; margin: 0 0 16px 0; }
    .notif-suave-ok { background-color: #e8f7ee !important; border: 1px solid #b7e4c7 !important; color: #1e6038 !important; border-radius: 8px; font-weight: 600; }
    .notif-suave-error { background-color: #fde8ea !important; border: 1px solid #f5c2c7 !important; color: #842029 !important; border-radius: 8px; font-weight: 600; }
    .btn-nuevo-producto-modal { background-color: #f2ecfc !important; border: 1px solid #d8c8f5 !important; color: #4d2c91 !important; font-weight: 600; border-radius: 7px; padding: 8px 15px; outline: none !important; }
    .btn-nuevo-producto-modal:hover, .btn-nuevo-producto-modal:focus { background-color: #e6dcf9 !important; border-color: #c7b1f0 !important; color: #3a1f73 !important; outline: none !important; box-shadow: none !important; }
    .acciones-superior-apiladas { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; margin-top: 4px; }
    .card-morada-stock { background-color: #ffffff; border: 1px solid #e7e1f5; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(92, 61, 166, 0.04); margin-bottom: 18px; }
    .toolbar-morada-stock { background-color: #faf8fe; border-bottom: 1px solid #ece6f7; padding: 13px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
    .buscador-stock .input-group-addon { background-color: #f5f1fc; border-color: #dfd7f0; color: #673ab7; border-radius: 7px 0 0 7px; }
    .buscador-stock .form-control { border-color: #dfd7f0; border-radius: 0 7px 7px 0; height: 36px; box-shadow: none; }
    .buscador-stock .form-control:focus, .input-stock-suave:focus { border-color: #7c52cc; box-shadow: 0 0 0 3px rgba(124, 82, 204, 0.12); }
    .input-stock-suave { border-color: #dfd7f0; border-radius: 7px; box-shadow: none; }
    .tabla-stock-morada { margin-bottom: 0 !important; }
    .tabla-stock-morada > thead > tr > th { background-color: #f5f1fc; color: #4d2c91; border-bottom: 2px solid #dfd5f5 !important; border-top: none !important; border-color: #ece6f7; font-weight: 600; padding: 11px 13px; }
    .tabla-stock-morada > tbody > tr > td { border-color: #efeaf9 !important; padding: 10px 13px; vertical-align: middle; color: #2b253b; }
    .tabla-stock-morada > tbody > tr.fila-producto-stock:hover { background-color: #fcfbfe; }
    .sumador-box { display: inline-flex; align-items: center; border: 1px solid #d8cef0; border-radius: 7px; overflow: hidden; background-color: #ffffff; }
    .btn-sumador { background-color: #f5f1fc !important; border: none !important; color: #4d2c91 !important; font-weight: 700; width: 30px; height: 30px; padding: 0; line-height: 1; outline: none !important; }
    .btn-sumador:hover, .btn-sumador:focus { background-color: #e7def9 !important; outline: none !important; box-shadow: none !important; }
    .input-sumador-cant { width: 58px; height: 30px; border: none !important; border-left: 1px solid #e6dff5 !important; border-right: 1px solid #e6dff5 !important; text-align: center; font-weight: 600; padding: 0 4px; box-shadow: none !important; }
    .btn-confirmar-ingreso { background-color: #673ab7 !important; border-color: #5a32a8 !important; color: #ffffff !important; font-weight: 600; border-radius: 7px; padding: 8px 18px; outline: none !important; }
    .btn-confirmar-ingreso:hover, .btn-confirmar-ingreso:focus { background-color: #562e9e !important; border-color: #4a268a !important; outline: none !important; box-shadow: none !important; }
    .btn-volver-bodega { background-color: #ffffff !important; border: 1px solid #dcd4ec !important; color: #3d3552 !important; font-weight: 600; border-radius: 7px; padding: 8px 16px; outline: none !important; }
    .btn-volver-bodega:hover, .btn-volver-bodega:focus { background-color: #f7f4fc !important; border-color: #c8bce3 !important; outline: none !important; box-shadow: none !important; }
    .btn-cancelar-rojo-suave { background-color: #fde8ea !important; border-color: #f5c2c7 !important; color: #842029 !important; font-weight: 600; border-radius: 7px; outline: none !important; }
    .btn-cancelar-rojo-suave:hover, .btn-cancelar-rojo-suave:focus { background-color: #fad2d6 !important; color: #66161d !important; outline: none !important; box-shadow: none !important; }
    .modal-backdrop { z-index: 10050 !important; }
    #modalNuevoProducto, #modalNuevaCategoria { z-index: 10060 !important; }
</style>

<br>
<div class="clearfix" style="margin-bottom: 12px;">
    <div class="pull-left">
        <h3 class="nuevo-stock-titulo"><i class="fa fa-plus-circle" style="color: #673ab7; margin-right: 6px;"></i> Ingresar Nuevo Stock</h3>
        <p class="nuevo-stock-subtitulo" style="margin-bottom: 0;">Al presionar <strong>+</strong> se actualiza el detalle en tiempo real con el precio de venta.</p>
    </div>
    <div class="pull-right acciones-superior-apiladas">
        <button type="button" class="btn btn-nuevo-producto-modal" id="btnAbrirModalNuevaCat" onmousedown="this.blur();">
            <i class="fa fa-folder-open"></i> Crear nueva categoría
        </button>
        <button type="button" class="btn btn-nuevo-producto-modal" id="btnAbrirModalNuevoProd" onmousedown="this.blur();">
            <i class="fa fa-tag"></i> Crear nuevo producto
        </button>
    </div>
</div>

<?php if ($mensaje !== ""): ?>
    <div class="alert <?php echo ($tipoNotificacion === 'ok') ? 'notif-suave-ok' : 'notif-suave-error'; ?>" style="margin-bottom: 16px;">
        <i class="fa <?php echo ($tipoNotificacion === 'ok') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="card-morada-stock">
    <div class="toolbar-morada-stock">
        <span style="font-weight: 600; color: #4d2c91;">
            <i class="fa fa-cubes"></i> Productos (<?php echo count($productos); ?>)
        </span>

        <div class="input-group buscador-stock" style="width: 300px; max-width: 100%;">
            <span class="input-group-addon"><i class="fa fa-search"></i></span>
            <input type="text" id="buscadorProductosStock" class="form-control" placeholder="Buscar producto, código o categoría..." autocomplete="off">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered tabla-stock-morada" id="tablaProductosStock">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th class="text-center">Precio Venta</th>
                    <th class="text-center">Stock Actual</th>
                    <th class="text-center">Unidades</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($productos)): ?>
                    <?php foreach ($productos as $prod): ?>
                        <tr class="fila-producto-stock"
                            data-id="<?php echo (int)$prod['id']; ?>"
                            data-codigo="<?php echo htmlspecialchars($prod['codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-nombre="<?php echo htmlspecialchars($prod['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-precio="<?php echo (float)$prod['precio']; ?>"
                            data-stock="<?php echo (int)$prod['stock']; ?>">
                            <td><?php echo htmlspecialchars($prod['codigo']); ?></td>
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                                <?php if ($prod['descripcion'] !== ''): ?>
                                    <div style="font-size: 12px; color: #6f6785;"><?php echo htmlspecialchars($prod['descripcion']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($prod['categoria']); ?></td>
                            <td class="text-center">$<?php echo number_format($prod['precio'], 0, ',', '.'); ?></td>
                            <td class="text-center"><strong><?php echo (int)$prod['stock']; ?></strong></td>
                            <td class="text-center" style="white-space: nowrap;">
                                <div class="sumador-box">
                                    <button type="button" class="btn btn-sumador btn-restar-uni" onmousedown="this.blur();" title="Restar 1 unidad">-</button>
                                    <input type="number" class="input-sumador-cant js-uni-fila" min="0" step="1" value="0" aria-label="Unidades a ingresar">
                                    <button type="button" class="btn btn-sumador btn-sumar-uni" onmousedown="this.blur();" title="Sumar 1 unidad">+</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="sinResultadosStock" style="display: none;">
                        <td colspan="6" class="text-center text-muted">No se encontraron productos que coincidan con la búsqueda.</td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding: 20px;">No hay productos registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card-morada-stock">
    <div class="toolbar-morada-stock">
        <span style="font-weight: 600; color: #4d2c91;">
            <i class="fa fa-list-alt"></i> Detalle en Tiempo Real
        </span>
        <span style="font-size: 12.5px; color: #6f6785;">
            Tipo movimiento: <strong>Ingreso</strong> | Total precio venta: <strong id="totalVentaDetalle">$0</strong>
        </span>
    </div>

    <div style="padding: 16px;">
        <div class="table-responsive" style="margin-bottom: 14px;">
            <table class="table table-bordered tabla-stock-morada" id="tablaDetalleIngreso">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-center">Precio Venta</th>
                        <th class="text-center">Subtotal Venta</th>
                    </tr>
                </thead>
                <tbody id="tbodyDetalleIngreso">
                    <tr id="filaDetalleVacia">
                        <td colspan="4" class="text-center text-muted" style="padding: 16px;">
                            Presiona <strong>+</strong> en algún producto para ver el detalle aquí al instante.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form method="post" action="nuevo_stock.php" id="formConfirmarIngreso">
            <input type="hidden" name="accion_post" value="guardar_ingreso">
            <input type="hidden" name="items_json" id="items_json" value="[]">

            <div class="form-group">
                <label for="observacion_general">Observación (opcional)</label>
                <input type="text" name="observacion_general" id="observacion_general" class="form-control input-stock-suave" placeholder="Ej: Reposición de bodega...">
            </div>

            <div class="clearfix" style="margin-top: 10px;">
                <a href="bodega.php" class="btn btn-volver-bodega" onmousedown="this.blur();">
                    <i class="fa fa-arrow-left"></i> Volver a Bodega
                </a>
                <button type="submit" class="btn btn-confirmar-ingreso pull-right" onmousedown="this.blur();">
                    <i class="fa fa-check"></i> Guardar ingreso
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Dialog: Crear nuevo producto -->
<div class="modal fade" id="modalNuevoProducto" tabindex="-1" role="dialog" aria-labelledby="modalNuevoProductoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden;">
            <form method="post" action="nuevo_stock.php" id="formNuevoProducto">
                <input type="hidden" name="accion_post" value="crear_producto">
                <div class="modal-header" style="background-color: #faf8fe; border-bottom-color: #ece6f7;">
                    <button type="button" class="close" id="btnCerrarModalNuevoProdX" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="modalNuevoProductoLabel" style="color: #4d2c91; font-weight: 700;">
                        <i class="fa fa-tag"></i> Crear nuevo producto
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nuevo_codigo">Código del producto</label>
                        <input type="text" name="nuevo_codigo" id="nuevo_codigo" class="form-control input-stock-suave" required placeholder="Ej: PROD-001">
                    </div>
                    <div class="form-group">
                        <label for="nuevo_nombre">Nombre del producto</label>
                        <input type="text" name="nuevo_nombre" id="nuevo_nombre" class="form-control input-stock-suave" required placeholder="Ej: Mouse inalámbrico">
                    </div>
                    <div class="form-group">
                        <label for="nueva_descripcion">Descripción del producto</label>
                        <textarea name="nueva_descripcion" id="nueva_descripcion" rows="2" class="form-control input-stock-suave" placeholder="Características, modelo, formato, etc."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="nuevo_precio">Precio de venta ($)</label>
                                <input type="number" name="nuevo_precio" id="nuevo_precio" class="form-control input-stock-suave" min="0" step="any" value="0" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="nuevo_stock_inicial">Stock inicial</label>
                                <input type="number" name="nuevo_stock_inicial" id="nuevo_stock_inicial" class="form-control input-stock-suave" min="0" step="1" value="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="nuevo_stock_minimo">Stock crítico / mínimo</label>
                                <input type="number" name="nuevo_stock_minimo" id="nuevo_stock_minimo" class="form-control input-stock-suave" min="0" step="1" value="3">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="nueva_categoria">Categoría</label>
                                <select name="nueva_categoria" id="nueva_categoria" class="form-control input-stock-suave">
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo (int)$cat['id_categoria']; ?>">
                                            <?php echo htmlspecialchars($cat['nombre_categoria']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #faf8fe; border-top-color: #ece6f7;">
                    <button type="button" class="btn btn-cancelar-rojo-suave" id="btnCancelarModalNuevoProd" onmousedown="this.blur();">Cancelar</button>
                    <button type="submit" class="btn btn-confirmar-ingreso" onmousedown="this.blur();">
                        <i class="fa fa-plus"></i> Crear producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Dialog: Crear nueva categoría -->
<div class="modal fade" id="modalNuevaCategoria" tabindex="-1" role="dialog" aria-labelledby="modalNuevaCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden;">
            <form method="post" action="nuevo_stock.php" id="formNuevaCategoria">
                <input type="hidden" name="accion_post" value="crear_categoria">
                <div class="modal-header" style="background-color: #faf8fe; border-bottom-color: #ece6f7;">
                    <button type="button" class="close" id="btnCerrarModalNuevaCatX" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="modalNuevaCategoriaLabel" style="color: #4d2c91; font-weight: 700;">
                        <i class="fa fa-folder-open"></i> Crear nueva categoría
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="form-group" style="<?php echo $colCatDesc ? '' : 'margin-bottom: 0;'; ?>">
                        <label for="nombre_categoria">Nombre de la categoría</label>
                        <input type="text" name="nombre_categoria" id="nombre_categoria" class="form-control input-stock-suave" required placeholder="Ej: Periféricos">
                    </div>
                    <?php if ($colCatDesc): ?>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="descripcion_categoria">Descripción (opcional)</label>
                            <textarea name="descripcion_categoria" id="descripcion_categoria" rows="2" class="form-control input-stock-suave" placeholder="Descripción breve de la categoría..."></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer" style="background-color: #faf8fe; border-top-color: #ece6f7;">
                    <button type="button" class="btn btn-cancelar-rojo-suave" id="btnCancelarModalNuevaCat" onmousedown="this.blur();">Cancelar</button>
                    <button type="submit" class="btn btn-confirmar-ingreso" onmousedown="this.blur();">
                        <i class="fa fa-plus"></i> Crear categoría
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function iniciarNuevoStock() {
    function limpiarBackdrops() {
        document.querySelectorAll(".modal-backdrop").forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove("modal-open");
        document.body.style.paddingRight = "";
    }

    function obtenerModalPorId(modalId) {
        document.querySelectorAll("body > #" + modalId).forEach(function (previo, idx) {
            if (idx > 0) previo.remove();
        });
        const modalEl = document.getElementById(modalId);
        if (modalEl && modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }
        return modalEl;
    }

    function abrirModalPorId(modalId) {
        const modalEl = obtenerModalPorId(modalId);
        if (!modalEl) return;
        limpiarBackdrops();
        if (window.jQuery && typeof jQuery.fn.modal === "function") {
            const $m = jQuery(modalEl);
            $m.off("hidden.bs.modal").on("hidden.bs.modal", limpiarBackdrops);
            $m.modal({ backdrop: "static", keyboard: true, show: true });
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

    function cerrarModalPorId(modalId) {
        const modalEl = obtenerModalPorId(modalId);
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

    function escapeHTML(str) {
        return String(str || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function formatoCLP(num) {
        return "$" + Math.round(Number(num) || 0).toLocaleString("es-CL");
    }

    function actualizarDetalleTiempoReal() {
        const tbodyDetalle = document.getElementById("tbodyDetalleIngreso");
        const inputItemsJson = document.getElementById("items_json");
        const totalVentaEl = document.getElementById("totalVentaDetalle");
        const filasProductos = document.querySelectorAll("#tablaProductosStock tbody tr.fila-producto-stock");

        if (!tbodyDetalle || !inputItemsJson) return;

        const seleccionados = [];
        let totalVenta = 0;

        filasProductos.forEach(function (fila) {
            const inp = fila.querySelector(".js-uni-fila");
            const cant = Math.max(0, parseInt(inp ? inp.value : 0, 10) || 0);
            if (inp && String(cant) !== inp.value && inp.value !== "") {
                inp.value = String(cant);
            }

            if (cant > 0) {
                const id = parseInt(fila.getAttribute("data-id"), 10) || 0;
                const nombre = fila.getAttribute("data-nombre") || "";
                const precio = parseFloat(fila.getAttribute("data-precio")) || 0;
                const subtotal = cant * precio;
                totalVenta += subtotal;

                seleccionados.push({
                    id_producto: id,
                    nombre: nombre,
                    unidades: cant,
                    precio_venta: precio
                });
            }
        });

        inputItemsJson.value = JSON.stringify(seleccionados);
        if (totalVentaEl) {
            totalVentaEl.textContent = formatoCLP(totalVenta);
        }

        if (seleccionados.length === 0) {
            tbodyDetalle.innerHTML = `
                <tr id="filaDetalleVacia">
                    <td colspan="4" class="text-center text-muted" style="padding: 16px;">
                        Presiona <strong>+</strong> en algún producto para ver el detalle aquí al instante.
                    </td>
                </tr>
            `;
            return;
        }

        let html = "";
        seleccionados.forEach(function (item) {
            const sub = item.unidades * item.precio_venta;
            html += `
                <tr>
                    <td style="font-weight: 500;">${escapeHTML(item.nombre)}</td>
                    <td class="text-center"><strong>${item.unidades}</strong></td>
                    <td class="text-center">${formatoCLP(item.precio_venta)}</td>
                    <td class="text-center" style="color: #4d2c91; font-weight: 700;">${formatoCLP(sub)}</td>
                </tr>
            `;
        });
        tbodyDetalle.innerHTML = html;
    }

    actualizarDetalleTiempoReal();

    if (window.__tecnostockNuevoStockInit) return;
    window.__tecnostockNuevoStockInit = true;

    document.addEventListener("input", function (e) {
        if (!e.target) return;
        if (e.target.id === "buscadorProductosStock") {
            const q = e.target.value.toLowerCase().trim();
            const filasProductos = document.querySelectorAll("#tablaProductosStock tbody tr.fila-producto-stock");
            const filaSinResultados = document.getElementById("sinResultadosStock");
            let visibles = 0;

            filasProductos.forEach(function (fila) {
                const coincide = fila.textContent.toLowerCase().includes(q);
                fila.style.display = coincide ? "" : "none";
                if (coincide) visibles++;
            });

            if (filaSinResultados) {
                filaSinResultados.style.display = (visibles === 0 && filasProductos.length > 0) ? "" : "none";
            }
            return;
        }

        if (e.target.classList && e.target.classList.contains("js-uni-fila")) {
            actualizarDetalleTiempoReal();
        }
    });

    document.addEventListener("click", function (e) {
        const btnAbrirProd = e.target.closest ? e.target.closest("#btnAbrirModalNuevoProd") : null;
        if (btnAbrirProd) {
            e.preventDefault();
            abrirModalPorId("modalNuevoProducto");
            return;
        }

        const btnCerrarProd = e.target.closest ? e.target.closest("#btnCancelarModalNuevoProd, #btnCerrarModalNuevoProdX") : null;
        if (btnCerrarProd) {
            e.preventDefault();
            cerrarModalPorId("modalNuevoProducto");
            return;
        }

        const btnAbrirCat = e.target.closest ? e.target.closest("#btnAbrirModalNuevaCat") : null;
        if (btnAbrirCat) {
            e.preventDefault();
            abrirModalPorId("modalNuevaCategoria");
            return;
        }

        const btnCerrarCat = e.target.closest ? e.target.closest("#btnCancelarModalNuevaCat, #btnCerrarModalNuevaCatX") : null;
        if (btnCerrarCat) {
            e.preventDefault();
            cerrarModalPorId("modalNuevaCategoria");
            return;
        }

        const btnMenos = e.target.closest ? e.target.closest(".btn-restar-uni") : null;
        if (btnMenos) {
            const fila = btnMenos.closest("tr.fila-producto-stock");
            const inp = fila ? fila.querySelector(".js-uni-fila") : null;
            if (inp) {
                const actual = Math.max(0, parseInt(inp.value, 10) || 0);
                inp.value = String(Math.max(0, actual - 1));
                actualizarDetalleTiempoReal();
            }
            return;
        }

        const btnMas = e.target.closest ? e.target.closest(".btn-sumar-uni") : null;
        if (btnMas) {
            const fila = btnMas.closest("tr.fila-producto-stock");
            const inp = fila ? fila.querySelector(".js-uni-fila") : null;
            if (inp) {
                const actual = Math.max(0, parseInt(inp.value, 10) || 0);
                inp.value = String(actual + 1);
                actualizarDetalleTiempoReal();
            }
        }
    });

    document.addEventListener("submit", function (e) {
        if (!e.target) return;
        if (e.target.id === "formConfirmarIngreso") {
            actualizarDetalleTiempoReal();
            const inputItemsJson = document.getElementById("items_json");
            const items = JSON.parse((inputItemsJson && inputItemsJson.value) || "[]");
            if (!Array.isArray(items) || items.length === 0) {
                e.preventDefault();
                alert("Debes sumar unidades en al menos un producto antes de guardar.");
                return;
            }
            if (!confirm("¿Confirmas que deseas ingresar estas unidades al stock?")) {
                e.preventDefault();
            }
            return;
        }

        if (e.target.id === "formNuevoProducto") {
            if (!confirm("¿Confirmas que deseas crear este nuevo producto?")) {
                e.preventDefault();
            }
            return;
        }

        if (e.target.id === "formNuevaCategoria") {
            if (!confirm("¿Confirmas que deseas crear esta nueva categoría?")) {
                e.preventDefault();
            }
        }
    });
})();
</script>

<?php
$conexion->close();
include 'visual/footer.php';
?>