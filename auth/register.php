<?php
// Include database configuration
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if already logged in
if (isLoggedIn()) {
    // Redirect to appropriate dashboard based on role
    header('Location: ../' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

// Process registration form submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Simple validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Register new user
        $result = registerUser($pdo, $username, $password, $email);
        
        if ($result === true) {
            $success = true;
        } else {
            $error = $result; // Error message from registerUser function
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-header card-header-primary">
                <h4 class="card-title mb-0">Register</h4>
            </div>
            
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        Registration successful! You can now <a href="login.php" class="alert-link">login</a> to your account.
                    </div>
                <?php else: ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                            <div class="invalid-feedback">Please choose a username</div>
                            <div class="form-text">Username must be unique and will be used for login.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Password must be at least 6 characters long</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Passwords must match</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <p class="mb-0 small">
                                <i class="fas fa-info-circle me-2"></i>
                                After registration, you will need to complete your member profile with additional information.
                            </p>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Register</button>
                        </div>
                    </form>
                <?php endif; ?>
                
                <div class="mt-4 text-center">
                    <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
