<?php
session_start();
require_once 'db.php';

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$order_id = intval($_GET['order_id'] ?? ($_GET['id'] ?? 0));

// Lấy thông tin đơn hàng và danh sách sản phẩm đồng bộ 100% với order_history.php
$order = null;
$order_items = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if ($order) {
        $itemStmt = $pdo->prepare("
            SELECT oi.*, 
                   p.name AS product_name, 
                   v.variant_name,
                   v.price AS unit_price
            FROM order_items oi 
            LEFT JOIN variants v ON oi.variant_id = v.id 
            LEFT JOIN products p ON v.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$order_id]);
        $order_items = $itemStmt->fetchAll();
    }
} catch (Exception $e) {
    // Giữ nguyên thông tin đơn hàng nếu có lỗi phụ
}

// Lấy số lượng thực tế của sản phẩm đầu tiên trong đơn để làm giá trị tối đa ban đầu
$default_first_qty = (!empty($order_items)) ? intval($order_items[0]['quantity'] ?? 1) : 1;

// Xử lý khi khách hàng gửi yêu cầu đổi trả
$success_msg = '';
$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? '');
    $selected_item = intval($_POST['product_id'] ?? 0);
    $return_qty = intval($_POST['return_quantity'] ?? 1);

    if ($reason === '') {
        $error_msg = 'Vui lòng nhập lý do đổi trả chi tiết để chúng tôi hỗ trợ tốt nhất!';
    } else {
        $max_allowed_qty = $default_first_qty;
        $unit_price = 0;

        if ($selected_item > 0) {
            $checkQ = $pdo->prepare("
                SELECT oi.quantity, v.price AS unit_price 
                FROM order_items oi 
                LEFT JOIN variants v ON oi.variant_id = v.id 
                WHERE oi.id = ?
            ");
            $checkQ->execute([$selected_item]);
            $checkRes = $checkQ->fetch();
            if ($checkRes) {
                $max_allowed_qty = intval($checkRes['quantity'] ?? 1);
                $unit_price = floatval($checkRes['unit_price'] ?? 0);
            }
        }

        if ($return_qty > $max_allowed_qty || $return_qty < 1) {
            $error_msg = 'Số lượng đổi trả không hợp lệ hoặc vượt quá số lượng đã mua (' . $max_allowed_qty . ' sản phẩm)!';
        } else {
            try {
                // 1. Đảm bảo bảng order_returns tồn tại
                $pdo->exec("CREATE TABLE IF NOT EXISTS order_returns (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    product_id INT DEFAULT 0,
                    return_qty INT DEFAULT 1,
                    reason TEXT NOT NULL,
                    refund_amount DECIMAL(10,2) DEFAULT 0,
                    status VARCHAR(50) DEFAULT 'Chờ xử lý',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");

                // 2. Kiểm tra các cột hiện có trong bảng order_returns và tự động thêm nếu bảng cũ bị thiếu cột
                $colStmt = $pdo->query("SHOW COLUMNS FROM order_returns");
                $existingCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!in_array('product_id', $existingCols)) {
                    $pdo->exec("ALTER TABLE order_returns ADD COLUMN product_id INT DEFAULT 0");
                }
                if (!in_array('return_qty', $existingCols)) {
                    $pdo->exec("ALTER TABLE order_returns ADD COLUMN return_qty INT DEFAULT 1");
                }
                if (!in_array('refund_amount', $existingCols)) {
                    $pdo->exec("ALTER TABLE order_returns ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT 0");
                }
                if (!in_array('status', $existingCols)) {
                    $pdo->exec("ALTER TABLE order_returns ADD COLUMN status VARCHAR(50) DEFAULT 'Chờ xử lý'");
                }

                // Tính tiền hoàn dự kiến dựa trên đơn giá nhân số lượng trả thực tế
                $total_order_amt = floatval($order['total_amount'] ?? ($order['total_price'] ?? 0));
                $price_val = $unit_price > 0 ? ($unit_price * $return_qty) : (($total_order_amt / max(1, $max_allowed_qty)) * $return_qty);

                // Ghi dữ liệu vào bảng order_returns
                $ins = $pdo->prepare("INSERT INTO order_returns (order_id, product_id, return_qty, reason, refund_amount, status) VALUES (?, ?, ?, ?, ?, 'Chờ xử lý')");
                $ins->execute([$order_id, $selected_item, $return_qty, $reason, $price_val]);

                // 3. Đồng bộ thêm vào bảng returns & return_items (nếu CSDL của bạn có dùng cho trang order_detail.php)
                try {
                    $insRet = $pdo->prepare("INSERT INTO returns (order_id, reason, refund_amount, status) VALUES (?, ?, ?, 'pending')");
                    $insRet->execute([$order_id, $reason, $price_val]);
                    $new_return_id = $pdo->lastInsertId();

                    if ($new_return_id && $selected_item > 0) {
                        $insRetItem = $pdo->prepare("INSERT INTO return_items (return_id, order_item_id, quantity) VALUES (?, ?, ?)");
                        $insRetItem->execute([$new_return_id, $selected_item, $return_qty]);
                    }
                } catch (Exception $ignore) {
                    // Bỏ qua nếu không dùng bảng returns phụ
                }

                $success_msg = 'Gửi yêu cầu đổi trả thành công (' . $return_qty . ' sản phẩm)! Nhân viên chăm sóc khách hàng sẽ liên hệ với bạn trong vòng 24 giờ.';
            } catch (Exception $e) {
                $error_msg = 'Lỗi hệ thống khi lưu yêu cầu: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Yêu Cầu Đổi Trả Hàng - ANHNGUYEN</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f5f7; color: #333; }
        .top-announcement-bar { background: #111; color: #ccc; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; padding: 8px 20px; display: flex; justify-content: space-around; align-items: center; border-bottom: 1px solid #222; }
        .top-announcement-bar span span { color: #e74c3c; font-weight: 700; margin-right: 5px; }
        header { background: linear-gradient(135deg, #111111 0%, #1a1a1a 100%); color: #fff; padding: 14px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.4); }
        .logo-area a { text-decoration: none; font-size: 24px; font-weight: 900; color: #fff; letter-spacing: 2.5px; text-transform: uppercase; }
        .logo-area span { color: #e74c3c; }
        .header-search { display: flex; align-items: center; background: rgba(255, 255, 255, 0.08); border-radius: 30px; padding: 4px 6px 4px 16px; width: 420px; border: 1px solid rgba(255, 255, 255, 0.15); }
        .header-search input { border: none; background: transparent; outline: none; width: 100%; padding: 6px 0; font-size: 13px; color: #fff; }
        .header-search button { background: #e74c3c; color: #fff; border: none; border-radius: 50%; cursor: pointer; font-size: 13px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; }
        .header-nav { display: flex; align-items: center; gap: 15px; }
        .header-nav a { color: #dcdcdc; text-decoration: none; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 6px; background: rgba(255, 255, 255, 0.04); }
        .badge { background: #e74c3c; color: #fff; padding: 2px 7px; border-radius: 10px; font-size: 11px; font-weight: 800; }
        .sub-menu-bar { background: #ffffff; padding: 0 50px; display: flex; justify-content: center; gap: 35px; align-items: center; height: 48px; border-bottom: 1px solid #eaeaea; }
        .sub-menu-bar a { color: #222; text-decoration: none; font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .container { max-width: 900px; margin: 45px auto; padding: 0 20px; }
        .breadcrumb { margin-bottom: 20px; font-size: 13px; font-weight: 600; }
        .breadcrumb a { color: #e74c3c; text-decoration: none; }
        .return-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #eaeaea; padding: 40px; }
        .return-title { font-size: 20px; font-weight: 900; text-transform: uppercase; color: #111; margin-bottom: 8px; border-left: 4px solid #e74c3c; padding-left: 12px; }
        .return-subtitle { font-size: 13px; color: #666; margin-bottom: 30px; }
        .alert-success { background: #e8f8f5; color: #27ae60; padding: 15px; border-radius: 6px; border-left: 4px solid #27ae60; font-weight: 600; margin-bottom: 25px; }
        .alert-error { background: #fff5f5; color: #e53e3e; padding: 15px; border-radius: 6px; border-left: 4px solid #e53e3e; font-weight: 600; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #333; margin-bottom: 8px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px 15px; font-size: 14px; border: 1px solid #dcdcdc; border-radius: 6px; background: #fafafa; color: #111; outline: none; }
        .policy-box { background: #fdfefe; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 20px; margin-bottom: 25px; }
        .policy-box h4 { font-size: 14px; font-weight: 800; color: #111; margin-bottom: 10px; }
        .policy-box ul { padding-left: 20px; font-size: 13px; color: #555; line-height: 1.6; }
        .btn-submit { display: block; width: 100%; background: #111; color: #fff; text-align: center; padding: 15px; border: none; border-radius: 6px; font-weight: 800; font-size: 14px; text-transform: uppercase; cursor: pointer; }
        .btn-submit:hover { background: #e74c3c; }
        .site-footer { background: #111111; color: #b0b0b0; padding-top: 50px; border-top: 3px solid #e74c3c; margin-top: 60px; }
        .footer-bottom { background: #080808; padding: 20px 30px; text-align: center; font-size: 13px; color: #777; border-top: 1px solid #1a1a1a; }
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
            <span style="font-size: 13px; color: #ccc;">👤 <strong><?= htmlspecialchars($_SESSION['user']['fullname'] ?? $_SESSION['user']['username'] ?? '') ?></strong></span>
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
    <a href="index.php?search=Sale">DANH MỤC SALE</a>
    <a href="index.php?gender=nam">ÁO NAM ▾</a>
    <a href="index.php?search=Quần">QUẦN NAM ▾</a>
    <a href="index.php?gender=nu">THỜI TRANG NỮ ▾</a>
    <a href="index.php?search=Khoác">ÁO KHOÁC</a>
</div>

<div class="container">
    <div class="breadcrumb"><a href="order_history.php">← Quay lại lịch sử đơn hàng</a></div>

    <div class="return-card">
        <div class="return-title">Yêu Cầu Đổi Trả Hàng</div>
        <div class="return-subtitle">Mã đơn hàng: <strong><?= htmlspecialchars($order['order_code'] ?? ('ORD-' . $order_id)) ?></strong></div>

        <?php if ($success_msg !== ''): ?>
            <div class="alert-success">✅ <?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>

        <?php if ($error_msg !== ''): ?>
            <div class="alert-error">⚠️ <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <div class="policy-box">
            <h4>📋 Chính Sách Đổi Trả Cao Cấp</h4>
            <ul>
                <li>Thời gian đổi trả trong vòng <strong>30 ngày</strong> kể từ khi nhận hàng.</li>
                <li>Sản phẩm còn nguyên tem mác, chưa qua sử dụng, giặt ủi hay sửa chữa.</li>
                <li>Hỗ trợ đổi size hoặc đổi màu miễn phí đối với lỗi từ nhà sản xuất.</li>
            </ul>
        </div>

        <form method="POST" onsubmit="return validateReturnQty();">
            <div class="form-group">
                <label for="product_id">Chọn sản phẩm muốn đổi trả</label>
                <select id="product_id" name="product_id" class="form-control" required onchange="updateMaxQty(this)">
                    <?php if (!empty($order_items)): ?>
                        <?php foreach ($order_items as $item): 
                            $item_qty = intval($item['quantity'] ?? 1);
                            $item_price = floatval($item['unit_price'] ?? ($item['price'] ?? 0));
                            $p_name = $item['product_name'] ?? 'Sản phẩm thời trang';
                            $v_name = (!empty($item['variant_name']) && $item['variant_name'] !== 'Mẫu tiêu chuẩn') ? ' (' . $item['variant_name'] . ')' : '';
                        ?>
                            <option value="<?= $item['id'] ?>" data-qty="<?= $item_qty ?>">
                                <?= htmlspecialchars($p_name . $v_name) ?> - Đã mua: <?= $item_qty ?> món
                                <?= $item_price > 0 ? ' (' . number_format($item_price * $item_qty, 0, ',', '.') . 'đ)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="0" data-qty="1">Sản phẩm tiêu chuẩn trong đơn hàng #<?= $order_id ?> - Đã mua: 1 món</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="return_quantity">Số lượng muốn đổi trả (Tối đa: <span id="max_qty_label"><?= $default_first_qty ?></span> món)</label>
                <input type="number" id="return_quantity" name="return_quantity" class="form-control" value="<?= $default_first_qty ?>" min="1" max="<?= $default_first_qty ?>" required>
            </div>

            <div class="form-group">
                <label for="reason">Lý do đổi trả chi tiết</label>
                <textarea id="reason" name="reason" class="form-control" rows="4" placeholder="Ví dụ: Sản phẩm bị lỗi chỉ, rộng size muốn đổi sang size nhỏ hơn..." required></textarea>
            </div>

            <button type="submit" class="btn-submit">Xác Nhận Gửi Yêu Cầu Đổi Trả</button>
        </form>

        <script>
            function updateMaxQty(selectElem) {
                var selectedOption = selectElem.options[selectElem.selectedIndex];
                var maxQty = parseInt(selectedOption.getAttribute('data-qty') || 1);
                var qtyInput = document.getElementById('return_quantity');
                var maxLabel = document.getElementById('max_qty_label');
                
                qtyInput.max = maxQty;
                qtyInput.value = maxQty;
                if (maxLabel) {
                    maxLabel.innerText = maxQty;
                }
            }
            
            function validateReturnQty() {
                var selectElem = document.getElementById('product_id');
                var selectedOption = selectElem.options[selectElem.selectedIndex];
                var maxQty = parseInt(selectedOption.getAttribute('data-qty') || 1);
                var qtyInput = document.getElementById('return_quantity');
                var enteredQty = parseInt(qtyInput.value);

                if (enteredQty > maxQty || enteredQty < 1) {
                    alert('Số lượng đổi trả phải nằm trong khoảng từ 1 đến ' + maxQty + ' sản phẩm!');
                    qtyInput.value = maxQty;
                    return false;
                }
                return true;
            }

            window.onload = function() {
                var selectElem = document.getElementById('product_id');
                if (selectElem) {
                    updateMaxQty(selectElem);
                }
            };
        </script>
    </div>
</div>

<footer class="site-footer">
    <div class="footer-bottom">
        Bản quyền thuộc về Cửa Hàng Thời Trang Cao Cấp | Thiết kế chuẩn phong cách thương hiệu.
    </div>
</footer>

</body>
</html>