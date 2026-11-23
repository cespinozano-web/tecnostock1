<?php
session_start();

if (!isset($_SESSION['nombre_usuario'])) {
    header("location: login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("location: nueva_orden.php");
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

$nombreComprador = trim($_POST['nombre_comprador'] ?? '');
$correoComprador = trim($_POST['correo_comprador'] ?? '');
$numeroComprador = trim($_POST['numero_comprador'] ?? '');
$descripcionVenta = trim($_POST['descripcion_venta'] ?? '');
$cantidadesPost = $_POST['cantidades'] ?? [];

if ($nombreComprador === '' || $correoComprador === '') {
    header("location: nueva_orden.php?error=" . urlencode("El nombre y el correo del comprador son obligatorios."));
    exit();
}

$idUsuario = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
if ($idUsuario <= 0) {
    $stmtUser = $conexion->prepare("SELECT id_usuario FROM usuario WHERE nombre_usuario = ? LIMIT 1");
    $stmtUser->bind_param("s", $_SESSION['nombre_usuario']);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();
    if ($filaUser = $resUser->fetch_assoc()) {
        $idUsuario = (int)$filaUser['id_usuario'];
    }
    $stmtUser->close();
}

$itemsVenta = [];
foreach ($cantidadesPost as $idProd => $cant) {
    $idProdInt = (int)$idProd;
    $cantInt = (int)$cant;
    if ($idProdInt > 0 && $cantInt > 0) {
        $itemsVenta[$idProdInt] = $cantInt;
    }
}

if (empty($itemsVenta)) {
    header("location: nueva_orden.php?error=" . urlencode("No seleccionaste cantidades válidas para la venta."));
    exit();
}

$conexion->begin_transaction();

try {
    $detalleArticulos = [];
    $productosValidados = [];

    $stmtProd = $conexion->prepare("SELECT id_producto, nombre_producto, precio_producto, stock_actual_producto FROM producto WHERE id_producto = ? FOR UPDATE");

    foreach ($itemsVenta as $idProd => $cantSolicitada) {
        $stmtProd->bind_param("i", $idProd);
        $stmtProd->execute();
        $resProd = $stmtProd->get_result();
        $prod = $resProd->fetch_assoc();

        if (!$prod) {
            throw new Exception("Uno de los productos seleccionados no existe.");
        }

        $stockActual = (int)$prod['stock_actual_producto'];
        if ($cantSolicitada > $stockActual) {
            throw new Exception("La cantidad solicitada para '" . $prod['nombre_producto'] . "' supera el stock actual (" . $stockActual . ").");
        }

        $precioUnit = (float)$prod['precio_producto'];
        $montoTotalItem = $cantSolicitada * $precioUnit;

        $productosValidados[] = [
            'id_producto' => (int)$prod['id_producto'],
            'nombre_producto' => $prod['nombre_producto'],
            'cantidad' => $cantSolicitada,
            'monto_total' => $montoTotalItem,
            'nuevo_stock' => $stockActual - $cantSolicitada
        ];

        $detalleArticulos[] = $prod['nombre_producto'] . ", " . number_format($montoTotalItem, 0, ',', '.');
    }
    $stmtProd->close();

    $lineasObservacion = [
        $nombreComprador,
        $correoComprador,
        ($numeroComprador !== '' ? $numeroComprador : 'No registrado')
    ];

    foreach ($detalleArticulos as $lineaArticulo) {
        $lineasObservacion[] = $lineaArticulo;
    }

    if ($descripcionVenta !== '') {
        $lineasObservacion[] = $descripcionVenta;
    }

    $observacionFinal = implode("\n", $lineasObservacion);
    $fechaHoraActual = date("Y-m-d H:i:s");
    $tipoMovimiento = "venta";

    $stmtUpdateStock = $conexion->prepare("UPDATE producto SET stock_actual_producto = ? WHERE id_producto = ?");
    $stmtInsertMov = $conexion->prepare(
        "INSERT INTO movimiento (fecha_hora_movimiento, tipo_movimiento, cantidad_movimiento, observacion_movimiento, id_producto, id_usuario) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    foreach ($productosValidados as $item) {
        $stmtUpdateStock->bind_param("ii", $item['nuevo_stock'], $item['id_producto']);
        if (!$stmtUpdateStock->execute()) {
            throw new Exception("Error al descontar el stock del producto ID " . $item['id_producto']);
        }

        $montoRedondeado = (int)round($item['monto_total']);
        $stmtInsertMov->bind_param(
            "ssisii",
            $fechaHoraActual,
            $tipoMovimiento,
            $montoRedondeado,
            $observacionFinal,
            $item['id_producto'],
            $idUsuario
        );

        if (!$stmtInsertMov->execute()) {
            throw new Exception("Error al registrar el movimiento en la base de datos.");
        }
    }

    $stmtUpdateStock->close();
    $stmtInsertMov->close();

    $conexion->commit();
    header("location: inicio.php?venta=ok");
    exit();

} catch (Exception $e) {
    $conexion->rollback();
    header("location: nueva_orden.php?error=" . urlencode($e->getMessage()));
    exit();
}