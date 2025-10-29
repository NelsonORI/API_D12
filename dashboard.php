<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Simple debug function
function debug_log($message) {
    file_put_contents('dashboard_debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

debug_log("=== DASHBOARD ACCESSED ===");

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    debug_log("User not authenticated, redirecting to login");
    header("Location: login.php");
    exit;
}

try {
    debug_log("Loading required files");
    require_once 'db.php';
    require_once 'UserManager.php';
    require_once 'EventManager.php';
    require_once 'OrderManager.php';
    debug_log("Required files loaded successfully");
    
    // Try to load MpesaService but don't break if it fails
    $mpesaServiceAvailable = false;
    if (file_exists('mpesa/MpesaService.php')) {
        require_once 'mpesa/MpesaService.php';
        $mpesaServiceAvailable = true;
        debug_log("MpesaService loaded successfully");
    } else {
        debug_log("MpesaService file not found");
    }
    
} catch (Exception $e) {
    debug_log("Error loading files: " . $e->getMessage());
    die("Error loading system files: " . $e->getMessage());
}

$pdo = getDBConnection();
$eventManager = new EventManager($pdo);
$orderManager = new OrderManager($pdo);

// Get user-specific data
$userId = $_SESSION['user_id'];
debug_log("User ID: " . $userId);

try {
    $userOrders = $orderManager->getUserOrders($userId);
    $allEvents = $eventManager->getAllEvents();
    debug_log("User orders: " . count($userOrders) . ", Events: " . count($allEvents));
} catch (Exception $e) {
    debug_log("Error getting data: " . $e->getMessage());
    $userOrders = [];
    $allEvents = [];
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    debug_log("POST request received: " . print_r($_POST, true));
    
    if (isset($_POST['create_order'])) {
        debug_log("Creating order...");
        $eventId = $_POST['event_id'];
        $quantity = $_POST['quantity'];
        
        $orderId = $orderManager->createOrder($userId, $eventId, $quantity);
        
        if ($orderId) {
            $_SESSION['pending_order'] = $orderId;
            $_SESSION['success'] = "Order #$orderId created successfully! Please complete payment to confirm your tickets.";
            debug_log("Order created successfully: #" . $orderId);
        } else {
            $_SESSION['error'] = "Failed to create order. Not enough tickets available.";
            debug_log("Order creation failed");
        }
        header("Location: dashboard.php");
        exit;
    }
    
    // Delete order
    if (isset($_POST['delete_order'])) {
        debug_log("Deleting order...");
        $orderId = $_POST['order_id'];
        $result = $orderManager->cancelOrder($orderId, $userId);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            debug_log("Order cancelled successfully");
        } else {
            $_SESSION['error'] = $result['message'];
            debug_log("Order cancellation failed: " . $result['message']);
        }
        header("Location: dashboard.php");
        exit;
    }
    
    // Update order quantity
    if (isset($_POST['update_order'])) {
        debug_log("Updating order quantity...");
        $orderId = $_POST['order_id'];
        $newQuantity = $_POST['quantity'];
        
        // First cancel the old order
        $cancelResult = $orderManager->cancelOrder($orderId, $userId);
        
        if ($cancelResult['success']) {
            // Create new order with updated quantity
            $eventId = $cancelResult['event_id'];
            $newOrderId = $orderManager->createOrder($userId, $eventId, $newQuantity);
            
            if ($newOrderId) {
                $_SESSION['pending_order'] = $newOrderId;
                $_SESSION['success'] = "Order updated successfully! New Order #$newOrderId created. Please complete payment.";
                debug_log("Order updated successfully: #" . $newOrderId);
            } else {
                $_SESSION['error'] = "Failed to update order. Not enough tickets available.";
                debug_log("Order update failed");
            }
        } else {
            $_SESSION['error'] = $cancelResult['message'];
            debug_log("Order cancellation failed: " . $cancelResult['message']);
        }
        header("Location: dashboard.php");
        exit;
    }
    
    // Process M-Pesa payment
    if (isset($_POST['process_payment'])) {
        debug_log("Processing M-Pesa payment...");
        $orderId = $_POST['order_id'];
        $phone = $_POST['phone'];
        
        try {
            if (!$mpesaServiceAvailable) {
                throw new Exception("M-Pesa service is not available");
            }
            
            // Get order details
            $order = $orderManager->getOrderById($orderId, $userId);
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            if ($order['status'] === 'confirmed') {
                throw new Exception("Order is already confirmed and paid");
            }
            
            $amount = $order['total_amount'];
            $accountReference = "TICKYFII_" . $orderId;
            
            // FIX: Validate and format amount for M-Pesa
            if (!is_numeric($amount) || $amount <= 0) {
                throw new Exception("Invalid amount: " . $amount);
            }
            
            // M-Pesa requires whole numbers (no decimals) in sandbox
            // Convert to integer and ensure it's at least 1
            $mpesaAmount = intval(ceil($amount));
            if ($mpesaAmount < 1) {
                $mpesaAmount = 1;
            }
            
            debug_log("Original amount: " . $amount . ", M-Pesa amount: " . $mpesaAmount);
            
            // Initialize M-Pesa service
            $mpesaService = getMpesaService();
            debug_log("MpesaService initialized");
            
            // FIX: Use ngrok URL for callback in development
            $callbackUrl = "https://tom-primatial-noncontrollablely.ngrok-free.dev/mpesa/mpesa_callback.php";
            debug_log("Using ngrok Callback URL: " . $callbackUrl);
            
            // Validate and format phone number
            $formattedPhone = $mpesaService->formatPhoneNumber($phone);
            if (!$mpesaService->validatePhoneNumber($formattedPhone)) {
                throw new Exception('Invalid phone number format. Use 07XXXXXXXX or 2547XXXXXXXX');
            }
            debug_log("Phone validated: " . $formattedPhone);
            
            // Initiate STK Push with corrected amount
            debug_log("Initiating STK Push with amount: " . $mpesaAmount);
            $stkResponse = $mpesaService->initiateSTKPush(
                $formattedPhone,
                $mpesaAmount,  // Use the corrected amount
                $accountReference,
                $callbackUrl
            );
            debug_log("STK Response: " . print_r($stkResponse, true));
            
            // Save transaction record
            $stmt = $pdo->prepare("
                INSERT INTO transactions 
                (order_id, checkout_request_id, phone_number, amount, account_reference, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([
                $orderId,
                $stkResponse['CheckoutRequestID'],
                $formattedPhone,
                $amount,  // Store original amount in database
                $accountReference
            ]);
            debug_log("Transaction record created");
            
            // Update order with checkout request ID
            $orderManager->updateCheckoutRequestId($orderId, $stkResponse['CheckoutRequestID']);
            debug_log("Order updated with checkout request ID");
            
            $_SESSION['checkout_request_id'] = $stkResponse['CheckoutRequestID'];
            $_SESSION['success'] = "M-Pesa payment request sent! Please check your phone and enter your M-Pesa PIN to complete payment.";
            debug_log("Payment initiated successfully");
            
        } catch (Exception $e) {
            $errorMsg = "Payment failed: " . $e->getMessage();
            $_SESSION['error'] = $errorMsg;
            debug_log("Payment Error: " . $e->getMessage());
            error_log("M-Pesa Payment Error: " . $e->getMessage());
        }
        header("Location: dashboard.php");
        exit;
    }
}

// Check for pending payments and their status
$pendingCheckoutId = $_SESSION['checkout_request_id'] ?? null;
$paymentStatus = null;

if ($pendingCheckoutId) {
    // Check payment status
    $checkUrl = "mpesa/check_payment_status.php?checkoutRequestId=" . urlencode($pendingCheckoutId);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Content-Type: application/json',
        ]
    ]);
    
    $response = @file_get_contents($checkUrl, false, $context);
    if ($response !== false) {
        $paymentStatus = json_decode($response, true);
        
        // If payment is completed, clear the session
        if (isset($paymentStatus['status']) && $paymentStatus['status'] === 'completed') {
            unset($_SESSION['checkout_request_id']);
            unset($_SESSION['pending_order']);
            
            // Update order status to confirmed
            if (isset($paymentStatus['order_id'])) {
                $orderManager->confirmOrderPayment($paymentStatus['order_id']);
            }
            
            $_SESSION['success'] = "Payment completed successfully! Your order is now confirmed.";
        }
    }
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

debug_log("Rendering dashboard page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Tickyfii</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-hover:hover {
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        .stats-card {
            border-left: 4px solid #0d6efd;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }
        .btn-group-sm .btn {
            margin: 1px;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .order-pending {
            border-left: 4px solid #ffc107;
        }
        .order-confirmed {
            border-left: 4px solid #198754;
        }
        .order-cancelled {
            border-left: 4px solid #6c757d;
        }
        .payment-processing {
            background: linear-gradient(45deg, #fff3cd, #ffeaa7) !important;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-ticket-alt"></i> Tickyfii
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                </span>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="fas fa-cog"></i> Admin Panel
                    </a>
                <?php endif; ?>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Payment Status Alert -->
        <?php if ($pendingCheckoutId): ?>
            <div class="alert alert-warning alert-dismissible fade show payment-processing" role="alert">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-clock"></i> 
                        <strong>Payment Processing</strong>
                        <div class="mt-1">
                            <small>Checkout ID: <?php echo substr($pendingCheckoutId, 0, 20) . '...'; ?></small>
                        </div>
                        <div class="mt-1">
                            <small>Please check your phone and enter your M-Pesa PIN to complete payment.</small>
                        </div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
                            <i class="fas fa-sync"></i> Refresh Status
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Auto-refresh script -->
            <script>
                setTimeout(function() {
                    window.location.reload();
                }, 5000);
            </script>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card card-hover">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">TOTAL EVENTS</h6>
                                <h3 class="text-primary"><?php echo count($allEvents); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card card-hover">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">MY ORDERS</h6>
                                <h3 class="text-success"><?php echo count($userOrders); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-shopping-cart fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card card-hover">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">ACCOUNT TYPE</h6>
                                <h3 class="text-info"><?php echo ucfirst($_SESSION['role']); ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card card-hover">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">PENDING PAYMENT</h6>
                                <h3 class="text-warning">
                                    <?php 
                                    $pendingCount = 0;
                                    foreach ($userOrders as $order) {
                                        if ($order['status'] === 'pending') {
                                            $pendingCount++;
                                        }
                                    }
                                    echo $pendingCount;
                                    ?>
                                </h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill-wave fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Content -->
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
                            <i class="fas fa-shopping-cart"></i> My Orders
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab">
                            <i class="fas fa-calendar-alt"></i> Available Events
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="dashboardTabsContent">
                    <!-- Orders Tab -->
                    <div class="tab-pane fade show active" id="orders" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <?php if (empty($userOrders)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                        <h5>No orders yet</h5>
                                        <p>Start by browsing events and making orders!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Order ID</th>
                                                    <th>Event</th>
                                                    <th>Venue</th>
                                                    <th>Date</th>
                                                    <th>Quantity</th>
                                                    <th>Amount</th>
                                                    <th>Payment Status</th>
                                                    <th>Order Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($userOrders as $order): 
                                                    $isPastEvent = strtotime($order['event_date']) <= time();
                                                    $canModify = !$isPastEvent && $order['status'] == 'pending';
                                                    $needsPayment = $order['status'] == 'pending';
                                                    $rowClass = 'order-' . $order['status'];
                                                    if ($order['checkout_request_id'] && $order['status'] == 'pending') {
                                                        $rowClass .= ' payment-processing';
                                                    }
                                                ?>
                                                <tr class="<?php echo $rowClass; ?>">
                                                    <td>#<?php echo $order['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($order['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['venue']); ?></td>
                                                    <td>
                                                        <?php echo date('M j, Y g:i A', strtotime($order['event_date'])); ?>
                                                        <?php if ($isPastEvent): ?>
                                                            <br><small class="text-muted">Past Event</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($canModify): ?>
                                                            <form method="post" class="d-inline" style="max-width: 80px;">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                <div class="input-group input-group-sm">
                                                                    <input type="number" name="quantity" value="<?php echo $order['quantity']; ?>" 
                                                                           min="1" max="10" class="form-control">
                                                                    <button type="submit" name="update_order" class="btn btn-sm btn-outline-primary"
                                                                            title="Update Quantity">
                                                                        <i class="fas fa-sync"></i>
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        <?php else: ?>
                                                            <?php echo $order['quantity']; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>Ksh<?php echo number_format($order['total_amount'], 2); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $order['payment_status'] == 'paid' ? 'success' : 
                                                                 ($order['payment_status'] == 'pending' ? 'warning' : 'secondary'); 
                                                        ?>">
                                                            <?php echo ucfirst($order['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $order['status'] == 'confirmed' ? 'success' : 
                                                                 ($order['status'] == 'pending' ? 'warning' : 'secondary'); 
                                                        ?>">
                                                            <?php echo ucfirst($order['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="action-buttons">
                                                        <div class="btn-group btn-group-sm">
                                                            <?php if ($needsPayment): ?>
                                                                <!-- Pay with M-Pesa Button -->
                                                                <button type="button" class="btn btn-success" 
                                                                        onclick="showPaymentModal(<?php echo $order['id']; ?>, <?php echo $order['total_amount']; ?>)">
                                                                    <i class="fas fa-mobile-alt"></i> Pay Now
                                                                </button>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <button type="submit" name="delete_order" class="btn btn-danger ms-1" 
                                                                            onclick="return confirm('Cancel this order?')"
                                                                            title="Cancel Order">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </form>
                                                            <?php elseif ($order['status'] == 'cancelled'): ?>
                                                                <span class="badge bg-secondary">Cancelled</span>
                                                            <?php else: ?>
                                                                <span class="text-muted" title="Order confirmed and paid">Completed</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Events Tab -->
                    <div class="tab-pane fade" id="events" role="tabpanel">
                        <div class="row">
                            <?php if (empty($allEvents)): ?>
                                <div class="col-12 text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5>No events available</h5>
                                    <p>Check back later for upcoming events!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($allEvents as $event): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card card-hover h-100">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($event['title']); ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text"><?php echo htmlspecialchars(substr($event['description'], 0, 100)); ?>...</p>
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item">
                                                    <i class="fas fa-map-marker-alt text-primary"></i>
                                                    <?php echo htmlspecialchars($event['venue']); ?>
                                                </li>
                                                <li class="list-group-item">
                                                    <i class="fas fa-calendar text-success"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?>
                                                </li>
                                                <li class="list-group-item">
                                                    <i class="fas fa-ticket-alt text-warning"></i>
                                                    Ksh<?php echo number_format($event['ticket_price'], 2); ?> per ticket
                                                </li>
                                                <li class="list-group-item">
                                                    <i class="fas fa-chair text-info"></i>
                                                    <?php echo $event['available_tickets']; ?> seats available
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="card-footer">
                                            <button class="btn btn-success btn-sm" 
                                                    onclick="prefillOrder(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['title']); ?>')">
                                                <i class="fas fa-shopping-cart"></i> Book Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="create_order" value="1">
                    <input type="hidden" name="event_id" id="orderEventId">
                    <div class="modal-header">
                        <h5 class="modal-title">Book Tickets: <span id="orderEventTitle"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Number of Tickets</label>
                            <input type="number" name="quantity" class="form-control" min="1" max="10" value="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="paymentForm">
                    <input type="hidden" name="process_payment" value="1">
                    <input type="hidden" name="order_id" id="paymentOrderId">
                    <div class="modal-header">
                        <h5 class="modal-title">Pay with M-Pesa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="text" class="form-control" id="paymentAmount" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">M-Pesa Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   placeholder="e.g., 0712345678" pattern="[0-9]{10,12}" required>
                            <div class="form-text">Enter your M-Pesa registered phone number</div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            You will receive an M-Pesa prompt on your phone to enter your PIN.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-mobile-alt"></i> Pay with M-Pesa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function prefillOrder(eventId, eventTitle) {
        document.getElementById('orderEventId').value = eventId;
        document.getElementById('orderEventTitle').textContent = eventTitle;
        new bootstrap.Modal(document.getElementById('orderModal')).show();
    }
    
    function showPaymentModal(orderId, amount) {
        document.getElementById('paymentOrderId').value = orderId;
        document.getElementById('paymentAmount').value = 'Ksh ' + amount.toLocaleString();
        new bootstrap.Modal(document.getElementById('paymentModal')).show();
    }
    
    // Enhanced confirmation for deletions
    document.addEventListener('DOMContentLoaded', function() {
        // Order cancellation confirmation
        const orderForms = document.querySelectorAll('form button[name="delete_order"]');
        orderForms.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('⚠️ Are you sure you want to cancel this order?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Phone number formatting
        const phoneInput = document.querySelector('input[name="phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^\d]/g, '');
            });
        }
        
        // Payment form submission
        const paymentForm = document.getElementById('paymentForm');
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            });
        }
    });
    </script>
</body>
</html>