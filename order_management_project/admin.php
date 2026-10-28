<?php
session_start();
require_once 'db.php';

// Kiểm tra quyền Admin (nếu cần thiết)
// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') { ... }

// Xử lý cập nhật trạng thái đơn hàng nếu có yêu cầu
if (isset($_GET['action']) && $_GET['action'] === 'update_status' && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    try {
        $updateStmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
        $updateStmt->execute([$order_id]);
    } catch (Exception $e) {
        // Bỏ qua nếu có lỗi cột
    }
    header('Location: admin.php');
    exit;
}

// Lấy danh sách đơn hàng từ CSDL
$orders = [];
$total_revenue = 0;
$pending_count = 0;
$completed_count = 0;

try {
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
    $orders = $stmt->fetchAll();

    foreach ($orders as $ord) {
        $amount = floatval($ord['total_amount'] ?? 0);
        $status = strtolower($ord['status'] ?? 'pending');
        
        if (in_array($status, ['completed', 'success', 'paid', 'da_xu_ly'])) {
            $total_revenue += $amount;
            $completed_count++;
        } else {
            $pending_count++;
        }
    }
} catch (Exception $e) {
    // Bỏ qua nếu bảng chưa có dữ liệu
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ Thống Quản Trị (Admin Panel) - Thời Trang Cao Cấp</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f8; color: #333; }
        
        /* Header Admin đen tuyền sang trọng */
        header { background: #151515; color: #fff; padding: 16px 50px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .logo-area a { text-decoration: none; font-size: 22px; font-weight: 800; color: #fff; letter-spacing: 2px; text-transform: uppercase; }
        .logo-area span { color: #e74c3c; }
        .header-nav { display: flex; align-items: center; gap: 20px; }
        .header-nav span { color: #aaa; font-size: 13px; }
        .header-nav a { color: #ccc; text-decoration: none; font-size: 13px; font-weight: 600; transition: color 0.2s; padding: 6px 12px; border-radius: 4px; background: rgba(255,255,255,0.05); }
        .header-nav a:hover { color: #fff; background: rgba(255,255,255,0.15); }
        .btn-logout { color: #ff7675 !important; }

        /* Container chính */
        .container { max-width: 1280px; margin: 40px auto; padding: 0 20px; }
        
        /* Thẻ thống kê tổng quan (Dashboard Cards) */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 22px 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaeaea; display: flex; align-items: center; justify-content: space-between; }
        .stat-info h3 { font-size: 13px; color: #777; text-transform: uppercase; font-weight: 700; margin-bottom: 5px; letter-spacing: 0.5px; }
        .stat-info .stat-val { font-size: 22px; font-weight: 800; color: #111; }
        .stat-icon { width: 50px; height: 50px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 22px; background: #f1f3f5; }

        /* Khung quản lý chính */
        .card-box { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; overflow: hidden; padding: 30px; }
        
        .box-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #f1f3f5; padding-bottom: 15px; }
        .box-heading { font-size: 20px; font-weight: 800; color: #111; text-transform: uppercase; letter-spacing: 0.5px; border-left: 4px solid #111; padding-left: 12px; }
        
        .back-link { color: #555; text-decoration: none; font-weight: 600; font-size: 13px; transition: color 0.2s; }
        .back-link:hover { color: #111; }

        /* Bảng dữ liệu quản lý */
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #111; color: #fff; padding: 14px 16px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        td { padding: 15px 16px; border-bottom: 1px solid #f1f3f5; font-size: 14px; color: #444; vertical-align: middle; }
        tr:hover td { background: #fafafa; }
        tr:last-child td { border-bottom: none; }

        .order-code { font-weight: 700; color: #111; }
        .order-price { font-weight: 800; color: #e74c3c; }

        /* Nhãn trạng thái (Badge) */
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; text-align: center; }
        .status-processing { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        /* Nút hành động */
        .btn-action { display: inline-block; background: #111; color: #fff; padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 700; text-transform: uppercase; transition: background 0.2s; }
        .btn-action:hover { background: #e74c3c; }
        .btn-done { background: #27ae60; color: #fff; pointer-events: none; }

        .empty-state { text-align: center; padding: 60px 20px; color: #777; font-size: 15px; }
    </style>
</head>
<body>

<!-- HEADER ADMIN -->
<header>
    <div class="logo-area">
        <a href="admin.php">TORANO <span>ADMIN</span></a>
    </div>
    <div class="header-nav">
        <span>Xin chào, <strong>Quản Trị Viên</strong></span>
        <a href="index.php">🌐 Xem Cửa Hàng</a>
        <a href="logout.php" class="btn-logout">Đăng Xuất</a>
    </div>
</header>

<div class="container">
    
    <!-- THỐNG KÊ TỔNG QUAN -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3>Tổng Đơn Hàng</h3>
                <div class="stat-val"><?= count($orders) ?></div>
            </div>
            <div class="stat-icon">📦</div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>Đơn Chưa Xử Lý</h3>
                <div class="stat-val" style="color: #f39c12;"><?= $pending_count ?></div>
            </div>
            <div class="stat-icon">⏳</div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>Tổng Doanh Thu</h3>
                <div class="stat-val" style="color: #e74c3c;"><?= number_format($total_revenue, 0, ',', '.') ?>đ</div>
            </div>
            <div class="stat-icon">💰</div>
        </div>
    </div>

    <!-- KHUNG QUẢN LÝ ĐƠN HÀNG -->
    <div class="card-box">
        <div class="box-header-flex">
            <h2 class="box-heading">Quản Lý Đơn Hàng Hệ Thống</h2>
            <a href="index.php" class="back-link">← Quay lại trang chủ cửa hàng</a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <p>𭕡 Chưa có đơn hàng nào trong hệ thống.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Khách Hàng</th>
                            <th>Tổng Tiền</th>
                            <th>Trạng Thái</th>
                            <th>Hết Hạn Giữ Chỗ</th>
                            <th>Ngày Tạo</th>
                            <th style="text-align: center;">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $ord): 
                            $raw_status = strtolower($ord['status'] ?? 'pending');
                            $is_completed = in_array($raw_status, ['completed', 'success', 'paid', 'da_xu_ly']);
                        ?>
                        <tr>
                            <td><span class="order-code"><?= htmlspecialchars($ord['order_code'] ?? 'ORD-' . strtoupper(substr(md5($ord['id']), 0, 6))) ?></span></td>
                            <td><strong><?= htmlspecialchars($ord['customer_name'] ?? ($ord['fullname'] ?? 'Khách hàng')) ?></strong></td>
                            <td><span class="order-price"><?= number_format($ord['total_amount'] ?? 0, 0, ',', '.') ?>đ</span></td>
                            <td>
                                <?php if ($is_completed): ?>
                                    <span class="status-badge status-completed">Đã Xử Lý</span>
                                <?php else: ?>
                                    <span class="status-badge status-processing">Chưa Xử Lý</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #666; font-size: 13px;"><?= htmlspecialchars($ord['expires_at'] ?? '---') ?></td>
                            <td style="color: #666; font-size: 13px;"><?= htmlspecialchars($ord['created_at'] ?? '---') ?></td>
                            <td style="text-align: center;">
                                <?php if (!$is_completed): ?>
                                    <a href="admin.php?action=update_status&id=<?= $ord['id'] ?>" class="btn-action" onclick="return confirm('Xác nhận hoàn tất đơn hàng này?');">Duyệt Đơn</a>
                                <?php else: ?>
                                    <span class="btn-action btn-done">Hoàn Tất</span>
                                <?php endif; ?>
                            </td>
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