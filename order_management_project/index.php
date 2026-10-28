<?php
session_start();
require_once 'db.php';

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

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cửa Hàng Thời Trang Chính Hãng</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; }
        
        /* --- HEADER NỀN ĐEN CHUẨN TORANO --- */
        header { 
            background: #151515; 
            color: #fff;
            padding: 14px 50px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
        }
        .logo-area a { 
            text-decoration: none;
            font-size: 26px; 
            font-weight: 800; 
            color: #fff; 
            letter-spacing: 3px; 
            text-transform: uppercase; 
        }
        .logo-area span { color: #e74c3c; }

        /* Thanh tìm kiếm nền trắng trên Header đen */
        .header-search { 
            display: flex; 
            align-items: center; 
            background: #ffffff; 
            border-radius: 4px; 
            padding: 4px; 
            width: 460px; 
        }
        .header-search input { 
            border: none; 
            background: transparent; 
            outline: none; 
            width: 100%; 
            padding: 8px 14px;
            font-size: 14px; 
            color: #333; 
        }
        .header-search button { 
            background: #222; 
            color: #fff;
            border: none; 
            border-radius: 3px;
            cursor: pointer; 
            font-size: 14px; 
            padding: 8px 14px; 
            transition: background 0.2s;
        }
        .header-search button:hover { background: #e74c3c; }

        /* Menu điều hướng phải */
        .header-nav { display: flex; align-items: center; gap: 24px; }
        .header-nav a { 
            color: #eee; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500; 
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s; 
        }
        .header-nav a:hover { color: #e74c3c; }
        .badge { 
            background: #f39c12; 
            color: #000; 
            padding: 2px 6px; 
            border-radius: 10px; 
            font-size: 11px; 
            font-weight: 800; 
        }
        .btn-admin { background: #e74c3c !important; color: #fff !important; padding: 6px 12px; border-radius: 4px; }

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
        .slide.active {
            opacity: 1;
            z-index: 1;
        }
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
        .slide-subtitle {
            font-size: 14px;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #dcdcdc;
            margin-bottom: 14px;
            font-weight: 500;
        }
        .slide-title {
            font-family: Georgia, 'Times New Roman', Times, serif;
            font-size: 56px;
            font-weight: 400;
            letter-spacing: 2px;
            margin-bottom: 12px;
            line-height: 1.1;
        }
        .slide-desc {
            font-size: 20px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #e2e2e2;
            margin-bottom: 35px;
            font-weight: 300;
        }
        .slide-btn {
            display: inline-flex;
            align-items: center;
            gap: 20px;
            padding: 13px 32px;
            border: 1px solid rgba(255,255,255,0.85);
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            background: rgba(0,0,0,0.2);
        }
        .slide-btn:hover {
            background: #ffffff;
            color: #111111;
        }

        .slider-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
            backdrop-filter: blur(4px);
        }
        .slider-arrow:hover { background: rgba(255,255,255,0.45); }
        .slider-arrow.prev { left: 25px; }
        .slider-arrow.next { right: 25px; }

        .slider-dots {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            gap: 8px;
        }
        .dot {
            width: 28px;
            height: 3px;
            background: rgba(255,255,255,0.4);
            cursor: pointer;
            transition: all 0.3s;
        }
        .dot.active {
            background: #ffffff;
            width: 42px;
        }

        /* --- KHỐI ĐỘC QUYỀN TRÊN WEBSITE --- */
        .exclusive-banner-box {
            background: #f3e6d3;
            padding: 35px 40px;
            border-radius: 12px;
            margin-bottom: 45px;
        }
        .exclusive-header {
            font-size: 22px;
            font-weight: 800;
            color: #111;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 22px;
        }
        .exclusive-badge {
            display: inline-block;
            background: #1a1a1a;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .exclusive-badge span { color: #e74c3c; }

        /* --- CONTAINER CHÍNH --- */
        .main-container { padding: 40px; max-width: 1280px; margin: auto; }
        
        .filter-bar { display: flex; gap: 12px; margin-bottom: 30px; align-items: center; }
        .filter-pill { padding: 8px 22px; background: #fff; border: 1px solid #ddd; border-radius: 30px; color: #444; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.2s; }
        .filter-pill.active, .filter-pill:hover { background: #111; color: white; border-color: #111; }

        /* --- LƯỚI SẢN PHẨM --- */
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 24px; }
        .product-card { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.04); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s ease; border: 1px solid #eaeaea; padding-top: 12px; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        
        .card-top-tag { padding: 0 15px; }
        .product-img-box { position: relative; overflow: hidden; background: #f1f3f5; height: 230px; margin: 6px 12px 0; border-radius: 6px; }
        .product-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .product-card:hover .product-img { transform: scale(1.06); }

        .product-content { padding: 15px; }
        .product-card h3 { font-size: 15px; font-weight: 600; margin: 0 0 8px 0; color: #111; line-height: 1.4; height: 42px; overflow: hidden; }
        .product-card h3:hover { color: #e74c3c; }
        
        .variant-name-tag { display: inline-block; background: #f1f3f5; color: #555; padding: 3px 8px; border-radius: 4px; font-size: 12px; margin-bottom: 10px; font-weight: 500; }
        
        .price-stock-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .price { color: #e74c3c; font-weight: 700; font-size: 17px; }
        .stock { font-size: 12px; color: #888; font-weight: 500; }

        .btn-buy { background: #111; color: white; padding: 11px; border: none; cursor: pointer; border-radius: 6px; width: 100%; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; transition: background 0.2s; text-align: center; display: block; text-decoration: none; }
        .btn-buy:hover { background: #e74c3c; }

        /* --- CHÂN TRANG (FOOTER) --- */
        .site-footer {
            background: #111111;
            color: #b0b0b0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 50px;
            border-top: 3px solid #e74c3c;
            margin-top: 60px;
        }
        .footer-newsletter {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px 40px;
            border-bottom: 1px solid #2a2a2a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .footer-newsletter h3 {
            color: #fff;
            font-size: 18px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .newsletter-form {
            display: flex;
            max-width: 450px;
            width: 100%;
        }
        .newsletter-form input {
            flex: 1;
            padding: 12px 16px;
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 4px 0 0 4px;
            color: #fff;
            outline: none;
            font-size: 14px;
        }
        .newsletter-form input:focus { border-color: #e74c3c; }
        .newsletter-form button {
            background: #e74c3c;
            color: #fff;
            border: none;
            padding: 0 24px;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            transition: background 0.2s;
        }
        .newsletter-form button:hover { background: #c0392b; }

        .footer-main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 30px;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1.5fr;
            gap: 40px;
        }
        @media (max-width: 850px) {
            .footer-main { grid-template-columns: 1fr; }
        }
        .footer-col h4 {
            color: #fff;
            font-size: 15px;
            text-transform: uppercase;
            margin-bottom: 20px;
            font-weight: 800;
            letter-spacing: 0.5px;
            border-left: 3px solid #e74c3c;
            padding-left: 10px;
        }
        .footer-col p, .footer-col li, .footer-col a {
            font-size: 14px;
            line-height: 1.8;
            color: #b0b0b0;
            text-decoration: none;
        }
        .footer-col ul { list-style: none; padding: 0; }
        .footer-col ul li a:hover { color: #fff; text-decoration: underline; }
        .footer-col .contact-info {
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .footer-bottom {
            background: #080808;
            padding: 20px 30px;
            text-align: center;
            font-size: 13px;
            color: #777;
            border-top: 1px solid #1a1a1a;
        }
    </style>
</head>
<body>

<!-- HEADER NỀN ĐEN -->
<header>
    <div class="logo-area">
        <a href="index.php">TORANO<span>.</span></a>
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
            <div class="slide-subtitle">NEW ARRIVAL</div>
            <h1 class="slide-title">FALL/WINTER 2026</h1>
            <div class="slide-desc">PHONG CÁCH PHÁI MẠNH MÙA LẠNH</div>
            <a href="#san-pham" class="slide-btn">MUA NGAY <span>❯</span></a>
        </div>
    </div>

    <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1490578474895-699cd4e2cf59?w=1600&auto=format&fit=crop&q=80');">
        <div class="slide-content">
            <div class="slide-subtitle">EXCLUSIVE COLLECTION</div>
            <h1 class="slide-title">ELEGANT & MODERN</h1>
            <div class="slide-desc">ĐỊNH HÌNH PHONG CÁCH LỊCH LÃM</div>
            <a href="index.php?gender=nam" class="slide-btn">KHÁM PHÁ NGAY <span>❯</span></a>
        </div>
    </div>

    <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?w=1600&auto=format&fit=crop&q=80');">
        <div class="slide-content">
            <div class="slide-subtitle">SPECIAL OFFER</div>
            <h1 class="slide-title">SEASON SALE 2026</h1>
            <div class="slide-desc">ƯU ĐÃI LÊN ĐẾN 50% TOÀN BỘ CỬA HÀNG</div>
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

<div class="main-container" id="san-pham">
    <div class="filter-bar">
        <span style="font-weight: 700; color: #444; margin-right: 5px; font-size: 14px;">Bộ lọc nhanh:</span>
        <a href="index.php" class="filter-pill <?= $gender === '' ? 'active' : '' ?>">Tất Cả</a>
        <a href="index.php?gender=nam" class="filter-pill <?= $gender === 'nam' ? 'active' : '' ?>"> Nam</a>
        <a href="index.php?gender=nu" class="filter-pill <?= $gender === 'nu' ? 'active' : '' ?>"> Nữ</a>
    </div>

    <?php if (empty($variants)): ?>
        <p style="text-align: center; color: #666; font-size: 16px; padding: 50px; background: #fff; border-radius: 10px;">Không tìm thấy sản phẩm phù hợp với từ khóa của bạn!</p>
    <?php else: ?>
        <div class="exclusive-banner-box">
            <div class="exclusive-header">ĐỘC QUYỀN TRÊN WEBSITE</div>
            <div class="product-grid">
                <?php foreach ($variants as $v): ?>
                <div class="product-card">
                    <div>
                        <div class="card-top-tag">
                            <span class="exclusive-badge"><span>★</span> ĐỘC QUYỀN TRÊN WEBSITE</span>
                        </div>
                        <div class="product-img-box">
                            <a href="product_detail.php?id=<?= $v['id'] ?>">
                                <img src="<?= htmlspecialchars($v['image_url'] ?? 'https://via.placeholder.com/250') ?>" alt="Ảnh sản phẩm" class="product-img">
                            </a>
                        </div>

                        <div class="product-content">
                            <a href="product_detail.php?id=<?= $v['id'] ?>" style="text-decoration: none;">
                                <h3><?= htmlspecialchars($v['product_name']) ?></h3>
                            </a>
                            <div class="variant-name-tag"><?= htmlspecialchars($v['variant_name']) ?></div>
                            
                            <div class="price-stock-row">
                                <div class="price"><?= number_format($v['price'], 0, ',', '.') ?>đ</div>
                                <div class="stock">Kho: <strong><?= $v['available_stock'] ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 0 15px 15px 15px;">
                        <a href="product_detail.php?id=<?= $v['id'] ?>" class="btn-buy">Chọn Size & Mua Ngay</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- CHÂN TRANG (FOOTER) CHỨA THÔNG TIN CỦA BẠN -->
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
            <div class="contact-info">
                <span>📍</span>
                <span><strong>Địa chỉ:</strong> 01 Ba La, Hà Đông, Hà Nội</span>
            </div>
            <div class="contact-info">
                <span>📞</span>
                <span><strong>Hotline/SĐT:</strong> 0976539070</span>
            </div>
            <div class="contact-info">
                <span>✉️</span>
                <span><strong>Email:</strong> nkoxslile701@gmail.com</span>
            </div>
            <div class="contact-info">
                <span>⏰</span>
                <span><strong>Giờ mở cửa:</strong> 08:30 - 22:00 (Tất cả các ngày)</span>
            </div>
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
            <p style="font-size: 13px; color: #888;">Hỗ trợ thanh toán linh hoạt qua chuyển khoản ngân hàng, ví điện tử hoặc thanh toán khi nhận hàng (COD).</p>
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
<!-- NÚT LIÊN HỆ ZALO NỔI GÓC MÀN HÌNH (CÓ TÍNH NĂNG TỰ ĐỘNG COPY SỐ) -->
<div id="zalo-floating-icon" style="position: fixed; bottom: 25px; right: 25px; z-index: 9999; display: flex; align-items: center; gap: 10px; cursor: pointer;">
    <a href="https://zalo.me/0569230660" target="_blank" onclick="copyZaloNumber(event)" title="Nhấn để chat Zalo & sao chép số" style="display: flex; align-items: center; justify-content: center; width: 55px; height: 55px; background: #0068ff; border-radius: 50%; box-shadow: 0 4px 15px rgba(0, 104, 255, 0.4); text-decoration: none; transition: transform 0.3s ease; position: relative;">
        <span style="color: #fff; font-size: 24px; font-weight: 800; font-family: Arial, sans-serif;">Z</span>
    </a>
</div>

<!-- THÔNG BÁO SAO CHÉP THÀNH CÔNG (TOAST) -->
<div id="zalo-toast" style="position: fixed; bottom: 35px; right: 95px; z-index: 10000; background: #111; color: #fff; padding: 10px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.2); opacity: 0; transition: opacity 0.3s ease; pointer-events: none; white-space: nowrap;">
    📋 Đã sao chép số Zalo: 0569230660
</div>

<!-- HIỆU ỨNG VÀ JAVASCRIPT HỖ TRỢ COPY -->
<style>
    #zalo-floating-icon a:hover {
        transform: scale(1.1);
    }
    @keyframes zalo-pulse {
        0% { box-shadow: 0 0 0 0 rgba(0, 104, 255, 0.5); }
        70% { box-shadow: 0 0 0 15px rgba(0, 104, 255, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 104, 255, 0); }
    }
    #zalo-floating-icon a {
        animation: zalo-pulse 2s infinite;
    }
</style>

<script>
function copyZaloNumber(event) {
    // Sao chép số điện thoại vào clipboard
    navigator.clipboard.writeText('0569230660').then(function() {
        // Hiển thị thông báo nhỏ gọn
        var toast = document.getElementById('zalo-toast');
        toast.style.opacity = '1';
        setTimeout(function() {
            toast.style.opacity = '0';
        }, 2500);
    }).catch(function(err) {
        console.error('Không thể sao chép: ', err);
    });
}
</script>
</body>
</html>