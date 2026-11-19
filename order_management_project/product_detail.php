<?php
session_start();
require_once 'db.php';

// Hàm ánh xạ tên sản phẩm với đúng file ảnh thực tế (sanpham1.jpg đến sanpham24.jpg)
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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Lấy thông tin biến thể và sản phẩm liên quan
$stmt = $pdo->prepare("SELECT v.*, p.name AS product_name, p.id AS real_product_id FROM variants v JOIN products p ON v.product_id = p.id WHERE v.id = ?");
$stmt->execute([$id]);
$variant = $stmt->fetch();

if (!$variant) {
    echo "<script>alert('Sản phẩm không tồn tại!'); window.location='index.php';</script>";
    exit;
}

$product_id = $variant['real_product_id'];

// Lấy tất cả các biến thể khác của sản phẩm này
$variantsStmt = $pdo->prepare("SELECT * FROM variants WHERE product_id = ?");
$variantsStmt->execute([$product_id]);
$all_variants = $variantsStmt->fetchAll();

// Xử lý thêm vào giỏ hàng (cộng dồn số lượng chính xác)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $selected_variant_id = intval($_POST['variant_id']);
    $quantity = intval($_POST['quantity']);
    if ($quantity < 1) $quantity = 1;

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$selected_variant_id])) {
        $_SESSION['cart'][$selected_variant_id] += $quantity;
    } else {
        $_SESSION['cart'][$selected_variant_id] = $quantity;
    }
    echo "<script>alert('Đã thêm sản phẩm vào giỏ hàng thành công!'); window.location='product_detail.php?id=$selected_variant_id';</script>";
    exit;
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$product_main_image = getExactProductImage($variant['product_name'] ?? '', $variant['variant_name'] ?? '', $variant['image_url'] ?? '');

// Kiểm tra tính chất sản phẩm
$p_name_lower = mb_strtolower($variant['product_name'], 'UTF-8');
$is_box_set = (strpos($p_name_lower, 'hộp') !== false || strpos($p_name_lower, 'set') !== false || strpos($p_name_lower, 'tất') !== false || strpos($p_name_lower, 'vớ') !== false);
$is_clothing = (strpos($p_name_lower, 'áo') !== false || strpos($p_name_lower, 'quần') !== false || strpos($p_name_lower, 'đầm') !== false || strpos($p_name_lower, 'váy') !== false || strpos($p_name_lower, 'len') !== false);

if ($is_box_set) {
    $label_text = 'PHÂN LOẠI SẢN PHẨM:';
} else {
    $label_text = $is_clothing ? 'CHỌN PHÂN LOẠI / KÍCH THƯỚC (SIZE & MÀU):' : 'CHỌN PHÂN LOẠI / MẪU MÃ:';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($variant['product_name']) ?> - Torano Chính Hãng</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; }
        
        header { background: #151515; color: #fff; padding: 14px 50px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .logo-area a { text-decoration: none; font-size: 24px; font-weight: 800; color: #fff; letter-spacing: 2px; text-transform: uppercase; }
        .logo-area span { color: #e74c3c; }
        .header-nav { display: flex; align-items: center; gap: 20px; }
        .header-nav a { color: #ccc; text-decoration: none; font-size: 13px; font-weight: 500; transition: color 0.2s; }
        .header-nav a:hover { color: #fff; }
        .badge { background: #f39c12; color: #000; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 800; }

        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #555; text-decoration: none; font-weight: 600; font-size: 13px; transition: color 0.2s; }
        .back-link:hover { color: #111; }

        .product-detail-card { background: #fff; border-radius: 14px; padding: 40px; box-shadow: 0 4px 25px rgba(0,0,0,0.04); border: 1px solid #eaeaea; display: grid; grid-template-columns: 1.1fr 1fr; gap: 40px; margin-bottom: 40px; }
        @media(max-width: 850px) { .product-detail-card { grid-template-columns: 1fr; } }

        .product-gallery { display: flex; flex-direction: column; gap: 15px; }
        .main-img-box { width: 100%; height: 480px; background: #f1f3f5; border-radius: 10px; overflow: hidden; border: 1px solid #eaeaea; }
        .main-img-box img { width: 100%; height: 100%; object-fit: cover; }
        
        .product-info-side h1 { font-size: 26px; font-weight: 800; color: #111; margin-bottom: 12px; line-height: 1.3; }
        .product-price-box { font-size: 26px; font-weight: 800; color: #e74c3c; margin-bottom: 15px; display: flex; align-items: center; gap: 15px; }
        .stock-status { font-size: 12px; background: #e8f8f5; color: #27ae60; padding: 4px 10px; border-radius: 20px; font-weight: 700; border: 1px solid #a3e4d7; }

        .section-label { font-size: 12px; font-weight: 700; color: #444; text-transform: uppercase; margin: 20px 0 10px; letter-spacing: 0.5px; }
        
        .variant-options { display: flex; flex-wrap: wrap; gap: 10px; }
        .variant-btn { padding: 10px 18px; border: 2px solid #ddd; border-radius: 8px; text-decoration: none; color: #333; font-size: 13px; font-weight: 600; background: #fff; transition: all 0.2s ease; display: inline-block; }
        .variant-btn:hover { border-color: #111; color: #111; }
        .variant-btn.active { border-color: #e74c3c; background: #fff5f5; color: #e74c3c; font-weight: 700; }

        .quantity-box { display: flex; align-items: center; gap: 15px; margin: 25px 0; }
        .quantity-input { width: 70px; padding: 10px; text-align: center; font-size: 15px; font-weight: 700; border: 1px solid #ddd; border-radius: 8px; outline: none; background: #fafafa; }
        .btn-add-cart { flex: 1; background: #111; color: #fff; padding: 14px; border: none; border-radius: 8px; font-weight: 800; font-size: 14px; text-transform: uppercase; cursor: pointer; transition: background 0.2s; letter-spacing: 0.5px; }
        .btn-add-cart:hover { background: #e74c3c; }

        .policy-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; }
        .policy-item { font-size: 12px; color: #555; display: flex; align-items: center; gap: 8px; font-weight: 500; }

        .details-section-grid { display: grid; grid-template-columns: 1fr; gap: 30px; }
        .box-container { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid #eaeaea; }
        .box-title { font-size: 16px; font-weight: 800; text-transform: uppercase; color: #111; margin-bottom: 20px; border-left: 4px solid #e74c3c; padding-left: 10px; letter-spacing: 0.5px; }
        
        .spec-table { width: 100%; border-collapse: collapse; }
        .spec-table tr td { padding: 12px 10px; font-size: 13px; border-bottom: 1px solid #f1f3f5; }
        .spec-table tr td:first-child { color: #777; font-weight: 600; width: 35%; }
        .spec-table tr td:last-child { color: #222; font-weight: 600; }
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
    <a href="index.php" class="back-link">← Quay lại trang chủ mua sắm</a>

    <div class="product-detail-card">
        <!-- Cột ảnh -->
        <div class="product-gallery">
            <div class="main-img-box">
                <img src="<?= htmlspecialchars($product_main_image) ?>" alt="Ảnh sản phẩm">
            </div>
        </div>

        <!-- Cột thông tin & chọn phân loại -->
        <div class="product-info-side">
            <h1><?= htmlspecialchars($variant['product_name']) ?></h1>
            
            <div class="product-price-box">
                <span><?= number_format($variant['price'], 0, ',', '.') ?>đ</span>
                <span class="stock-status">✓ Còn hàng (<?= $variant['available_stock'] ?> sản phẩm)</span>
            </div>

            <div class="section-label"><?= $label_text ?></div>
            <div class="variant-options">
                <?php if ($is_box_set): ?>
                    <span class="variant-btn active" style="cursor: default;">Hộp Tiêu Chuẩn (Set đầy đủ theo mẫu)</span>
                <?php else: ?>
                    <?php foreach ($all_variants as $v): 
                        $v_display = $v['variant_name'];
                        if (!$is_clothing) {
                            $v_display = trim(str_ireplace(['size s', 'size m', 'size l', 'size xl', 'size'], '', $v_display));
                            if (empty($v_display)) $v_display = 'Mẫu tiêu chuẩn';
                        }
                    ?>
                        <a href="product_detail.php?id=<?= $v['id'] ?>" class="variant-btn <?= ($v['id'] == $id) ? 'active' : '' ?>">
                            <?= htmlspecialchars($v_display) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Form thêm vào giỏ hàng -->
            <form method="POST">
                <input type="hidden" name="variant_id" value="<?= $variant['id'] ?>">
                
                <div class="section-label">SỐ LƯỢNG MUA:</div>
                <div class="quantity-box">
                    <input type="number" name="quantity" class="quantity-input" value="1" min="1" max="<?= max(1, $variant['available_stock']) ?>">
                    <button type="submit" name="add_to_cart" class="btn-add-cart">🛒 Thêm Vào Giỏ Hàng</button>
                </div>
            </form>

            <div class="policy-grid">
                <div class="policy-item">📦 Kiểm tra hàng trước khi thanh toán</div>
                <div class="policy-item">🔄 Đổi trả linh hoạt trong vòng 7 ngày</div>
                <div class="policy-item">⭐ Cam kết chính hãng 100%</div>
                <div class="policy-item">🚀 Giao hàng nhanh toàn quốc</div>
            </div>
        </div>
    </div>

    <!-- THÔNG SỐ CHI TIẾT -->
    <div class="details-section-grid">
        <div class="box-container">
            <div class="box-title">Thông Số Chi Tiết Sản Phẩm</div>
            <table class="spec-table">
                <tr>
                    <td>Mã sản phẩm:</td>
                    <td>TRN-<?= strtoupper(substr(md5($variant['id']), 0, 6)) ?></td>
                </tr>
                <tr>
                    <td>Tên sản phẩm:</td>
                    <td><?= htmlspecialchars($variant['product_name']) ?></td>
                </tr>
                <tr>
                    <td>Phân loại / Biến thể:</td>
                    <td><?= htmlspecialchars($variant['variant_name']) ?></td>
                </tr>
                <tr>
                    <td>Tình trạng kho:</td>
                    <td>Còn <?= $variant['available_stock'] ?> sản phẩm sẵn sàng giao hàng</td>
                </tr>
                <tr>
                    <td>Chính sách bảo hành:</td>
                    <td>Đổi trả trong 7 ngày nếu có lỗi từ nhà sản xuất</td>
                </tr>
            </table>
        </div>
    </div>
</div>

</body>
</html>