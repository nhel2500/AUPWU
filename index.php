<?php
// Include database configuration
require_once 'config/database.php';

// Include header
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section py-5 bg-light rounded">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h1 class="display-4 fw-bold text-primary">All UP Workers Union</h1>
                <p class="lead">Welcome to the AUPWU Management System</p>
                <p>A platform dedicated to serving the needs of university employees, promoting labor rights, and fostering solidarity among workers.</p>
                
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="d-grid gap-2 d-md-flex mt-4">
                        <a href="auth/login.php" class="btn btn-primary btn-lg me-md-2">Login</a>
                        <a href="auth/register.php" class="btn btn-outline-primary btn-lg">Register</a>
                    </div>
                <?php else: ?>
                    <div class="d-grid gap-2 d-md-flex mt-4">
                        <a href="<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-primary btn-lg">Go to Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <img src="https://pixabay.com/get/g6526ec4597bbf01a0d1a3d752418dbf2490a0249fff88954fd75d01e063891412b484d5207420036f0611db4203c9cb63ef676c2969fd068660c484612bdccfb_1280.jpg" alt="Union workers" class="img-fluid rounded shadow">
            </div>
        </div>
    </div>
</section>

<!-- Mission and Vision Section -->
<section class="my-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header card-header-primary">
                        <h3 class="card-title">Our Mission</h3>
                    </div>
                    <div class="card-body">
                        <p>To serve the public, promote public service, and contribute to the nation's development through a socially engaged community that upholds honor, excellence, and integrity.</p>
                        <ul class="mt-3">
                            <li>Advocate for workers' rights and welfare</li>
                            <li>Ensure fair and just treatment in the workplace</li>
                            <li>Promote professional development and growth</li>
                            <li>Foster unity and solidarity among university employees</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-header card-header-primary">
                        <h3 class="card-title">Our Vision</h3>
                    </div>
                    <div class="card-body">
                        <p>To become a great university that leads the development of a globally competitive Philippines.</p>
                        <ul class="mt-3">
                            <li>A strong and united workforce empowered to contribute to academic excellence</li>
                            <li>A model organization for labor-management relations</li>
                            <li>Champions of social justice and equality in the workplace</li>
                            <li>Partners in building a progressive and globally competitive national university</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5 bg-light rounded my-5">
    <div class="container">
        <h2 class="text-center mb-5">What We Offer</h2>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="card-title">Member Management</h4>
                        <p>Efficiently manage member information, track membership status, and maintain up-to-date records of all union members.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="icon">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                        <h4 class="card-title">Voting System</h4>
                        <p>Transparent and secure online voting for union elections, ensuring fair representation and democratic processes.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h4 class="card-title">Reports & Analytics</h4>
                        <p>Generate comprehensive reports and analytics to better understand membership demographics and make informed decisions.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="icon">
                            <i class="fas fa-sitemap"></i>
                        </div>
                        <h4 class="card-title">Committee Management</h4>
                        <p>Organize and track committee memberships, responsibilities, and activities to ensure efficient governance.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <h4 class="card-title">Member Profiles</h4>
                        <p>Detailed member profiles with employment information, contact details, and membership history.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card dashboard-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h4 class="card-title">Documentation</h4>
                        <p>Securely store and manage important union documents, including member signatures and photos.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="my-5">
    <div class="container">
        <h2 class="text-center mb-5">What Our Members Say</h2>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3 text-primary">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="card-text fst-italic">"The AUPWU Management System has transformed how we operate as a union. Everything from member registration to committee management is now streamlined and efficient."</p>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <img src="https://pixabay.com/get/g9da56d9d2deb66e8c2e0df333e659c74ca64d90249eaacf38eb5c69faf25cb97255a134e46ef2cdc90e6b60630a0a56cf8725d82fec368aa4b729a6c7129a3d2_1280.jpg" alt="Profile" class="rounded-circle" width="50" height="50">
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Maria Santos</h6>
                                <small class="text-muted">Union Officer, College of Arts and Sciences</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3 text-primary">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <p class="card-text fst-italic">"As an admin, I appreciate how easy it is to generate reports and track membership statistics. The system has made our administrative tasks much more manageable."</p>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <img src="https://pixabay.com/get/g80784a5ed85e55c2cc35a43b4adc1a21a4fe51811a5275d4c51144f69689eaa3ba1a760d0e6815f188373840ced34a8302f4bc1f04a4bbcd38d1b3e03cd8b698_1280.jpg" alt="Profile" class="rounded-circle" width="50" height="50">
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Juan Reyes</h6>
                                <small class="text-muted">Administrative Assistant, Human Resources</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3 text-primary">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="card-text fst-italic">"The online voting system is fantastic! It's secure, easy to use, and has significantly increased participation in our union elections."</p>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <img src="https://pixabay.com/get/g7d984fe9b1540ac2c63cd24c7634c78660a9cf8036e337c7d7bc3159532d3f9c79d6e5389dbe6d743fa02681c699d10757a8a0868b90460977f75b83718bdaa3_1280.jpg" alt="Profile" class="rounded-circle" width="50" height="50">
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Ana Lim</h6>
                                <small class="text-muted">Member, College of Engineering</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="py-5 bg-light rounded my-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-5 mb-4 mb-lg-0">
                <h2>Get In Touch</h2>
                <p class="lead">Have questions about the AUPWU or our management system? Contact us today!</p>
                
                <div class="mt-4">
                    <h5>Office Location</h5>
                    <p>Magsaysay Ave. near corner Ylanan St., <br>Diliman, Quezon City, Philippines</p>
                    
                    <h5>Contact Information</h5>
                    <p>
                        <i class="fas fa-phone me-2"></i> Tel No. 89818500<br>
                        <i class="fas fa-envelope me-2"></i> <a href="mailto:allupworkersunion@up.edu.ph">allupworkersunion@up.edu.ph</a>
                    </p>
                    
                    <h5>Office Hours</h5>
                    <p>Monday - Friday: 8:00 AM - 5:00 PM</p>
                </div>
            </div>
            <div class="col-lg-7">
                <img src="https://pixabay.com/get/ga974007053ae56a9501ae6c095e5969f049b09745767fa2d97426c409960101e0f5ddead86f3ddbaad0b1f515442e5987537646293d7dee460b029fe80cae059_1280.jpg" alt="Office administration" class="img-fluid rounded shadow">
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
include 'includes/footer.php';
?>
