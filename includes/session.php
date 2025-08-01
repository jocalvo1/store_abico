<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to a specific URL
 * @param string $url
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Set flash message
 * @param string $message
 * @param string $type (success, error, warning, info)
 */
function setFlash($message, $type = 'info') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 * @return array|null
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('Please log in to access this page', 'error');
        header('Location: login.php');
        exit();
    }
}

/**
 * Require specific user role
 * @param string|array $roles Required role(s)
 */
function requireRole($roles) {
    requireLogin();
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    if (!in_array($_SESSION['role'] ?? '', $roles)) {
        setFlash('You do not have permission to access this page', 'error');
        header('Location: ../index.php');
        exit();
    }
}
?>
