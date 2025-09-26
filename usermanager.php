<?php
class UserManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Generate password reset token
    public function generatePasswordResetToken($email) {
        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM project WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        // Generate unique token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token in database
        $stmt = $this->pdo->prepare("
            INSERT INTO password_reset_tokens (email, token, expires_at) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE token = ?, expires_at = ?
        ");
        
        $success = $stmt->execute([$email, $token, $expires, $token, $expires]);

        return $success ? $token : false;
    }

    // Verify reset token
    public function verifyResetToken($token) {
        $stmt = $this->pdo->prepare("
            SELECT email, expires_at 
            FROM password_reset_tokens 
            WHERE token = ? AND expires_at > NOW()
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Reset password
    public function resetPassword($email, $newPassword, $token) {
        // Verify token first
        $tokenData = $this->verifyResetToken($token);
        
        if (!$tokenData || $tokenData['email'] !== $email) {
            return false;
        }

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password
        $stmt = $this->pdo->prepare("UPDATE project SET password = ? WHERE email = ?");
        $success = $stmt->execute([$hashedPassword, $email]);

        if ($success) {
            // Delete used token
            $this->pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?")->execute([$token]);
            return true;
        }

        return false;
    }

    // Get all users (for admin)
    public function getAllUsers($limit = 50, $offset = 0) {
        $stmt = $this->pdo->prepare("SELECT id, username, email, role, phone FROM project LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create user (for admin)
    public function createUser($username, $email, $password, $phone, $role = 'user') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO project (username, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$username, $email, $hashedPassword, $phone, $role]);
    }

    // Update user (for admin)
    public function updateUser($id, $username, $email, $phone, $role) {
        $stmt = $this->pdo->prepare("UPDATE project SET username = ?, email = ?, phone = ?, role = ? WHERE id = ?");
        return $stmt->execute([$username, $email, $phone, $role, $id]);
    }

    // Delete user (for admin)
    public function deleteUser($id) {
        $stmt = $this->pdo->prepare("DELETE FROM project WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>