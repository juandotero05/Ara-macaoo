<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'cliente') {
    header('Location: ../auth/login.php');
    exit();
}

$carrito = $_SESSION['carrito'] ?? [];
if (empty($carrito)) {
    header('Location: ../views/productos/ver_carrito.php');
    exit();
}

$usuario_id = $_SESSION['usuario']['id'];
$total = 0;
$subtotal = 0;
$descuento = 0;
$valorPunto = 1000; 

try {
    $pdo->beginTransaction();

   
    foreach ($carrito as $item) {
        $subtotal += $item['precio'] * $item['cantidad'];
    }

   
    if ($subtotal >= 50000) {
        $descuento = $subtotal * 0.10; // 10%
    }

    $total = $subtotal - $descuento;

    
    $puntosGanados = floor($total / $valorPunto);


    $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, total) VALUES (?, ?)");
    $stmt->execute([$usuario_id, $total]);
    $pedido_id = $pdo->lastInsertId();


    $detalle_stmt = $pdo->prepare("INSERT INTO detalle_pedido (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");

    foreach ($carrito as $producto_id => $item) {
        $detalle_stmt->execute([
            $pedido_id,
            $producto_id,
            $item['cantidad'],
            $item['precio']
        ]);
    }


    $updatePuntos = $pdo->prepare("UPDATE usuarios SET puntos = puntos + ? WHERE id = ?");
    $updatePuntos->execute([$puntosGanados, $usuario_id]);

    $_SESSION['usuario']['puntos'] += $puntosGanados;

    $pdo->commit();
    unset($_SESSION['carrito']); 

    
    header("Location: ../views/pedidos/mis_pedidos.php?pedido=exito&desc=" . ($descuento > 0 ? "1" : "0"));
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p style='color:red; text-align:center;'>Error al procesar el pedido: " . htmlspecialchars($e->getMessage()) . "</p>";
}
