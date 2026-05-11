<?php
// auth.php - Authentication System
session_start();

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function register($username, $email, $password) {
        // Validate input
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }
        
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        try {
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $password_hash]);
            return ['success' => true, 'user_id' => $this->db->lastInsertId()];
        } catch(PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                return ['success' => false, 'error' => 'Username or email already exists'];
            }
            return ['success' => false, 'error' => 'Registration failed'];
        }
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            
            $this->updateLastLogin($user['id']);
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Invalid email or password'];
    }
    
    private function updateLastLogin($user_id) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->db->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
}

// Initialize auth
$auth = new Auth();
?>