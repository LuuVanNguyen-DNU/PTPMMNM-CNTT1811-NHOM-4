<?php
session_start();
require_once 'db.php';

// Lấy ID biến thể (variant_id) được truyền từ trang chủ
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 1. Lấy thông tin biến thể hiện tại và sản phẩm chính
$stmt = $pdo->prepare("SELECT v.*, p.name AS product_name, p.id AS real_product_id FROM variants v JOIN products p ON v.product_id = p.id WHERE v.id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<script>alert('Không tìm thấy sản phẩm!'); window.location='index.php';</script>";
    exit;
}

// 2. Lấy TẤT CẢ các biến thể (các Size khác nhau) của cùng một sản phẩm này
$variantsStmt = $pdo->prepare("SELECT * FROM variants WHERE product_id = ?");
$variantsStmt->execute([$product['real_product_id']]);
$all_variants = $variantsStmt->fetchAll();

// 3. Lấy danh sách ảnh từ bảng product_images cho biến thể hiện tại
$images = [];
try {
    $imgStmt = $pdo->prepare("SELECT image_url FROM product_images WHERE variant_id = ?");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Bỏ qua nếu bảng chưa tồn tại
}

$default_img = isset($product['image_url']) ? $product['image_url'] : 'https://via.placeholder.com/500';
if (empty($images)) {
    $images = array_fill(0, 4, $default_img);
}

// 4. Xử lý thêm vào giỏ hàng
if (isset($_POST['add_to_cart'])) {
    $selected_variant_id = intval($_POST['variant_id']);
    $qty = intval($_POST['quantity']);

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    
    if (isset($_SESSION['cart'][$selected_variant_id])) {
        $_SESSION['cart'][$selected_variant_id] += $qty;
    } else {
        $_SESSION['cart'][$selected_variant_id] = $qty;
    }
    echo "<script>alert('Đã thêm sản phẩm vào giỏ hàng!'); window.location='cart.php';</script>";
    exit;
}

$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><= htmlspecialchars($product['product_name']) ?> - Chi Tiết Sản Phẩm</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; }
        
        /* Header đồng bộ */
        header { background: #151515; color: #fff; padding: 14px 50px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }
        .logo-area a { text-decoration: none; font-size: 24px; font-weight: 800; color: #fff; letter-spacing: 2px; text-transform: uppercase; }
        .logo-area span { color: #e74c3c; }
        .header-nav { display: flex; align-items: center; gap: 20px; }
        .header-nav a { color: #ccc; text-decoration: none; font-size: 13px; font-weight: 500; transition: color 0.2s; }
        .header-nav a:hover { color: #fff; }
        .badge { background: #f39c12; color: #000; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 800; }

        /* Container chính */
        .container { max-width: 1200px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); display: flex; gap: 50px; flex-wrap: wrap; border: 1px solid #eaeaea; }
        
        /* Khung ảnh & Slider */
        .gallery-container { flex: 1; min-width: 400px; }
        .main-image-box { width: 100%; height: 480px; border-radius: 10px; overflow: hidden; background: #f1f3f5; border: 1px solid #eee; margin-bottom: 15px; }
        .main-image-preview { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .main-image-box:hover .main-image-preview { transform: scale(1.03); }
        
        .slider-wrapper { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 5px; }
        .thumbnail { width: 85px; height: 85px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 2px solid transparent; flex-shrink: 0; transition: all 0.2s; background: #f1f3f5; }
        .thumbnail:hover, .thumbnail.active { border-color: #111; transform: translateY(-2px); }

        /* Thông tin sản phẩm */
        .product-info { flex: 1; min-width: 350px; display: flex; flex-direction: column; justify-content: space-between; }
        .product-info h1 { margin-top: 0; font-size: 26px; font-weight: 800; color: #111; line-height: 1.3; margin-bottom: 15px; }
        
        .price-box { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .price { font-size: 28px; color: #e74c3c; font-weight: 800; }
        .stock-badge { background: #e2f0d9; color: #385723; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        
        /* Chọn Size */
        .size-group { margin: 20px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee; padding: 20px 0; }
        .size-label { font-weight: 700; display: block; margin-bottom: 12px; color: #222; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .size-options { display: flex; gap: 12px; flex-wrap: wrap; }
        .size-btn { padding: 10px 22px; border: 1px solid #ccc; border-radius: 6px; background: #fff; color: #333; text-decoration: none; font-weight: 700; font-size: 14px; transition: all 0.2s; display: inline-block; }
        .size-btn:hover { border-color: #111; color: #111; }
        .size-btn.active { background: #111; color: #fff; border-color: #111; }

        /* Chọn số lượng & Nút mua */
        .purchase-box { margin-top: 10px; }
        .quantity-row { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .quantity-row label { font-weight: 700; font-size: 13px; text-transform: uppercase; color: #444; }
        .quantity-box { display: flex; align-items: center; border: 1px solid #ccc; border-radius: 6px; overflow: hidden; background: #fff; }
        .quantity-box input { width: 60px; padding: 10px; text-align: center; border: none; outline: none; font-size: 16px; font-weight: 700; }
        
        .btn-add { background: #111; color: white; padding: 16px; border: none; border-radius: 6px; font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; width: 100%; transition: background 0.2s, transform 0.1s; }
        .btn-add:hover { background: #e74c3c; }
        .btn-add:active { transform: scale(0.99); }

        /* Cam kết dịch vụ */
        .policy-box { margin-top: 25px; background: #f8f9fa; border: 1px solid #eaeaea; border-radius: 8px; padding: 15px 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px; color: #555; }
        .policy-item { display: flex; align-items: center; gap: 8px; font-weight: 600; color: #222; }

        .back-link { display: inline-block; margin-bottom: 20px; color: #555; text-decoration: none; font-weight: 600; font-size: 14px; transition: color 0.2s; }
        .back-link:hover { color: #111; }
    </style>
</head>
<body>

<header>
    <div class="logo-area">
        <a href="index.php">TORANO<span>.</span></a>
    </div>
    <div class="header-nav">
        <a href="cart.php">🛒 Giỏ hàng <span class="badge"><?= $cart_count ?></span></a>
        <a href="index.php">Trang chủ</a>
    </div>
</header>

<div style="max-width: 1200px; margin: 25px auto 0; padding: 0 20px;">
    <a href="index.php" class="back-link">← Quay lại trang chủ mua sắm</a>
</div>

<div class="container">
    <!-- Phần lướt ảnh chuyên nghiệp -->
    <div class="gallery-container">
        <div class="main-image-box">
            <img id="mainPreview" src="<?= htmlspecialchars($images[0]) ?>" alt="Ảnh sản phẩm chính" class="main-image-preview">
        </div>
        <div class="slider-wrapper">
            <?php foreach ($images as $index => $img): ?>
                <img src="<?= htmlspecialchars($img) ?>" alt="Ảnh thu nhỏ" class="thumbnail <?= $index === 0 ? 'active' : '' ?>" onclick="changeImage('<?= htmlspecialchars($img) ?>', this)">
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Phần thông tin chi tiết & Đặt hàng -->
    <div class="product-info">
        <div>
            <h1><?= htmlspecialchars($product['product_name']) ?></h1>
            
            <div class="price-box">
                <div class="price"><?= number_format($product['price'], 0, ',', '.') ?>đ</div>
                <div class="stock-badge">✓ Còn hàng (<?= $product['available_stock'] ?> sản phẩm)</div>
            </div>

            <!-- Khối chọn Size / Biến thể -->
            <div class="size-group">
                <span class="size-label">Kích Thước (Size):</span>
                <div class="size-options">
                    <?php foreach ($all_variants as $v): ?>
                        <a href="product_detail.php?id=<?= $v['id'] ?>" class="size-btn <?= $v['id'] == $product['id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($v['variant_name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <form method="POST" class="purchase-box">
            <input type="hidden" name="variant_id" value="<?= $product['id'] ?>">
            
            <div class="quantity-row">
                <label for="quantity">Số lượng:</label>
                <div class="quantity-box">
                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= $product['available_stock'] ?>">
                </div>
            </div>

            <button type="submit" name="add_to_cart" class="btn-add">Thêm Vào Giỏ Hàng</button>
        </form>

        <!-- Khối cam kết mua hàng -->
        <div class="policy-box">
            <div class="policy-item">📦 Kiểm tra hàng trước khi thanh toán</div>
            <div class="policy-item">🔄 Đổi trả size trong vòng 7 ngày</div>
            <div class="policy-item">🛡️ Cam kết chính hãng 100%</div>
            <div class="policy-item">🚀 Giao hàng nhanh toàn quốc</div>
        </div>
    </div>
</div>

<script>
    function changeImage(src, element) {
        document.getElementById('mainPreview').src = src;
        document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
        element.classList.add('active');
    }
</script>

</body>
</html>