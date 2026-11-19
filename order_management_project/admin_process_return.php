<?php
session_start();
require_once 'db.php';

$return_id = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($return_id > 0 && in_array($action, ['approve', 'reject'])) {
    // Quy đổi trạng thái sang tiếng Việt tương ứng với giao diện admin
    $new_status = ($action === 'approve') ? 'Đã duyệt' : 'Từ chối';
    
    try {
        // Cập nhật trạng thái vào bảng order_returns
        $stmt = $pdo->prepare("UPDATE order_returns SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $return_id]);
    } catch (Exception $e) {
        // Dự phòng nếu bảng tên là returns
        try {
            $stmt2 = $pdo->prepare("UPDATE returns SET status = ? WHERE id = ?");
            $stmt2->execute([$new_status, $return_id]);
        } catch (Exception $e2) {}
    }
}

// Quay lại trang quản trị admin
header('Location: admin.php');
exit;
?>