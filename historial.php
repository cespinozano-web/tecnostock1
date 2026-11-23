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
$conexion->set_charset("utf8mb4");

function obtenerTabla(mysqli $db, array $candidatas): ?string {
    foreach ($candidatas as $t) {
        $res = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
        if ($res && $res->num_rows > 0) {
            $res->free();
            return $t;
        }
    }
    return null;
}

function obtenerCols(mysqli $db, string $tabla): array {
    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM `$tabla`");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
        $res->free();
    }
    return $cols;
}

function buscarC(array $cols, array $patrones): ?string {
    foreach ($patrones as $p) {
        foreach ($cols as $c) {
            if (strcasecmp($c, $p) === 0) return $c;
        }
    }
    foreach ($patrones as $p) {
        foreach ($cols as $c) {
            if (stripos($c, $p) !== false) return $c;
        }
    }
    return null;
}

function limpiarNumHist($val): float {
    if (is_int($val) || is_float($val)) return (float)$val;
    if (!is_string($val)) return 0.0;
    $s = preg_replace('/[^0-9,.-]/', '', trim($val));
    if ($s === '') return 0.0;
    if (preg_match('/^\-?\d{1,3}(\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);
    } elseif (substr_count($s, '.') > 0 && substr_count($s, ',') > 0) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^\-?\d+,\d{1,2}$/', $s)) {
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float)$s : 0.0;
}

function resolverUsuarioHist(string $valorRaw, array $usuariosPorId, array $usuariosPorNombre): string {
    $v = trim($valorRaw);
    if ($v === '') {
        return 'Sistema';
    }
    if (isset($usuariosPorId[$v])) {
        return $usuariosPorId[$v];
    }
    $lower = strtolower($v);
    if (isset($usuariosPorNombre[$lower])) {
        return $usuariosPorNombre[$lower];
    }
    return $v;
}

$tablaMov = obtenerTabla($conexion, ['movimiento', 'movimientos']);
$colsMov = $tablaMov ? obtenerCols($conexion, $tablaMov) : [];
$colPk = buscarC($colsMov, ['id_movimiento', 'id']) ?? ($colsMov[0] ?? 'id_movimiento');
$colFec = buscarC($colsMov, ['fecha_hora_movimiento', 'fecha_hora', 'fecha']);
$colTip = buscarC($colsMov, ['tipo_movimiento', 'tipo']);
$colUni = buscarC($colsMov, ['unidades_movimiento', 'unidades']);
$colMon = buscarC($colsMov, ['cantidad_movimiento', 'monto_movimiento', 'monto']);
$colObs = buscarC($colsMov, ['observacion_movimiento', 'observacion', 'observaciones']);
$colPrd = buscarC($colsMov, ['id_producto', 'producto']);
$colUsr = buscarC($colsMov, ['nombre_usuario', 'usuario', 'id_usuario']);
$colEst = buscarC($colsMov, ['estado_movimiento', 'estado']);

$usuariosPorId = [];
$usuariosPorNombre = [];
$tablaUsr = obtenerTabla($conexion, ['usuario', 'usuarios']);
if ($tablaUsr) {
    $colsU = obtenerCols($conexion, $tablaUsr);
    $cUid = buscarC($colsU, ['id_usuario', 'id']);
    $cUnom = buscarC($colsU, ['nombre_usuario', 'nombre']);
    if ($cUid && $cUnom) {
        $rU = $conexion->query("SELECT `$cUid`, `$cUnom` FROM `$tablaUsr`");
        if ($rU) {
            while ($u = $rU->fetch_assoc()) {
                $uidStr = trim((string)$u[$cUid]);
                $unomStr = trim((string)$u[$cUnom]);
                if ($unomStr !== '') {
                    if ($uidStr !== '') {
                        $usuariosPorId[$uidStr] = $unomStr;
                    }
                    $usuariosPorNombre[strtolower($unomStr)] = $unomStr;
                }
            }
            $rU->free();
        }
    }
}

$nombreUsuarioSesion = trim((string)($_SESSION['nombre_usuario'] ?? ''));
$idUsuarioSesion = isset($_SESSION['id_usuario']) ? trim((string)$_SESSION['id_usuario']) : '';
if ($idUsuarioSesion === '' && $nombreUsuarioSesion !== '') {
    $foundId = array_search(strtolower($nombreUsuarioSesion), array_map('strtolower', $usuariosPorId), true);
    if ($foundId !== false) {
        $idUsuarioSesion = (string)$foundId;
    }
}

$productosMap = [];
$preciosMap = [];
$tablaPrd = obtenerTabla($conexion, ['producto', 'productos']);
if ($tablaPrd) {
    $colsP = obtenerCols($conexion, $tablaPrd);
    $cPid = buscarC($colsP, ['id_producto', 'id']);
    $cPnom = buscarC($colsP, ['nombre_producto', 'nombre']);
    $cPpre = buscarC($colsP, ['precio_producto', 'precio']);
    if ($cPid && $cPnom) {
        $selP = "`$cPid`, `$cPnom`" . ($cPpre ? ", `$cPpre`" : "");
        $rP = $conexion->query("SELECT $selP FROM `$tablaPrd`");
        if ($rP) {
            while ($p = $rP->fetch_assoc()) {
                $k = trim((string)$p[$cPid]);
                $productosMap[$k] = (string)$p[$cPnom];
                if ($cPpre && isset($p[$cPpre])) {
                    $preciosMap[$k] = limpiarNumHist($p[$cPpre]);
                }
            }
            $rP->free();
        }
    }
}

$filasHist = [];
if ($tablaMov && $colPk) {
    $rM = $conexion->query("SELECT * FROM `$tablaMov` ORDER BY `$colPk` DESC");
    if ($rM) {
        while ($row = $rM->fetch_assoc()) {
            $tipoBase = $colTip ? trim((string)($row[$colTip] ?? 'Venta')) : 'Venta';
            $obsRaw = $colObs ? trim((string)($row[$colObs] ?? '')) : '';

            $activo = true;
            if ($colEst && isset($row[$colEst])) {
                $vE = $row[$colEst];
                if (is_string($vE) && in_array(strtolower(trim($vE)), ['0', 'false', 'inactivo', 'anulado', 'eliminado', 'no'], true)) {
                    $activo = false;
                } else {
                    $activo = ((int)$vE !== 0);
                }
            }

            $esEliminado = (!$activo) || (stripos($obsRaw, 'ELIMINADO POR') !== false);
            $esEditado = (!$esEliminado) && (stripos($obsRaw, 'EDITADO POR') !== false);
            $esIngreso = (strcasecmp($tipoBase, 'Ingreso') === 0 || stripos($tipoBase, 'ingreso') !== false);

            $baseEtiqueta = $esIngreso ? 'Ingreso' : 'Venta';
            $usrColVal = $colUsr ? trim((string)($row[$colUsr] ?? '')) : '';

            if ($esEliminado) {
                $categoriaHist = $baseEtiqueta . '/Eliminado';
                if (preg_match('/ELIMINADO\s+POR\s*:\s*([^\r\n<]+)/i', $obsRaw, $mDel)) {
                    $actor = resolverUsuarioHist($mDel[1], $usuariosPorId, $usuariosPorNombre);
                } else {
                    $actor = resolverUsuarioHist($usrColVal, $usuariosPorId, $usuariosPorNombre);
                }
            } elseif ($esEditado) {
                $categoriaHist = $baseEtiqueta . '/Editado';
                if (preg_match('/EDITADO\s+POR\s*:\s*([^\r\n<]+)/i', $obsRaw, $mEd)) {
                    $actor = resolverUsuarioHist($mEd[1], $usuariosPorId, $usuariosPorNombre);
                } else {
                    $actor = resolverUsuarioHist($usrColVal, $usuariosPorId, $usuariosPorNombre);
                }
            } else {
                $categoriaHist = $baseEtiqueta;
                $actor = resolverUsuarioHist($usrColVal, $usuariosPorId, $usuariosPorNombre);
            }

            // Filtrar estrictamente: solo mostrar movimientos del usuario actual
            $esUsuarioActual = (strcasecmp($actor, $nombreUsuarioSesion) === 0);
            if (!$esUsuarioActual && $idUsuarioSesion !== '' && $usrColVal === $idUsuarioSesion && !$esEliminado && !$esEditado) {
                $esUsuarioActual = true;
            }
            if (!$esUsuarioActual) {
                continue;
            }

            $pid = ($colPrd && isset($row[$colPrd])) ? trim((string)$row[$colPrd]) : '';
            $prodNombre = ($pid !== '' && isset($productosMap[$pid])) ? $productosMap[$pid] : ($pid !== '' ? $pid : '-');
            $precioU = ($pid !== '' && isset($preciosMap[$pid])) ? $preciosMap[$pid] : 0.0;
            $monto = ($colMon && isset($row[$colMon])) ? limpiarNumHist($row[$colMon]) : 0.0;

            if ($colUni && isset($row[$colUni]) && trim((string)$row[$colUni]) !== '') {
                $unidades = max(0, (int)limpiarNumHist($row[$colUni]));
            } elseif ($precioU > 0 && $monto > 0) {
                $unidades = max(1, (int)round($monto / $precioU));
            } else {
                $unidades = 1;
            }

            if ($monto <= 0 && $precioU > 0 && $unidades > 0) {
                $monto = $precioU * $unidades;
            }

            $filasHist[] = [
                'fecha' => ($colFec && trim((string)($row[$colFec] ?? '')) !== '') ? trim((string)$row[$colFec]) : '-',
                'categoria' => $categoriaHist,
                'producto' => $prodNombre,
                'unidades' => $unidades,
                'monto' => '$' . number_format($monto, 0, ',', '.'),
                'actor' => $actor,
            ];
        }
        $rM->free();
    }
}

include 'visual/header.php';
?>

<style>
    .hist-titulo { color: #1f1b2e; font-weight: 700; margin: 0 0 4px 0; }
    .hist-sub { color: #6f6785; font-size: 13.5px; margin: 0 0 18px 0; }
    .card-hist { background: #fff; border: 1px solid #e7e1f5; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(92, 61, 166, 0.04); }
    .toolbar-hist { background: #faf8fe; border-bottom: 1px solid #ece6f7; padding: 13px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
    .tabla-hist > thead > tr > th { background: #f5f1fc; color: #4d2c91; border-bottom: 2px solid #dfd5f5 !important; border-top: none !important; }
    .tabla-hist > tbody > tr > td { border-color: #efeaf9 !important; vertical-align: middle; }
    .badge-ingreso { background: #e8f7ee; color: #1e6038; border: 1px solid #b7e4c7; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
    .badge-venta { background: #ede4fc; color: #5a3e99; border: 1px solid #d7c5f7; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
    .badge-editado { background: #fff6e0; color: #7a5800; border: 1px solid #f2dc9b; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
    .badge-eliminado { background: #fde8ea; color: #842029; border: 1px solid #f5c2c7; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
</style>

<br>
<h3 class="hist-titulo"><i class="fa fa-history" style="color: #673ab7; margin-right: 6px;"></i> Historial de Movimientos</h3>
<p class="hist-sub">Registro categorizado de tus Ingresos, Ventas, Ediciones y Eliminaciones (<strong><?php echo htmlspecialchars($nombreUsuarioSesion); ?></strong>).</p>

<div class="card-hist">
    <div class="toolbar-hist">
        <div class="btn-group" id="filtrosCategoriaHist">
            <button type="button" class="btn btn-xs btn-default active js-filtro-cat" data-cat="">Todos</button>
            <button type="button" class="btn btn-xs btn-default js-filtro-cat" data-cat="Ingreso">Ingreso</button>
            <button type="button" class="btn btn-xs btn-default js-filtro-cat" data-cat="Ingreso/Editado">Ingreso/Editado</button>
            <button type="button" class="btn btn-xs btn-default js-filtro-cat" data-cat="Ingreso/Eliminado">Ingreso/Eliminado</button>
            <button type="button" class="btn btn-xs btn-default js-filtro-cat" data-cat="Venta">Venta</button>
            <button type="button" class="btn btn-xs btn-default js-filtro-cat" data-cat="Venta/Editado">Venta/Editado</button>
            <button type="button" class="btn btn-xs btn-default js-filtro-cat" data-cat="Venta/Eliminado">Venta/Eliminado</button>
        </div>

        <input type="text" id="buscadorHistorial" class="form-control" style="width: 260px; max-width: 100%; border-radius: 7px;" placeholder="Buscar producto o tipo..." autocomplete="off">
    </div>

    <div class="table-responsive">
        <table class="table table-bordered tabla-hist" id="tablaHistorial" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Tipo</th>
                    <th>Producto</th>
                    <th class="text-center">Cantidad</th>
                    <th>Monto</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($filasHist)): ?>
                    <?php foreach ($filasHist as $fh): ?>
                        <?php
                        $cat = $fh['categoria'];
                        if ($cat === 'Ingreso') {
                            $badgeClass = 'badge-ingreso';
                        } elseif (strpos($cat, 'Eliminado') !== false) {
                            $badgeClass = 'badge-eliminado';
                        } elseif (strpos($cat, 'Editado') !== false) {
                            $badgeClass = 'badge-editado';
                        } else {
                            $badgeClass = 'badge-venta';
                        }
                        ?>
                        <tr class="fila-hist" data-cat="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>">
                            <td><?php echo htmlspecialchars($fh['fecha']); ?></td>
                            <td><span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($cat); ?></span></td>
                            <td><?php echo htmlspecialchars($fh['producto']); ?></td>
                            <td class="text-center"><strong><?php echo (int)$fh['unidades']; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($fh['monto']); ?></strong></td>
                            <td><?php echo htmlspecialchars($fh['actor']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding: 20px;">No tienes movimientos registrados en el historial.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function iniciarHistorial() {
    if (window.__tecnostockHistorialInit) return;
    window.__tecnostockHistorialInit = true;

    let catActiva = "";

    function aplicarFiltros() {
        const buscador = document.getElementById("buscadorHistorial");
        const filas = document.querySelectorAll("#tablaHistorial tbody tr.fila-hist");
        const q = buscador ? buscador.value.toLowerCase().trim() : "";

        filas.forEach(function (fila) {
            const filaCat = fila.getAttribute("data-cat") || "";
            const coincideCat = (catActiva === "" || filaCat === catActiva);
            const coincideTexto = (q === "" || fila.textContent.toLowerCase().includes(q));
            fila.style.display = (coincideCat && coincideTexto) ? "" : "none";
        });
    }

    document.addEventListener("click", function (e) {
        const btn = e.target.closest ? e.target.closest(".js-filtro-cat") : null;
        if (!btn) return;
        document.querySelectorAll(".js-filtro-cat").forEach(function (b) { b.classList.remove("active"); });
        btn.classList.add("active");
        catActiva = btn.getAttribute("data-cat") || "";
        aplicarFiltros();
    });

    document.addEventListener("input", function (e) {
        if (e.target && e.target.id === "buscadorHistorial") {
            aplicarFiltros();
        }
    });
})();
</script>

<?php
$conexion->close();
include 'visual/footer.php';
?>