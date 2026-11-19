<?php
session_start();
require_once 'db.php';

// Hàm ánh xạ tên sản phẩm với đúng file ảnh thực tế (tránh lỗi hiển thị ảnh khi thanh toán)
function getExactProductImage($productName, $variantName, $defaultImg) {
    if (!empty($defaultImg) && strpos($defaultImg, 'placeholder.com') === false && strpos($defaultImg, 'unsplash.com') === false) return $defaultImg;
    
    $name = mb_strtolower(trim($productName . ' ' . $variantName), 'UTF-8');
    
    if (strpos($name, 'áo thun') !== false) {
        if (strpos($name, 'đen') !== false) return 'assets/sanpham2.jpg';
        if (strpos($name, 'trắng') !== false) return 'assets/sanpham2.jpg';
        if (strpos($name, 'xám') !== false) return 'assets/sanpham4.jpg';
        if (strpos($name, 'navy') !== false || strpos($name, 'xanh navy') !== false) return 'assets/sanpham5.jpg';
        if (strpos($name, 'rêu') !== false || strpos($name, 'form rộng') !== false) return 'assets/sanpham24.jpg';
        return 'assets/sanpham2.jpg';
    }
    
    if (strpos($name, 'bình giữ nhiệt') !== false) return 'assets/sanpham1.jpg';
    if (strpos($name, 'henley') !== false || strpos($name, 'trụ') !== false || strpos($name, 'hồng') !== false) return 'assets/sanpham3.jpg';
    if (strpos($name, 'tất') !== false || strpos($name, 'vớ') !== false) return 'assets/sanpham6.jpg';
    if (strpos($name, 'chống nắng') !== false || strpos($name, 'găng tay') !== false) return 'assets/sanpham7.jpg';
    if (strpos($name, 'cổ lọ') !== false || strpos($name, 'len') !== false) return 'assets/sanpham8.jpg';
    if (strpos($name, 'đầm') !== false || strpos($name, 'vây liền') !== false || strpos($name, 'dạo phố') !== false) return 'assets/sanpham9.jpg';
    if (strpos($name, 'giày tây') !== false || strpos($name, 'm610') !== false) return 'assets/sanpham10.jpg';
    if (strpos($name, 'túi xách') !== false) return 'assets/sanpham11.jpg';
    if (strpos($name, 'quần đùi') !== false || strpos($name, 'short') !== false) return 'assets/sanpham12.jpg';
    if (strpos($name, 'sơ mi') !== false && strpos($name, 'trắng') !== false) return 'assets/sanpham13.jpg';
    if (strpos($name, 'thể thao') !== false && strpos($name, 'bộ') !== false) return 'assets/sanpham14.jpg';
    if (strpos($name, 'thắt lưng') !== false || strpos($name, 'dây nịt') !== false) return 'assets/sanpham15.jpg';
    if (strpos($name, 'kính') !== false) return 'assets/sanpham16.jpg';
    if (strpos($name, 'ví') !== false) return 'assets/sanpham17.jpg';
    if (strpos($name, 'bomber') !== false || strpos($name, 'khoác') !== false) return 'assets/sanpham18.jpg';
    if (strpos($name, 'mũ') !== false || strpos($name, 'nón') !== false) return 'assets/sanpham19.jpg';
    if (strpos($name, 'đồng hồ') !== false) return 'assets/sanpham20.jpg';
    if (strpos($name, 'balo') !== false) return 'assets/sanpham21.jpg';
    if (strpos($name, 'sneaker') !== false) return 'assets/sanpham22.jpg';
    if (strpos($name, 'jean') !== false) return 'assets/sanpham23.jpg';
    
    return !empty($defaultImg) ? $defaultImg : 'assets/sanpham1.jpg';
}

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

    // Ánh xạ ảnh chuẩn xác cho từng sản phẩm
    $resolved_image = getExactProductImage($p['product_name'] ?? '', $p['variant_name'] ?? '', $p['image_url'] ?? '');

    $cart_items[] = [
        'id' => $vid,
        'product_name' => $p['product_name'],
        'variant_name' => $p['variant_name'],
        'price' => $p['price'],
        'image_url' => $resolved_image,
        'quantity' => $qty,
        'subtotal' => $subtotal
    ];
}

// --- XỬ LÝ MÃ GIẢM GIÁ ĐÃ LƯU TỪ TRANG CHỦ ---
$discount_amount = 0;
$applied_coupon_code = $_SESSION['applied_coupon'] ?? '';

if ($applied_coupon_code === 'TRN30' && $total_amount >= 399000) {
    $discount_amount = 30000;
} elseif ($applied_coupon_code === 'TRN60' && $total_amount >= 700000) {
    $discount_amount = 60000;
} elseif ($applied_coupon_code === 'TRN100' && $total_amount >= 1000000) {
    $discount_amount = 100000;
} elseif ($applied_coupon_code === 'TRN150' && $total_amount >= 1300000) {
    $discount_amount = 150000;
}

$final_total_amount = max(0, $total_amount - $discount_amount);

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
                $fields[] = 'total_amount'; $values[] = '?'; $params[] = $final_total_amount;
            } elseif (in_array('total_price', $tableCols)) {
                $fields[] = 'total_price'; $values[] = '?'; $params[] = $final_total_amount;
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
            if (in_array('expires_at', $tableCols)) {
                $fields[] = 'expires_at'; $values[] = 'DATE_ADD(NOW(), INTERVAL 1 HOUR)';
            }

            if (!empty($fields)) {
                $sql = "INSERT INTO orders (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
                $orderStmt = $pdo->prepare($sql);
                $orderStmt->execute($params);
                $order_id = $pdo->lastInsertId();
            } else {
                throw new Exception("Bảng orders không khớp cấu trúc.");
            }

            // Lưu chi tiết sản phẩm vào order_items (Đã loại bỏ cột price để khớp với CSDL hiện tại)
            $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, variant_id, quantity) VALUES (?, ?, ?)");
            foreach ($cart_items as $item) {
                $itemStmt->execute([$order_id, $item['id'], intval($item['quantity'])]);
            }

            // Trừ tồn kho đồng thời giữ hàng
            $stockStmt = $pdo->prepare("UPDATE variants SET available_stock = available_stock - ? WHERE id = ?");
            foreach ($cart_items as $item) {
                $stockStmt->execute([intval($item['quantity']), $item['id']]);
            }

            $pdo->commit();

            unset($_SESSION['cart']);
            unset($_SESSION['applied_coupon']);

            echo "<script>alert('Đặt hàng thành công! Đơn hàng được giữ trong vòng 1 tiếng.'); window.location='order_history.php';</script>";
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
    <title>Thanh Toán Đơn Hàng - ANHNGUYEN</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f5f7; color: #333; }
        .top-announcement-bar { background: #111; color: #ccc; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; padding: 8px 20px; display: flex; justify-content: space-around; align-items: center; border-bottom: 1px solid #222; }
        .top-announcement-bar span span { color: #e74c3c; font-weight: 700; margin-right: 5px; }
        header { background: linear-gradient(135deg, #111111 0%, #1a1a1a 100%); color: #fff; padding: 14px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.4); border-bottom: 1px solid rgba(255,255,255,0.08); }
        .logo-area a { text-decoration: none; font-size: 24px; font-weight: 900; color: #fff; letter-spacing: 2.5px; text-transform: uppercase; }
        .logo-area span { color: #e74c3c; }
        .header-search { display: flex; align-items: center; background: rgba(255, 255, 255, 0.08); border-radius: 30px; padding: 4px 6px 4px 16px; width: 420px; border: 1px solid rgba(255, 255, 255, 0.15); }
        .header-search input { border: none; background: transparent; outline: none; width: 100%; padding: 6px 0; font-size: 13px; color: #fff; }
        .header-search button { background: #e74c3c; color: #fff; border: none; border-radius: 50%; cursor: pointer; font-size: 13px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; }
        .header-nav { display: flex; align-items: center; gap: 15px; }
        .header-nav a { color: #dcdcdc; text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 6px; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.06); }
        .badge { background: #e74c3c; color: #fff; padding: 2px 7px; border-radius: 10px; font-size: 11px; font-weight: 800; }
        .sub-menu-bar { background: #ffffff; padding: 0 50px; display: flex; justify-content: center; gap: 35px; align-items: center; height: 48px; border-bottom: 1px solid #eaeaea; box-shadow: 0 2px 6px rgba(0,0,0,0.03); }
        .sub-menu-bar a { color: #222; text-decoration: none; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; display: flex; align-items: center; gap: 6px; }
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .page-title { font-size: 24px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 30px; letter-spacing: 0.5px; border-left: 4px solid #111; padding-left: 12px; }
        .checkout-layout { display: grid; grid-template-columns: 1.3fr 1fr; gap: 30px; align-items: start; }
        @media (max-width: 900px) { .checkout-layout { grid-template-columns: 1fr; } }
        .checkout-form-box { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; }
        .box-title { font-size: 18px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 20px; border-bottom: 2px solid #f1f3f5; padding-bottom: 12px; }
        .alert-error { background: #fff5f5; color: #e53e3e; padding: 12px 15px; border-radius: 6px; border-left: 4px solid #e53e3e; font-size: 13px; font-weight: 600; margin-bottom: 20px; word-break: break-all; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #333; margin-bottom: 7px; text-transform: uppercase; letter-spacing: 0.4px; }
        .form-control { width: 100%; padding: 12px 15px; font-size: 14px; border: 1px solid #dcdcdc; border-radius: 6px; background: #fafafa; color: #111; outline: none; }
        .order-summary-box { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; position: sticky; top: 90px; }
        .product-mini-list { max-height: 280px; overflow-y: auto; margin-bottom: 20px; padding-right: 5px; }
        .product-mini-item { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #f1f3f5; }
        .mini-img { width: 60px; height: 65px; object-fit: cover; border-radius: 6px; border: 1px solid #eee; background: #f1f3f5; }
        .mini-info { flex: 1; }
        .mini-name { font-size: 14px; font-weight: 700; color: #111; margin-bottom: 3px; }
        .mini-variant { font-size: 12px; color: #666; background: #f1f3f5; padding: 2px 6px; border-radius: 4px; display: inline-block; }
        .mini-price { font-size: 14px; font-weight: 800; color: #e74c3c; text-align: right; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; color: #555; }
        .summary-row.total { font-size: 18px; font-weight: 800; color: #111; border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px; }
        .summary-row.total .total-price { color: #e74c3c; }
        .btn-submit-order { display: block; width: 100%; background: #111; color: #fff; text-align: center; padding: 15px; border: none; border-radius: 6px; font-weight: 800; font-size: 15px; text-transform: uppercase; letter-spacing: 0.8px; cursor: pointer; margin-top: 20px; }
        .btn-submit-order:hover { background: #e74c3c; }
        .back-cart { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>
<div class="top-announcement-bar">
    <span><span>★</span> Đổi hàng trong 30 ngày</span>
    <span><span>★</span> Freeship mọi đơn hàng từ 500K</span>
    <span><span>★</span> Voucher lớn - Sale giá độc quyền</span>
</div>
<header>
    <div class="logo-area"><a href="index.php">ANHNGUYEN<span>.</span></a></div>
    <form method="GET" action="index.php" class="header-search">
        <input type="text" name="search" placeholder="Bạn đang tìm gì...">
        <button type="submit">🔍</button>
    </form>
    <div class="header-nav">
        <?php if (isset($_SESSION['user'])): ?>
            <span style="font-size: 13px; color: #ccc;">👤 <strong><?= htmlspecialchars($_SESSION['user']['fullname']) ?></strong></span>
        <?php endif; ?>
        <a href="order_history.php">📦 Đơn Hàng</a>
        <a href="cart.php">🛒 Giỏ hàng <span class="badge"><?= $cart_count ?></span></a>
        <a href="logout.php" style="color: #ff7675;">Đăng xuất</a>
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
            <div class="summary-row total"><span>Tổng thanh toán:</span><span class="total-price"><?= number_format($final_total_amount, 0, ',', '.') ?>đ</span></div>
            <button type="submit" class="btn-submit-order">Xác Nhận Đặt Hàng (Giữ hàng 1 giờ)</button>
            <a href="cart.php" class="back-cart">← Quay lại giỏ hàng</a>
        </div>
    </form>
</div>
</body>
</html>