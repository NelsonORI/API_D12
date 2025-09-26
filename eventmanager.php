<?php
class EventManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Create event with transaction
    public function createEvent($title, $description, $venue, $eventDate, $ticketPrice, $availableTickets) {
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO event (title, description, venue, event_date, ticket_price, available_tickets) VALUES (?, ?, ?, ?, ?, ?)");
            $success = $stmt->execute([$title, $description, $venue, $eventDate, $ticketPrice, $availableTickets]);

            if ($success) {
                $eventId = $this->pdo->lastInsertId();
                // Initialize inventories
                $invStmt = $this->pdo->prepare("INSERT INTO inventories (event_id, tickets_available, tickets_sold) VALUES (?, ?, 0)");
                $invStmt->execute([$eventId, $availableTickets]);
                
                $this->pdo->commit();
                return true;
            }
            
            $this->pdo->rollBack();
            return false;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Event creation error: " . $e->getMessage());
            return false;
        }
    }

    // Get all events (including past events for admin)
    public function getAllEvents($includePast = false) {
        $sql = "SELECT * FROM event";
        if (!$includePast) {
            $sql .= " WHERE event_date > NOW()";
        }
        $sql .= " ORDER BY event_date ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get event by ID
    public function getEventById($id) {
        $stmt = $this->pdo->prepare("SELECT e.*, i.tickets_available, i.tickets_sold FROM event e LEFT JOIN inventory i ON e.id = i.event_id WHERE e.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update event
    public function updateEvent($id, $title, $description, $venue, $eventDate, $ticketPrice, $availableTickets) {
        $stmt = $this->pdo->prepare("UPDATE event SET title = ?, description = ?, venue = ?, event_date = ?, ticket_price = ?, available_tickets = ? WHERE id = ?");
        return $stmt->execute([$title, $description, $venue, $eventDate, $ticketPrice, $availableTickets, $id]);
    }

    // Delete event
    public function deleteEvent($id) {
        $this->pdo->beginTransaction();
        
        try {
            // Delete from inventories first (due to foreign key constraint)
            $invStmt = $this->pdo->prepare("DELETE FROM inventories WHERE event_id = ?");
            $invStmt->execute([$id]);
            
            // Then delete the event
            $stmt = $this->pdo->prepare("DELETE FROM event WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            $this->pdo->commit();
            return $success;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Event deletion error: " . $e->getMessage());
            return false;
        }
    }
}
?>