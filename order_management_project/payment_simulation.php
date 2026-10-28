<?php
require_once 'db.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Đơn hàng không tồn tại!");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_ref = trim($_POST['transaction_ref']);
    $amount = floatval($_POST['amount']);

    try {
        $pdo->beginTransaction();

        $checkTx = $pdo->prepare("SELECT * FROM payment_events WHERE transaction_ref = ?");
        $checkTx->execute([$transaction_ref]);
        if ($checkTx->rowCount() > 0) {
            throw new Exception("Thông báo thanh toán đã được xử lý trước đó (Chống lặp thành công).");
        }

        $saveTx = $pdo->prepare("INSERT INTO payment_events (transaction_ref, order_id, amount, status) VALUES (?, ?, ?, 'success')");
        $saveTx->execute([$transaction_ref, $order_id, $amount]);

        $now = date('Y-m-d H:i:s');
        if ($order['status'] === 'expired' || $now > $order['expires_at']) {
            throw new Exception("Đơn hàng đã quá hạn giữ chỗ! Giao dịch đến muộn bị từ chối.");
        }

        if ($order['status'] !== 'pending_payment') {
            throw new Exception("Đơn hàng không ở trạng thái chờ thanh toán.");
        }

        if ($amount < $order['total_amount']) {
            throw new Exception("Số tiền thanh toán không đủ!");
        }

        $updateOrder = $pdo->prepare("UPDATE orders SET status = 'shipping' WHERE id = ?");
        $updateOrder->execute([$order_id]);

        $updateRes = $pdo->prepare("UPDATE reservations SET status = 'confirmed' WHERE order_id = ?");
        $updateRes->execute([$order_id]);

        $pdo->commit();
        echo "<script>alert('Thanh toán thành công!'); window.location='index.php';</script>";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Lỗi: " . $e->getMessage() . "'); window.location='index.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Mô phỏng Thanh toán</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f6f9; }
        .box { background: #fff; padding: 20px; border-radius: 5px; width: 400px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input { width: 100%; padding: 8px; margin: 8px 0; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px; border: none; width: 100%; cursor: pointer; }
    </style>
</head>
<body>
<div class="box">
    <h2>Mô phỏng Thanh toán: <?= htmlspecialchars($order['order_code']) ?></h2>
    <p>Số tiền: <strong><?= number_format($order['total_amount'], 0, ',', '.') ?>đ</strong></p>
    <form method="POST">
        <label>Mã Giao Dịch (Transaction Ref):</label>
        <input type="text" name="transaction_ref" required placeholder="VD: TXN_12345">
        <label>Số Tiền Chuyển:</label>
        <input type="number" name="amount" step="0.01" value="<?= $order['total_amount'] ?>" required>
        <button type="submit">Gửi Thanh Toán</button>
    </form>
    <br><a href="index.php">Quay lại</a>
</div>
</body>
</html>