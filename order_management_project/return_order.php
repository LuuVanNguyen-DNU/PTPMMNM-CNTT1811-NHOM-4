<?php
require_once 'db.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Đơn hàng không tồn tại!");
}

$itemsStmt = $pdo->prepare("SELECT oi.*, v.variant_name FROM order_items oi JOIN variants v ON oi.variant_id = v.id WHERE oi.order_id = ?");
$itemsStmt->execute([$order_id]);
$items = $itemsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $return_qty = intval($_POST['return_quantity']);
    $order_item_id = intval($_POST['order_item_id']);
    $reason = $_POST['reason'];

    try {
        $pdo->beginTransaction();

        $itemQuery = $pdo->prepare("SELECT * FROM order_items WHERE id = ?");
        $itemQuery->execute([$order_item_id]);
        $orderItem = $itemQuery->fetch();

        if (!$orderItem) throw new Exception("Sản phẩm không hợp lệ.");

        $returnedCheck = $pdo->prepare("SELECT SUM(ri.quantity) as total_returned FROM return_items ri JOIN returns r ON ri.return_id = r.id WHERE ri.order_item_id = ? AND r.status != 'rejected'");
        $returnedCheck->execute([$order_item_id]);
        $returnedData = $returnedCheck->fetch();
        $already_returned = $returnedData['total_returned'] ? intval($returnedData['total_returned']) : 0;

        $max_allowable = $orderItem['quantity'] - $already_returned;

        if ($return_qty > $max_allowable) {
            throw new Exception("Lỗi: Lượng trả ($return_qty) vượt quá số lượng cho phép ($max_allowable).");
        }

        $refund_amount = $return_qty * $orderItem['unit_price'];

        $insertReturn = $pdo->prepare("INSERT INTO returns (order_id, reason, refund_amount, status) VALUES (?, ?, ?, 'approved')");
        $insertReturn->execute([$order_id, $reason, $refund_amount]);
        $return_id = $pdo->lastInsertId();

        $insertReturnItem = $pdo->prepare("INSERT INTO return_items (return_id, order_item_id, quantity) VALUES (?, ?, ?)");
        $insertReturnItem->execute([$return_id, $order_item_id, $return_qty]);

        $updateVariant = $pdo->prepare("UPDATE variants SET stock = stock + ?, available_stock = available_stock + ? WHERE id = ?");
        $updateVariant->execute([$return_qty, $return_qty, $orderItem['variant_id']]);

        $pdo->commit();
        echo "<script>alert('Đổi trả thành công! Hoàn tiền: " . number_format($refund_amount, 0, ',', '.') . "đ'); window.location='index.php';</script>";

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
    <title>Đổi Trả Hàng</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f6f9; }
        .box { background: #fff; padding: 20px; border-radius: 5px; width: 450px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input, select, textarea { width: 100%; padding: 8px; margin: 8px 0; box-sizing: border-box; }
        button { background: #dc3545; color: white; padding: 10px; border: none; width: 100%; cursor: pointer; }
    </style>
</head>
<body>
<div class="box">
    <h2>Yêu cầu Trả hàng: <?= htmlspecialchars($order['order_code']) ?></h2>
    <form method="POST">
        <label>Chọn sản phẩm:</label>
        <select name="order_item_id" required>
            <?php foreach ($items as $item): ?>
                <option value="<?= $item['id'] ?>">
                    <?= htmlspecialchars($item['variant_name']) ?> (Đã mua: <?= $item['quantity'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <label>Số lượng trả:</label>
        <input type="number" name="return_quantity" value="1" min="1" required>
        <label>Lý do:</label>
        <textarea name="reason" rows="3" required></textarea>
        <button type="submit">Gửi Yêu Cầu</button>
    </form>
    <br><a href="index.php">Quay lại</a>
</div>
</body>
</html>