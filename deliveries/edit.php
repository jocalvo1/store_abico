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

// Include header
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">

    </div>
</div>

<?php 
// Include footer
require_once __DIR__ . '/../templates/footer.php'; 