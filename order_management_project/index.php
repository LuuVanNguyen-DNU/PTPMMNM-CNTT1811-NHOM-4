<?php
session_start();
require_once 'db.php';

// Hàm ánh xạ tên sản phẩm với đúng file ảnh thực tế (sanpham1.jpg đến sanpham24.jpg)
function getExactProductImage($productName, $defaultImg) {
    if (!empty($defaultImg)) return $defaultImg;
    
    $name = mb_strtolower(trim($productName), 'UTF-8');
    
    if (strpos($name, 'bình giữ nhiệt') !== false) return 'assets/sanpham1.jpg';
    if (strpos($name, 'áo thun') !== false && strpos($name, 'đen') !== false) return 'assets/sanpham2.jpg';
    if (strpos($name, 'henley') !== false || strpos($name, 'trụ') !== false || strpos($name, 'hồng') !== false) return 'assets/sanpham3.jpg';
    if (strpos($name, 'áo thun') !== false && strpos($name, 'xám') !== false) return 'assets/sanpham4.jpg';
    if (strpos($name, 'áo thun') !== false && (strpos($name, 'navy') !== false || strpos($name, 'xanh navy') !== false)) return 'assets/sanpham5.jpg';
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
    if (strpos($name, 'form rộng') !== false || strpos($name, 'rêu') !== false) return 'assets/sanpham24.jpg';
    
    return 'assets/sanpham2.jpg'; // Mặc định cho áo nếu không khớp
}

// Xử lý thêm vào giỏ hàng session
if (isset($_POST['add_to_cart'])) {
    $variant_id = intval($_POST['variant_id']);
    $quantity = intval($_POST['quantity']);

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$variant_id])) {
        $_SESSION['cart'][$variant_id] += $quantity;
    } else {
        $_SESSION['cart'][$variant_id] = $quantity;
    }
    echo "<script>alert('Đã thêm sản phẩm vào giỏ hàng!'); window.location='index.php';</script>";
}

// Xử lý lưu mã giảm giá vào session
if (isset($_GET['save_coupon'])) {
    $coupon_code = trim($_GET['save_coupon']);
    $_SESSION['applied_coupon'] = $coupon_code;
    echo "<script>alert('Đã lưu mã giảm giá [$coupon_code] thành công! Mã sẽ được áp dụng khi thanh toán.'); window.location='index.php';</script>";
    exit;
}

// Xử lý tìm kiếm và lọc theo danh mục
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$gender = isset($_GET['gender']) ? trim($_GET['gender']) : '';

$query = "SELECT v.*, p.name AS product_name FROM variants v JOIN products p ON v.product_id = p.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (p.name LIKE ? OR v.variant_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($gender === 'nam') {
    $query .= " AND (p.name LIKE '%nam%' OR v.variant_name LIKE '%nam%')";
} elseif ($gender === 'nu') {
    $query .= " AND (p.name LIKE '%nữ%' OR v.variant_name LIKE '%nữ%' OR p.name LIKE '%nu%' OR v.variant_name LIKE '%nu%')";
}

$query .= " ORDER BY v.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$variants = $stmt->fetchAll();

// Truy vấn riêng danh sách sản phẩm Áo để hiển thị ở khu vực giữa trang
try {
    $shirtStmt = $pdo->query("SELECT v.*, p.name AS product_name FROM variants v JOIN products p ON v.product_id = p.id WHERE p.name LIKE '%Áo%' OR p.name LIKE '%Ao%' OR v.variant_name LIKE '%Áo%' LIMIT 4");
    $shirt_variants = $shirtStmt->fetchAll();
} catch (Exception $e) {
    $shirt_variants = [];
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$current_saved_coupon = $_SESSION['applied_coupon'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Torano - Thời Trang Nam Nữ Cao Cấp Chính Hãng</title>
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

        /* Thanh tìm kiếm bo góc mượt mà, sang trọng */
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
        .header-search:focus-within input::placeholder { color: #888; }
        
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

        /* Menu điều hướng phải tinh tế */
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
        .btn-admin { background: #e74c3c !important; border-color: #e74c3c !important; color: #fff !important; }

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

        /* --- BẢNG TRƯỢT QUẢNG CÁO (HERO SLIDER) --- */
        .hero-slider {
            position: relative;
            width: 100%;
            height: 520px;
            overflow: hidden;
            background: #111;
        }
        .slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.8s ease-in-out;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
        }
        .slide.active { opacity: 1; z-index: 1; }
        .slide::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(15,15,15,0.75) 0%, rgba(15,15,15,0.35) 50%, rgba(0,0,0,0.05) 100%);
        }
        .slide-content {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 0 60px;
            color: #ffffff;
        }
        .slide-subtitle { font-size: 14px; letter-spacing: 5px; text-transform: uppercase; color: #dcdcdc; margin-bottom: 14px; font-weight: 500; }
        .slide-title { font-family: Georgia, serif; font-size: 52px; font-weight: 400; letter-spacing: 2px; margin-bottom: 12px; line-height: 1.1; }
        .slide-desc { font-size: 18px; letter-spacing: 3px; text-transform: uppercase; color: #e2e2e2; margin-bottom: 30px; font-weight: 300; }
        .slide-btn {
            display: inline-flex; align-items: center; gap: 15px; padding: 12px 28px;
            border: 1px solid rgba(255,255,255,0.85); color: #ffffff; text-decoration: none;
            font-size: 13px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
            transition: all 0.3s ease; background: rgba(0,0,0,0.2);
        }
        .slide-btn:hover { background: #ffffff; color: #111111; }

        .slider-arrow {
            position: absolute; top: 50%; transform: translateY(-50%); z-index: 10;
            width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.2);
            color: #fff; border: none; cursor: pointer; font-size: 18px; display: flex;
            align-items: center; justify-content: center; transition: background 0.3s;
        }
        .slider-arrow:hover { background: rgba(255,255,255,0.45); }
        .slider-arrow.prev { left: 25px; }
        .slider-arrow.next { right: 25px; }

        .slider-dots {
            position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
            z-index: 10; display: flex; gap: 8px;
        }
        .dot { width: 28px; height: 3px; background: rgba(255,255,255,0.4); cursor: pointer; transition: all 0.3s; }
        .dot.active { background: #ffffff; width: 42px; }

        /* --- THANH BANNER QUẢNG CÁO CHẠY NGANG (PROMO MARQUEE / TICKER) --- */
        .promo-ticker-wrap {
            background: #e74c3c;
            color: #fff;
            padding: 12px 0;
            overflow: hidden;
            white-space: nowrap;
            position: relative;
            margin: 30px 0;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.25);
        }
        .promo-ticker-content {
            display: inline-block;
            animation: marquee 25s linear infinite;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .promo-ticker-content span {
            margin: 0 40px;
        }
        @keyframes marquee {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        /* --- KHU VỰC MÃ GIẢM GIÁ ĐỘC QUYỀN (COUPON SECTION) --- */
        .coupon-section-wrap {
            max-width: 1280px;
            margin: 35px auto 10px;
            padding: 0 20px;
        }
        .section-header-title {
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            color: #111;
            margin-bottom: 15px;
            letter-spacing: 0.5px;
            border-left: 4px solid #e74c3c;
            padding-left: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .applied-notice {
            font-size: 12px;
            color: #27ae60;
            background: #e8f8f5;
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid #a3e4d7;
            font-weight: 600;
            text-transform: none;
        }
        .coupon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }
        .coupon-card {
            background: #fff;
            border: 1px solid #eaeaea;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .coupon-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.07);
        }
        .coupon-card.active-coupon {
            border-color: #27ae60;
            background: #f9fffb;
        }
        .coupon-tag {
            background: #111;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .coupon-code {
            font-size: 20px;
            font-weight: 900;
            color: #111;
            margin-bottom: 4px;
            letter-spacing: 1px;
        }
        .coupon-desc {
            font-size: 14px;
            font-weight: 800;
            color: #e74c3c;
            margin-bottom: 6px;
        }
        .coupon-cond {
            font-size: 12px;
            color: #666;
            margin-bottom: 15px;
        }
        .btn-coupon {
            display: block;
            text-align: center;
            width: 100%;
            background: #111;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-coupon:hover { background: #e74c3c; }
        .btn-coupon.saved {
            background: #27ae60;
            pointer-events: none;
        }

        /* --- CONTAINER CHÍNH --- */
        .main-container { padding: 20px 40px 50px; max-width: 1280px; margin: auto; }
        
        .filter-bar { display: flex; gap: 12px; margin-bottom: 25px; align-items: center; }
        .filter-pill { padding: 8px 22px; background: #fff; border: 1px solid #ddd; border-radius: 30px; color: #444; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.2s; }
        .filter-pill.active, .filter-pill:hover { background: #111; color: white; border-color: #111; }

        /* --- LƯỚI SẢN PHẨM CHUẨN ĐẸP KHÔNG BỊ RỐI --- */
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; }
        .product-card { 
            background: #fff; 
            border-radius: 10px; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.04); 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            transition: all 0.3s ease; 
            border: 1px solid #eaeaea; 
            position: relative;
        }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        
        /* Nhãn giảm giá hoặc độc quyền góc trên ảnh */
        .card-badge-top {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 5;
            background: #d63031;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 6px rgba(214,48,49,0.4);
        }

        .product-img-box { position: relative; overflow: hidden; background: #f1f3f5; height: 280px; }
        .product-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .product-card:hover .product-img { transform: scale(1.05); }

        .product-content { padding: 16px; }
        .product-card h3 { 
            font-size: 14px; 
            font-weight: 600; 
            margin: 0 0 6px 0; 
            color: #222; 
            line-height: 1.4; 
            height: 40px; 
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .product-card h3:hover { color: #e74c3c; }
        
        .variant-tag { display: inline-block; background: #f1f3f5; color: #666; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-bottom: 12px; font-weight: 500; }
        
        .price-stock-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .price { color: #e74c3c; font-weight: 800; font-size: 16px; }
        .stock { font-size: 12px; color: #777; font-weight: 500; }

        .btn-buy { 
            background: #111; color: white; padding: 12px; border: none; cursor: pointer; 
            border-radius: 6px; width: 100%; font-weight: 700; font-size: 12px; text-transform: uppercase; 
            letter-spacing: 0.5px; transition: background 0.2s; text-align: center; display: block; text-decoration: none; 
        }
        .btn-buy:hover { background: #e74c3c; }

        /* --- CHÂN TRANG (FOOTER) --- */
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
    <div class="logo-area">
        <a href="index.php">ANHNGUYEN<span>.</span></a>
    </div>

    <form method="GET" class="header-search">
        <input type="text" name="search" placeholder="Bạn đang tìm gì..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">🔍</button>
    </form>

    <div class="header-nav">
        <?php if (isset($_SESSION['user'])): ?>
            <span style="font-size: 13px; color: #ccc;">👤 <strong><?= htmlspecialchars($_SESSION['user']['fullname']) ?></strong></span>
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                <a href="admin.php" class="btn-admin">⚙️ Quản Trị</a>
            <?php else: ?>
                <a href="order_history.php">📦 Đơn Hàng</a>
            <?php endif; ?>
            <a href="cart.php">🛒 Giỏ hàng <span class="badge"><?= $cart_count ?></span></a>
            <a href="logout.php" style="color: #ff7675;">Đăng xuất</a>
        <?php else: ?>
            <a href="login.php">👤 Tài khoản</a>
            <a href="cart.php">🛒 Giỏ hàng <span class="badge"><?= $cart_count ?></span></a>
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

<?php if ($search === '' && $gender === ''): ?>
<!-- BẢNG TRƯỢT QUẢNG CÁO (HERO SLIDER) -->
<div class="hero-slider" id="heroSlider">
    <div class="slide active" style="background-image: url('https://images.unsplash.com/photo-1516826957135-700dedea698c?w=1600&auto=format&fit=crop&q=80');">
        <div class="slide-content">
            <div class="slide-subtitle">NEW COLLECTION</div>
            <h1 class="slide-title">FALL/WINTER 2026</h1>
            <div class="slide-desc">PHONG CÁCH PHÁI MẠNH HIỆN ĐẠI</div>
            <a href="#san-pham" class="slide-btn">MUA NGAY <span>❯</span></a>
        </div>
    </div>

    <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1490578474895-699cd4e2cf59?w=1600&auto=format&fit=crop&q=80');">
        <div class="slide-content">
            <div class="slide-subtitle">EXCLUSIVE STYLE</div>
            <h1 class="slide-title">ELEGANT & MODERN</h1>
            <div class="slide-desc">ĐỊNH HÌNH PHONG CÁCH LỊCH LÃM</div>
            <a href="index.php?gender=nam" class="slide-btn">KHÁM PHÁ NGAY <span>❯</span></a>
        </div>
    </div>

    <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?w=1600&auto=format&fit=crop&q=80');">
        <div class="slide-content">
            <div class="slide-subtitle">SPECIAL OFFER</div>
            <h1 class="slide-title">SEASON SALE UP TO 50%</h1>
            <div class="slide-desc">ƯU ĐÃI ĐẶC BIỆT TOÀN BỘ CỬA HÀNG</div>
            <a href="#san-pham" class="slide-btn">XEM ƯU ĐÃI <span>❯</span></a>
        </div>
    </div>

    <button class="slider-arrow prev" onclick="moveSlide(-1)">❮</button>
    <button class="slider-arrow next" onclick="moveSlide(1)">❯</button>

    <div class="slider-dots">
        <span class="dot active" onclick="goToSlide(0)"></span>
        <span class="dot" onclick="goToSlide(1)"></span>
        <span class="dot" onclick="goToSlide(2)"></span>
    </div>
</div>
<?php endif; ?>

<!-- KHU VỰC QUẢNG CÁO GIẢM GIÁ 30% - 50% VÀ MÃ GIẢM GIÁ ĐỘC QUYỀN -->
<?php if ($search === '' && $gender === ''): ?>
<div class="coupon-section-wrap">
    
    <!-- BANNER QUẢNG CÁO THỜI TRANG GIẢM GIÁ 30% - 50% VỚI HÌNH ẢNH AI/UNSPLASH ĐẸP MẮT -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <!-- Banner 1 -->
        <div style="position: relative; height: 210px; border-radius: 12px; overflow: hidden; background: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.65)), url('https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?w=900&auto=format&fit=crop&q=80') center/cover no-repeat; display: flex; flex-direction: column; justify-content: flex-end; padding: 25px; color: #fff; box-shadow: 0 6px 20px rgba(0,0,0,0.15);">
            <span style="background: #e74c3c; color: #fff; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 4px; width: fit-content; margin-bottom: 8px; text-transform: uppercase;">⚡ Siêu Sale Độc Quyền</span>
            <h3 style="font-size: 24px; font-weight: 900; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">Giảm Giá Đến 50%</h3>
            <p style="font-size: 13px; color: #f1f1f1; font-weight: 400;">Áp dụng cho toàn bộ các mặt hàng áo khoác, áo sơ mi và thời trang nam nữ cao cấp.</p>
        </div>

        <!-- Banner 2 -->
        <div style="position: relative; height: 210px; border-radius: 12px; overflow: hidden; background: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.65)), url('https://images.unsplash.com/photo-1490578474895-699cd4e2cf59?w=900&auto=format&fit=crop&q=80') center/cover no-repeat; display: flex; flex-direction: column; justify-content: flex-end; padding: 25px; color: #fff; box-shadow: 0 6px 20px rgba(0,0,0,0.15);">
            <span style="background: #f39c12; color: #000; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 4px; width: fit-content; margin-bottom: 8px; text-transform: uppercase;">🔥 Đỉnh Cao Phong Cách</span>
            <h3 style="font-size: 24px; font-weight: 900; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">Ưu Đãi Lên Đến 30%</h3>
            <p style="font-size: 13px; color: #f1f1f1; font-weight: 400;">Sưu tầm voucher ngay bên dưới để áp dụng trực tiếp khi thanh toán đơn hàng.</p>
        </div>
    </div>

    <div class="section-header-title">
        <span>Mã Giảm Giá Độc Quyền Trên Website</span>
        <?php if ($current_saved_coupon !== ''): ?>
            <span class="applied-notice">✓ Đang áp dụng mã: <strong><?= htmlspecialchars($current_saved_coupon) ?></strong></span>
        <?php endif; ?>
    </div>
    
    <div class="coupon-grid">
        <!-- Mã 1 -->
        <div class="coupon-card <?= ($current_saved_coupon === 'TRN30') ? 'active-coupon' : '' ?>">
            <div class="coupon-tag">★ Độc Quyền Website</div>
            <div class="coupon-code">TRN30</div>
            <div class="coupon-desc">Giảm 30.000đ</div>
            <div class="coupon-cond">Áp dụng cho đơn hàng từ 399.000đ</div>
            <?php if ($current_saved_coupon === 'TRN30'): ?>
                <a href="#" class="btn-coupon saved">Đã lưu & Áp dụng</a>
            <?php else: ?>
                <a href="index.php?save_coupon=TRN30#san-pham" class="btn-coupon">Lưu mã</a>
            <?php endif; ?>
        </div>

        <!-- Mã 2 -->
        <div class="coupon-card <?= ($current_saved_coupon === 'TRN60') ? 'active-coupon' : '' ?>">
            <div class="coupon-tag">★ Độc Quyền Website</div>
            <div class="coupon-code">TRN60</div>
            <div class="coupon-desc">Giảm 60.000đ</div>
            <div class="coupon-cond">Áp dụng cho đơn hàng từ 700.000đ</div>
            <?php if ($current_saved_coupon === 'TRN60'): ?>
                <a href="#" class="btn-coupon saved">Đã lưu & Áp dụng</a>
            <?php else: ?>
                <a href="index.php?save_coupon=TRN60#san-pham" class="btn-coupon">Lưu mã</a>
            <?php endif; ?>
        </div>

        <!-- Mã 3 -->
        <div class="coupon-card <?= ($current_saved_coupon === 'TRN100') ? 'active-coupon' : '' ?>">
            <div class="coupon-tag">★ Độc Quyền Website</div>
            <div class="coupon-code">TRN100</div>
            <div class="coupon-desc">Giảm 100.000đ</div>
            <div class="coupon-cond">Áp dụng cho đơn hàng từ 1.000.000đ</div>
            <?php if ($current_saved_coupon === 'TRN100'): ?>
                <a href="#" class="btn-coupon saved">Đã lưu & Áp dụng</a>
            <?php else: ?>
                <a href="index.php?save_coupon=TRN100#san-pham" class="btn-coupon">Lưu mã</a>
            <?php endif; ?>
        </div>

        <!-- Mã 4 -->
        <div class="coupon-card <?= ($current_saved_coupon === 'TRN150') ? 'active-coupon' : '' ?>">
            <div class="coupon-tag">★ Độc Quyền Website</div>
            <div class="coupon-code">TRN150</div>
            <div class="coupon-desc">Giảm 150.000đ</div>
            <div class="coupon-cond">Áp dụng cho đơn hàng từ 1.300.000đ</div>
            <?php if ($current_saved_coupon === 'TRN150'): ?>
                <a href="#" class="btn-coupon saved">Đã lưu & Áp dụng</a>
            <?php else: ?>
                <a href="index.php?save_coupon=TRN150#san-pham" class="btn-coupon">Lưu mã</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- --- KHU VỰC RIÊNG: DANH MỤC ÁO NỔI BẬT Ở GIỮA TRANG (ĐỒNG GIÁ 135K) --- -->
<?php if ($search === '' && $gender === '' && !empty($shirt_variants)): ?>
<div style="max-width: 1280px; margin: 35px auto 10px; padding: 0 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-left: 4px solid #111; padding-left: 12px;">
        <h2 style="font-size: 18px; font-weight: 800; text-transform: uppercase; color: #111; letter-spacing: 0.5px;">Bộ Sưu Tập Áo Hot Nhất</h2>
        <a href="index.php?search=Áo" style="font-size: 13px; font-weight: 700; color: #e74c3c; text-decoration: none;">Xem tất cả áo →</a>
    </div>

    <div class="product-grid">
        <?php foreach ($shirt_variants as $index => $sv): 
            $shirt_imgs = ['assets/sanpham2.jpg', 'assets/sanpham3.jpg', 'assets/sanpham4.jpg', 'assets/sanpham5.jpg'];
            $shirt_img = $shirt_imgs[$index % count($shirt_imgs)];
            $real_img = !empty($sv['image_url']) ? $sv['image_url'] : $shirt_img;
        ?>
        <div class="product-card">
            <span class="card-badge-top" style="background: #111;">HOT ÁO</span>
            <div>
                <div class="product-img-box">
                    <a href="product_detail.php?id=<?= $sv['id'] ?>">
                        <img src="<?= htmlspecialchars($real_img) ?>" alt="Ảnh áo" class="product-img">
                    </a>
                </div>
                <div class="product-content">
                    <a href="product_detail.php?id=<?= $sv['id'] ?>" style="text-decoration: none;">
                        <h3><?= htmlspecialchars($sv['product_name']) ?></h3>
                    </a>
                    <div class="variant-tag"><?= htmlspecialchars($sv['variant_name']) ?></div>
                    
                    <div class="price-stock-row">
                        <div class="price">135.000đ</div>
                        <div class="stock">Kho: <strong><?= $sv['available_stock'] ?></strong></div>
                    </div>
                </div>
            </div>
            <div style="padding: 0 16px 16px 16px;">
                <a href="product_detail.php?id=<?= $sv['id'] ?>" class="btn-buy">Chọn Size & Mua Ngay</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- --- THANH BANNER QUẢNG CÁO CHẠY NGANG (PROMO TICKER) --- -->
<div class="promo-ticker-wrap">
    <div class="promo-ticker-content">
        <span>🔥 ƯU ĐÃI ĐẶC BIỆT: GIẢM ĐẾN 50% TOÀN BỘ SẢN PHẨM THỜI TRANG THU ĐÔNG</span>
        <span>★ MIỄN PHÍ VẬN CHUYỂN CHO ĐƠN HÀNG TỪ 500K</span>
        <span>⚡ ĐỔI TRẢ HÀNG TRONG VÒNG 7 NGÀY MIỄN PHÍ</span>
        <span>💎 CAM KẾT CHÍNH HÃNG 100% - HỖ TRỢ TƯ VẤN 24/7 QUA ZALO</span>
    </div>
</div>

<div class="main-container" id="san-pham">
    <div class="filter-bar">
        <span style="font-weight: 700; color: #444; margin-right: 5px; font-size: 13px;">LỌC SẢN PHẨM:</span>
        <a href="index.php" class="filter-pill <?= $gender === '' ? 'active' : '' ?>">Tất Cả</a>
        <a href="index.php?gender=nam" class="filter-pill <?= $gender === 'nam' ? 'active' : '' ?>">Thời Trang Nam</a>
        <a href="index.php?gender=nu" class="filter-pill <?= $gender === 'nu' ? 'active' : '' ?>">Thời Trang Nữ</a>
    </div>

    <?php if (empty($variants)): ?>
        <p style="text-align: center; color: #666; font-size: 15px; padding: 50px; background: #fff; border-radius: 10px; border: 1px solid #eaeaea;">Không tìm thấy sản phẩm phù hợp với từ khóa của bạn!</p>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($variants as $index => $v): 
                $discounts = ['-30%', '-40%', '-50%', '-33%', '-25%'];
                $discount_label = $discounts[$index % count($discounts)];
                
                $real_img = getExactProductImage($v['product_name'] ?? '', $v['image_url'] ?? '');
            ?>
            <div class="product-card">
                <span class="card-badge-top">SALE <?= $discount_label ?></span>
                
                <div>
                    <div class="product-img-box">
                        <a href="product_detail.php?id=<?= $v['id'] ?>">
                            <img src="<?= htmlspecialchars($real_img) ?>" alt="Ảnh sản phẩm" class="product-img">
                        </a>
                    </div>

                    <div class="product-content">
                        <a href="product_detail.php?id=<?= $v['id'] ?>" style="text-decoration: none;">
                            <h3><?= htmlspecialchars($v['product_name']) ?></h3>
                        </a>
                        <div class="variant-tag"><?= htmlspecialchars($v['variant_name']) ?></div>
                        
                        <div class="price-stock-row">
                            <div class="price"><?= number_format($v['price'], 0, ',', '.') ?>đ</div>
                            <div class="stock">Kho: <strong><?= $v['available_stock'] ?></strong></div>
                        </div>
                    </div>
                </div>

                <div style="padding: 0 16px 16px 16px;">
                    <a href="product_detail.php?id=<?= $v['id'] ?>" class="btn-buy">Chọn Size & Mua Ngay</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

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

<!-- JAVASCRIPT ĐIỀU KHIỂN BẢNG TRƯỢT QUẢNG CÁO -->
<script>
    let currentSlide = 0;
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    let slideInterval;

    function showSlide(index) {
        if (slides.length === 0) return;
        if (index >= slides.length) currentSlide = 0;
        else if (index < 0) currentSlide = slides.length - 1;
        else currentSlide = index;

        slides.forEach(s => s.classList.remove('active'));
        dots.forEach(d => d.classList.remove('active'));

        slides[currentSlide].classList.add('active');
        dots[currentSlide].classList.add('active');
    }

    function moveSlide(step) {
        showSlide(currentSlide + step);
        resetTimer();
    }

    function goToSlide(index) {
        showSlide(index);
        resetTimer();
    }

    function resetTimer() {
        clearInterval(slideInterval);
        slideInterval = setInterval(() => showSlide(currentSlide + 1), 5000);
    }

    if (slides.length > 0) {
        slideInterval = setInterval(() => showSlide(currentSlide + 1), 5000);
    }
</script>

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
</body>
</html>