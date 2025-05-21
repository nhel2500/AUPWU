<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL of the application
$baseUrl = '/';

// Get current user information if logged in
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Header Error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AUPWU Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo $baseUrl; ?>aupwu/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <!-- Logo and Brand -->
            <a class="navbar-brand" href="<?php echo $baseUrl; ?>aupwu/member/dashboard.php">
                <img src="<?php echo $baseUrl; ?>aupwu/images/logo.svg" alt="AUPWU Logo" height="40" class="d-inline-block align-text-top me-2">
                AUPWU
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $baseUrl; ?>aupwu/member/dashboard.php">Home</a>
                    </li>
                    
                    <?php if (isset($currentUser)): ?>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <!-- Admin Navigation -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Admin
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                    <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/admin/dashboard.php">Dashboard</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/admin/members.php">Manage Members</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/admin/committees.php">Manage Committees</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/admin/voting.php">Manage Elections</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/admin/reports.php">Reports</a></li>
                                </ul>
                            </li>
                        <?php elseif ($currentUser['role'] === 'officer'): ?>
                            <!-- Officer Navigation -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="officerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Officer
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="officerDropdown">
                                    <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/officer/dashboard.php">Dashboard</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/officer/committees.php">Committees</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/officer/reports.php">Reports</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Member Navigation (all authenticated users) -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="memberDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Member
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="memberDropdown">
                                <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/member/dashboard.php">My Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/member/profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/member/vote.php">Elections & Voting</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- User Authentication -->
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <?php if (isset($currentUser)): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($currentUser['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/member/profile.php">My Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $baseUrl; ?>aupwu/auth/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $baseUrl; ?>aupwu/auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $baseUrl; ?>aupwu/auth/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Page Content Container -->
    <div class="container mb-4">
        <!-- Display flash messages if any -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
