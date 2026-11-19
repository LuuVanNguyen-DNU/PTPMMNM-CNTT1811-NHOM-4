<?php
session_start();
require_once 'db.php';

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$current_user = $_SESSION['user']['fullname'] ?? ($_SESSION['user']['username'] ?? '');
$current_user_id = $_SESSION['user']['id'] ?? null;

// --- TỰ ĐỘNG HỦY VÀ HOÀN KHO CHO ĐƠN QUÁ HẠN 1 TIẾNG ---
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

// --- XỬ LÝ KHI NGƯỜI DÙNG BẤM HỦY ĐƠN THỦ CÔNG ---
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    try {
        $pdo->beginTransaction();
        
        $chkStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status IN ('pending_payment', 'Chưa Xử Lý', 'pending')");
        $chkStmt->execute([$cancel_id]);
        $ordToCancel = $chkStmt->fetch();

        if ($ordToCancel) {
            $itemQuery = $pdo->prepare("SELECT variant_id, quantity FROM order_items WHERE order_id = ?");
            $itemQuery->execute([$cancel_id]);
            $items = $itemQuery->fetchAll();

            foreach ($items as $it) {
                if (!empty($it['variant_id'])) {
                    $upStock = $pdo->prepare("UPDATE variants SET available_stock = available_stock + ? WHERE id = ?");
                    $upStock->execute([$it['quantity'], $it['variant_id']]);
                }
            }

            $upOrd = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $upOrd->execute([$cancel_id]);

            $pdo->commit();
            echo "<script>alert('Đã hủy đơn hàng thành công và hoàn lại kho!'); window.location='order_history.php';</script>";
            exit;
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
    }
}

// Lấy danh sách đơn hàng
$orders = [];
try {
    $colQuery = $pdo->query("SHOW COLUMNS FROM orders");
    $tableCols = $colQuery->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT o.* FROM orders o";

    if (in_array('user_id', $tableCols) && $current_user_id) {
        $stmt = $pdo->prepare("$sql WHERE o.user_id = ? ORDER BY o.id DESC");
        $stmt->execute([$current_user_id]);
        $orders = $stmt->fetchAll();
    } elseif (in_array('customer_name', $tableCols) && $current_user) {
        $stmt = $pdo->prepare("$sql WHERE o.customer_name = ? ORDER BY o.id DESC");
        $stmt->execute([$current_user]);
        $orders = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("$sql ORDER BY o.id DESC");
        $orders = $stmt->fetchAll();
    }

    foreach ($orders as &$ord) {
        $order_id = $ord['id'];
        
        // Truy vấn chính xác order_items kết hợp bảng variants và products để lấy số lượng thực tế
        $itemStmt = $pdo->prepare("SELECT p.name AS product_name, v.variant_name, oi.quantity 
                                    FROM order_items oi 
                                    LEFT JOIN variants v ON oi.variant_id = v.id 
                                    LEFT JOIN products p ON v.product_id = p.id 
                                    WHERE oi.order_id = ?");
        $itemStmt->execute([$order_id]);
        $items = $itemStmt->fetchAll();

        if (!empty($items)) {
            $names = [];
            foreach ($items as $it) {
                $p_name = $it['product_name'] ?? 'Sản phẩm';
                $v_name = (!empty($it['variant_name']) && $it['variant_name'] !== 'Mẫu tiêu chuẩn') ? ' (' . $it['variant_name'] . ')' : '';
                // Lấy đúng số lượng quantity thực tế từ CSDL
                $qty_val = intval($it['quantity'] ?? 1);
                $names[] = '<strong>' . htmlspecialchars($p_name) . '</strong>' . htmlspecialchars($v_name) . ' x' . $qty_val;
            }
            $ord['display_product_names'] = implode('<br>', $names);
        } else {
            $ord['display_product_names'] = 'Sản phẩm thời trang cao cấp Torano x1';
        }
    }
    unset($ord);

} catch (Exception $e) {
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch Sử Đơn Hàng - ANHNGUYEN</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f5f7; color: #333; }
        
        .top-announcement-bar {
            background: #111;
            color: #ccc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 8px 20px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            border-bottom: 1px solid #222;
        }
        .top-announcement-bar span span { color: #e74c3c; font-weight: 700; margin-right: 5px; }

        header { 
            background: linear-gradient(135deg, #111111 0%, #1a1a1a 100%); 
            color: #fff;
            padding: 14px 40px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .logo-area a { 
            text-decoration: none;
            font-size: 24px; 
            font-weight: 900; 
            color: #fff; 
            letter-spacing: 2.5px; 
            text-transform: uppercase; 
        }
        .logo-area span { color: #e74c3c; }

        .header-search { 
            display: flex; 
            align-items: center; 
            background: rgba(255, 255, 255, 0.08); 
            border-radius: 30px; 
            padding: 4px 6px 4px 16px; 
            width: 420px; 
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
        }
        .header-search:focus-within {
            background: #ffffff;
            border-color: #fff;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
        }
        .header-search input { 
            border: none; 
            background: transparent; 
            outline: none; 
            width: 100%; 
            padding: 6px 0;
            font-size: 13px; 
            color: #fff; 
        }
        .header-search:focus-within input { color: #222; }
        .header-search input::placeholder { color: #aaa; }
        
        .header-search button { 
            background: #e74c3c; 
            color: #fff;
            border: none; 
            border-radius: 50%;
            cursor: pointer; 
            font-size: 13px; 
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, transform 0.2s;
        }
        .header-search button:hover { background: #c0392b; transform: scale(1.05); }

        .header-nav { display: flex; align-items: center; gap: 15px; }
        .header-nav a { 
            color: #dcdcdc; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 600; 
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.2s ease; 
        }
        .header-nav a:hover { 
            color: #fff; 
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .badge { 
            background: #e74c3c; 
            color: #fff; 
            padding: 2px 7px; 
            border-radius: 10px; 
            font-size: 11px; 
            font-weight: 800; 
        }

        .sub-menu-bar { 
            background: #ffffff; 
            padding: 0 50px; 
            display: flex; 
            justify-content: center;
            gap: 35px; 
            align-items: center; 
            height: 48px; 
            border-bottom: 1px solid #eaeaea;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }
        .sub-menu-bar a { 
            color: #222; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.4px; 
            transition: color 0.2s; 
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sub-menu-bar a:hover { color: #e74c3c; }
        .sale-tag {
            background: #d63031;
            color: #fff;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 800;
        }

        .container { max-width: 1250px; margin: 40px auto; padding: 0 20px; }
        .card-box { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; overflow: hidden; padding: 25px; }
        .box-heading { font-size: 18px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 20px; border-bottom: 2px solid #f1f3f5; padding-bottom: 12px; }
        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #111; color: #fff; padding: 14px 16px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        td { padding: 15px 16px; border-bottom: 1px solid #f1f3f5; font-size: 14px; color: #444; vertical-align: middle; }
        tr:hover td { background: #fafafa; }
        tr:last-child td { border-bottom: none; }
        .order-code { font-weight: 700; color: #111; text-decoration: none; }
        .order-code:hover { color: #e74c3c; text-decoration: underline; }
        .order-items-name { font-weight: 600; color: #222; line-height: 1.5; }
        .order-price { font-weight: 800; color: #e74c3c; }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; text-align: center; }
        .status-processing { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-cancelled { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-return { background: #e74c3c; color: #fff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; transition: background 0.2s; }
        .btn-return:hover { background: #c0392b; }
        .btn-cancel { background: #6c757d; color: #fff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; transition: background 0.2s; margin-right: 5px; }
        .btn-cancel:hover { background: #5a6268; }
        .empty-history { text-align: center; padding: 50px 20px; color: #777; font-size: 15px; }
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
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                <a href="admin.php" style="background: #e74c3c; border-color: #e74c3c;">⚙️ Quản Trị</a>
            <?php endif; ?>
        <?php endif; ?>
        <a href="order_history.php">📦 Đơn Hàng</a>
        <a href="cart.php">🛒 Giỏ hàng <span class="badge"><?= $cart_count ?></span></a>
        <?php if (isset($_SESSION['user'])): ?>
            <a href="logout.php" style="color: #ff7675;">Đăng xuất</a>
        <?php else: ?>
            <a href="login.php">Tài khoản</a>
        <?php endif; ?>
    </div>
</header>

<div class="sub-menu-bar">
    <a href="index.php">SẢN PHẨM MỚI</a>
    <a href="index.php?search=Sale">DANH MỤC SALE <span class="sale-tag">-50%</span></a>
    <a href="index.php?gender=nam">ÁO NAM ▾</a>
    <a href="index.php?search=Quần">QUẦN NAM ▾</a>
    <a href="index.php?gender=nu">THỜI TRANG NỮ ▾</a>
    <a href="index.php?search=Khoác">ÁO KHOÁC</a>
</div>

<div class="container">
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
                            <th>Tên Mặt Hàng / Sản Phẩm</th>
                            <th>Tổng Tiền</th>
                            <th>Trạng Thái</th>
                            <th>Ngày Tạo</th>
                            <th>Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $ord): 
                            $raw_status = strtolower($ord['status'] ?? 'pending');
                            
                            if (in_array($raw_status, ['completed', 'success', 'paid', 'da_xu_ly'])) {
                                $status_text = 'Đã Xử Lý';
                                $status_class = 'status-completed';
                            } elseif (in_array($raw_status, ['cancelled', 'da_huy'])) {
                                $status_text = 'Đã Hủy';
                                $status_class = 'status-cancelled';
                            } else {
                                $status_text = 'Chưa Xử Lý';
                                $status_class = 'status-processing';
                            }
                        ?>
                        <tr>
                            <td>
                                <a href="order_detail.php?id=<?= $ord['id'] ?>" class="order-code">
                                    <?= htmlspecialchars($ord['order_code'] ?? 'ORD-' . strtoupper(substr(md5($ord['id']), 0, 6))) ?>
                                </a>
                            </td>
                            <td><span class="order-items-name"><?= $ord['display_product_names'] ?></span></td>
                            <td><span class="order-price"><?= number_format($ord['total_amount'] ?? ($ord['total_price'] ?? 0), 0, ',', '.') ?>đ</span></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                            <td style="color: #666; font-size: 13px;"><?= htmlspecialchars($ord['created_at'] ?? '---') ?></td>
                            <td>
                                <?php if ($status_text === 'Chưa Xử Lý'): ?>
                                    <a href="order_history.php?cancel_id=<?= $ord['id'] ?>" class="btn-cancel" onclick="return confirm('Bạn có chắc chắn muốn hủy đơn hàng này không?');">Hủy Đơn</a>
                                    <span style="color: #999; font-size: 11px; display: block; margin-top: 4px;">Giữ hàng 1h</span>
                                <?php elseif ($status_text === 'Đã Xử Lý'): ?>
                                    <a href="return_order.php?order_id=<?= $ord['id'] ?>" class="btn-return">Đổi Trả Hàng</a>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px; font-style: italic;">Đã hủy / Quá hạn</span>
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
<footer class="site-footer">
    <div class="footer-newsletter">
        <h3>Đăng Ký Nhận Bản Tin Thời Trang</h3>
        <form class="newsletter-form" onsubmit="event.preventDefault(); alert('Cảm ơn bạn đã đăng ký nhận tin thành công!');">
            <input type="email" placeholder="Nhập địa chỉ email của bạn..." required>
            <button type="submit">Đăng Ký</button>
        </form>
    </div>

    <div class="footer-main">
        <div class="footer-col">
            <h4>Về Chúng Tôi</h4>
            <p style="margin-bottom: 15px;">Hệ thống phân phối thời trang nam nữ cao cấp, mang đến phong cách lịch lãm, hiện đại và trẻ trung.</p>
            <div class="contact-info"><span>📍</span><span><strong>Địa chỉ:</strong> 01 Ba La, Hà Đông, Hà Nội</span></div>
            <div class="contact-info"><span>📞</span><span><strong>Hotline/SĐT:</strong> 0976539070</span></div>
            <div class="contact-info"><span>✉️</span><span><strong>Email:</strong> nkoxslile701@gmail.com</span></div>
            <div class="contact-info"><span>⏰</span><span><strong>Giờ mở cửa:</strong> 08:30 - 22:00 (Tất cả các ngày)</span></div>
        </div>

        <div class="footer-col">
            <h4>Chính Sách & Hỗ Trợ</h4>
            <ul>
                <li><a href="#">Hướng dẫn chọn size</a></li>
                <li><a href="#">Chính sách đổi trả trong 7 ngày</a></li>
                <li><a href="#">Quy định bảo hành sản phẩm</a></li>
                <li><a href="#">Chính sách thanh toán & giao hàng</a></li>
                <li><a href="#">Chính sách bảo mật thông tin</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Hệ Thống Cửa Hàng</h4>
            <p style="margin-bottom: 15px;"><strong>Cơ sở chính (Hà Nội):</strong><br>01 Ba La, Phường Phú Lãm, Quận Hà Đông, TP. Hà Nội</p>
            <h4 style="margin-top: 25px;">Phương Thức Thanh Toán</h4>
            <p style="font-size: 13px; color: #888;">Hỗ trợ thanh toán linh hoạt qua chuyển khoản, ví điện tử hoặc thanh toán khi nhận hàng (COD).</p>
        </div>
    </div>

    <div class="footer-bottom">
        Bản quyền thuộc về Cửa Hàng Thời Trang Cao Cấp | Thiết kế chuẩn phong cách thương hiệu.
    </div>
</footer>

<div id="zalo-floating-icon" style="position: fixed; bottom: 25px; right: 25px; z-index: 9999; display: flex; align-items: center; gap: 10px; cursor: pointer;">
    <a href="https://zalo.me/0569230660" target="_blank" onclick="copyZaloNumber(event)" title="Nhấn để chat Zalo & sao chép số" style="display: flex; align-items: center; justify-content: center; width: 55px; height: 55px; background: #0068ff; border-radius: 50%; box-shadow: 0 4px 15px rgba(0, 104, 255, 0.4); text-decoration: none; transition: transform 0.3s ease; position: relative;">
        <span style="color: #fff; font-size: 24px; font-weight: 800; font-family: Arial, sans-serif;">Z</span>
    </a>
</div>

<div id="zalo-toast" style="position: fixed; bottom: 35px; right: 95px; z-index: 10000; background: #111; color: #fff; padding: 10px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.2); opacity: 0; transition: opacity 0.3s ease; pointer-events: none; white-space: nowrap;">
    📋 Đã sao chép số Zalo: 0569230660
</div>

<style>
    .site-footer {
        background: #111111; color: #b0b0b0; padding-top: 50px;
        border-top: 3px solid #e74c3c; margin-top: 60px;
    }
    .footer-newsletter {
        max-width: 1200px; margin: 0 auto; padding: 0 30px 40px;
        border-bottom: 1px solid #2a2a2a; display: flex;
        justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;
    }
    .footer-newsletter h3 { color: #fff; font-size: 18px; text-transform: uppercase; font-weight: 800; }
    .newsletter-form { display: flex; max-width: 450px; width: 100%; }
    .newsletter-form input {
        flex: 1; padding: 12px 16px; background: #1e1e1e; border: 1px solid #333;
        border-radius: 4px 0 0 4px; color: #fff; outline: none; font-size: 14px;
    }
    .newsletter-form button {
        background: #e74c3c; color: #fff; border: none; padding: 0 24px;
        font-weight: 700; text-transform: uppercase; border-radius: 0 4px 4px 0; cursor: pointer;
    }
    .newsletter-form button:hover { background: #c0392b; }

    .footer-main {
        max-width: 1200px; margin: 0 auto; padding: 50px 30px;
        display: grid; grid-template-columns: 1.5fr 1fr 1.5fr; gap: 40px;
    }
    @media (max-width: 850px) { .footer-main { grid-template-columns: 1fr; } }
    .footer-col h4 {
        color: #fff; font-size: 15px; text-transform: uppercase; margin-bottom: 20px;
        font-weight: 800; border-left: 3px solid #e74c3c; padding-left: 10px;
    }
    .footer-col p, .footer-col li, .footer-col a { font-size: 14px; line-height: 1.8; color: #b0b0b0; text-decoration: none; }
    .footer-col ul { list-style: none; padding: 0; }
    .footer-col ul li a:hover { color: #fff; text-decoration: underline; }
    .contact-info { margin-bottom: 10px; display: flex; align-items: flex-start; gap: 10px; }
    .footer-bottom { background: #080808; padding: 20px 30px; text-align: center; font-size: 13px; color: #777; border-top: 1px solid #1a1a1a; }
    
    #zalo-floating-icon a:hover { transform: scale(1.1); }
    @keyframes zalo-pulse {
        0% { box-shadow: 0 0 0 0 rgba(0, 104, 255, 0.5); }
        70% { box-shadow: 0 0 0 15px rgba(0, 104, 255, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 104, 255, 0); }
    }
    #zalo-floating-icon a { animation: zalo-pulse 2s infinite; }
</style>

<script>
function copyZaloNumber(event) {
    navigator.clipboard.writeText('0569230660').then(function() {
        var toast = document.getElementById('zalo-toast');
        toast.style.opacity = '1';
        setTimeout(function() {
            toast.style.opacity = '0';
        }, 2500);
    }).catch(function(err) {});
}
</script>
</html>