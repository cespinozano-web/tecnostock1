<?php
session_start();
if (!isset($_SESSION['nombre_usuario']) || !isset($_SESSION['id_usuario'])) {
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

$id_usuario = (int)$_SESSION['id_usuario'];
$cantidadesPost = $_POST['cantidades'] ?? [];

$nombre_comprador = trim((string)($_POST['nombre_comprador'] ?? ''));
$correo_comprador = trim((string)($_POST['correo_comprador'] ?? ''));
$numero_comprador = trim((string)($_POST['numero_comprador'] ?? ''));
$descripcion_venta = trim((string)($_POST['descripcion_venta'] ?? ''));

$itemsSeleccionados = [];
if (is_array($cantidadesPost)) {
    foreach ($cantidadesPost as $idProd => $cant) {
        $idProdInt = (int)$idProd;
        $cantInt = (int)$cant;
        if ($idProdInt > 0 && $cantInt > 0) {
            $itemsSeleccionados[$idProdInt] = $cantInt;
        }
    }
}

if (empty($itemsSeleccionados)) {
    header("Location: nueva_orden.php?error=" . urlencode("Debes seleccionar al menos 1 unidad de algún producto."));
    exit();
}

if ($nombre_comprador === '' || $correo_comprador === '') {
    header("Location: nueva_orden.php?error=" . urlencode("El nombre y el correo del comprador son obligatorios."));
    exit();
}

$partesObs = [];
if ($descripcion_venta !== '') {
    $partesObs[] = $descripcion_venta;
}
$infoComprador = "Comprador: " . $nombre_comprador . " (" . $correo_comprador . ")";
if ($numero_comprador !== '') {
    $infoComprador .= " - Tel: " . $numero_comprador;
}
$partesObs[] = $infoComprador;
$observacion_movimiento = implode(" | ", $partesObs);

$tipo_movimiento = "Venta";
$estado_movimiento = 1;

$conexion->begin_transaction();

try {
    $stmtProd = $conexion->prepare("SELECT nombre_producto, precio_producto, stock_actual_producto FROM producto WHERE id_producto = ? FOR UPDATE");
    $stmtUpdStock = $conexion->prepare("UPDATE producto SET stock_actual_producto = ? WHERE id_producto = ?");
    $stmtInsMov = $conexion->prepare(
        "INSERT INTO movimiento (
            fecha_hora_movimiento,
            tipo_movimiento,
            cantidad_movimiento,
            unidades_movimiento,
            observacion_movimiento,
            id_producto,
            id_usuario,
            estado_movimiento
        ) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($itemsSeleccionados as $idProd => $unidades) {
        $stmtProd->bind_param("i", $idProd);
        $stmtProd->execute();
        $resProd = $stmtProd->get_result();

        if ($resProd->num_rows === 0) {
            throw new Exception("Producto ID #{$idProd} no encontrado.");
        }

        $prod = $resProd->fetch_assoc();
        $nombreProd = (string)$prod['nombre_producto'];
        $precioUnit = (float)$prod['precio_producto'];
        $stockActual = (int)$prod['stock_actual_producto'];

        if ($stockActual < $unidades) {
            throw new Exception("Stock insuficiente para '{$nombreProd}'. Disponible: {$stockActual}, solicitado: {$unidades}.");
        }

        // cantidad_movimiento = monto total ($) de este producto
        // unidades_movimiento = cantidad de unidades compradas de este producto
        $montoItem = round($precioUnit * $unidades, 2);

        $stmtInsMov->bind_param(
            "sdisiii",
            $tipo_movimiento,
            $montoItem,
            $unidades,
            $observacion_movimiento,
            $idProd,
            $id_usuario,
            $estado_movimiento
        );
        if (!$stmtInsMov->execute()) {
            throw new Exception("Error al registrar el movimiento de '{$nombreProd}'.");
        }

        $nuevoStock = $stockActual - $unidades;
        $stmtUpdStock->bind_param("ii", $nuevoStock, $idProd);
        if (!$stmtUpdStock->execute()) {
            throw new Exception("Error al actualizar el stock de '{$nombreProd}'.");
        }
    }

    $stmtProd->close();
    $stmtUpdStock->close();
    $stmtInsMov->close();

    $conexion->commit();

    header("Location: historial.php");
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    header("Location: nueva_orden.php?error=" . urlencode($e->getMessage()));
    exit();
} finally {
    $conexion->close();
}