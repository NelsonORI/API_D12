<?php
session_start();
require_once 'db.php';
require_once 'UserManager.php';
require_once 'EventManager.php';
require_once 'OrderManager.php';

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    header("Location: login.php");
    exit;
}

$pdo = getDBConnection();
$eventManager = new EventManager($pdo);
$orderManager = new OrderManager($pdo);

// Get user-specific data
$userId = $_SESSION['user_id'];
$userOrders = $orderManager->getUserOrders($userId);
$allEvents = $eventManager->getAllEvents();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_order'])) {
        $eventId = $_POST['event_id'];
        $quantity = $_POST['quantity'];
        $orderId = $orderManager->createOrder($userId, $eventId, $quantity);
        
        if ($orderId) {
            $_SESSION['success'] = "Order #$orderId created successfully!";
        } else {
            $_SESSION['error'] = "Failed to create order. Not enough tickets available.";
        }
        header("Location: dashboard.php");
        exit;
    }
    
    // Delete order
    if (isset($_POST['delete_order'])) {
        $orderId = $_POST['order_id'];
        $result = $orderManager->cancelOrder($orderId, $userId);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        header("Location: dashboard.php");
        exit;
    }
    
    // Update order quantity
    if (isset($_POST['update_order'])) {
        $orderId = $_POST['order_id'];
        $newQuantity = $_POST['quantity'];
        
        // First cancel the old order
        $cancelResult = $orderManager->cancelOrder($orderId, $userId);
        
        if ($cancelResult['success']) {
            // Create new order with updated quantity
            $eventId = $cancelResult['event_id'];
            $newOrderId = $orderManager->createOrder($userId, $eventId, $newQuantity);
            
            if ($newOrderId) {
                $_SESSION['success'] = "Order updated successfully! New Order #$newOrderId created.";
            } else {
                $_SESSION['error'] = "Failed to update order. Not enough tickets available.";
            }
        } else {
            $_SESSION['error'] = $cancelResult['message'];
        }
        header("Location: dashboard.php");
        exit;
    }
}

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
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
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
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
            <div class="col-md-4">
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
            <div class="col-md-4">
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
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($userOrders as $order): 
                                                    $isPastEvent = strtotime($order['event_date']) <= time();
                                                    $canModify = !$isPastEvent && $order['status'] == 'confirmed';
                                                ?>
                                                <tr>
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
                                                            echo $order['status'] == 'confirmed' ? 'success' : 
                                                                 ($order['status'] == 'pending' ? 'warning' : 'secondary'); 
                                                        ?>">
                                                            <?php echo ucfirst($order['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="action-buttons">
                                                        <div class="btn-group btn-group-sm">
                                                            <?php if ($canModify): ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <button type="submit" name="delete_order" class="btn btn-danger" 
                                                                            onclick="return confirm('Cancel this order? Tickets will be released.')"
                                                                            title="Cancel Order">
                                                                        <i class="fas fa-times"></i> Cancel
                                                                    </button>
                                                                </form>
                                                            <?php elseif ($order['status'] == 'cancelled'): ?>
                                                                <span class="badge bg-secondary">Cancelled</span>
                                                            <?php else: ?>
                                                                <span class="text-muted" title="Cannot modify past or pending orders">Locked</span>
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
                        <button type="submit" class="btn btn-primary">Confirm Booking</button>
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
    
    // Enhanced confirmation for deletions
    document.addEventListener('DOMContentLoaded', function() {
        // Order cancellation confirmation
        const orderForms = document.querySelectorAll('form button[name="delete_order"]');
        orderForms.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('⚠️ Are you sure you want to cancel this order?\n\nThis will release your tickets back to the event.')) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
</body>
</html>