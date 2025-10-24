<?php
// mpesa/stk_push.php

require_once '../db_connection.php';
require_once 'MpesaService.php';

header('Content-Type: application/json');

try {
    // Check if user is authenticated
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $phoneNumber = $input['phoneNumber'] ?? '';
    $amount = $input['amount'] ?? '';
    $productId = $input['productId'] ?? '';
    
    if (empty($phoneNumber) || empty($amount) || empty($productId)) {
        throw new Exception('Missing required parameters');
    }
    
    $mpesaService = new MpesaService();
    
    // Validate phone number
    if (!$mpesaService->validatePhoneNumber($phoneNumber)) {
        throw new Exception('Invalid phone number format. Use format: 0712345678 or 254712345678');
    }
    
    $formattedPhone = $mpesaService->formatPhoneNumber($phoneNumber);
    $accountReference = 'ORDER_' . $_SESSION['user_id'] . '_' . time();
    
    // Create order record
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, product_id, amount, status, created_at) 
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $productId, $amount]);
    $orderId = $pdo->lastInsertId();
    
    // Generate callback URL
    $callbackUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . 
                   "/mpesa_callback.php";
    
    // Remove any double slashes
    $callbackUrl = str_replace('//', '/', $callbackUrl);
    $callbackUrl = str_replace(':/', '://', $callbackUrl);
    
    // Initiate STK Push
    $response = $mpesaService->initiateSTKPush(
        $formattedPhone,
        $amount,
        $accountReference,
        $callbackUrl
    );
    
    // Save transaction record
    $stmt = $pdo->prepare("
        INSERT INTO transactions 
        (order_id, checkout_request_id, phone_number, amount, account_reference, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $orderId,
        $response['CheckoutRequestID'],
        $formattedPhone,
        $amount,
        $accountReference
    ]);
    
    // Update order with checkout request ID
    $stmt = $pdo->prepare("UPDATE orders SET checkout_request_id = ? WHERE id = ?");
    $stmt->execute([$response['CheckoutRequestID'], $orderId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'M-Pesa prompt sent to your phone. Please enter your PIN to complete payment.',
        'CheckoutRequestID' => $response['CheckoutRequestID'],
        'ResponseCode' => $response['ResponseCode']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
