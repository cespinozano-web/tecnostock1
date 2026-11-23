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
    #modalRealizarVenta {
        z-index: 10050 !important;
    }
    .modal-backdrop {
        z-index: 10040 !important;
    }
    #modalRealizarVenta .modal-content {
        background-color: #ffffff !important;
        opacity: 1 !important;
        border-radius: 0 !important;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.35);
    }

    /* Morado suave leve */
    .orden-header-suave {
        color: #5a3e99;
        font-weight: 600;
    }

    .panel-orden-suave {
        border-color: #e6dff5 !important;
    }

    .panel-orden-suave > .panel-heading {
        background-color: #faf8fe !important;
        border-color: #e6dff5 !important;
        color: #5a3e99;
    }

    .panel-orden-suave > .panel-footer {
        background-color: #faf8fe !important;
        border-color: #e6dff5 !important;
    }

    .buscador-orden-suave .input-group-addon {
        background-color: #f6f2fc;
        border-color: #dfd6f2;
        color: #6b4cb0;
        border-radius: 0;
    }

    .buscador-orden-suave .form-control {
        border-color: #dfd6f2;
        border-radius: 0;
    }

    .buscador-orden-suave .form-control:focus {
        border-color: #a487db;
        box-shadow: none;
    }

    .tabla-orden-suave > thead > tr > th {
        background-color: #f7f4fc;
        color: #53388f;
        border-color: #e6dff5 !important;
    }

    .tabla-orden-suave > tbody > tr.fila-orden > td {
        border-color: #ece6f7 !important;
    }

    .tabla-orden-suave > tbody > tr.fila-orden:hover {
        background-color: #fcfbfe;
    }

    .tabla-orden-suave > tbody > tr.fila-orden a,
    .tabla-orden-suave > tbody > tr.fila-orden a:hover,
    .tabla-orden-suave > tbody > tr.fila-orden a:focus,
    .tabla-orden-suave > tbody > tr.fila-orden a:active {
        color: #6547a8;
        font-weight: 500;
        outline: 0 !important;
        outline-style: none !important;
        outline-width: 0 !important;
        box-shadow: none !important;
        -webkit-tap-highlight-color: transparent;
    }

    .tabla-orden-suave > tbody > tr.fila-orden a:hover {
        color: #482e82;
        text-decoration: underline;
    }

    .tabla-orden-suave > tbody > tr.fila-detalle > td {
        background-color: #fcfbfe !important;
        color: #39464e !important;
        border-color: #ece6f7 !important;
    }

    .collapse-content {
        background-color: #fcfbfe !important;
        color: #39464e !important;
        border-left: 3px solid #b69ae8;
        padding: 10px 14px;
    }

    /* Botones Confirmar (verde claro) y Cancelar (rojo claro) */
    .btn-confirmar-claro {
        background-color: #d4edda !important;
        border-color: #b7dfc2 !important;
        color: #155724 !important;
        font-weight: 600;
        border-radius: 0;
        outline: none !important;
    }

    .btn-confirmar-claro:hover,
    .btn-confirmar-claro:focus,
    .btn-confirmar-claro:active {
        background-color: #c3e6cb !important;
        border-color: #a5d4b0 !important;
        color: #10461d !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .btn-cancelar-claro {
        background-color: #f8d7da !important;
        border-color: #f1bcc2 !important;
        color: #721c24 !important;
        font-weight: 600;
        border-radius: 0;
        outline: none !important;
    }

    .btn-cancelar-claro:hover,
    .btn-cancelar-claro:focus,
    .btn-cancelar-claro:active {
        background-color: #f5c6cb !important;
        border-color: #eba8b0 !important;
        color: #59141b !important;
        outline: none !important;
        box-shadow: none !important;
    }
</style>

<br>
<div class="clearfix" style="margin-bottom: 15px;">
    <h4 class="pull-left orden-header-suave" style="margin: 5px 0 0 0;"><i class="fa fa-exchange"></i> Nueva orden / venta</h4>
    <a href="inicio.php" class="btn btn-default pull-right" style="border-radius: 0;">
        <i class="fa fa-arrow-left"></i> Volver al inicio
    </a>
</div>

<?php if (isset($_GET['error'])) { ?>
    <div class="alert alert-danger" style="border-radius: 0;">
        <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
<?php } ?>

<form action="guardar_orden.php" method="post" id="formNuevaOrden">
    
    <input type="hidden" name="nombre_comprador" id="hidden_nombre_comprador">
    <input type="hidden" name="correo_comprador" id="hidden_correo_comprador">
    <input type="hidden" name="numero_comprador" id="hidden_numero_comprador">
    <input type="hidden" name="descripcion_venta" id="hidden_descripcion_venta">
    <input type="hidden" name="total_orden" id="inputTotalOrden" value="0">

    <div class="panel panel-default panel-orden-suave" style="border-radius: 0;">
        <div class="panel-heading" style="border-radius: 0;">
            <div class="form-group buscador-orden-suave" style="margin-bottom: 0;">
                <label for="buscadorOrden">Buscar producto</label>
                <div class="input-group" style="width: 100%;">
                    <span class="input-group-addon"><i class="fa fa-search"></i></span>
                    <input type="text" id="buscadorOrden" class="form-control" placeholder="Buscar por código, nombre o categoría..." autocomplete="off">
                </div>
            </div>
        </div>

        <div class="panel-body" style="padding: 0;">
            <div class="table-responsive" style="margin-bottom: 0;">
                <table class="table table-bordered table-collapse tabla-orden-suave" id="tablaOrdenProductos" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Stock Actual</th>
                            <th>Precio Unit.</th>
                            <th style="width: 160px;" class="text-center">Cantidad</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0) { ?>
                            <?php while ($row = $resultado->fetch_assoc()) { ?>
                                <?php 
                                    $idProd = (int)$row['id_producto'];
                                    $stockMax = (int)$row['stock_actual_producto'];
                                    $precioUnit = (float)$row['precio_producto'];
                                    $nombreProd = htmlspecialchars($row['nombre_producto'], ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="fila-orden"
                                    data-id="<?php echo $idProd; ?>"
                                    data-nombre="<?php echo $nombreProd; ?>"
                                    data-stock="<?php echo $stockMax; ?>"
                                    data-precio="<?php echo $precioUnit; ?>">
                                    <td><?php echo htmlspecialchars($row['codigo_producto']); ?></td>
                                    <td>
                                        <a data-toggle="collapse" data-target="#detalleOrden<?php echo $idProd; ?>" aria-expanded="false" href="javascript:void(0);" onmousedown="this.blur();" style="cursor: pointer;">
                                            <?php echo htmlspecialchars($row['nombre_producto']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['nombre_categoria']); ?></td>
                                    <td><?php echo $stockMax; ?></td>
                                    <td>$<?php echo number_format($precioUnit, 0, ',', '.'); ?></td>
                                    <td class="text-center">
                                        <div class="input-group" style="width: 130px; margin: 0 auto;">
                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-default btn-menos" style="border-radius: 0;" <?php echo ($stockMax <= 0) ? 'disabled' : ''; ?>>-</button>
                                            </span>
                                            <input type="number"
                                                   name="cantidades[<?php echo $idProd; ?>]"
                                                   class="form-control text-center input-cantidad"
                                                   value="0"
                                                   min="0"
                                                   max="<?php echo $stockMax; ?>"
                                                   style="border-radius: 0; padding: 6px 4px;"
                                                   <?php echo ($stockMax <= 0) ? 'readonly' : ''; ?>>
                                            <span class="input-group-btn">
                                                <button type="button" class="btn btn-default btn-mas" style="border-radius: 0;" <?php echo ($stockMax <= 0) ? 'disabled' : ''; ?>>+</button>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-right subtotal-fila">$0</td>
                                </tr>

                                <tr class="fila-detalle">
                                    <td colspan="7" class="collapse-td">
                                        <div class="collapse-content collapse" id="detalleOrden<?php echo $idProd; ?>">
                                            <span class="header-6" style="font-weight: bold; color: #53388f;">Descripción:</span>
                                            <p style="margin-top: 5px; margin-bottom: 0;">
                                                <?php echo !empty($row['descripcion_producto']) ? htmlspecialchars($row['descripcion_producto']) : 'Sin descripción registrada para este producto.'; ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                            <tr id="sinResultadosOrden" style="display: none;">
                                <td colspan="7" class="text-center text-muted">No se encontraron productos con ese criterio.</td>
                            </tr>
                        <?php } else { ?>
                            <tr>
                                <td colspan="7" class="text-center">No hay productos registrados.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-footer clearfix" style="border-radius: 0;">
            <div class="pull-left" style="padding-top: 6px;">
                <strong>Unidades seleccionadas:</strong> <span id="totalUnidades">0</span> &nbsp;|&nbsp;
                <strong>Total dinero:</strong> <span id="totalDinero" style="font-size: 16px; color: #53388f;">$0</span>
            </div>
            <button type="button" id="btnAbrirModalVenta" class="btn btn-confirmar-claro pull-right">
                <i class="fa fa-check"></i> Realizar venta
            </button>
        </div>
    </div>
</form>

<div class="modal fade" id="modalRealizarVenta" tabindex="-1" role="dialog" aria-labelledby="modalRealizarVentaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="border-radius: 0; background-color: #faf8fe; border-bottom-color: #e6dff5;">
                <button type="button" class="close" id="btnCerrarModalX" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modalRealizarVentaLabel" style="color: #53388f;"><i class="fa fa-user"></i> Información del comprador</h4>
            </div>
            <div class="modal-body">
                <div id="alertaModalVenta" class="alert alert-warning" style="display: none; border-radius: 0;"></div>

                <div class="form-group">
                    <label for="nombre_comprador">Nombre del comprador <span class="text-danger">*</span></label>
                    <input type="text" id="nombre_comprador" class="form-control" style="border-radius: 0;" required placeholder="Ej: Esteban Paredes">
                </div>

                <div class="form-group">
                    <label for="correo_comprador">Correo del comprador <span class="text-danger">*</span></label>
                    <input type="email" id="correo_comprador" class="form-control" style="border-radius: 0;" required placeholder="Ej: cliente@correo.cl">
                </div>

                <div class="form-group">
                    <label for="numero_comprador">Número de contacto (opcional)</label>
                    <input type="text" id="numero_comprador" class="form-control" style="border-radius: 0;" placeholder="Ej: +56 9 1234 5678">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="descripcion_venta">Descripción de la venta (opcional)</label>
                    <textarea id="descripcion_venta" rows="3" class="form-control" style="border-radius: 0;" placeholder="Notas adicionales, despacho, medio de pago..."></textarea>
                </div>

                <div class="well well-sm" style="border-radius: 0; margin-bottom: 0; background-color: #faf8fe; border-color: #e6dff5;">
                    <strong>Resumen de la venta:</strong>
                    <ul id="resumenArticulosModal" style="margin: 6px 0 6px 18px; padding: 0;"></ul>
                    <div><strong>Total a cobrar:</strong> <span id="modalTotalDinero">$0</span></div>
                </div>
            </div>
            <div class="modal-footer" style="border-radius: 0; background-color: #faf8fe; border-top-color: #e6dff5;">
                <button type="button" id="btnCerrarModalVenta" class="btn btn-cancelar-claro">Cancelar</button>
                <button type="button" id="btnConfirmarVenta" class="btn btn-confirmar-claro">
                    <i class="fa fa-check-circle"></i> Confirmar venta
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function iniciarNuevaOrden() {
    function setup() {
        const inputBuscador = document.getElementById("buscadorOrden");
        const filas = document.querySelectorAll("#tablaOrdenProductos tbody tr.fila-orden");
        const filaSinResultados = document.getElementById("sinResultadosOrden");
        const spanTotalUnidades = document.getElementById("totalUnidades");
        const spanTotalDinero = document.getElementById("totalDinero");
        const inputTotalOrden = document.getElementById("inputTotalOrden");
        const btnAbrirModal = document.getElementById("btnAbrirModalVenta");
        const btnCerrarModal = document.getElementById("btnCerrarModalVenta");
        const btnCerrarModalX = document.getElementById("btnCerrarModalX");
        const btnConfirmarVenta = document.getElementById("btnConfirmarVenta");
        const alertaModal = document.getElementById("alertaModalVenta");
        const listaResumen = document.getElementById("resumenArticulosModal");
        const spanModalTotal = document.getElementById("modalTotalDinero");
        const modalEl = document.getElementById("modalRealizarVenta");
        const formNuevaOrden = document.getElementById("formNuevaOrden");

        if (!filas.length && !formNuevaOrden) return;

        const inputNombre = document.getElementById("nombre_comprador");
        const inputCorreo = document.getElementById("correo_comprador");
        const inputNumero = document.getElementById("numero_comprador");
        const inputDescripcion = document.getElementById("descripcion_venta");

        const hiddenNombre = document.getElementById("hidden_nombre_comprador");
        const hiddenCorreo = document.getElementById("hidden_correo_comprador");
        const hiddenNumero = document.getElementById("hidden_numero_comprador");
        const hiddenDescripcion = document.getElementById("hidden_descripcion_venta");

        // Evitar modales duplicados si se entró antes por AJAX
        document.querySelectorAll("body > #modalRealizarVenta").forEach(function (previo) {
            if (previo !== modalEl) previo.remove();
        });

        if (modalEl && modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }

        function limpiarBackdropsHuerfanos() {
            document.querySelectorAll(".modal-backdrop").forEach(function (el) {
                el.remove();
            });
            document.body.classList.remove("modal-open");
            document.body.style.paddingRight = "";
        }

        function abrirModal() {
            limpiarBackdropsHuerfanos();
            if (window.jQuery && typeof jQuery.fn.modal === "function") {
                jQuery("#modalRealizarVenta").modal({
                    backdrop: "static",
                    keyboard: true,
                    show: true
                });
            } else if (modalEl) {
                modalEl.classList.add("in");
                modalEl.style.display = "block";
                document.body.classList.add("modal-open");
            }
        }

        function cerrarModal() {
            if (window.jQuery && typeof jQuery.fn.modal === "function") {
                jQuery("#modalRealizarVenta").modal("hide");
            }
            if (modalEl) {
                modalEl.classList.remove("in");
                modalEl.style.display = "none";
                modalEl.setAttribute("aria-hidden", "true");
            }
            limpiarBackdropsHuerfanos();
        }

        function formatoCLP(valor) {
            return "$" + Math.round(valor).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function recalcularTotales() {
            let sumaUnidades = 0;
            let sumaDinero = 0;

            filas.forEach(function (fila) {
                const precio = parseFloat(fila.getAttribute("data-precio")) || 0;
                const inputCant = fila.querySelector(".input-cantidad");
                const celdaSubtotal = fila.querySelector(".subtotal-fila");

                let cant = parseInt(inputCant.value, 10);
                if (isNaN(cant) || cant < 0) cant = 0;

                const subtotal = cant * precio;
                if (celdaSubtotal) {
                    celdaSubtotal.textContent = formatoCLP(subtotal);
                }

                sumaUnidades += cant;
                sumaDinero += subtotal;
            });

            if (spanTotalUnidades) spanTotalUnidades.textContent = sumaUnidades;
            if (spanTotalDinero) spanTotalDinero.textContent = formatoCLP(sumaDinero);
            if (inputTotalOrden) inputTotalOrden.value = Math.round(sumaDinero);
        }

        if (inputBuscador) {
            inputBuscador.oninput = function () {
                const termino = this.value.toLowerCase().trim();
                let visibles = 0;

                filas.forEach(function (fila) {
                    const filaDet = fila.nextElementSibling;
                    const textoFila = fila.textContent.toLowerCase();
                    const textoDet = (filaDet && filaDet.classList.contains("fila-detalle")) ? filaDet.textContent.toLowerCase() : "";
                    const coincide = textoFila.includes(termino) || textoDet.includes(termino);

                    fila.style.display = coincide ? "" : "none";
                    if (filaDet && filaDet.classList.contains("fila-detalle")) {
                        filaDet.style.display = coincide ? "" : "none";
                    }
                    if (coincide) visibles++;
                });

                if (filaSinResultados) {
                    filaSinResultados.style.display = (visibles === 0 && filas.length > 0) ? "" : "none";
                }
            };
        }

        filas.forEach(function (fila) {
            const btnMenos = fila.querySelector(".btn-menos");
            const btnMas = fila.querySelector(".btn-mas");
            const inputCant = fila.querySelector(".input-cantidad");
            const stockMax = parseInt(fila.getAttribute("data-stock"), 10) || 0;

            function normalizarYActualizar(valorDeseado) {
                let val = parseInt(valorDeseado, 10);
                if (isNaN(val) || val < 0) val = 0;
                if (val > stockMax) val = stockMax;
                inputCant.value = val;
                recalcularTotales();
            }

            if (btnMenos) {
                btnMenos.onclick = function () {
                    const actual = parseInt(inputCant.value, 10) || 0;
                    normalizarYActualizar(actual - 1);
                };
            }

            if (btnMas) {
                btnMas.onclick = function () {
                    const actual = parseInt(inputCant.value, 10) || 0;
                    normalizarYActualizar(actual + 1);
                };
            }

            inputCant.oninput = function () {
                normalizarYActualizar(this.value);
            };

            inputCant.onchange = function () {
                normalizarYActualizar(this.value);
            };
        });

        if (btnAbrirModal) {
            btnAbrirModal.onclick = function () {
                recalcularTotales();
                alertaModal.style.display = "none";
                listaResumen.innerHTML = "";

                let totalItems = 0;
                let totalPlata = 0;

                filas.forEach(function (fila) {
                    const nombre = fila.getAttribute("data-nombre");
                    const precio = parseFloat(fila.getAttribute("data-precio")) || 0;
                    const cant = parseInt(fila.querySelector(".input-cantidad").value, 10) || 0;

                    if (cant > 0) {
                        const sub = cant * precio;
                        totalItems += cant;
                        totalPlata += sub;

                        const li = document.createElement("li");
                        li.textContent = nombre + " (x" + cant + ") - " + formatoCLP(sub);
                        listaResumen.appendChild(li);
                    }
                });

                if (totalItems <= 0) {
                    alertaModal.textContent = "Debes seleccionar al menos 1 unidad de algún producto antes de realizar la venta.";
                    alertaModal.style.display = "block";
                    btnConfirmarVenta.disabled = true;
                    abrirModal();
                    return;
                }

                btnConfirmarVenta.disabled = false;
                spanModalTotal.textContent = formatoCLP(totalPlata);
                abrirModal();
            };
        }

        if (btnConfirmarVenta) {
            btnConfirmarVenta.onclick = function () {
                const nombreVal = inputNombre.value.trim();
                const correoVal = inputCorreo.value.trim();

                if (nombreVal === "" || correoVal === "") {
                    alertaModal.textContent = "El nombre y el correo del comprador son obligatorios.";
                    alertaModal.style.display = "block";
                    return;
                }

                if (!inputCorreo.checkValidity()) {
                    alertaModal.textContent = "Ingresa un correo electrónico válido.";
                    alertaModal.style.display = "block";
                    return;
                }

                alertaModal.style.display = "none";
                hiddenNombre.value = nombreVal;
                hiddenCorreo.value = correoVal;
                hiddenNumero.value = inputNumero.value.trim();
                hiddenDescripcion.value = inputDescripcion.value.trim();

                cerrarModal();
                formNuevaOrden.submit();
            };
        }

        if (btnCerrarModal) {
            btnCerrarModal.onclick = cerrarModal;
        }

        if (btnCerrarModalX) {
            btnCerrarModalX.onclick = cerrarModal;
        }

        if (window.jQuery && typeof jQuery.fn.modal === "function") {
            jQuery("#modalRealizarVenta").off("hidden.bs.modal").on("hidden.bs.modal", limpiarBackdropsHuerfanos);
        }

        recalcularTotales();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", setup);
    } else {
        setup();
    }
})();
</script>

<?php 
if (isset($resultado) && is_object($resultado)) {
    $resultado->free();
}
$conexion->close();
include 'visual/footer.php';
?>