<?php
session_start();
require_once 'db.php';

// Nếu đã đăng nhập thì chuyển thẳng về trang chủ
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($fullname === '' || $username === '' || $password === '') {
        $error = 'Vui lòng điền đầy đủ tất cả các thông tin!';
    } elseif (strlen($password) < 4) {
        $error = 'Mật khẩu phải có ít nhất 4 ký tự!';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp!';
    } else {
        // Kiểm tra xem tên đăng nhập đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Tên đăng nhập này đã được sử dụng, vui lòng chọn tên khác!';
        } else {
            // Mã hóa mật khẩu và lưu vào CSDL với quyền mặc định là 'customer'
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (fullname, username, password, role) VALUES (?, ?, ?, 'customer')");
            
            if ($insertStmt->execute([$fullname, $username, $hashed_password])) {
                echo "<script>alert('Đăng ký tài khoản thành công! Vui lòng đăng nhập.'); window.location='login.php';</script>";
                exit;
            } else {
                $error = 'Có lỗi xảy ra trong quá trình đăng ký, vui lòng thử lại!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng Ký Thành Viên - Cửa Hàng Thời Trang</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f4f6f8; 
            color: #222; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }

        /* Header đồng bộ với Trang chủ */
        header { 
            background: #ffffff; 
            padding: 16px 40px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #eaeaea; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        .logo-area a { 
            text-decoration: none; 
            font-size: 24px; 
            font-weight: 800; 
            color: #111; 
            letter-spacing: 0.5px; 
            text-transform: uppercase; 
        }
        .logo-area span { color: #e74c3c; }
        .back-home { 
            text-decoration: none; 
            color: #444; 
            font-size: 14px; 
            font-weight: 600; 
            padding: 8px 16px;
            border-radius: 6px;
            background: #f1f3f5;
            transition: all 0.2s; 
        }
        .back-home:hover { background: #111; color: #fff; }

        /* Khung Đăng ký trung tâm */
        .auth-wrapper { 
            flex: 1; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 40px 20px; 
        }
        .auth-card { 
            background: #ffffff; 
            width: 100%; 
            max-width: 500px; 
            border-radius: 12px; 
            box-shadow: 0 12px 35px rgba(0,0,0,0.06); 
            border: 1px solid #eaeaea; 
            overflow: hidden;
        }

        /* Thanh chuyển đổi Tab Đăng nhập / Đăng ký chuẩn Shop */
        .auth-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            background: #fafafa;
        }
        .auth-tabs a {
            flex: 1;
            text-align: center;
            padding: 18px 0;
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            transition: all 0.2s;
            border-bottom: 3px solid transparent;
        }
        .auth-tabs a:hover {
            color: #111;
        }
        .auth-tabs a.active {
            color: #111;
            background: #fff;
            border-bottom-color: #e74c3c;
        }

        .auth-body {
            padding: 35px 40px 40px;
        }

        .auth-subtitle {
            text-align: center;
            font-size: 14px;
            color: #666;
            margin-bottom: 25px;
        }

        /* Thông báo lỗi */
        .alert-error { 
            background: #fff5f5; 
            color: #e53e3e; 
            padding: 12px 15px; 
            border-radius: 6px; 
            border-left: 4px solid #e53e3e; 
            font-size: 13px; 
            font-weight: 600; 
            margin-bottom: 22px; 
        }

        /* Ô nhập liệu */
        .form-group { margin-bottom: 18px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group label { 
            display: block; 
            font-size: 12px; 
            font-weight: 700; 
            color: #333; 
            margin-bottom: 7px; 
            text-transform: uppercase; 
            letter-spacing: 0.4px; 
        }
        .form-control { 
            width: 100%; 
            padding: 12px 15px; 
            font-size: 14px; 
            border: 1px solid #dcdcdc; 
            border-radius: 6px; 
            background: #fafafa; 
            color: #111; 
            transition: all 0.25s ease; 
            outline: none; 
        }
        .form-control:focus { 
            background: #fff; 
            border-color: #111; 
            box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.06); 
        }

        /* Quyền lợi thành viên */
        .benefit-box {
            background: #f8f9fa;
            border: 1px dashed #ced4da;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }
        .benefit-box strong { color: #111; }

        /* Nút bấm */
        .btn-submit { 
            width: 100%; 
            padding: 14px; 
            background: #111; 
            color: #fff; 
            border: none; 
            border-radius: 6px; 
            font-size: 15px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.8px; 
            cursor: pointer; 
            transition: background 0.2s, transform 0.1s; 
        }
        .btn-submit:hover { background: #e74c3c; }
        .btn-submit:active { transform: scale(0.99); }
    </style>
</head>
<body>

<header>
    <div class="logo-area">
        <a href="index.php">Cửa Hàng<span>.</span></a>
    </div>
    <a href="index.php" class="back-home">← Tiếp tục mua sắm</a>
</header>

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Thanh Tab chuyển đổi giống các shop thời trang -->
        <div class="auth-tabs">
            <a href="login.php">Đăng Nhập</a>
            <a href="register.php" class="active">Đăng Ký Tài Khoản</a>
        </div>

        <div class="auth-body">
            <p class="auth-subtitle">Trở thành thành viên để nhận ưu đãi và theo dõi đơn hàng dễ dàng hơn</p>

            <?php if ($error !== ''): ?>
                <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="fullname">Họ và tên khách hàng</label>
                    <input type="text" id="fullname" name="fullname" class="form-control" placeholder="Nhập họ và tên của bạn..." value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="username">Tên tài khoản (Username)</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Viết liền không dấu (VD: hoangnam99)..." value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>

                <!-- Chia 2 cột cho Mật khẩu và Nhập lại mật khẩu nhìn gọn và hiện đại -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Mật khẩu</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Tối thiểu 4 ký tự..." required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Xác nhận mật khẩu</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Nhập lại mật khẩu..." required>
                    </div>
                </div>

                <div class="benefit-box">
                    🎁 <strong>Đặc quyền thành viên:</strong> Lưu trữ lịch sử đơn hàng, giữ chỗ giỏ hàng tự động và hỗ trợ đổi trả nhanh chóng.
                </div>

                <button type="submit" class="btn-submit">Hoàn Tất Đăng Ký</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>