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

    if ($username === '' || $password === '') {$error = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu!';
    } else {
        // Kiểm tra theo username (hoặc email nếu bảng của bạn có dùng)
        $stmt =$pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user =$stmt->fetch();

        // Hỗ trợ cả mật khẩu mã hóa (password_verify) và mật khẩu thường (plain text) trong lúc test
        if ($user && (password_verify($password,$user['password']) || $password ===$user['password'])) {
            $_SESSION['user'] =$user;
            header('Location: index.php');
            exit;
        } else {
            $error = 'Tài khoản hoặc mật khẩu không chính xác!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng Nhập - Cửa Hàng Thời Trang</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f8f9fa; 
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
            color: #555; 
            font-size: 14px; 
            font-weight: 600; 
            transition: color 0.2s; 
        }
        .back-home:hover { color: #111; }

        /* Khung Đăng nhập trung tâm */
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
            max-width: 440px; 
            padding: 45px 40px; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            border: 1px solid #f0f0f0; 
        }
        .auth-header { text-align: center; margin-bottom: 30px; }
        .auth-header h1 { 
            font-size: 24px; 
            font-weight: 800; 
            color: #111; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            margin-bottom: 8px; 
        }
        .auth-header p { font-size: 14px; color: #777; }

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
            font-size: 13px; 
            font-weight: 700; 
            color: #333; 
            margin-bottom: 8px; 
            text-transform: uppercase; 
            letter-spacing: 0.3px; 
        }
        .form-control { 
            width: 100%; 
            padding: 13px 16px; 
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
            margin-top: 10px; 
        }
        .btn-submit:hover { background: #333; }
        .btn-submit:active { transform: scale(0.99); }

        /* Liên kết chuyển trang */
        .auth-footer { 
            text-align: center; 
            margin-top: 25px; 
            padding-top: 20px; 
            border-top: 1px solid #eee; 
            font-size: 14px; 
            color: #666; 
        }
        .auth-footer a { 
            color: #111; 
            font-weight: 700; 
            text-decoration: none; 
            margin-left: 4px; 
        }
        .auth-footer a:hover { color: #e74c3c; text-decoration: underline; }
    </style>
</head>
<body>

<header>
    <div class="logo-area">
        <a href="index.php">Cửa Hàng<span>.</span></a>
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
                <input type="password" id="password" name="password" class="form-control" placeholder="Nhập mật khẩu..." required>
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