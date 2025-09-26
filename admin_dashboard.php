<?php
session_start();
require_once 'db.php';
require_once 'UserManager.php';
require_once 'EventManager.php';
require_once 'OrderManager.php';

// Check if user is admin
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated'] || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$pdo = getDBConnection();
$userManager = new UserManager($pdo);
$eventManager = new EventManager($pdo);
$orderManager = new OrderManager($pdo);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $entity = $_POST['entity'] ?? '';
    
    if ($entity == 'event') {
        if ($action == 'create') {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $venue = trim($_POST['venue']);
            $event_date = $_POST['event_date'];
            $ticket_price = floatval($_POST['ticket_price']);
            $available_tickets = intval($_POST['available_tickets']);
            
            if ($eventManager->createEvent($title, $description, $venue, $event_date, $ticket_price, $available_tickets)) {
                $_SESSION['message'] = "Event created successfully!";
            } else {
                $_SESSION['error'] = "Failed to create event.";
            }
        }
        elseif ($action == 'update') {
            $id = intval($_POST['id']);
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $venue = trim($_POST['venue']);
            $event_date = $_POST['event_date'];
            $ticket_price = floatval($_POST['ticket_price']);
            $available_tickets = intval($_POST['available_tickets']);
            
            if ($eventManager->updateEvent($id, $title, $description, $venue, $event_date, $ticket_price, $available_tickets)) {
                $_SESSION['message'] = "Event updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update event.";
            }
        }
        elseif ($action == 'delete') {
            $id = intval($_POST['id']);
            if ($eventManager->deleteEvent($id)) {
                $_SESSION['message'] = "Event deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete event.";
            }
        }
    }
    elseif ($entity == 'user') {
        if ($action == 'create') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'user';
            
            if ($userManager->createUser($username, $email, $password, $phone, $role)) {
                $_SESSION['message'] = "User created successfully!";
            } else {
                $_SESSION['error'] = "Failed to create user.";
            }
        }
        elseif ($action == 'update') {
            $id = intval($_POST['id']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'user';
            
            if ($userManager->updateUser($id, $username, $email, $phone, $role)) {
                $_SESSION['message'] = "User updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update user.";
            }
        }
        elseif ($action == 'delete') {
            $id = intval($_POST['id']);
            if ($userManager->deleteUser($id)) {
                $_SESSION['message'] = "User deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete user.";
            }
        }
    }
    elseif ($entity == 'order' && $action == 'update_status') {
        $order_id = intval($_POST['order_id']);
        $status = $_POST['status'];
        
        if ($orderManager->updateOrderStatus($order_id, $status)) {
            $_SESSION['message'] = "Order status updated!";
        } else {
            $_SESSION['error'] = "Failed to update order status.";
        }
    }
    
    header("Location: admin_dashboard.php?tab=" . ($entity == 'user' ? 'users' : $entity . 's'));
    exit;
}

// Fetch data
$users = $userManager->getAllUsers(50, 0);
$events = $eventManager->getAllEvents(true);
$orders = $orderManager->getAllOrders();
$currentTab = $_GET['tab'] ?? 'dashboard';

// Get event data for editing
$editEvent = null;
if (isset($_GET['edit_event'])) {
    $editEvent = $eventManager->getEventById(intval($_GET['edit_event']));
}

// Get user data for editing - FIXED TABLE NAME
$editUser = null;
if (isset($_GET['edit_user'])) {
    $userId = intval($_GET['edit_user']);
    // Fixed table name from 'projects' to 'project'
    $stmt = $pdo->prepare("SELECT id, username, email, phone, role FROM project WHERE id = ?");
    $stmt->execute([$userId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Tickyfii</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #f8f9fa;
        }
        .sidebar .nav-link {
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 5px;
            color: #333;
        }
        .sidebar .nav-link.active {
            background: #007bff;
            color: white;
        }
        .stats-card {
            border-left: 4px solid;
        }
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .border-left-primary { border-left-color: #007bff !important; }
        .border-left-success { border-left-color: #28a745 !important; }
        .border-left-warning { border-left-color: #ffc107 !important; }
        .border-left-info { border-left-color: #17a2b8 !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar p-3">
                <h4 class="text-center mb-4">Admin Panel</h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentTab == 'dashboard' ? 'active' : '' ?>" href="?tab=dashboard">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentTab == 'users' ? 'active' : '' ?>" href="?tab=users">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentTab == 'events' ? 'active' : '' ?>" href="?tab=events">
                            <i class="fas fa-calendar-alt"></i> Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentTab == 'orders' ? 'active' : '' ?>" href="?tab=orders">
                            <i class="fas fa-shopping-cart"></i> Orders
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>Admin Dashboard</h2>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm">User View</a>
                        <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $_SESSION['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Dashboard Tab -->
                <?php if ($currentTab == 'dashboard'): ?>
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card border-left-primary">
                                <div class="card-body">
                                    <h5>Total Users</h5>
                                    <h3 class="text-primary"><?= count($users) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card border-left-success">
                                <div class="card-body">
                                    <h5>Total Events</h5>
                                    <h3 class="text-success"><?= count($events) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card border-left-warning">
                                <div class="card-body">
                                    <h5>Total Orders</h5>
                                    <h3 class="text-warning"><?= count($orders) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card border-left-info">
                                <div class="card-body">
                                    <h5>Revenue</h5>
                                    <h3 class="text-info">Ksh<?= number_format(array_sum(array_column($orders, 'total_amount')), 2) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Users Tab -->
                <?php elseif ($currentTab == 'users'): ?>
                    <div class="d-flex justify-content-between mb-3">
                        <h3>User Management</h3>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                            <i class="fas fa-plus"></i> Add User
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : 'secondary' ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?tab=users&edit_user=<?= $user['id'] ?>" class="btn btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="entity" value="user">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-danger" 
                                                        onclick="return confirm('Delete this user?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- Events Tab -->
                <?php elseif ($currentTab == 'events'): ?>
                    <div class="d-flex justify-content-between mb-3">
                        <h3>Event Management</h3>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal">
                            <i class="fas fa-plus"></i> Add Event
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Venue</th>
                                    <th>Date</th>
                                    <th>Price</th>
                                    <th>Tickets</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): 
                                    $isPast = strtotime($event['event_date']) < time();
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($event['title']) ?></td>
                                    <td><?= htmlspecialchars($event['venue']) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($event['event_date'])) ?></td>
                                    <td>Ksh<?= number_format($event['ticket_price'], 2) ?></td>
                                    <td><?= $event['available_tickets'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $isPast ? 'secondary' : 'success' ?>">
                                            <?= $isPast ? 'Past' : 'Upcoming' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?tab=events&edit_event=<?= $event['id'] ?>" class="btn btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="entity" value="event">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                                <button type="submit" class="btn btn-danger" 
                                                        onclick="return confirm('Delete this event?')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- Orders Tab -->
                <?php elseif ($currentTab == 'orders'): ?>
                    <h3>Order Management</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Event</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td><?= htmlspecialchars($order['username']) ?></td>
                                    <td><?= htmlspecialchars($order['title']) ?></td>
                                    <td><?= $order['quantity'] ?></td>
                                    <td>Ksh<?= number_format($order['total_amount'], 2) ?></td>
                                    <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $order['status'] == 'confirmed' ? 'success' : ($order['status'] == 'cancelled' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="entity" value="order">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <select name="status" onchange="this.form.submit()" class="form-select form-select-sm">
                                                <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="confirmed" <?= $order['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Event Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="entity" value="event">
                    <input type="hidden" name="action" value="<?= $editEvent ? 'update' : 'create' ?>">
                    <?php if ($editEvent): ?>
                        <input type="hidden" name="id" value="<?= $editEvent['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="modal-header">
                        <h5 class="modal-title"><?= $editEvent ? 'Edit Event' : 'Add New Event' ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Event Title</label>
                            <input type="text" name="title" class="form-control" 
                                   value="<?= $editEvent ? htmlspecialchars($editEvent['title']) : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" required><?= $editEvent ? htmlspecialchars($editEvent['description']) : '' ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Venue</label>
                            <input type="text" name="venue" class="form-control" 
                                   value="<?= $editEvent ? htmlspecialchars($editEvent['venue']) : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Event Date & Time</label>
                            <input type="datetime-local" name="event_date" class="form-control" 
                                   value="<?= $editEvent ? date('Y-m-d\TH:i', strtotime($editEvent['event_date'])) : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ticket Price (Ksh)</label>
                            <input type="number" step="0.01" name="ticket_price" class="form-control" 
                                   value="<?= $editEvent ? $editEvent['ticket_price'] : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Available Tickets</label>
                            <input type="number" name="available_tickets" class="form-control" 
                                   value="<?= $editEvent ? $editEvent['available_tickets'] : '' ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><?= $editEvent ? 'Update' : 'Create' ?> Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="entity" value="user">
                    <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="modal-header">
                        <h5 class="modal-title"><?= $editUser ? 'Edit User' : 'Add New User' ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" 
                                   value="<?= $editUser ? htmlspecialchars($editUser['username']) : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= $editUser ? htmlspecialchars($editUser['email']) : '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?= $editUser ? htmlspecialchars($editUser['phone']) : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="user" <?= ($editUser && $editUser['role'] == 'user') ? 'selected' : '' ?>>User</option>
                                <option value="admin" <?= ($editUser && $editUser['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <?php if (!$editUser): ?>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><?= $editUser ? 'Update' : 'Create' ?> User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Auto-open modals when editing
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_GET['edit_user']) && $editUser): ?>
            var userModal = new bootstrap.Modal(document.getElementById('userModal'));
            userModal.show();
        <?php endif; ?>
        
        <?php if (isset($_GET['edit_event']) && $editEvent): ?>
            var eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
            eventModal.show();
        <?php endif; ?>

        // Clear URL parameters when modals are closed
        const userModalEl = document.getElementById('userModal');
        const eventModalEl = document.getElementById('eventModal');

        if (userModalEl) {
            userModalEl.addEventListener('hidden.bs.modal', function () {
                if (window.location.search.includes('edit_user')) {
                    window.history.replaceState({}, document.title, window.location.pathname + '?tab=users');
                }
            });
        }

        if (eventModalEl) {
            eventModalEl.addEventListener('hidden.bs.modal', function () {
                if (window.location.search.includes('edit_event')) {
                    window.history.replaceState({}, document.title, window.location.pathname + '?tab=events');
                }
            });
        }
    });
    </script>
</body>
</html>