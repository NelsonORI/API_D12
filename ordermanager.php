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

            if (!$event || $event['available_tickets'] < $quantity) {
                throw new Exception("Not enough tickets available");
            }

            $totalAmount = $event['ticket_price'] * $quantity;

            // Create order - ADDED BACKTICKS
            $orderStmt = $this->pdo->prepare("INSERT INTO `order` (user_id, event_id, quantity, total_amount) VALUES (?, ?, ?, ?)");
            $orderStmt->execute([$userId, $eventId, $quantity, $totalAmount]);

            // Update event available tickets
            $eventUpdateStmt = $this->pdo->prepare("UPDATE event SET available_tickets = available_tickets - ? WHERE id = ?");
            $eventUpdateStmt->execute([$quantity, $eventId]);

            $this->pdo->commit();
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    // Get user order - ADDED BACKTICKS
    public function getUserOrders($userId) {
        $stmt = $this->pdo->prepare("SELECT o.*, e.title, e.venue, e.event_date FROM `order` o JOIN event e ON o.event_id = e.id WHERE o.user_id = ? ORDER BY o.order_date DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all order (admin) - ADDED BACKTICKS
    public function getAllOrders() {
        $stmt = $this->pdo->prepare("SELECT o.*, e.title, u.username FROM `order` o JOIN event e ON o.event_id = e.id JOIN project u ON o.user_id = u.id ORDER BY o.order_date DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update order status - ADDED BACKTICKS
    public function updateOrderStatus($orderId, $status) {
        $stmt = $this->pdo->prepare("UPDATE `order` SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $orderId]);
    }

    // Cancel order (for user dashboard) - ADDED BACKTICKS
    public function cancelOrder($orderId, $userId) {
        $this->pdo->beginTransaction();

        try {
            // Get order details - ADDED BACKTICKS
            $orderStmt = $this->pdo->prepare("SELECT o.*, e.event_date FROM `order` o JOIN event e ON o.event_id = e.id WHERE o.id = ? AND o.user_id = ?");
            $orderStmt->execute([$orderId, $userId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Order not found");
            }

            // Check if event is in the past
            if (strtotime($order['event_date']) <= time()) {
                throw new Exception("Cannot cancel order for past event");
            }

            // Update order status - ADDED BACKTICKS
            $statusStmt = $this->pdo->prepare("UPDATE `order` SET status = 'cancelled' WHERE id = ?");
            $statusStmt->execute([$orderId]);


            // Restore tickets to event
            $eventStmt = $this->pdo->prepare("UPDATE event SET available_tickets = available_tickets + ? WHERE id = ?");
            $eventStmt->execute([$order['quantity'], $order['event_id']]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Order cancelled successfully', 'event_id' => $order['event_id']];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}