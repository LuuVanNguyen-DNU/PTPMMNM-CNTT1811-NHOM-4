<?php
session_start();
require_once 'db.php';

if (isset($_GET['id']) || isset($_POST['id']) || isset($_POST['variant_id'])) {
    $variant_id = intval($_POST['variant_id'] ?? ($_GET['variant_id'] ?? ($_POST['id'] ?? $_GET['id'])));
    
    // Lấy đúng số lượng người dùng chọn từ trang chi tiết (mặc định là 1 nếu không truyền lên)
    $quantity = intval($_POST['quantity'] ?? ($_GET['quantity'] ?? 1));
    if ($quantity < 1) { $quantity = 1; }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Cộng dồn số lượng nếu sản phẩm đã có trong giỏ hàng
    if (isset($_SESSION['cart'][$variant_id])) {
        $_SESSION['cart'][$variant_id] += $quantity;
    } else {
        $_SESSION['cart'][$variant_id] = $quantity;
    }

    header('Location: cart.php');
    exit;
}