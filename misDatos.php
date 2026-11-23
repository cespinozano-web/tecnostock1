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

$id_usuario_actual = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
if ($id_usuario_actual <= 0) {
    $stmtId = $conexion->prepare("SELECT id_usuario FROM usuario WHERE nombre_usuario = ? LIMIT 1");
    $stmtId->bind_param("s", $_SESSION['nombre_usuario']);
    $stmtId->execute();
    $resId = $stmtId->get_result();
    if ($filaId = $resId->fetch_assoc()) {
        $id_usuario_actual = (int)$filaId['id_usuario'];
        $_SESSION['id_usuario'] = $id_usuario_actual;
    }
    $stmtId->close();
}

$mensaje_tipo = "";
$mensaje_texto = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $nuevo_nombre = trim($_POST['nombre_usuario'] ?? '');
    $nuevo_correo = trim($_POST['correo_usuario'] ?? '');
    $nuevo_telefono = trim($_POST['telefono_usuario'] ?? '');
    $nueva_clave = $_POST['clave_usuario'] ?? '';

    if ($nuevo_nombre === '' || $nuevo_correo === '') {
        $mensaje_tipo = "error";
        $mensaje_texto = "El nombre y el correo son obligatorios.";
    } elseif (strlen($nuevo_nombre) > 80 || strlen($nuevo_correo) > 30 || strlen($nuevo_telefono) > 20 || strlen($nueva_clave) > 100) {
        $mensaje_tipo = "error";
        $mensaje_texto = "Uno o más campos exceden el largo permitido.";
    } else {
        $stmtCheck = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo_usuario = ? AND id_usuario != ? LIMIT 1");
        $stmtCheck->bind_param("si", $nuevo_correo, $id_usuario_actual);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();

        if ($resCheck->num_rows > 0) {
            $mensaje_tipo = "error";
            $mensaje_texto = "Ese correo ya está registrado por otro usuario.";
            $stmtCheck->close();
        } else {
            $stmtCheck->close();

            if (trim($nueva_clave) !== '') {
                $stmtUpd = $conexion->prepare("UPDATE usuario SET nombre_usuario = ?, correo_usuario = ?, telefono_usuario = ?, clave_usuario = ? WHERE id_usuario = ?");
                $stmtUpd->bind_param("ssssi", $nuevo_nombre, $nuevo_correo, $nuevo_telefono, $nueva_clave, $id_usuario_actual);
            } else {
                $stmtUpd = $conexion->prepare("UPDATE usuario SET nombre_usuario = ?, correo_usuario = ?, telefono_usuario = ? WHERE id_usuario = ?");
                $stmtUpd->bind_param("sssi", $nuevo_nombre, $nuevo_correo, $nuevo_telefono, $id_usuario_actual);
            }

            if ($stmtUpd->execute()) {
                $_SESSION['nombre_usuario'] = $nuevo_nombre;
                $mensaje_tipo = "exito";
                $mensaje_texto = "Datos actualizados correctamente.";
            } else {
                $mensaje_tipo = "error";
                $mensaje_texto = "No se pudieron guardar los cambios.";
            }
            $stmtUpd->close();
        }
    }
}

$sql = "SELECT u.id_usuario, u.nombre_usuario, u.correo_usuario, u.estado_usuario, u.telefono_usuario, c.nombre_cargo, c.descripcion_cargo
        FROM usuario u
        INNER JOIN cargo c ON u.id_cargo = c.id_cargo
        WHERE u.id_usuario = ?
        LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario_actual);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

$iniciales = "US";
if ($usuario && !empty($usuario['nombre_usuario'])) {
    $partes = preg_split('/\s+/', trim($usuario['nombre_usuario']));
    if (count($partes) >= 2) {
        $iniciales = strtoupper(mb_substr($partes[0], 0, 1, 'UTF-8') . mb_substr($partes[1], 0, 1, 'UTF-8'));
    } else {
        $iniciales = strtoupper(mb_substr($partes[0], 0, 2, 'UTF-8'));
    }
}

include 'visual/header.php';
?>

<style>
    .perfil-titulo {
        color: #1f1b2e;
        font-weight: 700;
        margin: 0 0 4px 0;
    }

    .perfil-subtitulo {
        color: #6f6785;
        margin: 0 0 20px 0;
        font-size: 14px;
    }

    .card-morada-suave {
        background-color: #ffffff;
        border: 1px solid #e7e1f5;
        border-radius: 10px;
        padding: 22px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(92, 61, 166, 0.04);
    }

    .avatar-circulo-morado {
        width: 84px;
        height: 84px;
        border-radius: 50%;
        background-color: #673ab7;
        color: #ffffff;
        font-size: 28px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 14px auto;
        letter-spacing: 1px;
    }

    .badge-cargo-suave {
        display: inline-block;
        background-color: #f1ebfc;
        color: #5e35b1;
        border: 1px solid #dfd3f7;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 20px;
        margin-bottom: 16px;
    }

    .badge-estado-activo {
        display: inline-block;
        background-color: #e6f7ed;
        color: #1e7e46;
        border: 1px solid #c3ebd3;
        font-size: 12px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
    }

    .badge-estado-inactivo {
        display: inline-block;
        background-color: #fdebec;
        color: #a62639;
        border: 1px solid #f7c9ce;
        font-size: 12px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
    }

    .perfil-meta-item {
        display: flex;
        align-items: flex-start;
        padding: 12px 0;
        border-top: 1px solid #f1edf9;
    }

    .perfil-meta-item i {
        color: #7c52cc;
        width: 22px;
        margin-top: 3px;
        font-size: 14px;
    }

    .perfil-meta-label {
        font-size: 12px;
        color: #7a7291;
        display: block;
    }

    .perfil-meta-valor {
        font-size: 13.5px;
        color: #2b253b;
        font-weight: 600;
        word-break: break-word;
    }

    .seccion-form-titulo {
        color: #2b253b;
        font-weight: 700;
        font-size: 16px;
        margin: 0 0 2px 0;
    }

    .seccion-form-sub {
        color: #7a7291;
        font-size: 13px;
        margin: 0 0 18px 0;
        padding-bottom: 14px;
        border-bottom: 1px solid #f1edf9;
    }

    .form-label-suave {
        color: #362f4a;
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .input-suave-morado {
        border-color: #dfd7f0;
        border-radius: 7px;
        height: 40px;
        color: #2b253b;
        box-shadow: none;
    }

    .input-suave-morado:focus {
        border-color: #7c52cc;
        box-shadow: 0 0 0 3px rgba(124, 82, 204, 0.12);
    }

    .btn-guardar-morado {
        background-color: #673ab7;
        border-color: #5b32a3;
        color: #ffffff !important;
        border-radius: 7px;
        padding: 9px 18px;
        font-weight: 600;
        outline: none !important;
    }

    .btn-guardar-morado:hover,
    .btn-guardar-morado:focus,
    .btn-guardar-morado:active {
        background-color: #572fa0 !important;
        border-color: #4b278c !important;
        color: #ffffff !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .btn-volver-suave {
        background-color: #ffffff;
        border: 1px solid #dcd4ec;
        color: #3d3552 !important;
        border-radius: 7px;
        padding: 9px 18px;
        font-weight: 600;
        outline: none !important;
    }

    .btn-volver-suave:hover,
    .btn-volver-suave:focus {
        background-color: #f7f4fc !important;
        border-color: #c8bce3 !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .banner-privacidad-morado {
        background-color: #f5f1fc;
        border: 1px solid #e4dcf7;
        border-radius: 10px;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .banner-icono-morado {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background-color: #e9e0fa;
        color: #673ab7;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        font-size: 15px;
    }
</style>

<?php if ($mensaje_tipo === "exito") { ?>
    <div id="notificacionPerfil" style="position: fixed; bottom: 20px; right: 20px; z-index: 10060; min-width: 290px; max-width: 380px; background-color: #28a745; color: #ffffff; padding: 14px 18px; border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,0.2);">
        <button type="button" onclick="this.parentElement.remove();" style="background: transparent; border: none; color: #ffffff; float: right; font-size: 18px; line-height: 1; cursor: pointer; margin-left: 10px;">&times;</button>
        <strong><i class="fa fa-check-circle"></i> Listo</strong>
        <div style="margin-top: 4px; font-size: 13px;"><?php echo htmlspecialchars($mensaje_texto); ?></div>
    </div>
    <script>
        setTimeout(function () {
            var n = document.getElementById("notificacionPerfil");
            if (n) n.style.display = "none";
        }, 4500);
    </script>
<?php } elseif ($mensaje_tipo === "error") { ?>
    <div id="notificacionPerfilError" style="position: fixed; bottom: 20px; right: 20px; z-index: 10060; min-width: 290px; max-width: 380px; background-color: #dc3545; color: #ffffff; padding: 14px 18px; border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,0.2);">
        <button type="button" onclick="this.parentElement.remove();" style="background: transparent; border: none; color: #ffffff; float: right; font-size: 18px; line-height: 1; cursor: pointer; margin-left: 10px;">&times;</button>
        <strong><i class="fa fa-times-circle"></i> Error</strong>
        <div style="margin-top: 4px; font-size: 13px;"><?php echo htmlspecialchars($mensaje_texto); ?></div>
    </div>
    <script>
        setTimeout(function () {
            var n = document.getElementById("notificacionPerfilError");
            if (n) n.style.display = "none";
        }, 5500);
    </script>
<?php } ?>

<h2 class="perfil-titulo">Mi perfil</h2>
<p class="perfil-subtitulo">Consulta y actualiza tu información personal en TecnoStock.</p>

<?php if ($usuario) { ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card-morada-suave text-center">
                <div class="avatar-circulo-morado"><?php echo htmlspecialchars($iniciales); ?></div>
                <h4 style="margin: 0 0 8px 0; font-weight: 700; color: #2b253b;">
                    <?php echo htmlspecialchars($usuario['nombre_usuario']); ?>
                </h4>
                <div class="badge-cargo-suave"><?php echo htmlspecialchars($usuario['nombre_cargo']); ?></div>

                <div class="text-left">
                    <div class="perfil-meta-item">
                        <i class="fa fa-hashtag"></i>
                        <div>
                            <span class="perfil-meta-label">ID de usuario</span>
                            <span class="perfil-meta-valor">#<?php echo (int)$usuario['id_usuario']; ?></span>
                        </div>
                    </div>

                    <div class="perfil-meta-item">
                        <i class="fa fa-envelope-o"></i>
                        <div>
                            <span class="perfil-meta-label">Correo electrónico</span>
                            <span class="perfil-meta-valor"><?php echo htmlspecialchars($usuario['correo_usuario']); ?></span>
                        </div>
                    </div>

                    <div class="perfil-meta-item">
                        <i class="fa fa-shield"></i>
                        <div>
                            <span class="perfil-meta-label">Estado de cuenta</span>
                            <?php if ((int)$usuario['estado_usuario'] === 1) { ?>
                                <span class="badge-estado-activo">Activo</span>
                            <?php } else { ?>
                                <span class="badge-estado-inactivo">Inactivo</span>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="perfil-meta-item" style="padding-bottom: 0;">
                        <i class="fa fa-briefcase"></i>
                        <div>
                            <span class="perfil-meta-label">Descripción del cargo</span>
                            <span class="perfil-meta-valor" style="font-weight: 500; color: #5b5370;">
                                <?php echo !empty($usuario['descripcion_cargo']) ? htmlspecialchars($usuario['descripcion_cargo']) : 'Sin descripción registrada.'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card-morada-suave">
                <h4 class="seccion-form-titulo"><i class="fa fa-user-o" style="color: #673ab7; margin-right: 6px;"></i> Información personal</h4>
                <p class="seccion-form-sub">Actualiza tus datos de contacto y contraseña de acceso.</p>

                <form method="POST" action="misDatos.php">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="form-label-suave" for="nombre_usuario">Nombre de usuario *</label>
                                <input type="text"
                                       id="nombre_usuario"
                                       name="nombre_usuario"
                                       maxlength="80"
                                       class="form-control input-suave-morado"
                                       value="<?php echo htmlspecialchars($usuario['nombre_usuario']); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="form-label-suave" for="correo_usuario">Correo electrónico *</label>
                                <input type="email"
                                       id="correo_usuario"
                                       name="correo_usuario"
                                       maxlength="30"
                                       class="form-control input-suave-morado"
                                       value="<?php echo htmlspecialchars($usuario['correo_usuario']); ?>"
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="form-label-suave" for="telefono_usuario">Teléfono</label>
                                <input type="text"
                                       id="telefono_usuario"
                                       name="telefono_usuario"
                                       maxlength="20"
                                       class="form-control input-suave-morado"
                                       placeholder="Ej: +56 9 1234 5678"
                                       value="<?php echo htmlspecialchars($usuario['telefono_usuario'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="form-label-suave" for="clave_usuario">Nueva contraseña (opcional)</label>
                                <input type="password"
                                       id="clave_usuario"
                                       name="clave_usuario"
                                       maxlength="100"
                                       class="form-control input-suave-morado"
                                       placeholder="Déjalo vacío para mantener la actual"
                                       autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <div class="clearfix" style="margin-top: 10px;">
                        <button type="submit" name="actualizar_perfil" class="btn btn-guardar-morado pull-left">
                            <i class="fa fa-floppy-o"></i> Guardar cambios
                        </button>
                        <a href="inicio.php" class="btn btn-volver-suave pull-right">Volver</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="banner-privacidad-morado">
        <div style="display: flex; align-items: center;">
            <span class="banner-icono-morado"><i class="fa fa-lock"></i></span>
            <div>
                <div style="font-weight: 700; color: #2b253b; font-size: 13.5px;">Tu información está protegida</div>
                <div style="color: #6f6785; font-size: 12.5px;">Tus datos se utilizan únicamente para la gestión interna del sistema TecnoStock.</div>
            </div>
        </div>
    </div>
<?php } else { ?>
    <div class="card-morada-suave">
        <p style="margin: 0;">No se encontraron datos del usuario.</p>
    </div>
<?php } ?>

<?php
$conexion->close();
include 'visual/footer.php';
?>