<?php
session_start();
require_once 'db.php';

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_user_id = $_SESSION['user']['id'] ?? null;
$is_admin = (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin');

// Lấy thông tin đơn hàng
$order = null;
if ($order_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
}

if (!$order) {
    echo "<script>alert('Đơn hàng không tồn tại!'); window.location='order_history.php';</script>";
    exit;
}

// Lấy danh sách sản phẩm trong đơn hàng (order_items) kèm thông tin biến thể và sản phẩm
$items = [];
try {
    $itemStmt = $pdo->prepare("SELECT oi.*, p.name AS product_name, v.variant_name, p.image_url 
                                FROM order_items oi 
                                LEFT JOIN variants v ON oi.variant_id = v.id 
                                LEFT JOIN products p ON v.product_id = p.id 
                                WHERE oi.order_id = ?");
    $itemStmt->execute([$order_id]);
    $items = $itemStmt->fetchAll();
} catch (Exception $e) {
    $items = [];
}

// Lấy lịch sử đổi trả của đơn hàng này
$returns = [];
try {
    $returnStmt = $pdo->prepare("SELECT r.*, ri.quantity as return_qty, oi.id as item_id 
                                 FROM returns r 
                                 LEFT JOIN return_items ri ON r.id = ri.return_id 
                                 LEFT JOIN order_items oi ON ri.order_item_id = oi.id 
                                 WHERE r.order_id = ?");
    $returnStmt->execute([$order_id]);
    $returns = $returnStmt->fetchAll();
} catch (Exception $e) {
    $returns = [];
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$raw_status = strtolower($order['status'] ?? 'pending');
$is_completed = in_array($raw_status, ['completed', 'success', 'paid', 'da_xu_ly']);
$is_cancelled = in_array($raw_status, ['cancelled', 'da_huy']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi Tiết Đơn Hàng - Torano</title>
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
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #555; text-decoration: none; font-weight: 600; font-size: 14px; }
        .back-link:hover { color: #111; }
        .card-box { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; padding: 30px; margin-bottom: 30px; }
        .box-heading { font-size: 18px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 20px; border-bottom: 2px solid #f1f3f5; padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 25px; font-size: 14px; }
        .info-item { background: #fafafa; padding: 12px 15px; border-radius: 6px; border: 1px solid #eee; }
        .info-item span { color: #777; display: block; font-size: 12px; margin-bottom: 4px; text-transform: uppercase; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 10px; }
        th { background: #111; color: #fff; padding: 12px 14px; font-size: 12px; text-transform: uppercase; font-weight: 700; }
        td { padding: 12px 14px; border-bottom: 1px solid #f1f3f5; font-size: 14px; color: #444; vertical-align: middle; }
        .item-img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-processing { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .total-box { text-align: right; margin-top: 20px; font-size: 16px; font-weight: 700; color: #111; }
        .total-box span { color: #e74c3c; font-size: 20px; font-weight: 800; margin-left: 10px; }
        .btn-action { background: #e74c3c; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-block; }
        .btn-action:hover { background: #c0392b; }
    </style>
</head>
<body>

<header>
    <div class="logo-area"><a href="index.php">TORANO<span>.</span></a></div>
    <div class="header-nav">
        <a href="order_history.php">Đơn Hàng</a>
        <a href="cart.php">🛒 Giỏ Hàng <span class="badge"><?= $cart_count ?></span></a>
        <?php if (isset($_SESSION['user'])): ?><a href="logout.php" style="color: #ff7675;">Đăng Xuất</a><?php endif; ?>
    </div>
</header>

<div class="container">
    <a href="<?= $is_admin ? 'admin.php' : 'order_history.php' ?>" class="back-link">← Quay lại danh sách đơn hàng</a>

    <div class="card-box">
        <div class="box-heading">
            Chi Tiết Đơn Hàng: <?= htmlspecialchars($order['order_code'] ?? ('ORD-' . $order['id'])) ?>
            <?php if ($is_completed): ?>
                <span class="status-badge status-completed">Đã Xử Lý</span>
            <?php elseif ($is_cancelled): ?>
                <span class="status-badge status-cancelled">Đã Hủy</span>
            <?php else: ?>
                <span class="status-badge status-processing">Chưa Xử Lý</span>
            <?php endif; ?>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <span>Khách hàng nhận</span>
                <strong><?= htmlspecialchars($order['customer_name'] ?? ($order['fullname'] ?? 'Khách lẻ')) ?></strong>
            </div>
            <div class="info-item">
                <span>Ngày tạo đơn</span>
                <strong><?= htmlspecialchars($order['created_at'] ?? '---') ?></strong>
            </div>
            <div class="info-item">
                <span>Thời hạn giữ chỗ</span>
                <strong><?= htmlspecialchars($order['expires_at'] ?? 'Không giới hạn') ?></strong>
            </div>
            <div class="info-item">
                <span>Phương thức thanh toán</span>
                <strong>Thanh toán khi nhận hàng (COD)</strong>
            </div>
        </div>

        <h3 style="font-size: 15px; font-weight: 700; text-transform: uppercase; margin-bottom: 10px; color: #222;">Danh Sách Mặt Hàng Đã Đặt</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Tên Sản Phẩm</th>
                        <th>Phân Loại</th>
                        <th>Đơn Giá</th>
                        <th>Số Lượng Mua</th>
                        <th>Thành Tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total = 0;
                    foreach ($items as $it): 
                        $qty = intval($it['quantity'] ?? 1);
                        $price = floatval($it['price'] ?? 0);
                        $sub = $price * $qty;
                        $grand_total += $sub;
                    ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($it['image_url'] ?? 'https://via.placeholder.com/50') ?>" alt="Ảnh" class="item-img"></td>
                        <td><strong><?= htmlspecialchars($it['product_name'] ?? 'Sản phẩm thời trang') ?></strong></td>
                        <td><span style="background: #f1f3f5; padding: 3px 8px; border-radius: 4px; font-size: 12px;"><?= htmlspecialchars($it['variant_name'] ?? 'Tiêu chuẩn') ?></span></td>
                        <td><?= number_format($price, 0, ',', '.') ?>đ</td>
                        <td><strong><?= $qty ?></strong></td>
                        <td><strong style="color: #e74c3c;"><?= number_format($sub, 0, ',', '.') ?>đ</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="total-box">
            Tổng Giá Trị Đơn Hàng: <span><?= number_format($order['total_amount'] ?? $grand_total, 0, ',', '.') ?>đ</span>
        </div>
    </div>

    <!-- KHUNG KIỂM TRA LƯỢNG ĐÃ TRẢ / ĐỔI TRẢ -->
    <div class="card-box">
        <div class="box-heading" style="font-size: 16px;">Kiểm Tra Lượng Đã Giao & Yêu Cầu Trả Một Phần</div>
        <?php if (empty($returns)): ?>
            <p style="color: #666; font-size: 14px; font-style: italic;">Đơn hàng này chưa có yêu cầu đổi trả hoặc trả hàng nào.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Mã Yêu Cầu</th>
                        <th>Lý Do Đổi Trả</th>
                        <th>Số Lượng Đã Trả</th>
                        <th>Tiền Hoàn Lại</th>
                        <th>Trạng Thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($returns as $ret): ?>
                    <tr>
                        <td>#<?= $ret['id'] ?></td>
                        <td><?= htmlspecialchars($ret['reason']) ?></td>
                        <td><strong><?= $ret['return_qty'] ?? 1 ?> sản phẩm</strong></td>
                        <td><strong style="color: #e74c3c;"><?= number_format($ret['refund_amount'] ?? 0, 0, ',', '.') ?>đ</strong></td>
                        <td>
                            <span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; background: <?= ($ret['status'] == 'approved') ? '#d4edda' : '#fff3cd' ?>; color: <?= ($ret['status'] == 'approved') ? '#155724' : '#856404' ?>;">
                                <?= ($ret['status'] == 'approved') ? 'Đã Duyệt / Hoàn Tiền' : 'Chờ Xử Lý' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($is_completed && empty($returns)): ?>
            <div style="margin-top: 20px;">
                <a href="return_order.php?order_id=<?= $order['id'] ?>" class="btn-action">Gửi Yêu Cầu Đổi Trả Hàng</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>