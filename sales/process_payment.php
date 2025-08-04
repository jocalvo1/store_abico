<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if this is a POST request and the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_payment'])) {
    // Include required files
    require_once __DIR__ . '/../includes/database.php';
    
    // Get form data
    $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Calculate cart total
    $cart = $_SESSION['cart'] ?? [];
    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $total = $subtotal; // For now, no tax or discount
    
    // Validate cart is not empty
    if (empty($cart)) {
        $_SESSION['error'] = 'Please add items to cart first';
        header('Location: add.php');
        exit();
    }
    
    // Store cart data in session for payment page
    $_SESSION['sale_data'] = [
        'customer_id' => $customerId,
        'items' => $cart,
        'subtotal' => $subtotal,
        'total' => $total,
        'notes' => $notes
    ];
    
    // Redirect to payment page
    header('Location: payment.php');
    exit();
} else {
    // If someone tries to access this file directly without submitting the form
    header('Location: add.php');
    exit();
}
?>
