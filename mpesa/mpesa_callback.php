<?php
// mpesa/mpesa_callback.php

require_once 'MpesaService.php';

// Log the raw input for debugging
file_put_contents('../logs/mpesa_raw_callback.log', date('Y-m-d H:i:s') . " - " . file_get_contents('php://input') . "\n", FILE_APPEND);

try {
    $callbackData = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON in callback');
    }
    
    $mpesaService = new MpesaService();
    $result = $mpesaService->processCallback($callbackData);
    
    // Always respond with success to M-Pesa
    header('Content-Type: application/json');
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Success'
    ]);
    
} catch (Exception $e) {
    error_log("Callback processing error: " . $e->getMessage());
    
    // Still respond with success to M-Pesa to avoid retries
    header('Content-Type: application/json');
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Success'
    ]);
}
?>



<?php
// mpesa/check_payment_status.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../db_connection.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$checkoutRequestId = $_GET['checkoutRequestId'] ?? '';

if (empty($checkoutRequestId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing checkoutRequestId']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT t.status, t.mpesa_response, o.id as order_id, o.status as order_status, o.payment_status 
        FROM transactions t 
        LEFT JOIN `order` o ON t.order_id = o.id 
        WHERE t.checkout_request_id = ?
    ");
    $stmt->execute([$checkoutRequestId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['status' => 'not_found']);
        exit;
    }
    
    echo json_encode([
        'status' => $transaction['status'],
        'order_status' => $transaction['order_status'],
        'payment_status' => $transaction['payment_status'],
        'order_id' => $transaction['order_id']
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
