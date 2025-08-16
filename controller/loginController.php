<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/session.php';

// Handle login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no validation errors, attempt to authenticate
    if (empty($errors)) {
        $user = authenticateUser($username, $password);
        
        if ($user) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            
            // Set success message
            setFlash('Login successful!', 'success');
            
            // Redirect to dashboard or home page
            header('Location: ../index.php');
            exit();
        } else {
            // Invalid credentials
            $errors[] = 'Invalid username or password';
        }
    }
    
    // If we get here, there were errors
    $_SESSION['login_errors'] = $errors;
    $_SESSION['login_username'] = $username;
    
    // Redirect back to login page
    header('Location: ../login.php');
    exit();
}

/**
 * Authenticate user with username and password
 * @param string $username
 * @param string $password
 * @return array|false User data if authenticated, false otherwise
 */
function authenticateUser($username, $password) {
    $conn = getDBConnection();
    
    // Debug: Log connection status
    error_log("Database connection " . ($conn ? "successful" : "failed"));
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, username, password, name, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("Query executed. Found rows: " . $result->num_rows);
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        error_log("User found: " . print_r($user, true));
        
        // Check if password matches hash
        $passwordMatchesHash = password_verify($password, $user['password']);
        $passwordMatchesPlain = ($password === $user['password']);
        
        error_log("Password verification - Hash match: " . ($passwordMatchesHash ? 'true' : 'false'));
        error_log("Password verification - Plain match: " . ($passwordMatchesPlain ? 'true' : 'false'));
        
        if ($passwordMatchesHash || $passwordMatchesPlain) {
            // Don't return password in the user array
            unset($user['password']);
            return $user;
        } else {
            error_log("Password verification failed");
        }
    } else {
        error_log("No user found with username: " . $username);
    }
    
    return false;
}

?>