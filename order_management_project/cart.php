<?php
session_start();
require_once 'db.php';

// Hàm ánh xạ tên sản phẩm và phân biệt chuẩn xác từng màu sắc của áo thun
function getExactProductImage($productName, $variantName, $defaultImg) {
    if (!empty($defaultImg) && strpos($defaultImg, 'placeholder.com') === false) return $defaultImg;
    
    $name = mb_strtolower(trim($productName . ' ' . $variantName), 'UTF-8');
    
    // Ưu tiên nhận diện đúng các loại áo thun theo màu sắc cụ thể
    if (strpos($name, 'áo thun') !== false) {
        if (strpos($name, 'đen') !== false) return 'assets/sanpham2.jpg';
        if (strpos($name, 'trắng') !== false) return 'assets/sanpham2.jpg'; // Hoặc sanpham4.jpg tùy bạn chọn, ở đây gán ảnh áo thun phù hợp
        if (strpos($name, 'xám') !== false) return 'assets/sanpham4.jpg';
        if (strpos($name, 'navy') !== false || strpos($name, 'xanh navy') !== false) return 'assets/sanpham5.jpg';
        if (strpos($name, 'rêu') !== false || strpos($name, 'form rộng') !== false) return 'assets/sanpham24.jpg';
        return 'assets/sanpham2.jpg'; // Mặc định cho áo thun chung
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
    
    return !empty($defaultImg) ? $defaultImg : 'assets/sanpham2.jpg';
}

// Xử lý cập nhật số lượng hoặc xóa sản phẩm trong giỏ hàng
if (isset($_GET['action'])) {
    $vid = intval($_GET['id'] ?? 0);
    if ($_GET['action'] === 'increase') {
        if (isset($_SESSION['cart'][$vid])) $_SESSION['cart'][$vid]++;
    } elseif ($_GET['action'] === 'decrease') {
        if (isset($_SESSION['cart'][$vid])) {
            $_SESSION['cart'][$vid]--;
            if ($_SESSION['cart'][$vid] <= 0) unset($_SESSION['cart'][$vid]);
        }
    } elseif ($_GET['action'] === 'delete') {
        unset($_SESSION['cart'][$vid]);
    }
    header('Location: cart.php');
    exit;
}

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

        // Truyền cả tên sản phẩm và tên biến thể (variant_name như Size M - Màu Trắng) vào hàm ánh xạ ảnh
        $resolved_image = getExactProductImage($p['product_name'] ?? '', $p['variant_name'] ?? '', $p['image_url'] ?? '');

        $cart_items[] = [
            'id' => $vid,
            'product_name' => $p['product_name'],
            'variant_name' => $p['variant_name'],
            'price' => $p['price'],
            'image_url' => $resolved_image,
            'quantity' => $qty,
            'subtotal' => $subtotal,
            'max_stock' => $p['available_stock']
        ];
    }
}

// Xử lý mã giảm giá áp dụng
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

$final_total = max(0, $total_amount - $discount_amount);
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ Hàng - ANHNGUYEN</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f5f7; color: #333; }
        
        /* --- THANH THÔNG BÁO CHẠY TRÊN CÙNG (TOP BAR) --- */
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

        /* --- HEADER NỀN ĐEN CAO CẤP & HIỆN ĐẠI --- */
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

        /* Thanh tìm kiếm bo góc mượt mà */
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

        /* Menu điều hướng phải */
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

        /* --- THANH MENU DANH MỤC NỀN TRẮNG --- */
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

        /* CONTAINER CHÍNH GIỎ HÀNG */
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .page-title { font-size: 22px; font-weight: 800; color: #111; text-transform: uppercase; margin-bottom: 25px; border-left: 4px solid #111; padding-left: 12px; }
        
        .cart-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start; }
        @media(max-width: 900px) { .cart-layout { grid-template-columns: 1fr; } }
        
        .cart-box { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #111; color: #fff; padding: 12px 14px; font-size: 12px; text-transform: uppercase; }
        td { padding: 15px 14px; border-bottom: 1px solid #f1f3f5; font-size: 14px; color: #333; vertical-align: middle; }
        .cart-img { width: 65px; height: 65px; object-fit: cover; border-radius: 6px; border: 1px solid #eee; background: #f1f3f5; }
        .qty-btn { display: inline-block; width: 28px; height: 28px; line-height: 26px; text-align: center; background: #f1f3f5; border: 1px solid #ddd; color: #333; text-decoration: none; font-weight: 700; border-radius: 4px; }
        .qty-btn:hover { background: #111; color: #fff; border-color: #111; }
        .item-price { font-weight: 800; color: #e74c3c; }
        
        .summary-box { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; position: sticky; top: 90px; }
        .summary-title { font-size: 16px; font-weight: 800; text-transform: uppercase; margin-bottom: 15px; border-bottom: 2px solid #f1f3f5; padding-bottom: 10px; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 14px; color: #555; }
        .summary-row.total { font-size: 18px; font-weight: 800; color: #111; border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px; }
        .summary-row.total .price-val { color: #e74c3c; }
        
        .btn-checkout { display: block; width: 100%; background: #111; color: #fff; text-align: center; padding: 14px; border-radius: 6px; font-weight: 800; font-size: 14px; text-transform: uppercase; text-decoration: none; margin-top: 20px; transition: background 0.2s; }
        .btn-checkout:hover { background: #e74c3c; }
        .empty-cart { text-align: center; padding: 50px 20px; color: #777; font-size: 15px; }
    </style>
</head>
<body>

<!-- THANH THÔNG BÁO ƯU ĐÃI TRÊN CÙNG -->
<div class="top-announcement-bar">
    <span><span>★</span> Đổi hàng trong 30 ngày</span>
    <span><span>★</span> Freeship mọi đơn hàng từ 500K</span>
    <span><span>★</span> Voucher lớn - Sale giá độc quyền</span>
</div>

<!-- HEADER NỀN ĐEN CAO CẤP -->
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

<!-- THANH MENU DANH MỤC NỀN TRẮNG -->
<div class="sub-menu-bar">
    <a href="index.php">SẢN PHẨM MỚI</a>
    <a href="index.php?search=Sale">DANH MỤC SALE <span class="sale-tag">-50%</span></a>
    <a href="index.php?gender=nam">ÁO NAM ▾</a>
    <a href="index.php?search=Quần">QUẦN NAM ▾</a>
    <a href="index.php?gender=nu">THỜI TRANG NỮ ▾</a>
    <a href="index.php?search=Khoác">ÁO KHOÁC</a>
</div>

<div class="container">
    <h1 class="page-title">Giỏ Hàng Của Bạn</h1>

    <?php if (empty($cart_items)): ?>
        <div class="cart-box empty-cart">
            <p>🛒 Giỏ hàng của bạn đang trống!</p>
            <a href="index.php" style="display: inline-block; margin-top: 15px; background: #111; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 13px;">← Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-box">
                <table>
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>Tên Sản Phẩm</th>
                            <th>Đơn Giá</th>
                            <th>Số Lượng</th>
                            <th>Thành Tiền</th>
                            <th>Xóa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td><img src="<?= htmlspecialchars($item['image_url']) ?>" alt="Ảnh sản phẩm" class="cart-img"></td>
                            <td>
                                <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                <span style="font-size: 12px; color: #666; background: #f1f3f5; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px;"><?= htmlspecialchars($item['variant_name']) ?></span>
                            </td>
                            <td><span class="item-price"><?= number_format($item['price'], 0, ',', '.') ?>đ</span></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <a href="cart.php?action=decrease&id=<?= $item['id'] ?>" class="qty-btn">-</a>
                                    <span style="font-weight: 700; width: 25px; text-align: center;"><?= $item['quantity'] ?></span>
                                    <a href="cart.php?action=increase&id=<?= $item['id'] ?>" class="qty-btn">+</a>
                                </div>
                            </td>
                            <td><span class="item-price"><?= number_format($item['subtotal'], 0, ',', '.') ?>đ</span></td>
                            <td><a href="cart.php?action=delete&id=<?= $item['id'] ?>" onclick="return confirm('Xóa sản phẩm này khỏi giỏ hàng?');" style="color: #e74c3c; text-decoration: none; font-weight: 700; font-size: 16px;" title="Xóa">✕</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="summary-box">
                <div class="summary-title">Cộng Giỏ Hàng</div>
                <div class="summary-row"><span>Tạm tính:</span><span><strong><?= number_format($total_amount, 0, ',', '.') ?>đ</strong></span></div>
                
                <?php if ($discount_amount > 0): ?>
                    <div class="summary-row" style="color: #27ae60; font-weight: 600;">
                        <span>Mã giảm giá (<?= htmlspecialchars($applied_coupon_code) ?>):</span>
                        <span>-<?= number_format($discount_amount, 0, ',', '.') ?>đ</span>
                    </div>
                <?php endif; ?>

                <div class="summary-row"><span>Phí vận chuyển:</span><span>Miễn phí</span></div>
                <div class="summary-row total"><span>Tổng thanh toán:</span><span class="price-val"><?= number_format($final_total, 0, ',', '.') ?>đ</span></div>
                
                <a href="checkout.php" class="btn-checkout">Tiến Hành Thanh Toán</a>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
<!-- CHÂN TRANG (FOOTER) -->
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

<!-- NÚT LIÊN HỆ ZALO NỔI GÓC MÀN HÌNH -->
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