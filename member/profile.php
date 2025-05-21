<?php
// Include database configuration
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get current user info
$user = getCurrentUser($pdo);
$userId = $user['id'];

// Get member profile
$member = getMemberByUserId($pdo, $userId);

// Process form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get form data
        $name = $_POST['name'] ?? '';
        $address = $_POST['address'] ?? '';
        $unitCollege = $_POST['unit_college'] ?? '';
        $designation = $_POST['designation'] ?? '';
        $chapter = $_POST['chapter'] ?? '';
        $dateOfAppointment = $_POST['date_of_appointment'] ?? '';
        $dateOfBirth = $_POST['date_of_birth'] ?? '';
        $contactNumber = $_POST['contact_number'] ?? '';
        $email = $_POST['email'] ?? '';
        $upStatus = $_POST['up_status'] ?? 'in';
        
        // Simple validation
        if (empty($name) || empty($address) || empty($unitCollege) || 
            empty($designation) || empty($chapter) || empty($dateOfAppointment) || 
            empty($dateOfBirth) || empty($contactNumber) || empty($email)) {
            throw new Exception("All fields are required");
        }
        
        // Handle image uploads
        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoPath = uploadImage($_FILES['photo'], 'aupwu/uploads/photos');
            if (!$photoPath) {
                throw new Exception("Error uploading photo. Please try again.");
            }
        }
        
        $signaturePath = null;
        if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
            $signaturePath = uploadImage($_FILES['signature'], 'aupwu/uploads/signatures', 400, 200);
            if (!$signaturePath) {
                throw new Exception("Error uploading signature. Please try again.");
            }
        }
        
        if ($member) {
            // Update existing member profile
            $updateFields = [
                'name' => $name,
                'address' => $address,
                'unit_college' => $unitCollege,
                'designation' => $designation,
                'chapter' => $chapter,
                'date_of_appointment' => $dateOfAppointment,
                'date_of_birth' => $dateOfBirth,
                'contact_number' => $contactNumber,
                'email' => $email,
                'up_status' => $upStatus
            ];
            
            // Add photo path if uploaded
            if ($photoPath) {
                $updateFields['photo_path'] = $photoPath;
            }
            
            // Add signature path if uploaded
            if ($signaturePath) {
                $updateFields['signature_path'] = $signaturePath;
            }
            
            // Build the SQL query
            $setClause = implode(', ', array_map(fn($field) => "$field = ?", array_keys($updateFields)));
            $values = array_values($updateFields);
            $values[] = $member['id']; // Add member ID for WHERE clause
            
            // Update member
            $stmt = $pdo->prepare("UPDATE members SET $setClause WHERE id = ?");
            $stmt->execute($values);
            
            // Log the activity
            logActivity($pdo, $userId, "Updated profile", "Updated member profile");
            
            $success = true;
        } else {
            // Create new member profile
            $stmt = $pdo->prepare('
                INSERT INTO members (user_id, name, address, unit_college, designation, chapter, 
                    date_of_appointment, date_of_birth, contact_number, email, is_active, up_status, 
                    photo_path, signature_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
            ');
            $stmt->execute([
                $userId, $name, $address, $unitCollege, $designation, $chapter,
                $dateOfAppointment, $dateOfBirth, $contactNumber, $email, $upStatus,
                $photoPath, $signaturePath
            ]);
            
            // Log the activity
            logActivity($pdo, $userId, "Created profile", "Created member profile");
            
            $success = true;
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Refresh member data
        $member = getMemberByUserId($pdo, $userId);
        
        // Set success message
        $_SESSION['success_message'] = "Profile saved successfully";
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        error_log('Profile Error: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">My Profile</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> Your profile has been saved successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header card-header-primary">
        <h5 class="card-title mb-0"><?php echo $member ? 'Edit Profile' : 'Complete Your Profile'; ?></h5>
    </div>
    <div class="card-body">
        <?php if (!$member): ?>
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i> Welcome to AUPWU Management System! Please complete your profile information to gain full access to member features.
            </div>
        <?php endif; ?>
        
        <form action="profile.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="form-section">
                <h5 class="form-section-title">Personal Information</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo $member ? htmlspecialchars($member['name']) : ''; ?>" required>
                        <div class="invalid-feedback">Please enter your full name</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo $member ? htmlspecialchars($member['email']) : $user['email']; ?>" required>
                        <div class="invalid-feedback">Please enter a valid email address</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                               value="<?php echo $member ? $member['date_of_birth'] : ''; ?>" required>
                        <div class="invalid-feedback">Please enter your date of birth</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" 
                               value="<?php echo $member ? htmlspecialchars($member['contact_number']) : ''; ?>" required>
                        <div class="invalid-feedback">Please enter your contact number</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $member ? htmlspecialchars($member['address']) : ''; ?></textarea>
                    <div class="invalid-feedback">Please enter your address</div>
                </div>
            </div>
            
            <div class="form-section">
                <h5 class="form-section-title">Employment Information</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="unit_college" class="form-label">Unit/College</label>
                        <input type="text" class="form-control" id="unit_college" name="unit_college" 
                               value="<?php echo $member ? htmlspecialchars($member['unit_college']) : ''; ?>" required>
                        <div class="invalid-feedback">Please enter your unit/college</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="designation" class="form-label">Designation</label>
                        <input type="text" class="form-control" id="designation" name="designation" 
                               value="<?php echo $member ? htmlspecialchars($member['designation']) : ''; ?>" required>
                        <div class="invalid-feedback">Please enter your designation</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="chapter" class="form-label">Chapter</label>
                        <input type="text" class="form-control" id="chapter" name="chapter" 
                               value="<?php echo $member ? htmlspecialchars($member['chapter']) : ''; ?>" required>
                        <div class="invalid-feedback">Please enter your chapter</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="date_of_appointment" class="form-label">Date of Original Appointment</label>
                        <input type="date" class="form-control" id="date_of_appointment" name="date_of_appointment" 
                               value="<?php echo $member ? $member['date_of_appointment'] : ''; ?>" required>
                        <div class="invalid-feedback">Please enter your date of appointment</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">UP Status</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="up_status" id="up_status_in" value="in" 
                               <?php echo (!$member || $member['up_status'] === 'in') ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-primary" for="up_status_in">In UP</label>
                        
                        <input type="radio" class="btn-check" name="up_status" id="up_status_out" value="out" 
                               <?php echo ($member && $member['up_status'] === 'out') ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-warning" for="up_status_out">Out of UP</label>
                    </div>
                    <div class="form-text">Select "In UP" if you are currently working at the University of the Philippines, or "Out of UP" if you are no longer employed by the university.</div>
                </div>
            </div>
            
            <div class="form-section">
                <h5 class="form-section-title">Documents and Photos</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="photo" class="form-label">Profile Photo (1x1)</label>
                        <input type="file" class="form-control image-upload-input" id="photo" name="photo" 
                               accept="image/*" data-preview="photo-preview" <?php echo !$member ? 'required' : ''; ?>>
                        <div class="invalid-feedback">Please upload your 1x1 photo</div>
                        <div class="form-text">Upload a clear 1x1 ID photo (JPEG, PNG, or GIF format)</div>
                        
                        <div class="mt-2 text-center">
                            <?php if ($member && $member['photo_path']): ?>
                                <img src="<?php echo htmlspecialchars($member['photo_path']); ?>" id="photo-preview" 
                                     alt="Profile Photo" class="img-thumbnail" style="max-height: 150px;">
                            <?php else: ?>
                                <img src="#" id="photo-preview" alt="Profile Photo Preview" 
                                     class="img-thumbnail" style="max-height: 150px; display: none;">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="signature" class="form-label">Signature</label>
                        <input type="file" class="form-control image-upload-input" id="signature" name="signature" 
                               accept="image/*" data-preview="signature-preview" <?php echo !$member ? 'required' : ''; ?>>
                        <div class="invalid-feedback">Please upload your signature</div>
                        <div class="form-text">Upload an image of your signature (JPEG, PNG, or GIF format)</div>
                        
                        <div class="mt-2 text-center">
                            <?php if ($member && $member['signature_path']): ?>
                                <img src="<?php echo htmlspecialchars($member['signature_path']); ?>" id="signature-preview" 
                                     alt="Signature" class="img-thumbnail" style="max-height: 100px;">
                            <?php else: ?>
                                <img src="#" id="signature-preview" alt="Signature Preview" 
                                     class="img-thumbnail" style="max-height: 100px; display: none;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <a href="dashboard.php" class="btn btn-outline-secondary me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Profile</button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
