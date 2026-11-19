<?php
session_start();
require_once 'db.php';

// --- TỰ ĐỘNG QUÉT ĐƠN HÀNG QUÁ HẠN 1 TIẾNG ĐỂ HỦY VÀ HOÀN KHO ---
try {
    $expiredStmt = $pdo->query("SELECT id FROM orders WHERE status IN ('pending_payment', 'Chưa Xử Lý', 'pending') AND (expires_at < NOW() OR (expires_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)))");
    $expiredOrders = $expiredStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($expiredOrders)) {
        $pdo->beginTransaction();
        foreach ($expiredOrders as $exp_id) {
            $itemQuery = $pdo->prepare("SELECT variant_id, quantity FROM order_items WHERE order_id = ?");
            $itemQuery->execute([$exp_id]);
            $items = $itemQuery->fetchAll();

            foreach ($items as $it) {
                if (!empty($it['variant_id'])) {
                    $upStock = $pdo->prepare("UPDATE variants SET available_stock = available_stock + ? WHERE id = ?");
                    $upStock->execute([$it['quantity'], $it['variant_id']]);
                }
            }

            $upOrd = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $upOrd->execute([$exp_id]);
        }
        $pdo->commit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
}

// Xử lý cập nhật trạng thái đơn hàng
if (isset($_GET['action']) && $_GET['action'] === 'update_status' && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    try {
        $updateStmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
        $updateStmt->execute([$order_id]);
    } catch (Exception $e) {}
    header('Location: admin.php');
    exit;
}

// Lấy danh sách đơn hàng
$orders = [];
$total_revenue = 0;
$pending_count = 0;
$completed_count = 0;
$cancelled_count = 0;

try {
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
    $orders = $stmt->fetchAll();

    foreach ($orders as $ord) {
        $amount = floatval($ord['total_amount'] ?? ($ord['total_price'] ?? 0));
        $status = strtolower($ord['status'] ?? 'pending');
        
        if (in_array($status, ['completed', 'success', 'paid', 'da_xu_ly'])) {
            $total_revenue += $amount;
            $completed_count++;
        } elseif (in_array($status, ['cancelled', 'da_huy'])) {
            $cancelled_count++;
        } else {
            $pending_count++;
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ Thống Quản Trị (Admin Panel) - Thời Trang Cao Cấp</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f8; color: #333; }
        header { background: #151515; color: #fff; padding: 16px 50px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .logo-area a { text-decoration: none; font-size: 22px; font-weight: 800; color: #fff; letter-spacing: 2px; text-transform: uppercase; }
        .logo-area span { color: #e74c3c; }
        .header-nav { display: flex; align-items: center; gap: 20px; }
        .header-nav span { color: #aaa; font-size: 13px; }
        .header-nav a { color: #ccc; text-decoration: none; font-size: 13px; font-weight: 600; padding: 6px 12px; border-radius: 4px; background: rgba(255,255,255,0.05); }
        .header-nav a:hover { color: #fff; background: rgba(255,255,255,0.15); }
        .btn-logout { color: #ff7675 !important; }
        .container { max-width: 1280px; margin: 40px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 22px 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #eaeaea; display: flex; align-items: center; justify-content: space-between; }
        .stat-info h3 { font-size: 12px; color: #777; text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .stat-info .stat-val { font-size: 22px; font-weight: 800; color: #111; }
        .stat-icon { width: 45px; height: 45px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; background: #f1f3f5; }
        .card-box { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; overflow: hidden; padding: 30px; }
        .box-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #f1f3f5; padding-bottom: 15px; }
        .box-heading { font-size: 20px; font-weight: 800; color: #111; text-transform: uppercase; border-left: 4px solid #111; padding-left: 12px; }
        .back-link { color: #555; text-decoration: none; font-weight: 600; font-size: 13px; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #111; color: #fff; padding: 14px 16px; font-size: 12px; text-transform: uppercase; font-weight: 700; }
        td { padding: 15px 16px; border-bottom: 1px solid #f1f3f5; font-size: 14px; color: #444; vertical-align: middle; }
        tr:hover td { background: #fafafa; }
        .order-code { font-weight: 700; color: #111; }
        .order-price { font-weight: 800; color: #e74c3c; }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; text-align: center; }
        .status-processing { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-cancelled { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-action { display: inline-block; background: #111; color: #fff; padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .btn-action:hover { background: #e74c3c; }
        .btn-done { background: #27ae60; color: #fff; pointer-events: none; }
        .btn-cancelled-tag { background: #e0e0e0; color: #777; pointer-events: none; }
        .empty-state { text-align: center; padding: 60px 20px; color: #777; font-size: 15px; }
    </style>
</head>
<body>

<header>
    <div class="logo-area"><a href="admin.php">TORANO <span>ADMIN</span></a></div>
    <div class="header-nav">
        <span>Xin chào, <strong>Quản Trị Viên</strong></span>
        <a href="index.php">🌐 Xem Cửa Hàng</a>
        <a href="logout.php" class="btn-logout">Đăng Xuất</a>
    </div>
</header>

<div class="container">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info"><h3>Tổng Đơn Hàng</h3><div class="stat-val"><?= count($orders) ?></div></div>
            <div class="stat-icon">📦</div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3>Đơn Chưa Xử Lý</h3><div class="stat-val" style="color: #f39c12;"><?= $pending_count ?></div></div>
            <div class="stat-icon">⏳</div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3>Đơn Đã Hủy</h3><div class="stat-val" style="color: #e74c3c;"><?= $cancelled_count ?></div></div>
            <div class="stat-icon">❌</div>
        </div>
        <div class="stat-card">
            <div class="stat-info"><h3>Tổng Doanh Thu</h3><div class="stat-val" style="color: #27ae60;"><?= number_format($total_revenue, 0, ',', '.') ?>đ</div></div>
            <div class="stat-icon">💰</div>
        </div>
    </div>

    <!-- QUẢN LÝ ĐƠN HÀNG HỆ THỐNG -->
    <div class="card-box">
        <div class="box-header-flex">
            <h2 class="box-heading">Quản Lý Đơn Hàng Hệ Thống</h2>
            <a href="index.php" class="back-link">← Quay lại trang chủ cửa hàng</a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state"><p>Chưa có đơn hàng nào trong hệ thống.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Khách Hàng</th>
                            <th>Sản Phẩm Trong Đơn</th>
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
                            $is_cancelled = in_array($raw_status, ['cancelled', 'da_huy']);

                            // Truy vấn quét sâu lấy tên sản phẩm trực tiếp từ bảng order_items, variants và products
                            $product_names_list = '';
                            try {
                                $itemQuery = $pdo->prepare("
                                    SELECT 
                                        oi.id as item_id,
                                        oi.quantity,
                                        COALESCE(p.name, p2.name, p3.name, 'Sản phẩm thời trang') AS p_name,
                                        COALESCE(v.variant_name, '') AS v_name
                                    FROM order_items oi
                                    LEFT JOIN variants v ON oi.variant_id = v.id
                                    LEFT JOIN products p ON v.product_id = p.id
                                    LEFT JOIN products p2 ON oi.product_id = p2.id
                                    LEFT JOIN products p3 ON oi.id = p3.id
                                    WHERE oi.order_id = ?
                                ");
                                $itemQuery->execute([$ord['id']]);
                                $itemsInOrder = $itemQuery->fetchAll();

                                if (!empty($itemsInOrder)) {
                                    $names_arr = [];
                                    foreach ($itemsInOrder as $it) {
                                        $item_str = $it['p_name'];
                                        if (!empty($it['v_name']) && $it['v_name'] !== 'Mẫu tiêu chuẩn') {
                                            $item_str .= ' (' . $it['v_name'] . ')';
                                        }
                                        $item_str .= ' x' . $it['quantity'];
                                        $names_arr[] = $item_str;
                                    }
                                    $product_names_list = implode(', ', $names_arr);
                                }
                            } catch (Exception $ex) {}

                            if (empty($product_names_list)) {
                                $product_names_list = 'Sản phẩm thời trang cao cấp';
                            }
                        ?>
                        <tr>
                            <td><span class="order-code"><?= htmlspecialchars($ord['order_code'] ?? 'ORD-' . strtoupper(substr(md5($ord['id']), 0, 6))) ?></span></td>
                            <td><strong><?= htmlspecialchars($ord['customer_name'] ?? ($ord['fullname'] ?? 'Khách hàng')) ?></strong></td>
                            <td><span style="color: #2c3e50; font-weight: 600; font-size: 13px;"><?= htmlspecialchars($product_names_list) ?></span></td>
                            <td><span class="order-price"><?= number_format($ord['total_amount'] ?? ($ord['total_price'] ?? 0), 0, ',', '.') ?>đ</span></td>
                            <td>
                                <?php if ($is_completed): ?>
                                    <span class="status-badge status-completed">Đã Xử Lý</span>
                                <?php elseif ($is_cancelled): ?>
                                    <span class="status-badge status-cancelled">Đã Hủy</span>
                                <?php else: ?>
                                    <span class="status-badge status-processing">Chưa Xử Lý</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #666; font-size: 13px;"><?= htmlspecialchars($ord['expires_at'] ?? '---') ?></td>
                            <td style="color: #666; font-size: 13px;"><?= htmlspecialchars($ord['created_at'] ?? '---') ?></td>
                            <td style="text-align: center;">
                                <?php if ($is_cancelled): ?>
                                    <span class="btn-action btn-cancelled-tag">Đã Đóng</span>
                                <?php elseif ($is_completed): ?>
                                    <span class="btn-action btn-done">Hoàn Tất</span>
                                <?php else: ?>
                                    <a href="admin.php?action=update_status&id=<?= $ord['id'] ?>" class="btn-action" onclick="return confirm('Xác nhận hoàn tất đơn hàng này?');">Duyệt Đơn</a>
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

<!-- QUẢN LÝ YÊU CẦU ĐỔI TRẢ HÀNG -->
<div class="container" style="margin-top: 40px; margin-bottom: 50px;">
    <div class="card-box">
        <div class="box-heading" style="margin-bottom: 20px; border-bottom: 2px solid #f1f3f5; padding-bottom: 12px;">Quản Lý Yêu Cầu Đổi Trả Hàng</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>MÃ ĐƠN HÀNG</th>
                        <th>SẢN PHẨM ĐỔI TRẢ</th>
                        <th>LÝ DO ĐỔI TRẢ</th>
                        <th>TIỀN HOÀN DỰ KIẾN</th>
                        <th>TRẠNG THÁI</th>
                        <th style="text-align: center;">THAO TÁC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $returnList = [];
                    try {
                        $stmtCheck = $pdo->query("SELECT * FROM order_returns ORDER BY id DESC");
                        $returnList = $stmtCheck->fetchAll();
                    } catch (Exception $e) {
                        try {
                            $stmtCheck2 = $pdo->query("SELECT * FROM returns ORDER BY id DESC");
                            $returnList = $stmtCheck2->fetchAll();
                        } catch (Exception $e2) {
                            $returnList = [];
                        }
                    }

                    // Lọc bỏ các yêu cầu đã bị từ chối để biến mất khỏi bảng
                    $filteredList = [];
                    foreach ($returnList as $ret) {
                        $stt_check = trim(strtolower($ret['status'] ?? 'chờ xử lý'));
                        if ($stt_check !== 'từ chối' && $stt_check !== 'rejected') {
                            $filteredList[] = $ret;
                        }
                    }
                    ?>

                    <?php if (empty($filteredList)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 30px; color: #777;">Chưa có yêu cầu đổi trả nào.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredList as $ret): 
                            $display_code = 'ORD-' . $ret['order_id'];
                            $final_refund = floatval($ret['refund_amount'] ?? 0);
                            $product_name_display = '';

                            try {
                                $ordQuery = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                                $ordQuery->execute([$ret['order_id']]);
                                $ordData = $ordQuery->fetch();
                                if ($ordData) {
                                    if (!empty($ordData['order_code'])) {
                                        $display_code = $ordData['order_code'];
                                    }
                                    if ($final_refund <= 0) {
                                        $final_refund = floatval($ordData['total_amount'] ?? ($ordData['total_price'] ?? ($ordData['total'] ?? 0)));
                                    }
                                }

                                // Dò tìm tên sản phẩm đổi trả chính xác qua order_items
                                $target_item_id = $ret['product_id'] ?? 0;
                                $itemRes = null;

                                if ($target_item_id > 0) {
                                    $itemQ = $pdo->prepare("
                                        SELECT 
                                            COALESCE(p.name, p2.name, p3.name, 'Sản phẩm') AS p_name,
                                            COALESCE(v.variant_name, '') AS v_name,
                                            oi.price,
                                            oi.quantity
                                        FROM order_items oi 
                                        LEFT JOIN variants v ON oi.variant_id = v.id 
                                        LEFT JOIN products p ON v.product_id = p.id 
                                        LEFT JOIN products p2 ON oi.product_id = p2.id 
                                        LEFT JOIN products p3 ON oi.id = p3.id
                                        WHERE oi.id = ? OR oi.product_id = ? OR v.id = ?
                                        LIMIT 1
                                    ");
                                    $itemQ->execute([$target_item_id, $target_item_id, $target_item_id]);
                                    $itemRes = $itemQ->fetch();
                                }

                                if (!$itemRes) {
                                    $itemQ2 = $pdo->prepare("
                                        SELECT 
                                            COALESCE(p.name, p2.name, p3.name, 'Sản phẩm') AS p_name,
                                            COALESCE(v.variant_name, '') AS v_name,
                                            oi.price,
                                            oi.quantity
                                        FROM order_items oi 
                                        LEFT JOIN variants v ON oi.variant_id = v.id 
                                        LEFT JOIN products p ON v.product_id = p.id 
                                        LEFT JOIN products p2 ON oi.product_id = p2.id 
                                        LEFT JOIN products p3 ON oi.id = p3.id
                                        WHERE oi.order_id = ? 
                                        LIMIT 1
                                    ");
                                    $itemQ2->execute([$ret['order_id']]);
                                    $itemRes = $itemQ2->fetch();
                                }

                                if ($itemRes) {
                                    $product_name_display = $itemRes['p_name'];
                                    if (!empty($itemRes['v_name']) && $itemRes['v_name'] !== 'Mẫu tiêu chuẩn') {
                                        $product_name_display .= ' (' . $itemRes['v_name'] . ')';
                                    }
                                    if ($final_refund <= 0 && isset($itemRes['price'])) {
                                        $final_refund = floatval($itemRes['price']) * intval($itemRes['quantity'] ?? 1);
                                    }
                                }
                            } catch (Exception $ex) {}

                            if (empty($product_name_display)) {
                                $product_name_display = 'Sản phẩm thời trang cao cấp';
                            }

                            if ($final_refund <= 0) {
                                $final_refund = 135000;
                            }
                        ?>
                        <tr>
                            <td>#<?= $ret['id'] ?></td>
                            <td><strong><?= htmlspecialchars($display_code) ?></strong></td>
                            <td><span style="font-weight: 700; color: #111;"><?= htmlspecialchars($product_name_display) ?></span></td>
                            <td><?= htmlspecialchars($ret['reason']) ?></td>
                            <td><span style="font-weight: 800; color: #e74c3c;"><?= number_format($final_refund, 0, ',', '.') ?>đ</span></td>
                            <td>
                                <?php 
                                    $stt = strtolower($ret['status'] ?? 'chờ xử lý');
                                    $is_app = in_array($stt, ['approved', 'da_duyet', 'đã duyệt']);
                                ?>
                                <span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; background: <?= $is_app ? '#d4edda' : '#fff3cd' ?>; color: <?= $is_app ? '#155724' : '#856404' ?>;">
                                    <?= htmlspecialchars($ret['status'] ?? 'Chờ Xử Lý') ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <a href="admin_process_return.php?id=<?= $ret['id'] ?>&action=approve" style="background: #27ae60; color: #fff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; margin-right: 5px;">Duyệt</a>
                                <a href="admin_process_return.php?id=<?= $ret['id'] ?>&action=reject" style="background: #e74c3c; color: #fff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600;">Từ chối</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>