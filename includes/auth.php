<?php
/**
 * Authentication functions
 * 
 * This file contains functions related to user authentication,
 * session management, and access control.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Register a new user
 * 
 * @param PDO $pdo Database connection
 * @param string $username Username
 * @param string $password Plain text password
 * @param string $email User email
 * @param string $role User role (admin, member, officer)
 * @return bool|string True on success, error message on failure
 */
function registerUser($pdo, $username, $password, $email, $role = 'member') {
    try {
        // Check if username already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            return "Username or email already exists";
        }
        
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)');
        $result = $stmt->execute([$username, $hashedPassword, $email, $role]);
        
        if ($result) {
            // Log the activity
            logActivity($pdo, $pdo->lastInsertId(), "User registered", "New user $username registered");
            return true;
        } else {
            return "Registration failed";
        }
    } catch (PDOException $e) {
        error_log('Registration Error: ' . $e->getMessage());
        return "An error occurred during registration";
    }
}

/**
 * Authenticate a user
 * 
 * @param PDO $pdo Database connection
 * @param string $username Username
 * @param string $password Plain text password
 * @return bool|array False on failure, user data on success
 */
function loginUser($pdo, $username, $password) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Log the activity
            logActivity($pdo, $user['id'], "User login", "User {$user['username']} logged in");
            
            return $user;
        } else {
            return false;
        }
    } catch (PDOException $e) {
        error_log('Login Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 * 
 * @return int|null User ID if logged in, null otherwise
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current user role
 * 
 * @return string|null User role if logged in, null otherwise
 */
function getCurrentUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

/**
 * Check if user has specific role
 * 
 * @param string|array $roles Role or array of roles to check
 * @return bool True if user has any of the specified roles
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_string($roles)) {
        return $_SESSION['role'] === $roles;
    } else if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    
    return false;
}

/**
 * Get current user details
 * 
 * @param PDO $pdo Database connection
 * @return array|null User details if logged in, null otherwise
 */
function getCurrentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get User Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Log user activity for audit trail
 * 
 * @param PDO $pdo Database connection
 * @param int|null $userId User ID or null if not logged in
 * @param string $action Action being performed
 * @param string $details Additional details about the action
 * @return bool Success status
 */
function logActivity($pdo, $userId, $action, $details = '') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
        return $stmt->execute([$userId, $action, $details, $ip]);
    } catch (PDOException $e) {
        error_log('Activity Log Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log out the current user
 */
function logout() {
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Require authentication to access a page
 * Redirects to login page if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit;
    }
}

/**
 * Require specific role to access a page
 * Redirects to login page if not authenticated or not authorized
 * 
 * @param string|array $roles Required role(s)
 */
function requireRole($roles) {
    requireLogin();
    
    if (!hasRole($roles)) {
        header('Location: /index.php?error=unauthorized');
        exit;
    }
}
?>
