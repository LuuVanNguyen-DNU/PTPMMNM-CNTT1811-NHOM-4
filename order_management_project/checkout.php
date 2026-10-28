<?php
session_start();
require_once 'db.php';

// Nếu giỏ hàng trống, chuyển hướng về trang chủ
if (empty($_SESSION['cart'])) {
    echo "<script>alert('Giỏ hàng của bạn đang trống!'); window.location='index.php';</script>";
    exit;
}

// Lấy thông tin chi tiết sản phẩm trong giỏ hàng
$cart_items = [];
$total_amount = 0;
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
        'subtotal' => $subtotal
    ];
}

// Xử lý khi người dùng bấm nút Đặt Hàng
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($fullname === '' || $phone === '' || $address === '') {
        $error = 'Vui lòng điền đầy đủ họ tên, số điện thoại và địa chỉ nhận hàng!';
    } else {
        try {
            $pdo->beginTransaction();

            $order_code = 'ORD-' . strtoupper(substr(uniqid(), -6));
            $username = $_SESSION['user']['fullname'] ?? ($_SESSION['user']['username'] ?? $fullname);
            $user_id = $_SESSION['user']['id'] ?? null;

            // Kiểm tra các cột thực tế đang có trong bảng orders
            $colQuery = $pdo->query("SHOW COLUMNS FROM orders");
            $tableCols = $colQuery->fetchAll(PDO::FETCH_COLUMN);

            $fields = [];
            $values = [];
            $params = [];

            if (in_array('order_code', $tableCols)) {
                $fields[] = 'order_code'; $values[] = '?'; $params[] = $order_code;
            }
            if (in_array('customer_name', $tableCols)) {
                $fields[] = 'customer_name'; $values[] = '?'; $params[] = $username;
            } elseif (in_array('fullname', $tableCols)) {
                $fields[] = 'fullname'; $values[] = '?'; $params[] = $username;
            }
            if (in_array('total_amount', $tableCols)) {
                $fields[] = 'total_amount'; $values[] = '?'; $params[] = $total_amount;
            } elseif (in_array('total_price', $tableCols)) {
                $fields[] = 'total_price'; $values[] = '?'; $params[] = $total_amount;
            }
            if (in_array('status', $tableCols)) {
                $fields[] = 'status'; $values[] = '?'; $params[] = 'pending_payment';
            }
            if (in_array('user_id', $tableCols) && $user_id) {
                $fields[] = 'user_id'; $values[] = '?'; $params[] = $user_id;
            }
            if (in_array('created_at', $tableCols)) {
                $fields[] = 'created_at'; $values[] = 'NOW()';
            }

            if (!empty($fields)) {
                $sql = "INSERT INTO orders (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
                $orderStmt = $pdo->prepare($sql);
                $orderStmt->execute($params);
                $order_id = $pdo->lastInsertId();
            } else {
                throw new Exception("Bảng orders không khớp cấu trúc.");
            }

            // 2. Lưu chi tiết sản phẩm vào order_items
            try {
                $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, variant_id, quantity, price) VALUES (?, ?, ?, ?)");
                foreach ($cart_items as $item) {
                    $itemStmt->execute([$order_id, $item['id'], $item['quantity'], $item['price']]);
                }
            } catch (Exception $e) {}

            // 3. Trừ tồn kho
            try {
                $stockStmt = $pdo->prepare("UPDATE variants SET available_stock = available_stock - ? WHERE id = ?");
                foreach ($cart_items as $item) {
                    $stockStmt->execute([$item['quantity'], $item['id']]);
                }
            } catch (Exception $e) {}

            $pdo->commit();

            unset($_SESSION['cart']);

            echo "<script>alert('Đặt hàng thành công!'); window.location='order_history.php';</script>";
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Lỗi cơ sở dữ liệu MySQL: ' . $e->getMessage();
        }
    }
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thanh Toán Đơn Hàng - Cửa Hàng Thời Trang</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; }
        header { background: #151515; color: #fff; padding: 14px 50px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }
        .logo-area a { text-decoration: none; font-size: 24px; font-weight: 800; color: #fff; letter-spacing: 2px; text-transform: uppercase; }
        .logo-area span { color: #e74c3c; }
        .header-nav { display: flex; align-items: center; gap: 20px; }
        .header-nav a { color: #ccc; text-decoration: none; font-size: 13px; font-weight: 500; transition: color 0.2s; }
        .header-nav a:hover { color: #fff; }
        .badge { background: #f39c12; color: #000; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 800; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .page-title { font-size: 24px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 30px; letter-spacing: 0.5px; border-left: 4px solid #111; padding-left: 12px; }
        .checkout-layout { display: grid; grid-template-columns: 1.3fr 1fr; gap: 30px; align-items: start; }
        @media (max-width: 900px) { .checkout-layout { grid-template-columns: 1fr; } }
        .checkout-form-box { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; }
        .box-title { font-size: 18px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 20px; border-bottom: 2px solid #f1f3f5; padding-bottom: 12px; }
        .alert-error { background: #fff5f5; color: #e53e3e; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #e53e3e; font-size: 13px; font-weight: 600; margin-bottom: 20px; word-break: break-all; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #333; margin-bottom: 7px; text-transform: uppercase; letter-spacing: 0.4px; }
        .form-control { width: 100%; padding: 12px 15px; font-size: 14px; border: 1px solid #dcdcdc; border-radius: 6px; background: #fafafa; color: #111; outline: none; transition: all 0.25s ease; }
        .form-control:focus { background: #fff; border-color: #111; box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.06); }
        .order-summary-box { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; position: sticky; top: 90px; }
        .product-mini-list { max-height: 280px; overflow-y: auto; margin-bottom: 20px; padding-right: 5px; }
        .product-mini-item { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #f1f3f5; }
        .product-mini-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .mini-img { width: 60px; height: 65px; object-fit: cover; border-radius: 6px; border: 1px solid #eee; background: #f1f3f5; }
        .mini-info { flex: 1; }
        .mini-name { font-size: 14px; font-weight: 700; color: #111; margin-bottom: 3px; }
        .mini-variant { font-size: 12px; color: #666; background: #f1f3f5; padding: 2px 6px; border-radius: 4px; display: inline-block; }
        .mini-price { font-size: 14px; font-weight: 800; color: #e74c3c; text-align: right; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; color: #555; }
        .summary-row.total { font-size: 18px; font-weight: 800; color: #111; border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px; }
        .summary-row.total .total-price { color: #e74c3c; }
        .btn-submit-order { display: block; width: 100%; background: #111; color: #fff; text-align: center; padding: 15px; border: none; border-radius: 6px; font-weight: 800; font-size: 15px; text-transform: uppercase; letter-spacing: 0.8px; cursor: pointer; margin-top: 20px; transition: background 0.2s, transform 0.1s; }
        .btn-submit-order:hover { background: #e74c3c; }
        .back-cart { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; font-size: 13px; font-weight: 600; transition: color 0.2s; }
        .back-cart:hover { color: #111; }
    </style>
</head>
<body>

<header>
    <div class="logo-area"><a href="index.php">TORANO<span>.</span></a></div>
    <div class="header-nav">
        <a href="cart.php">🛒 Giỏ hàng (<span class="badge"><?= $cart_count ?></span>)</a>
        <a href="index.php">Trang chủ</a>
    </div>
</header>

<div class="container">
    <h1 class="page-title">Tiến Hành Thanh Toán</h1>
    <?php if ($error !== ''): ?><div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="checkout.php" class="checkout-layout">
        <div class="checkout-form-box">
            <div class="box-title">Thông Tin Giao Hàng</div>
            <div class="form-group">
                <label for="fullname">Họ và tên người nhận</label>
                <input type="text" id="fullname" name="fullname" class="form-control" value="<?= htmlspecialchars($_POST['fullname'] ?? ($_SESSION['user']['fullname'] ?? '')) ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="phone">Số điện thoại liên hệ</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="address">Địa chỉ giao hàng chi tiết</label>
                <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="note">Ghi chú đơn hàng (Tùy chọn)</label>
                <textarea id="note" name="note" class="form-control" rows="3"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <label>Phương thức thanh toán</label>
                <div style="background: #f8f9fa; padding: 12px 15px; border-radius: 6px; border: 1px solid #ddd; font-size: 14px; font-weight: 600; color: #222;">💵 Thanh toán khi nhận hàng (COD)</div>
            </div>
        </div>
        <div class="order-summary-box">
            <div class="box-title">Đơn Hàng Của Bạn (<?= count($cart_items) ?> sản phẩm)</div>
            <div class="product-mini-list">
                <?php foreach ($cart_items as $item): ?>
                <div class="product-mini-item">
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="Ảnh sản phẩm" class="mini-img">
                    <div class="mini-info">
                        <div class="mini-name"><?= htmlspecialchars($item['product_name']) ?></div>
                        <div class="mini-variant"><?= htmlspecialchars($item['variant_name']) ?> (x<?= $item['quantity'] ?>)</div>
                    </div>
                    <div class="mini-price"><?= number_format($item['subtotal'], 0, ',', '.') ?>đ</div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="summary-row"><span>Tạm tính:</span><span><strong><?= number_format($total_amount, 0, ',', '.') ?>đ</strong></span></div>
            <div class="summary-row"><span>Phí vận chuyển:</span><span>Miễn phí giao hàng</span></div>
            <div class="summary-row total"><span>Tổng thanh toán:</span><span class="total-price"><?= number_format($total_amount, 0, ',', '.') ?>đ</span></div>
            <button type="submit" class="btn-submit-order">Xác Nhận Đặt Hàng</button>
            <a href="cart.php" class="back-cart">← Quay lại giỏ hàng</a>
        </div>
    </form>
</div>
</body>
</html>