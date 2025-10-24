<?php
// process_payment.php - M-Pesa Payment Processing WITH REAL MPESA INTEGRATION
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/MpesaService.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to log payment activities
function logPaymentActivity($message, $data = null) {
    $logFile = __DIR__ . '/payment_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= " - " . json_encode($data);
    }
    
    $logMessage .= "\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// FIXED: Better request data handling
function getRequestData() {
    $method = $_SERVER['REQUEST_METHOD'];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    logPaymentActivity("Request details", [
        'method' => $method,
        'content_type' => $contentType,
        'get_params' => $_GET,
        'post_params' => $_POST
    ]);
    
    if ($method === 'GET') {
        return $_GET;
    }
    
    if ($method === 'POST') {
        // First, check if we have regular POST data
        if (!empty($_POST)) {
            logPaymentActivity("Regular POST data found", $_POST);
            return $_POST;
        }
        
        // If no POST data, check for JSON input
        $rawInput = file_get_contents('php://input');
        logPaymentActivity("Raw input data", $rawInput);
        
        // Try to decode as JSON
        if (!empty($rawInput)) {
            $jsonData = json_decode($rawInput, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                logPaymentActivity("JSON data decoded successfully", $jsonData);
                return $jsonData;
            } else {
                // If not JSON, try to parse as form data
                parse_str($rawInput, $formData);
                if (!empty($formData)) {
                    logPaymentActivity("Form data parsed from raw input", $formData);
                    return $formData;
                }
            }
        }
        
        logPaymentActivity("No data could be parsed");
        return null;
    }
    
    return null;
}

try {
    // Get the request data using our helper function
    $data = getRequestData();
    
    logPaymentActivity("Final data to process", $data);

    // Validate input data
    if (empty($data)) {
        $rawInput = file_get_contents('php://input');
        $response = [
            'success' => false,
            'message' => 'No input data received',
            'debug_info' => [
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set',
                'raw_input' => $rawInput,
                'get_params' => $_GET,
                'post_params' => $_POST
            ]
        ];
        
        logPaymentActivity("No data error", $response);
        echo json_encode($response);
        exit;
    }
    
    // Required fields - with better validation
    $requiredFields = ['phone', 'amount', 'order_id', 'account_number'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        $response = [
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missingFields),
            'received_data' => $data,
            'missing_fields' => $missingFields
        ];
        
        logPaymentActivity("Missing fields error", $response);
        echo json_encode($response);
        exit;
    }
    
    $phone = $data['phone'];
    $amount = $data['amount'];
    $orderId = $data['order_id'];
    $accountNumber = $data['account_number'];
    
    // Validate phone number (Kenyan format)
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (strlen($phone) === 9) {
        $phone = '254' . $phone;
    }
    
    if (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254') {
        throw new Exception('Invalid phone number format. Use 07XXXXXXXX or 2547XXXXXXXX');
    }
    
    // Validate amount
    if (!is_numeric($amount) || $amount <= 0) {
        throw new Exception('Invalid amount');
    }
    
    // Get database connection
    $conn = getDBConnection();
    logPaymentActivity("Database connection established");
    
    // Check if order exists in the `order` table
    $stmt = $conn->prepare("SELECT * FROM `order` WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception("Order #$orderId not found");
    }
    
    if ($order['status'] === 'confirmed') {
        throw new Exception("Order #$orderId is already confirmed and paid");
    }
    
    // Verify order amount matches payment amount
    $orderAmount = floatval($order['total_amount']);
    $paymentAmount = floatval($amount);
    
    if ($orderAmount !== $paymentAmount) {
        throw new Exception("Payment amount (Ksh $paymentAmount) does not match order amount (Ksh $orderAmount)");
    }
    
    // Initialize M-Pesa Service - USE THE HELPER FUNCTION
    $mpesaService = getMpesaService();
    
    // UPDATED: Use ngrok public URL for callback
    $callbackUrl = "https://tom-primatial-noncontrollablely.ngrok-free.dev/mpesa/mpesa_callback.php";
    
    logPaymentActivity("Initiating real M-Pesa STK Push", [
        'phone' => $phone,
        'amount' => $amount,
        'account_reference' => $accountNumber,
        'callback_url' => $callbackUrl
    ]);
    
    // INITIATE REAL M-PESA STK PUSH
    $stkResponse = $mpesaService->initiateSTKPush(
        $phone,
        $amount,
        $accountNumber,
        $callbackUrl
    );
    
    logPaymentActivity("M-Pesa STK Push Response", $stkResponse);
    
    // Check if STK push was successful
    if ($stkResponse['ResponseCode'] === '0') {
        // STK push initiated successfully
        $checkoutRequestId = $stkResponse['CheckoutRequestID'];
        $merchantRequestId = $stkResponse['MerchantRequestID'];
        
        // Insert transaction record
        $stmt = $conn->prepare("
            INSERT INTO transactions 
            (order_id, checkout_request_id, phone_number, amount, account_reference, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        
        $stmt->execute([$orderId, $checkoutRequestId, $phone, $amount, $accountNumber]);
        $transactionId = $conn->lastInsertId();
        
        logPaymentActivity("Transaction record created", [
            'transaction_id' => $transactionId,
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId
        ]);
        
        // Update order with checkout request ID (but don't confirm status yet - wait for callback)
        $stmt = $conn->prepare("
            UPDATE `order` 
            SET checkout_request_id = ?, payment_reference = ?
            WHERE id = ?
        ");
        $stmt->execute([$checkoutRequestId, $accountNumber, $orderId]);
        
        logPaymentActivity("Order updated with payment info", [
            'order_id' => $orderId,
            'checkout_request_id' => $checkoutRequestId,
            'payment_reference' => $accountNumber
        ]);
        
        // Success response
        $response = [
            'success' => true,
            'message' => 'M-Pesa STK push sent successfully! Please check your phone and enter your M-Pesa PIN to complete payment.',
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
            'payment_reference' => $accountNumber,
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
            'amount' => $amount,
            'phone' => $phone,
            'mpesa_response' => $stkResponse['ResponseDescription'],
            'real_mpesa' => true,
            'callback_url' => $callbackUrl
        ];
        
    } else {
        // STK push failed
        $errorMessage = $stkResponse['ResponseDescription'] ?? 'STK push initiation failed';
        
        // Insert failed transaction record
        $checkoutRequestId = 'failed_' . time();
        $stmt = $conn->prepare("
            INSERT INTO transactions 
            (order_id, checkout_request_id, phone_number, amount, account_reference, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'failed', NOW(), NOW())
        ");
        
        $stmt->execute([$orderId, $checkoutRequestId, $phone, $amount, $accountNumber]);
        
        throw new Exception("M-Pesa STK Push failed: " . $errorMessage);
    }
    
    logPaymentActivity("Payment response", $response);
    echo json_encode($response);
    
} catch (PDOException $e) {
    logPaymentActivity("Database error", ['error' => $e->getMessage()]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_type' => 'database'
    ]);
    
} catch (Exception $e) {
    logPaymentActivity("Payment processing error", ['error' => $e->getMessage()]);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'validation'
    ]);
}
?>