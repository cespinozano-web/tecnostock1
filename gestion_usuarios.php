<?php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
    header("location: login.html");
    exit();
}

$cargoActual = strtolower(trim((string)($_SESSION['nombre_cargo'] ?? '')));
$esDueno = in_array($cargoActual, ['dueño', 'dueno', 'owner'], true);
$esAdmin = in_array($cargoActual, ['administrador', 'admin'], true);

if (!$esDueno && !$esAdmin) {
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

function obtenerColsGU(mysqli $db, string $tabla): array {
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

function buscarColGU(array $cols, array $candidatas): ?string {
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

function esCargoDuenoGU(string $nombreCargo): bool {
    $norm = strtolower(trim($nombreCargo));
    return in_array($norm, ['dueño', 'dueno', 'owner'], true)
        || stripos($norm, 'dueñ') !== false
        || stripos($norm, 'duen') !== false;
}

function esCargoAdminGU(string $nombreCargo): bool {
    $norm = strtolower(trim($nombreCargo));
    return in_array($norm, ['administrador', 'admin'], true)
        || stripos($norm, 'admin') !== false;
}

function esCargoUsuarioNormal(string $nombreCargo): bool {
    $norm = strtolower(trim($nombreCargo));
    if ($norm === '' || $norm === 'usuario' || $norm === 'usuarios') {
        return true;
    }
    return !esCargoDuenoGU($norm) && !esCargoAdminGU($norm);
}

function puedeGestionarObjetivoGU(bool $esDueno, bool $esAdmin, string $cargoObjetivo): bool {
    if (esCargoDuenoGU($cargoObjetivo)) {
        return false;
    }
    if ($esDueno) {
        return esCargoUsuarioNormal($cargoObjetivo) || esCargoAdminGU($cargoObjetivo);
    }
    if ($esAdmin) {
        return esCargoUsuarioNormal($cargoObjetivo);
    }
    return false;
}

$colsUsuario = obtenerColsGU($conexion, 'usuario');
$colUsrId = buscarColGU($colsUsuario, ['id_usuario', 'id']) ?? 'id_usuario';
$colUsrNom = buscarColGU($colsUsuario, ['nombre_usuario', 'usuario']) ?? 'nombre_usuario';
$colUsrCargo = buscarColGU($colsUsuario, ['id_cargo', 'cargo']);
$colUsrCorreo = buscarColGU($colsUsuario, ['correo_usuario', 'email_usuario', 'correo', 'email']);
$colUsrTel = buscarColGU($colsUsuario, ['telefono_usuario', 'fono_usuario', 'celular_usuario', 'telefono', 'fono', 'celular']);
$colUsrEstado = buscarColGU($colsUsuario, ['estado_usuario', 'estado', 'activo']);

$colsCargo = obtenerColsGU($conexion, 'cargo');
$colCargoId = buscarColGU($colsCargo, ['id_cargo', 'id']) ?? 'id_cargo';
$colCargoNom = buscarColGU($colsCargo, ['nombre_cargo', 'cargo', 'nombre']) ?? 'nombre_cargo';

$mensaje = "";
$tipoNotificacion = "ok";

// Alternar estado activo/inactivo según permisos del rol en sesión y cargo del objetivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'toggle_estado_usuario') {
    $targetId = (int)($_POST['id_usuario'] ?? 0);
    $usuarioSesionNombre = trim((string)($_SESSION['nombre_usuario'] ?? ''));

    if (!$colUsrEstado) {
        $mensaje = "La tabla usuario no tiene columna de estado configurada.";
        $tipoNotificacion = "error";
    } elseif ($targetId <= 0) {
        $mensaje = "Usuario no válido.";
        $tipoNotificacion = "error";
    } else {
        if ($colUsrCargo) {
            $sqlTarget = "SELECT u.`$colUsrId` AS id_u, u.`$colUsrNom` AS nom_u, u.`$colUsrEstado` AS est_u, c.`$colCargoNom` AS nom_c
                          FROM `usuario` u
                          LEFT JOIN `cargo` c ON u.`$colUsrCargo` = c.`$colCargoId`
                          WHERE u.`$colUsrId` = ? LIMIT 1";
        } else {
            $sqlTarget = "SELECT `$colUsrId` AS id_u, `$colUsrNom` AS nom_u, `$colUsrEstado` AS est_u, 'Usuario' AS nom_c
                          FROM `usuario`
                          WHERE `$colUsrId` = ? LIMIT 1";
        }

        $stmtT = $conexion->prepare($sqlTarget);
        $stmtT->bind_param("i", $targetId);
        $stmtT->execute();
        $resT = $stmtT->get_result();
        $filaTarget = $resT ? $resT->fetch_assoc() : null;
        $stmtT->close();

        if (!$filaTarget) {
            $mensaje = "No se encontró el usuario seleccionado.";
            $tipoNotificacion = "error";
        } elseif (strcasecmp(trim((string)$filaTarget['nom_u']), $usuarioSesionNombre) === 0) {
            $mensaje = "No puedes cambiar el estado de tu propia cuenta.";
            $tipoNotificacion = "error";
        } elseif (!puedeGestionarObjetivoGU($esDueno, $esAdmin, (string)($filaTarget['nom_c'] ?? ''))) {
            $mensaje = $esDueno
                ? "No puedes cambiar el estado de una cuenta con cargo de Dueño."
                : "Como Administrador, solo puedes activar o desactivar usuarios con cargo de Usuario.";
            $tipoNotificacion = "error";
        } else {
            $estadoActual = ((int)($filaTarget['est_u'] ?? 1) !== 0) ? 1 : 0;
            $nuevoEstado = ($estadoActual === 1) ? 0 : 1;

            $stmtUpd = $conexion->prepare("UPDATE `usuario` SET `$colUsrEstado` = ? WHERE `$colUsrId` = ?");
            $stmtUpd->bind_param("ii", $nuevoEstado, $targetId);
            if ($stmtUpd->execute()) {
                $estadoTexto = ($nuevoEstado === 1) ? "activado" : "desactivado";
                $mensaje = "Usuario \"{$filaTarget['nom_u']}\" {$estadoTexto} correctamente.";
                $tipoNotificacion = "ok";
            } else {
                $mensaje = "No se pudo actualizar el estado del usuario.";
                $tipoNotificacion = "error";
            }
            $stmtUpd->close();
        }
    }
}

$usuariosLista = [];
$selCorreo = $colUsrCorreo ? "u.`$colUsrCorreo`" : "''";
$selTel = $colUsrTel ? "u.`$colUsrTel`" : "''";
$selEst = $colUsrEstado ? "u.`$colUsrEstado`" : "1";

if ($colUsrCargo) {
    $sqlLista = "SELECT u.`$colUsrId` AS id_usuario,
                        u.`$colUsrNom` AS nombre_usuario,
                        {$selCorreo} AS correo_usuario,
                        {$selTel} AS telefono_usuario,
                        {$selEst} AS estado_usuario,
                        COALESCE(c.`$colCargoNom`, 'Usuario') AS nombre_cargo
                 FROM `usuario` u
                 LEFT JOIN `cargo` c ON u.`$colUsrCargo` = c.`$colCargoId`
                 ORDER BY u.`$colUsrNom` ASC";
} else {
    $sqlLista = "SELECT `$colUsrId` AS id_usuario,
                        `$colUsrNom` AS nombre_usuario,
                        " . ($colUsrCorreo ? "`$colUsrCorreo`" : "''") . " AS correo_usuario,
                        " . ($colUsrTel ? "`$colUsrTel`" : "''") . " AS telefono_usuario,
                        " . ($colUsrEstado ? "`$colUsrEstado`" : "1") . " AS estado_usuario,
                        'Usuario' AS nombre_cargo
                 FROM `usuario`
                 ORDER BY `$colUsrNom` ASC";
}

$resLista = $conexion->query($sqlLista);
if ($resLista) {
    while ($u = $resLista->fetch_assoc()) {
        $usuariosLista[] = [
            'id' => (int)$u['id_usuario'],
            'nombre' => (string)($u['nombre_usuario'] ?? ''),
            'correo' => (string)($u['correo_usuario'] ?? ''),
            'telefono' => (string)($u['telefono_usuario'] ?? ''),
            'cargo' => (string)($u['nombre_cargo'] ?? 'Usuario'),
            'activo' => ((int)($u['estado_usuario'] ?? 1) !== 0),
        ];
    }
    $resLista->free();
}

$usuarioSesionActual = trim((string)($_SESSION['nombre_usuario'] ?? ''));

include 'visual/header.php';
?>

<style>
    .gu-titulo { color: #1f1b2e; font-weight: 700; margin: 0 0 4px 0; }
    .gu-subtitulo { color: #6f6785; font-size: 13.5px; margin: 0; }
    .notif-gu-ok { background-color: #e8f7ee !important; border: 1px solid #b7e4c7 !important; color: #1e6038 !important; border-radius: 8px; font-weight: 600; }
    .notif-gu-error { background-color: #fde8ea !important; border: 1px solid #f5c2c7 !important; color: #842029 !important; border-radius: 8px; font-weight: 600; }
    .btn-gu-nuevo { background-color: #673ab7 !important; border-color: #5a32a8 !important; color: #ffffff !important; font-weight: 600; border-radius: 7px; padding: 8px 16px; outline: none !important; }
    .btn-gu-nuevo:hover, .btn-gu-nuevo:focus { background-color: #562e9e !important; border-color: #4a268a !important; color: #ffffff !important; outline: none !important; box-shadow: none !important; }
    .card-gu-morada { background-color: #ffffff; border: 1px solid #e7e1f5; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(92, 61, 166, 0.04); }
    .toolbar-gu-morada { background-color: #faf8fe; border-bottom: 1px solid #ece6f7; padding: 13px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
    .buscador-gu .input-group-addon { background-color: #f5f1fc; border-color: #dfd7f0; color: #673ab7; border-radius: 7px 0 0 7px; }
    .buscador-gu .form-control { border-color: #dfd7f0; border-radius: 0 7px 7px 0; height: 36px; box-shadow: none; }
    .buscador-gu .form-control:focus { border-color: #7c52cc; box-shadow: 0 0 0 3px rgba(124, 82, 204, 0.12); }
    .tabla-gu-morada { margin-bottom: 0 !important; }
    .tabla-gu-morada > thead > tr > th { background-color: #f5f1fc; color: #4d2c91; border-bottom: 2px solid #dfd5f5 !important; border-top: none !important; border-color: #ece6f7; font-weight: 600; padding: 11px 13px; }
    .tabla-gu-morada > tbody > tr > td { border-color: #efeaf9 !important; padding: 10px 13px; vertical-align: middle; color: #2b253b; }
    .badge-estado-activo { background-color: #e8f7ee; color: #1e6038; border: 1px solid #b7e4c7; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 12px; }
    .badge-estado-inactivo { background-color: #fde8ea; color: #842029; border: 1px solid #f5c2c7; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 12px; }
    .btn-toggle-desactivar { background-color: #fde8ea !important; border: 1px solid #f5c2c7 !important; color: #842029 !important; font-weight: 600; border-radius: 6px; padding: 4px 10px; font-size: 12px; outline: none !important; }
    .btn-toggle-desactivar:hover, .btn-toggle-desactivar:focus { background-color: #fad2d6 !important; color: #66161d !important; outline: none !important; box-shadow: none !important; }
    .btn-toggle-activar { background-color: #e8f7ee !important; border: 1px solid #b7e4c7 !important; color: #1e6038 !important; font-weight: 600; border-radius: 6px; padding: 4px 10px; font-size: 12px; outline: none !important; }
    .btn-toggle-activar:hover, .btn-toggle-activar:focus { background-color: #d4f0df !important; color: #144628 !important; outline: none !important; box-shadow: none !important; }
</style>

<br>
<div class="clearfix" style="margin-bottom: 16px;">
    <div class="pull-left">
        <h3 class="gu-titulo"><i class="fa fa-users" style="color: #673ab7; margin-right: 6px;"></i> Gestión de Usuarios</h3>
        <p class="gu-subtitulo">
            <?php if ($esDueno): ?>
                Listado general de usuarios activos y no activos. Como <strong>Dueño</strong> puedes activar/desactivar a <strong>Usuarios</strong> y <strong>Administradores</strong>.
            <?php else: ?>
                Listado general de usuarios activos y no activos. Como <strong>Administrador</strong> puedes activar/desactivar a <strong>Usuarios</strong>.
            <?php endif; ?>
        </p>
    </div>
    <div class="pull-right" style="margin-top: 4px;">
        <a href="nuevo_usuario.php" class="btn btn-gu-nuevo" onmousedown="this.blur();">
            <i class="fa fa-user-plus"></i> Nuevo usuario
        </a>
    </div>
</div>

<?php if ($mensaje !== ""): ?>
    <div class="alert <?php echo ($tipoNotificacion === 'ok') ? 'notif-gu-ok' : 'notif-gu-error'; ?>" style="margin-bottom: 16px;">
        <i class="fa <?php echo ($tipoNotificacion === 'ok') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($mensaje); ?>
    </div>
<?php endif; ?>

<div class="card-gu-morada">
    <div class="toolbar-gu-morada">
        <span style="font-weight: 600; color: #4d2c91;">
            <i class="fa fa-list"></i> Usuarios registrados (<?php echo count($usuariosLista); ?>)
        </span>

        <div class="input-group buscador-gu" style="width: 300px; max-width: 100%;">
            <span class="input-group-addon"><i class="fa fa-search"></i></span>
            <input type="text" id="buscadorUsuarios" class="form-control" placeholder="Buscar usuario, correo, cargo o estado..." autocomplete="off">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered tabla-gu-morada" id="tablaGestionUsuarios">
            <thead>
                <tr>
                    <th>Nombre de usuario</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th class="text-center">Cargo</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($usuariosLista)): ?>
                    <?php foreach ($usuariosLista as $u): ?>
                        <?php
                        $esMismaCuenta = (strcasecmp(trim($u['nombre']), $usuarioSesionActual) === 0);
                        $puedeGestionarEstado = (!$esMismaCuenta) && puedeGestionarObjetivoGU($esDueno, $esAdmin, $u['cargo']);
                        ?>
                        <tr class="fila-usuario-gu">
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($u['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($u['correo'] !== '' ? $u['correo'] : '-'); ?></td>
                            <td><?php echo htmlspecialchars($u['telefono'] !== '' ? $u['telefono'] : '-'); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($u['cargo']); ?></td>
                            <td class="text-center">
                                <?php if ($u['activo']): ?>
                                    <span class="badge-estado-activo">Activo</span>
                                <?php else: ?>
                                    <span class="badge-estado-inactivo">No activo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($puedeGestionarEstado): ?>
                                    <form method="post" action="gestion_usuarios.php" style="display: inline; margin: 0;">
                                        <input type="hidden" name="accion" value="toggle_estado_usuario">
                                        <input type="hidden" name="id_usuario" value="<?php echo (int)$u['id']; ?>">
                                        <?php if ($u['activo']): ?>
                                            <button type="submit" class="btn btn-toggle-desactivar" onmousedown="this.blur();" onclick="return confirm('¿Desactivar a <?php echo htmlspecialchars(addslashes($u['nombre'])); ?>?');">
                                                <i class="fa fa-ban"></i> Desactivar
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-toggle-activar" onmousedown="this.blur();" onclick="return confirm('¿Activar a <?php echo htmlspecialchars(addslashes($u['nombre'])); ?>?');">
                                                <i class="fa fa-check"></i> Activar
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 12px;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="sinResultadosUsuarios" style="display: none;">
                        <td colspan="6" class="text-center text-muted">No se encontraron usuarios que coincidan con la búsqueda.</td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding: 20px;">No hay usuarios registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function iniciarGestionUsuarios() {
    if (window.__tecnostockGestionUsuariosInit) return;
    window.__tecnostockGestionUsuariosInit = true;

    document.addEventListener("input", function (e) {
        if (!e.target || e.target.id !== "buscadorUsuarios") return;
        const q = e.target.value.toLowerCase().trim();
        const filas = document.querySelectorAll("#tablaGestionUsuarios tbody tr.fila-usuario-gu");
        const filaVacia = document.getElementById("sinResultadosUsuarios");
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