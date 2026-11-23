<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_perfil = ($pagina_actual == 'misDatos.php');
$es_gestion_usuarios = in_array($pagina_actual, ['gestion_usuarios.php', 'nuevo_usuario.php'], true);
$es_notificaciones = ($pagina_actual == 'notificaciones.php');

$cargoSidebar = strtolower(trim((string)($_SESSION['nombre_cargo'] ?? '')));
$mostrar_admin = function_exists('esAdmin')
    ? esAdmin()
    : in_array($cargoSidebar, ['dueño', 'dueno', 'admin', 'administrador', 'owner'], true);

$totalStockCriticoSidebar = isset($totalNotificaciones) ? (int)$totalNotificaciones : 0;

if ($mostrar_admin && !isset($totalNotificaciones)) {
    $dbSidebar = @new mysqli("localhost", "root", "", "tecnostock1");
    if ($dbSidebar && !$dbSidebar->connect_errno) {
        $dbSidebar->set_charset("utf8mb4");

        $colsProdSb = [];
        $resColsSb = @$dbSidebar->query("SHOW COLUMNS FROM `producto`");
        if ($resColsSb) {
            while ($cRow = $resColsSb->fetch_assoc()) {
                $colsProdSb[] = $cRow['Field'];
            }
            $resColsSb->free();
        }

        $colStockSb = null;
        foreach (['stock_actual', 'stock', 'cantidad_stock', 'cantidad', 'existencia'] as $cand) {
            foreach ($colsProdSb as $col) {
                if (strcasecmp($col, $cand) === 0 || stripos($col, $cand) !== false) {
                    $colStockSb = $col;
                    break 2;
                }
            }
        }

        $colCriticoSb = null;
        foreach (['stock_critico', 'stock_minimo', 'minimo_stock', 'critico', 'minimo'] as $cand) {
            foreach ($colsProdSb as $col) {
                if (strcasecmp($col, $cand) === 0 || stripos($col, $cand) !== false) {
                    $colCriticoSb = $col;
                    break 2;
                }
            }
        }

        $colEstadoSb = null;
        foreach (['estado_producto', 'estado', 'activo'] as $cand) {
            foreach ($colsProdSb as $col) {
                if (strcasecmp($col, $cand) === 0 || stripos($col, $cand) !== false) {
                    $colEstadoSb = $col;
                    break 2;
                }
            }
        }

        if ($colStockSb) {
            $exprStock = "COALESCE(`$colStockSb`, 0)";
            $exprCrit = $colCriticoSb ? "COALESCE(`$colCriticoSb`, 5)" : "5";
            $whereSb = ["{$exprStock} <= ({$exprCrit})"];

            if ($colEstadoSb) {
                $whereSb[] = "(`$colEstadoSb` IS NULL OR `$colEstadoSb` != 0)";
            }

            $sqlCountSb = "SELECT COUNT(*) AS total_criticos FROM `producto` WHERE " . implode(" AND ", $whereSb);
            $resCntSb = @$dbSidebar->query($sqlCountSb);
            if ($resCntSb && ($rowCntSb = $resCntSb->fetch_assoc())) {
                $totalStockCriticoSidebar = (int)($rowCntSb['total_criticos'] ?? 0);
                $resCntSb->free();
            }
        }

        $dbSidebar->close();
    }
}
?>

<style>
    .msb a,
    .msb a:hover,
    .msb a:focus,
    .msb a:active,
    .msb button,
    .msb button:hover,
    .msb button:focus,
    .msb button:active {
        outline: 0 !important;
        outline-style: none !important;
        outline-width: 0 !important;
        box-shadow: none !important;
        -webkit-tap-highlight-color: transparent;
    }

    .msb {
        background-color: #f6f3fb;
        border-right: 1px solid #e3daf2;
    }

    .msb .navbar-header {
        background: linear-gradient(135deg, #7c52cc 0%, #5e35b1 100%);
        border-bottom: 1px solid #522ca1;
        margin-bottom: 12px;
    }

    .msb .brand-wrapper {
        padding: 4px 12px;
    }

    .msb .navbar-brand {
        color: #ffffff;
        font-weight: 700;
        letter-spacing: 0.4px;
        float: none;
        display: inline-block;
    }

    .msb .navbar-brand:hover,
    .msb .navbar-brand:focus {
        color: #f3ecff;
    }

    .msb .side-menu-container .navbar-nav {
        float: none;
        width: 100%;
        margin: 0;
    }

    .msb .navbar-nav > li > a {
        color: #4a3f63;
        font-weight: 500;
        padding: 13px 18px;
        border-left: 3px solid transparent;
        transition: background-color 0.2s ease, color 0.2s ease, border-left-color 0.2s ease;
    }

    .msb .navbar-nav > li > a:hover,
    .msb .navbar-nav > li > a:focus {
        background-color: #ece4f9;
        color: #562cb0;
        border-left-color: #9b72e8;
    }

    .msb .navbar-nav > li.active > a,
    .msb .navbar-nav > li.active > a:hover,
    .msb .navbar-nav > li.active > a:focus {
        background-color: #e6dcf8;
        color: #4d23a8;
        font-weight: 600;
        border-left-color: #6f42c1;
    }

    .msb .navbar-nav > li > a .fa {
        color: #7950c9;
        width: 18px;
        text-align: center;
        margin-right: 10px;
    }

    .msb .badge-notif-critico {
        float: right;
        background-color: #dc3545;
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 12px;
        line-height: 1.2;
        margin-top: 1px;
    }

    .msb .panel.panel-default {
        background-color: transparent;
        border: none;
        box-shadow: none;
    }

    .msb #dropdown-lvl1 {
        overflow: hidden;
        max-height: 0;
        opacity: 0;
        visibility: hidden;
        transition: max-height 0.3s cubic-bezier(0.25, 0.8, 0.25, 1), opacity 0.22s ease, visibility 0.22s ease;
    }

    .msb #dropdown-lvl1.in {
        max-height: 220px;
        opacity: 1;
        visibility: visible;
    }

    .msb #dropdown-lvl1 .panel-body {
        padding: 0;
        background-color: #efe9fa;
        border-top: 1px solid #dfd3f4;
        border-bottom: 1px solid #dfd3f4;
    }

    .msb #dropdown-lvl1 .navbar-nav > li > a {
        padding: 10px 18px 10px 44px;
        font-size: 13px;
        color: #554872;
        border-left: 3px solid transparent;
    }

    .msb #dropdown-lvl1 .navbar-nav > li > a:hover,
    .msb #dropdown-lvl1 .navbar-nav > li.active > a {
        background-color: #e2d5f7;
        color: #4b21a6;
        border-left-color: #7c52cc;
    }

    .msb .caret {
        float: right;
        margin-top: 8px;
        color: #7950c9;
        transition: transform 0.25s ease;
    }

    .msb #btnTogglePerfil[aria-expanded="true"] .caret {
        transform: rotate(180deg);
    }

    #modalCerrarSesion {
        z-index: 10070 !important;
    }

    #modalCerrarSesion .modal-content {
        border-radius: 10px;
        border: 1px solid #e5def5;
        box-shadow: 0 12px 30px rgba(31, 27, 46, 0.22);
        overflow: hidden;
    }

    #modalCerrarSesion .modal-header {
        background-color: #faf8fe;
        border-bottom: 1px solid #ece6f7;
    }

    #modalCerrarSesion .modal-footer {
        background-color: #faf8fe;
        border-top: 1px solid #ece6f7;
    }

    .btn-cancelar-sesion {
        background-color: #f8d7da !important;
        border-color: #f1bcc2 !important;
        color: #721c24 !important;
        font-weight: 600;
        border-radius: 7px;
        outline: none !important;
    }

    .btn-cancelar-sesion:hover,
    .btn-cancelar-sesion:focus {
        background-color: #f5c6cb !important;
        border-color: #eba8b0 !important;
        color: #59141b !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .btn-confirmar-sesion {
        background-color: #d4edda !important;
        border-color: #b7dfc2 !important;
        color: #155724 !important;
        font-weight: 600;
        border-radius: 7px;
        outline: none !important;
    }

    .btn-confirmar-sesion:hover,
    .btn-confirmar-sesion:focus {
        background-color: #c3e6cb !important;
        border-color: #a5d4b0 !important;
        color: #10461d !important;
        outline: none !important;
        box-shadow: none !important;
    }
</style>

<div class="msb" id="msb">
    <nav class="navbar navbar-default" role="navigation">
        <div class="navbar-header">
            <div class="brand-wrapper">
                <div class="brand-name-wrapper">
                    <a class="navbar-brand" href="inicio.php" onmousedown="this.blur();">
                        <i class="fa fa-bolt" style="margin-right: 6px;"></i> TecnoStock
                    </a>
                </div>
            </div>
        </div>

        <div class="side-menu-container">
            <ul class="nav navbar-nav">

                <li class="<?php echo ($pagina_actual == 'inicio.php') ? 'active' : ''; ?>">
                    <a href="inicio.php" onmousedown="this.blur();"><i class="fa fa-home"></i> Inicio</a>
                </li>

                <li class="<?php echo ($pagina_actual == 'historial.php') ? 'active' : ''; ?>">
                    <a href="historial.php" onmousedown="this.blur();"><i class="fa fa-history"></i> Historial</a>
                </li>

                <li class="<?php echo ($pagina_actual == 'bodega.php') ? 'active' : ''; ?>">
                    <a href="bodega.php" onmousedown="this.blur();"><i class="fa fa-cubes"></i> Bodega</a>
                </li>

                <?php if ($mostrar_admin): ?>
                    <li class="<?php echo ($pagina_actual == 'movimientos.php') ? 'active' : ''; ?>">
                        <a href="movimientos.php" onmousedown="this.blur();"><i class="fa fa-exchange"></i> Movimientos</a>
                    </li>
                    <li class="<?php echo $es_gestion_usuarios ? 'active' : ''; ?>">
                        <a href="gestion_usuarios.php" onmousedown="this.blur();"><i class="fa fa-users"></i> Gestión de usuarios</a>
                    </li>
                    <li class="<?php echo $es_notificaciones ? 'active' : ''; ?>">
                        <a href="notificaciones.php" onmousedown="this.blur();">
                            <i class="fa fa-bell"></i> Notificaciones
                            <span class="badge-notif-critico" id="badgeNotificacionesCriticas"><?php echo (int)$totalStockCriticoSidebar; ?></span>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="panel panel-default <?php echo $es_perfil ? 'active' : ''; ?>" id="dropdown">
                    <a href="javascript:void(0);" id="btnTogglePerfil" onmousedown="this.blur();" aria-expanded="<?php echo $es_perfil ? 'true' : 'false'; ?>">
                        <i class="fa fa-user"></i> Mi Perfil <span class="caret"></span>
                    </a>

                    <div id="dropdown-lvl1" class="panel-collapse <?php echo $es_perfil ? 'in' : ''; ?>">
                        <div class="panel-body">
                            <ul class="nav navbar-nav">
                                <li class="<?php echo ($pagina_actual == 'misDatos.php') ? 'active' : ''; ?>">
                                    <a href="misDatos.php" onmousedown="this.blur();"><i class="fa fa-id-card-o"></i> Mis Datos</a>
                                </li>
                                <li>
                                    <a href="logout.php" id="btnAbrirModalCerrarSesion" onmousedown="this.blur();"><i class="fa fa-sign-out"></i> Cerrar Sesión</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </li>

            </ul>
        </div>
    </nav>
</div>

<div class="modal fade" id="modalCerrarSesion" tabindex="-1" role="dialog" aria-labelledby="modalCerrarSesionLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" id="btnCerrarModalSesionX" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modalCerrarSesionLabel" style="color: #4d2c91; font-weight: 700;">
                    <i class="fa fa-sign-out"></i> Cerrar sesión
                </h4>
            </div>
            <div class="modal-body" style="color: #2b253b;">
                ¿Estás seguro de que deseas cerrar tu sesión actual en <strong>TecnoStock</strong>?
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCancelarCerrarSesion" class="btn btn-cancelar-sesion">Cancelar</button>
                <a href="logout.php" id="btnConfirmarCerrarSesion" class="btn btn-confirmar-sesion">
                    <i class="fa fa-check"></i> Confirmar
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__tecnoStockSidebarBound) return;
    window.__tecnoStockSidebarBound = true;

    const modalSesion = document.getElementById("modalCerrarSesion");
    if (modalSesion && modalSesion.parentNode !== document.body) {
        document.body.appendChild(modalSesion);
    }

    function limpiarBackdropsSesion() {
        document.querySelectorAll(".modal-backdrop").forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove("modal-open");
        document.body.style.paddingRight = "";
    }

    function abrirModalCerrarSesion() {
        limpiarBackdropsSesion();
        if (window.jQuery && typeof jQuery.fn.modal === "function") {
            jQuery("#modalCerrarSesion").modal({
                backdrop: "static",
                keyboard: true,
                show: true
            });
        } else if (modalSesion) {
            modalSesion.classList.add("in");
            modalSesion.style.display = "block";
            document.body.classList.add("modal-open");
        }
    }

    function cerrarModalCerrarSesion() {
        if (window.jQuery && typeof jQuery.fn.modal === "function") {
            jQuery("#modalCerrarSesion").modal("hide");
        }
        if (modalSesion) {
            modalSesion.classList.remove("in");
            modalSesion.style.display = "none";
            modalSesion.setAttribute("aria-hidden", "true");
        }
        limpiarBackdropsSesion();
    }

    document.addEventListener("click", function (evento) {
        const btnPerfil = evento.target.closest("#btnTogglePerfil");
        if (btnPerfil) {
            evento.preventDefault();
            evento.stopPropagation();
            if (typeof btnPerfil.blur === "function") btnPerfil.blur();

            const submenu = document.getElementById("dropdown-lvl1");
            if (!submenu) return;

            const estaAbierto = submenu.classList.contains("in");
            submenu.classList.toggle("in", !estaAbierto);
            btnPerfil.setAttribute("aria-expanded", (!estaAbierto) ? "true" : "false");
            return;
        }

        const btnAbrirSesion = evento.target.closest("#btnAbrirModalCerrarSesion");
        if (btnAbrirSesion) {
            evento.preventDefault();
            evento.stopPropagation();
            abrirModalCerrarSesion();
            return;
        }

        if (evento.target.closest("#btnCancelarCerrarSesion, #btnCerrarModalSesionX")) {
            evento.preventDefault();
            cerrarModalCerrarSesion();
        }
    }, true);
})();
</script>