<?php
session_start();
require_once 'db.php';

// Xử lý cập nhật số lượng hoặc xóa sản phẩm trong giỏ
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id'] ?? 0);

    if ($action === 'delete') {
        unset($_SESSION['cart'][$id]);
    } elseif ($action === 'update') {
        $qty = intval($_GET['qty'] ?? 1);
        if ($qty > 0) {
            $_SESSION['cart'][$id] = $qty;
        } else {
            unset($_SESSION['cart'][$id]);
        }
    }
    header('Location: cart.php');
    exit;
}

// Lấy thông tin chi tiết các sản phẩm có trong giỏ hàng từ CSDL
$cart_items = [];
$total_amount = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $pdo->prepare("SELECT v.*, p.name AS product_name FROM variants v JOIN products p ON v.product_id = p.id WHERE v.id IN ($placeholders)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $p) {
        $vid = $p['id'];
        $qty = $_SESSION['cart'][$vid];
        $subtotal = $p['price'] * $qty;
        $total_amount += $subtotal;

        $cart_items[] = [
            'id' => $vid,
            'product_name' => $p['product_name'],
            'variant_name' => $p['variant_name'],
            'price' => $p['price'],
            'image_url' => $p['image_url'] ?? 'https://via.placeholder.com/150',
            'quantity' => $qty,
            'subtotal' => $subtotal,
            'available_stock' => $p['available_stock']
        ];
    }
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ Hàng - Cửa Hàng Thời Trang</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; }
        
        /* Header đồng bộ */
        header { background: #151515; color: #fff; padding: 14px 50px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }
        .logo-area a { text-decoration: none; font-size: 24px; font-weight: 800; color: #fff; letter-spacing: 2px; text-transform: uppercase; }
        .logo-area span { color: #e74c3c; }
        .header-nav { display: flex; align-items: center; gap: 20px; }
        .header-nav a { color: #ccc; text-decoration: none; font-size: 13px; font-weight: 500; transition: color 0.2s; }
        .header-nav a:hover { color: #fff; }
        .badge { background: #f39c12; color: #000; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 800; }

        /* Container chính */
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .page-title { font-size: 24px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 30px; letter-spacing: 0.5px; border-left: 4px solid #111; padding-left: 12px; }

        /* Khi giỏ hàng trống */
        .empty-cart { background: #fff; padding: 60px 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; }
        .empty-cart-icon { font-size: 60px; margin-bottom: 20px; color: #ccc; }
        .empty-cart h2 { font-size: 20px; color: #222; margin-bottom: 10px; font-weight: 700; }
        .empty-cart p { color: #666; font-size: 14px; margin-bottom: 25px; }
        .btn-shop-now { display: inline-block; background: #111; color: #fff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 14px; text-transform: uppercase; transition: background 0.2s; }
        .btn-shop-now:hover { background: #e74c3c; }

        /* Bố cục giỏ hàng 2 cột */
        .cart-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start; }
        @media (max-width: 900px) { .cart-layout { grid-template-columns: 1fr; } }

        /* Danh sách sản phẩm */
        .cart-items-list { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; overflow: hidden; }
        .cart-item { display: flex; align-items: center; padding: 20px; border-bottom: 1px solid #f1f3f5; gap: 20px; }
        .cart-item:last-child { border-bottom: none; }
        .item-img { width: 90px; height: 100px; object-fit: cover; border-radius: 8px; background: #f1f3f5; border: 1px solid #eee; flex-shrink: 0; }
        
        .item-details { flex: 1; }
        .item-name { font-size: 16px; font-weight: 700; color: #111; text-decoration: none; margin-bottom: 5px; display: block; }
        .item-name:hover { color: #e74c3c; }
        .item-variant { font-size: 13px; color: #666; background: #f1f3f5; padding: 3px 8px; border-radius: 4px; display: inline-block; margin-bottom: 8px; font-weight: 500; }
        .item-price { font-size: 15px; font-weight: 700; color: #e74c3c; }

        /* Điều chỉnh số lượng */
        .item-quantity { display: flex; align-items: center; gap: 8px; }
        .qty-btn { width: 30px; height: 30px; background: #f1f3f5; border: 1px solid #ddd; border-radius: 4px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none; color: #333; transition: 0.2s; }
        .qty-btn:hover { background: #111; color: #fff; border-color: #111; }
        .qty-display { font-size: 14px; font-weight: 700; width: 35px; text-align: center; }

        .item-subtotal { font-size: 16px; font-weight: 800; color: #111; text-align: right; min-width: 110px; }
        .btn-delete { color: #999; text-decoration: none; font-size: 18px; transition: color 0.2s; padding: 5px; }
        .btn-delete:hover { color: #e74c3c; }

        /* Khung tổng tiền (Order Summary) */
        .cart-summary { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; position: sticky; top: 90px; }
        .summary-title { font-size: 18px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 20px; border-bottom: 2px solid #f1f3f5; padding-bottom: 12px; }
        
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 14px; color: #555; }
        .summary-row.total { font-size: 18px; font-weight: 800; color: #111; border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px; }
        .summary-row.total .total-price { color: #e74c3c; }

        .btn-checkout { display: block; width: 100%; background: #111; color: #fff; text-align: center; padding: 15px; border-radius: 6px; text-decoration: none; font-weight: 800; font-size: 15px; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 20px; transition: background 0.2s, transform 0.1s; }
        .btn-checkout:hover { background: #e74c3c; }
        .btn-checkout:active { transform: scale(0.99); }

        .back-shopping { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; font-size: 13px; font-weight: 600; transition: color 0.2s; }
        .back-shopping:hover { color: #111; }
    </style>
</head>
<body>

<header>
    <div class="logo-area">
        <a href="index.php">TORANO<span>.</span></a>
    </div>
    <div class="header-nav">
        <a href="cart.php" style="color:#fff; font-weight:700;">🛒 Giỏ hàng (<span class="badge"><?= $cart_count ?></span>)</a>
        <a href="index.php">Trang chủ</a>
    </div>
</header>

<div class="container">
    <h1 class="page-title">Giỏ Hàng Của Bạn</h1>

    <?php if (empty($cart_items)): ?>
        <!-- Giao diện khi giỏ hàng trống cực đẹp -->
        <div class="empty-cart">
            <div class="empty-cart-icon">🛒</div>
            <h2>Giỏ hàng của bạn đang trống!</h2>
            <p>Hãy khám phá ngay các bộ sưu tập thời trang mới nhất của chúng tôi nhé.</p>
            <a href="index.php" class="btn-shop-now">Khám phá sản phẩm ngay</a>
        </div>
    <?php else: ?>
        <!-- Giao diện khi có sản phẩm (Bố cục 2 cột) -->
        <div class="cart-layout">
            <!-- Cột trái: Danh sách sản phẩm -->
            <div class="cart-items-list">
                <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="Ảnh sản phẩm" class="item-img">
                    
                    <div class="item-details">
                        <a href="product_detail.php?id=<?= $item['id'] ?>" class="item-name"><?= htmlspecialchars($item['product_name']) ?></a>
                        <span class="item-variant">Phân loại: <?= htmlspecialchars($item['variant_name']) ?></span>
                        <div class="item-price"><?= number_format($item['price'], 0, ',', '.') ?>đ</div>
                    </div>

                    <!-- Nút tăng giảm số lượng -->
                    <div class="item-quantity">
                        <a href="cart.php?action=update&id=<?= $item['id'] ?>&qty=<?= $item['quantity'] - 1 ?>" class="qty-btn">-</a>
                        <span class="qty-display"><?= $item['quantity'] ?></span>
                        <a href="cart.php?action=update&id=<?= $item['id'] ?>&qty=<?= $item['quantity'] + 1 ?>" class="qty-btn">+</a>
                    </div>

                    <div class="item-subtotal">
                        <?= number_format($item['subtotal'], 0, ',', '.') ?>đ
                    </div>

                    <!-- Nút xóa sản phẩm -->
                    <a href="cart.php?action=delete&id=<?= $item['id'] ?>" class="btn-delete" title="Xóa sản phẩm">🗑️</a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Cột phải: Tổng tiền thanh toán -->
            <div class="cart-summary">
                <div class="summary-title">Thông Tin Đơn Hàng</div>
                
                <div class="summary-row">
                    <span>Tạm tính:</span>
                    <span><strong><?= number_format($total_amount, 0, ',', '.') ?>đ</strong></span>
                </div>
                
                <div class="summary-row">
                    <span>Phí vận chuyển:</span>
                    <span>Miễn phí giao hàng</span>
                </div>

                <div class="summary-row total">
                    <span>Tổng cộng:</span>
                    <span class="total-price"><?= number_format($total_amount, 0, ',', '.') ?>đ</span>
                </div>

                <a href="checkout.php" class="btn-checkout">Tiến Hành Thanh Toán</a>
                <a href="index.php" class="back-shopping">← Tiếp tục mua sắm</a>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>