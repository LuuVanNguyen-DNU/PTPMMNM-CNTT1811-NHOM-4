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
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu!';
    } else {
        try {
            // Truy vấn an toàn chỉ theo cột username để tránh lỗi SQL column not found
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Hỗ trợ cả mật khẩu mã hóa (password_verify) và mật khẩu thường (plain text)
            if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
                $_SESSION['user'] = $user;
                header('Location: index.php');
                exit;
            } else {
                $error = 'Tài khoản hoặc mật khẩu không chính xác!';
            }
        } catch (Exception $e) {
            $error = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng Nhập - ANHNGUYEN</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f4f5f7; 
            color: #333; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }

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

        /* Header đồng bộ cao cấp */
        header { 
            background: linear-gradient(135deg, #111111 0%, #1a1a1a 100%); 
            color: #fff;
            padding: 14px 40px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
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
        .back-home { 
            text-decoration: none; 
            color: #dcdcdc; 
            font-size: 13px; 
            font-weight: 600; 
            padding: 8px 14px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.2s ease; 
        }
        .back-home:hover { color: #fff; background: rgba(255, 255, 255, 0.12); }

        /* Khung Đăng nhập trung tâm với nền thời trang */
        .auth-wrapper { 
            flex: 1; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 50px 20px;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.65)), url('https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?w=1600&auto=format&fit=crop&q=80') center/cover no-repeat;
        }
        .auth-card { 
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            width: 100%; 
            max-width: 440px; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        .auth-header { text-align: center; margin-bottom: 25px; }
        .auth-header h1 { 
            font-size: 24px; 
            font-weight: 900; 
            color: #111; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            margin-bottom: 8px; 
        }
        .auth-header p { font-size: 13px; color: #666; }

        /* Thông báo lỗi */
        .alert-error { 
            background: #fff5f5; 
            color: #e53e3e; 
            padding: 12px 15px; 
            border-radius: 6px; 
            border-left: 4px solid #e53e3e; 
            font-size: 13px; 
            font-weight: 600; 
            margin-bottom: 20px; 
        }

        /* Ô nhập liệu */
        .form-group { margin-bottom: 20px; }
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
            padding: 12px 16px; 
            font-size: 14px; 
            border: 1px solid #dcdcdc; 
            border-radius: 8px; 
            background: #fafafa; 
            color: #111; 
            transition: all 0.25s ease; 
            outline: none; 
        }
        .form-control:focus { 
            background: #fff; 
            border-color: #111; 
            box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.08); 
        }

        /* Nút bấm */
        .btn-submit { 
            width: 100%; 
            padding: 14px; 
            background: #111; 
            color: #fff; 
            border: none; 
            border-radius: 8px; 
            font-size: 14px; 
            font-weight: 800; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            cursor: pointer; 
            transition: background 0.2s, transform 0.1s; 
            margin-top: 25px; 
        }
        .btn-submit:hover { background: #e74c3c; }

        /* Liên kết chuyển trang */
        .auth-footer { 
            text-align: center; 
            margin-top: 20px; 
            font-size: 13px; 
            color: #666; 
        }
        .auth-footer a { 
            color: #e74c3c; 
            font-weight: 700; 
            text-decoration: none; 
            margin-left: 4px; 
        }
        .auth-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<!-- THANH THÔNG BÁO ƯU ĐÃI TRÊN CÙNG -->
<div class="top-announcement-bar">
    <span><span>★</span> Đổi hàng trong 30 ngày</span>
    <span><span>★</span> Freeship mọi đơn hàng từ 500K</span>
    <span><span>★</span> Voucher lớn - Sale giá độc quyền</span>
</div>

<header>
    <div class="logo-area">
        <a href="index.php">ANHNGUYEN<span>.</span></a>
    </div>
    <a href="index.php" class="back-home">← Quay lại cửa hàng</a>
</header>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Đăng Nhập</h1>
            <p>Chào mừng bạn quay trở lại cùng chúng tôi</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Tên đăng nhập</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Nhập tài khoản của bạn..." value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required>
            </div>

            <button type="submit" class="btn-submit">Đăng Nhập</button>
        </form>

        <div class="auth-footer">
            Khách hàng mới? <a href="register.php">Đăng ký tài khoản ngay</a>
        </div>
    </div>
</div>

</body>
</html>