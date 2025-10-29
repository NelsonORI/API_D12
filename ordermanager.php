<?php
class OrderManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Create order
    public function createOrder($userId, $eventId, $quantity) {
        $this->pdo->beginTransaction();

        try {
            // Get event details and check availability
            $eventStmt = $this->pdo->prepare("SELECT ticket_price, available_tickets FROM event WHERE id = ?");
            $eventStmt->execute([$eventId]);
            $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                throw new Exception("Event not found");
            }

            if ($event['available_tickets'] < $quantity) {
                throw new Exception("Not enough tickets available");
            }

            $totalAmount = $event['ticket_price'] * $quantity;

            // Create order - using existing schema without payment_status
            $orderStmt = $this->pdo->prepare("
                INSERT INTO `order` (user_id, event_id, quantity, total_amount, status) 
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $orderStmt->execute([$userId, $eventId, $quantity, $totalAmount]);
            $orderId = $this->pdo->lastInsertId();

            // Update event available tickets
            $eventUpdateStmt = $this->pdo->prepare("UPDATE event SET available_tickets = available_tickets - ? WHERE id = ?");
            $eventUpdateStmt->execute([$quantity, $eventId]);

            $this->pdo->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Order creation error: " . $e->getMessage());
            return false;
        }
    }

    // Get user orders - add payment_status dynamically
    public function getUserOrders($userId) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, e.title, e.venue, e.event_date 
            FROM `order` o 
            JOIN event e ON o.event_id = e.id 
            WHERE o.user_id = ? 
            ORDER BY o.order_date DESC
        ");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add payment_status field dynamically based on order status
        foreach ($orders as &$order) {
            $order['payment_status'] = $this->getPaymentStatusFromOrder($order);
        }
        
        return $orders;
    }

    // Get all orders (admin) - add payment_status dynamically
    public function getAllOrders() {
        $stmt = $this->pdo->prepare("
            SELECT o.*, e.title, p.username 
            FROM `order` o 
            JOIN event e ON o.event_id = e.id 
            JOIN project p ON o.user_id = p.id 
            ORDER BY o.order_date DESC
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add payment_status field dynamically
        foreach ($orders as &$order) {
            $order['payment_status'] = $this->getPaymentStatusFromOrder($order);
        }
        
        return $orders;
    }

    // Update order status
    public function updateOrderStatus($orderId, $status) {
        $stmt = $this->pdo->prepare("UPDATE `order` SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $orderId]);
    }

    // Update payment status - simulate using order status
    public function updatePaymentStatus($orderId, $paymentStatus) {
        // Since we don't have payment_status column, map payment status to order status
        if ($paymentStatus === 'paid') {
            return $this->updateOrderStatus($orderId, 'confirmed');
        } elseif ($paymentStatus === 'failed') {
            // Keep as pending if payment fails
            return $this->updateOrderStatus($orderId, 'pending');
        }
        return true;
    }

    // Confirm order and payment
    public function confirmOrderPayment($orderId) {
        $stmt = $this->pdo->prepare("UPDATE `order` SET status = 'confirmed' WHERE id = ?");
        return $stmt->execute([$orderId]);
    }

    // Cancel order (for user dashboard)
    public function cancelOrder($orderId, $userId) {
        $this->pdo->beginTransaction();

        try {
            // Get order details
            $orderStmt = $this->pdo->prepare("
                SELECT o.*, e.event_date 
                FROM `order` o 
                JOIN event e ON o.event_id = e.id 
                WHERE o.id = ? AND o.user_id = ?
            ");
            $orderStmt->execute([$orderId, $userId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Order not found");
            }

            // Check if order is already cancelled
            if ($order['status'] === 'cancelled') {
                throw new Exception("Order is already cancelled");
            }

            // Check if event is in the past
            if (strtotime($order['event_date']) <= time()) {
                throw new Exception("Cannot cancel order for past event");
            }

            // Update order status
            $statusStmt = $this->pdo->prepare("UPDATE `order` SET status = 'cancelled' WHERE id = ?");
            $statusStmt->execute([$orderId]);

            // Restore tickets to event
            $eventStmt = $this->pdo->prepare("UPDATE event SET available_tickets = available_tickets + ? WHERE id = ?");
            $eventStmt->execute([$order['quantity'], $order['event_id']]);

            $this->pdo->commit();
            return [
                'success' => true, 
                'message' => 'Order cancelled successfully', 
                'event_id' => $order['event_id']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false, 
                'message' => $e->getMessage()
            ];
        }
    }

    // Get order by ID
    public function getOrderById($orderId, $userId = null) {
        $sql = "
            SELECT o.*, e.title, e.venue, e.event_date 
            FROM `order` o 
            JOIN event e ON o.event_id = e.id 
            WHERE o.id = ?
        ";
        
        $params = [$orderId];
        
        if ($userId !== null) {
            $sql .= " AND o.user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add payment_status dynamically
        if ($order) {
            $order['payment_status'] = $this->getPaymentStatusFromOrder($order);
        }
        
        return $order;
    }

    // Update checkout request ID
    public function updateCheckoutRequestId($orderId, $checkoutRequestId) {
        $stmt = $this->pdo->prepare("UPDATE `order` SET checkout_request_id = ? WHERE id = ?");
        return $stmt->execute([$checkoutRequestId, $orderId]);
    }

    // Get order by checkout request ID
    public function getOrderByCheckoutRequestId($checkoutRequestId) {
        $stmt = $this->pdo->prepare("SELECT * FROM `order` WHERE checkout_request_id = ?");
        $stmt->execute([$checkoutRequestId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add payment_status dynamically
        if ($order) {
            $order['payment_status'] = $this->getPaymentStatusFromOrder($order);
        }
        
        return $order;
    }

    // Helper method to determine payment status from order status
    private function getPaymentStatusFromOrder($order) {
        // Map order status to payment status
        switch ($order['status']) {
            case 'confirmed':
                return 'paid';
            case 'cancelled':
                return 'cancelled';
            case 'pending':
            default:
                return 'pending';
        }
    }

    // Check if order needs payment
    public function orderNeedsPayment($order) {
        return $order['status'] === 'pending';
    }
}
?>