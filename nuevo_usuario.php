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

function obtenerColsUsr(mysqli $db, string $tabla): array {
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

function buscarColUsr(array $cols, array $candidatas): ?string {
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

function esCargoPrivilegiado(string $nombreCargo): bool {
    $norm = strtolower(trim($nombreCargo));
    return in_array($norm, ['dueño', 'dueno', 'owner', 'administrador', 'admin'], true)
        || stripos($norm, 'admin') !== false
        || stripos($norm, 'dueñ') !== false
        || stripos($norm, 'duen') !== false;
}

function esCargoDueno(string $nombreCargo): bool {
    $norm = strtolower(trim($nombreCargo));
    return in_array($norm, ['dueño', 'dueno', 'owner'], true)
        || stripos($norm, 'dueñ') !== false
        || stripos($norm, 'duen') !== false;
}

$colsUsuario = obtenerColsUsr($conexion, 'usuario');
$colUsrId = buscarColUsr($colsUsuario, ['id_usuario', 'id']) ?? 'id_usuario';
$colUsrNom = buscarColUsr($colsUsuario, ['nombre_usuario', 'usuario']) ?? 'nombre_usuario';
$colUsrPass = buscarColUsr($colsUsuario, ['contrasena_usuario', 'password_usuario', 'clave_usuario', 'contrasena', 'password', 'clave']);
$colUsrCargo = buscarColUsr($colsUsuario, ['id_cargo', 'cargo']);
$colUsrCorreo = buscarColUsr($colsUsuario, ['correo_usuario', 'email_usuario', 'correo', 'email']);
$colUsrTel = buscarColUsr($colsUsuario, ['telefono_usuario', 'fono_usuario', 'celular_usuario', 'telefono', 'fono', 'celular']);
$colUsrEstado = buscarColUsr($colsUsuario, ['estado_usuario', 'estado', 'activo']);

$colsCargo = obtenerColsUsr($conexion, 'cargo');
$colCargoId = buscarColUsr($colsCargo, ['id_cargo', 'id']) ?? 'id_cargo';
$colCargoNom = buscarColUsr($colsCargo, ['nombre_cargo', 'cargo', 'nombre']) ?? 'nombre_cargo';

$cargosPermitidos = [];
$cargosMapaPorId = [];
$resCargos = $conexion->query("SELECT `$colCargoId` AS id_cargo, `$colCargoNom` AS nombre_cargo FROM `cargo` ORDER BY `$colCargoNom` ASC");
if ($resCargos) {
    while ($c = $resCargos->fetch_assoc()) {
        $cid = (int)$c['id_cargo'];
        $cnom = (string)$c['nombre_cargo'];

        if (esCargoDueno($cnom)) {
            continue;
        }
        if (!$esDueno && esCargoPrivilegiado($cnom)) {
            continue;
        }

        $cargosPermitidos[] = $c;
        $cargosMapaPorId[$cid] = $cnom;
    }
    $resCargos->free();
}

$mensaje = "";
$tipoNotificacion = "ok";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreUsuario = trim((string)($_POST['nombre_usuario'] ?? ''));
    $correoUsr = trim((string)($_POST['correo_usr'] ?? ''));
    $telefonoUsr = trim((string)($_POST['telefono_usr'] ?? ''));
    $nuevaClave = (string)($_POST['contrasena'] ?? '');
    $confirmarClave = (string)($_POST['confirmar_contrasena'] ?? '');
    $nuevoCargoId = (int)($_POST['id_cargo'] ?? 0);

    if ($nombreUsuario === '') {
        $mensaje = "Debes ingresar el nombre de usuario.";
        $tipoNotificacion = "error";
    } elseif ($colUsrCorreo && $correoUsr === '') {
        $mensaje = "Debes ingresar el correo electrónico.";
        $tipoNotificacion = "error";
    } elseif ($nuevaClave === '' || $confirmarClave === '') {
        $mensaje = "Debes ingresar y confirmar la contraseña.";
        $tipoNotificacion = "error";
    } elseif (strlen($nuevaClave) < 4) {
        $mensaje = "La contraseña debe tener al menos 4 caracteres.";
        $tipoNotificacion = "error";
    } elseif ($nuevaClave !== $confirmarClave) {
        $mensaje = "Las contraseñas no coinciden.";
        $tipoNotificacion = "error";
    } elseif ($colUsrCargo && ($nuevoCargoId <= 0 || !isset($cargosMapaPorId[$nuevoCargoId]))) {
        $mensaje = "No tienes permisos para asignar ese cargo o el cargo seleccionado no es válido.";
        $tipoNotificacion = "error";
    } else {
        $stmtCheck = $conexion->prepare("SELECT `$colUsrId` FROM `usuario` WHERE LOWER(TRIM(`$colUsrNom`)) = LOWER(?) LIMIT 1");
        $stmtCheck->bind_param("s", $nombreUsuario);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $existe = ($resCheck && $resCheck->num_rows > 0);
        $stmtCheck->close();

        if ($existe) {
            $mensaje = "El nombre de usuario \"{$nombreUsuario}\" ya existe.";
            $tipoNotificacion = "error";
        } else {
            $conexion->begin_transaction();
            try {
                $campos = ["`$colUsrNom`"];
                $placeholders = ["?"];
                $tipos = "s";
                $valores = [$nombreUsuario];

                if ($colUsrCorreo) {
                    $campos[] = "`$colUsrCorreo`";
                    $placeholders[] = "?";
                    $tipos .= "s";
                    $valores[] = $correoUsr;
                }

                if ($colUsrTel) {
                    $campos[] = "`$colUsrTel`";
                    $placeholders[] = "?";
                    $tipos .= "s";
                    $valores[] = $telefonoUsr;
                }

                if ($colUsrPass) {
                    $campos[] = "`$colUsrPass`";
                    $placeholders[] = "?";
                    $tipos .= "s";
                    $valores[] = password_hash($nuevaClave, PASSWORD_DEFAULT);
                }

                if ($colUsrCargo) {
                    $campos[] = "`$colUsrCargo`";
                    $placeholders[] = "?";
                    $tipos .= "i";
                    $valores[] = $nuevoCargoId;
                }

                if ($colUsrEstado) {
                    $campos[] = "`$colUsrEstado`";
                    $placeholders[] = "?";
                    $tipos .= "i";
                    $valores[] = 1;
                }

                $sqlIns = "INSERT INTO `usuario` (" . implode(", ", $campos) . ") VALUES (" . implode(", ", $placeholders) . ")";
                $stmtIns = $conexion->prepare($sqlIns);
                $stmtIns->bind_param($tipos, ...$valores);
                $stmtIns->execute();
                $stmtIns->close();

                $conexion->commit();
                $cargoAsignadoTxt = $cargosMapaPorId[$nuevoCargoId] ?? 'Usuario';
                $mensaje = "Usuario \"{$nombreUsuario}\" ({$cargoAsignadoTxt}) creado correctamente.";
                $tipoNotificacion = "ok";
                $_POST = [];
            } catch (Throwable $e) {
                $conexion->rollback();
                $mensaje = "No se pudo crear el usuario: " . $e->getMessage();
                $tipoNotificacion = "error";
            }
        }
    }
}

include 'visual/header.php';
?>

<style>
    .usr-centrado-wrapper {
        max-width: 680px;
        margin: 0 auto;
    }
    .usr-titulo {
        color: #1f1b2e;
        font-weight: 700;
        margin: 0 0 4px 0;
    }
    .usr-subtitulo {
        color: #6f6785;
        font-size: 13.5px;
        margin: 0 0 18px 0;
    }
    .notif-usr-ok {
        background-color: #e8f7ee !important;
        border: 1px solid #b7e4c7 !important;
        color: #1e6038 !important;
        border-radius: 8px;
        font-weight: 600;
    }
    .notif-usr-error {
        background-color: #fde8ea !important;
        border: 1px solid #f5c2c7 !important;
        color: #842029 !important;
        border-radius: 8px;
        font-weight: 600;
    }
    .card-usr-morada {
        background-color: #ffffff;
        border: 1px solid #e7e1f5;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(92, 61, 166, 0.04);
    }
    .header-usr-morada {
        background-color: #faf8fe;
        border-bottom: 1px solid #ece6f7;
        padding: 13px 18px;
        font-weight: 600;
        color: #4d2c91;
    }
    .input-usr-suave {
        border-color: #dfd7f0;
        border-radius: 7px;
        height: 38px;
        box-shadow: none;
    }
    .input-usr-suave:focus {
        border-color: #7c52cc;
        box-shadow: 0 0 0 3px rgba(124, 82, 204, 0.12);
    }
    .btn-guardar-usr {
        background-color: #673ab7 !important;
        border-color: #5a32a8 !important;
        color: #ffffff !important;
        font-weight: 600;
        border-radius: 7px;
        padding: 8px 18px;
        outline: none !important;
    }
    .btn-guardar-usr:hover,
    .btn-guardar-usr:focus {
        background-color: #562e9e !important;
        border-color: #4a268a !important;
        outline: none !important;
        box-shadow: none !important;
    }
    .btn-volver-usr {
        background-color: #ffffff !important;
        border: 1px solid #dcd4ec !important;
        color: #3d3552 !important;
        font-weight: 600;
        border-radius: 7px;
        padding: 8px 16px;
        outline: none !important;
    }
    .btn-volver-usr:hover,
    .btn-volver-usr:focus {
        background-color: #f7f4fc !important;
        border-color: #c8bce3 !important;
        outline: none !important;
        box-shadow: none !important;
    }
</style>

<br>
<div class="usr-centrado-wrapper">
    <h3 class="usr-titulo"><i class="fa fa-user-plus" style="color: #673ab7; margin-right: 6px;"></i> Nuevo Usuario</h3>
    <p class="usr-subtitulo">
        <?php if ($esDueno): ?>
            Como <strong>Dueño</strong>, puedes crear cuentas de <strong>Administrador</strong> y de <strong>Usuario</strong>. Todos los campos son obligatorios excepto el teléfono.
        <?php else: ?>
            Como <strong>Administrador</strong>, puedes crear cuentas de <strong>Usuario</strong>. Todos los campos son obligatorios excepto el teléfono.
        <?php endif; ?>
    </p>

    <?php if ($mensaje !== ""): ?>
        <div class="alert <?php echo ($tipoNotificacion === 'ok') ? 'notif-usr-ok' : 'notif-usr-error'; ?>" style="margin-bottom: 16px;">
            <i class="fa <?php echo ($tipoNotificacion === 'ok') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>

    <div class="card-usr-morada">
        <div class="header-usr-morada">
            <i class="fa fa-id-card-o"></i> Datos de la cuenta
        </div>

        <div style="padding: 20px;">
            <form method="post" action="nuevo_usuario.php" autocomplete="off">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="nombre_usuario">Nombre de usuario *</label>
                            <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control input-usr-suave" required value="<?php echo htmlspecialchars((string)($_POST['nombre_usuario'] ?? '')); ?>" placeholder="Ej: Cristóbal Espinoza">
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="id_cargo">Cargo / Rol *</label>
                            <select name="id_cargo" id="id_cargo" class="form-control input-usr-suave" required>
                                <?php if (count($cargosPermitidos) > 1): ?>
                                    <option value="">Selecciona un cargo...</option>
                                <?php endif; ?>
                                <?php foreach ($cargosPermitidos as $cargo): ?>
                                    <?php $cid = (int)$cargo['id_cargo']; ?>
                                    <option value="<?php echo $cid; ?>" <?php echo ((int)($_POST['id_cargo'] ?? 0) === $cid || count($cargosPermitidos) === 1) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cargo['nombre_cargo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <?php if ($colUsrCorreo): ?>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="correo_usr">Correo electrónico *</label>
                                <input type="email" name="correo_usr" id="correo_usr" class="form-control input-usr-suave" required value="<?php echo htmlspecialchars((string)($_POST['correo_usr'] ?? '')); ?>" placeholder="usuario@tecnostock.cl">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="<?php echo $colUsrCorreo ? 'col-sm-6' : 'col-sm-12'; ?>">
                        <div class="form-group">
                            <label for="telefono_usr">Teléfono (opcional)</label>
                            <input type="text" name="telefono_usr" id="telefono_usr" class="form-control input-usr-suave" value="<?php echo htmlspecialchars((string)($_POST['telefono_usr'] ?? '')); ?>" placeholder="Ej: +56 9 1234 5678">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="contrasena">Contraseña *</label>
                            <input type="password" name="contrasena" id="contrasena" class="form-control input-usr-suave" required minlength="4" placeholder="Mínimo 4 caracteres">
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="confirmar_contrasena">Confirmar contraseña *</label>
                            <input type="password" name="confirmar_contrasena" id="confirmar_contrasena" class="form-control input-usr-suave" required minlength="4" placeholder="Repite la contraseña">
                        </div>
                    </div>
                </div>

                <div class="clearfix" style="margin-top: 12px;">
                    <a href="gestion_usuarios.php" class="btn btn-volver-usr" onmousedown="this.blur();">
                        <i class="fa fa-arrow-left"></i> Volver
                    </a>
                    <button type="submit" class="btn btn-guardar-usr pull-right" onmousedown="this.blur();">
                        <i class="fa fa-user-plus"></i> Crear usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$conexion->close();
include 'visual/footer.php';
?>