<?php
session_start();
require_once 'db.php';

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// Lấy thông tin tài khoản đang đăng nhập
$current_user = $_SESSION['user']['fullname'] ?? ($_SESSION['user']['username'] ?? '');
$current_user_id = $_SESSION['user']['id'] ?? null;
$is_admin = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin');

// Lấy danh sách đơn hàng chỉ thuộc về user đó (hoặc Admin nếu muốn xem tất cả)
$orders = [];
try {
    // Kiểm tra xem bảng orders có cột user_id không
    $colQuery = $pdo->query("SHOW COLUMNS FROM orders");
    $tableCols = $colQuery->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('user_id', $tableCols) && $current_user_id) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$current_user_id]);
        $orders = $stmt->fetchAll();
    } elseif (in_array('customer_name', $tableCols) && $current_user) {
        // Lọc theo tên khách hàng khớp với tài khoản đăng nhập
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_name = ? ORDER BY id DESC");
        $stmt->execute([$current_user]);
        $orders = $stmt->fetchAll();
    } else {
        // Fallback: nếu chưa khớp được trường lọc, lấy toàn bộ hoặc rỗng
        $orders = [];
    }
} catch (Exception $e) {
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch Sử Đơn Hàng - Cửa Hàng Thời Trang</title>
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
        .back-link { display: inline-block; margin-bottom: 20px; color: #555; text-decoration: none; font-weight: 600; font-size: 14px; transition: color 0.2s; }
        .back-link:hover { color: #111; }
        .card-box { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; overflow: hidden; padding: 25px; }
        .box-heading { font-size: 18px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 20px; border-bottom: 2px solid #f1f3f5; padding-bottom: 12px; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #111; color: #fff; padding: 14px 16px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        td { padding: 15px 16px; border-bottom: 1px solid #f1f3f5; font-size: 14px; color: #444; }
        tr:hover td { background: #fafafa; }
        tr:last-child td { border-bottom: none; }
        .order-code { font-weight: 700; color: #111; }
        .order-price { font-weight: 800; color: #e74c3c; }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; text-align: center; }
        .status-processing { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .empty-history { text-align: center; padding: 50px 20px; color: #777; font-size: 15px; }
    </style>
</head>
<body>

<header>
    <div class="logo-area"><a href="index.php">TORANO<span>.</span></a></div>
    <div class="header-nav">
        <?php if (isset($_SESSION['user'])): ?>
            <span style="color: #ccc;">Xin chào, <strong><?= htmlspecialchars($_SESSION['user']['fullname']) ?></strong></span>
        <?php endif; ?>
        <a href="index.php">Trang Chủ</a>
        <a href="cart.php">🛒 Giỏ Hàng <span class="badge"><?= $cart_count ?></span></a>
        <?php if (isset($_SESSION['user'])): ?>
            <a href="logout.php" style="color: #ff7675;">Đăng Xuất</a>
        <?php endif; ?>
    </div>
</header>

<div class="container">
    <a href="index.php" class="back-link">← Quay lại trang chủ cửa hàng</a>
    
    <div class="card-box">
        <div class="box-heading">Lịch Sử Đơn Hàng Của Bạn</div>

        <?php if (empty($orders)): ?>
            <div class="empty-history">
                <p>Bạn chưa có đơn hàng nào thuộc tài khoản này!</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Mã Đơn Hàng</th>
                            <th>Tổng Tiền</th>
                            <th>Trạng Thái</th>
                            <th>Hết Hạn Giữ Chỗ</th>
                            <th>Ngày Tạo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $ord): 
                            $raw_status = strtolower($ord['status'] ?? 'pending');
                            if (in_array($raw_status, ['completed', 'success', 'paid', 'da_xu_ly'])) {
                                $status_text = 'Đã Xử Lý';
                                $status_class = 'status-completed';
                            } else {
                                $status_text = 'Chưa Xử Lý';
                                $status_class = 'status-processing';
                            }
                        ?>
                        <tr>
                            <td><span class="order-code"><?= htmlspecialchars($ord['order_code'] ?? 'ORD-' . strtoupper(substr(md5($ord['id']), 0, 6))) ?></span></td>
                            <td><span class="order-price"><?= number_format($ord['total_amount'] ?? 0, 0, ',', '.') ?>đ</span></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                            <td style="color: #666; font-size: 13px;"><?= htmlspecialchars($ord['expires_at'] ?? '---') ?></td>
                            <td style="color: #666; font-size: 13px;"><?= htmlspecialchars($ord['created_at'] ?? '---') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>