<?php
session_start();
// Xóa toàn bộ session đăng nhập và giỏ hàng
unset($_SESSION['user']);
unset($_SESSION['cart']);
unset($_SESSION['applied_coupon']);
session_destroy();

// Chuyển hướng về trang đăng nhập
echo "<script>alert('Đăng xuất thành công!'); window.location='login.php';</script>";
exit;
?>