<?php
// Start session
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action.']);
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get input data
    $delivery_id = isset($_POST['delivery_id']) ? intval($_POST['delivery_id']) : 0;
    $cancel_reason = isset($_POST['cancellation_reason']) ? trim($_POST['cancellation_reason']) : '';

    // Validate input
    if ($delivery_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid delivery ID.']);
        exit();
    }

    if (empty($cancel_reason)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a reason for cancellation.']);
        exit();
    }

    // Include database connection
    require_once '../includes/database.php';
    $conn = getDBConnection();

    try {
        error_log("Attempting to cancel delivery ID: $delivery_id");
        error_log("Cancel reason: $cancel_reason");
        
        // First, check if delivery exists and is cancellable
        $checkQuery = "SELECT id, status FROM deliveries WHERE id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param('i', $delivery_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            throw new Exception('Delivery not found.');
        }
        
        $delivery = $checkResult->fetch_assoc();
        error_log("Current delivery status: " . $delivery['status']);
        
        // Update delivery status to cancelled
        $query = "UPDATE deliveries 
                 SET status = 'cancelled', 
                     cancel_reason = ?,
                     updated_at = NOW()
                 WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $cancel_reason, $delivery_id);
        $result = $stmt->execute();
        
        $affectedRows = $stmt->affected_rows;
        error_log("Update query executed. Rows affected: " . $affectedRows);
        
        if ($result && $affectedRows > 0) {
            $_SESSION['success'] = 'Delivery has been cancelled successfully.';
            header('Location: index.php');
            exit();
        } else {
            error_log("SQL Error: " . $conn->error);
            $_SESSION['error'] = 'Failed to update delivery status. ' . ($stmt->error ?: 'No rows affected.');
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }
        
    } catch (Exception $e) {
        error_log('Delivery Cancellation Error: ' . $e->getMessage());
        $_SESSION['error'] = 'An error occurred while cancelling the delivery: ' . $e->getMessage();
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    } finally {
        if (isset($checkStmt)) $checkStmt->close();
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
    }
} else {
    // If not a POST request, redirect with error
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: index.php');
    exit();
}
