<?php 
// Start session
session_start();

// Check for login errors
$loginErrors = $_SESSION['login_errors'] ?? [];
$loginUsername = $_SESSION['login_username'] ?? '';

// Clear the error messages after displaying them
unset($_SESSION['login_errors'], $_SESSION['login_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Abico Store</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom-login.css">
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h4>Welcome Back!</h4>
                <p class="mb-0">Please sign in to continue</p>
            </div>
            <div class="card-body p-4">
                                    <?php if (!empty($loginErrors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php foreach ($loginErrors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form id="loginForm" action="controller/loginController.php" method="POST" novalidate>
                        <div class="input-group mb-4">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" 
                                   class="form-control with-icon" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Enter your username" 
                                   value="<?php echo htmlspecialchars($loginUsername); ?>"
                                   required 
                                   autofocus>
                    </div>
                    
                    <div class="input-group mb-4">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               class="form-control with-icon" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required>
                    </div>
                    
                    <div class="d-grid gap-2 mb-3">
                        <button type="submit" class="btn btn-primary btn-login" id="loginButton">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <span class="button-text">Sign In</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="<?php echo $base_url; ?>assets/js/custom-login.js"></script>
</body>
</html>
