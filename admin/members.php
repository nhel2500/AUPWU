<?php
// Include database configuration
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin role to access this page
requireRole('admin');

// Get action from URL
$action = $_GET['action'] ?? 'list';
$memberId = $_GET['id'] ?? null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        if ($action === 'add' || $action === 'edit') {
            // Get form data
            $userId = $_POST['user_id'] ?? null;
            $name = $_POST['name'] ?? '';
            $address = $_POST['address'] ?? '';
            $unitCollege = $_POST['unit_college'] ?? '';
            $designation = $_POST['designation'] ?? '';
            $chapter = $_POST['chapter'] ?? '';
            $dateOfAppointment = $_POST['date_of_appointment'] ?? '';
            $dateOfBirth = $_POST['date_of_birth'] ?? '';
            $contactNumber = $_POST['contact_number'] ?? '';
            $email = $_POST['email'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $upStatus = $_POST['up_status'] ?? 'in';
            
            // Handle image uploads
            $photoPath = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $photoPath = uploadImage($_FILES['photo'], '..aupwu/uploads/photos');
            }
            
            $signaturePath = null;
            if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
                $signaturePath = uploadImage($_FILES['signature'], '..aupwu/uploads/signatures');
            }
            
            if ($action === 'add') {
                // Create a user account if not exists
                if (empty($userId)) {
                    // Generate username and password
                    $username = strtolower(preg_replace('/[^a-z0-9]/i', '', explode(' ', $name)[0])) . rand(100, 999);
                    $password = bin2hex(random_bytes(4)); // 8 characters random password
                    
                    // Create user
                    $stmt = $pdo->prepare('INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, 'member']);
                    $userId = $pdo->lastInsertId();
                    
                    // Set success message with credentials
                    $_SESSION['success_message'] = "Member added successfully. User account created with username: $username and password: $password";
                }
                
                // Insert member profile
                $stmt = $pdo->prepare('
                    INSERT INTO members (user_id, name, address, unit_college, designation, chapter, 
                        date_of_appointment, date_of_birth, contact_number, email, is_active, up_status, 
                        photo_path, signature_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $userId, $name, $address, $unitCollege, $designation, $chapter,
                    $dateOfAppointment, $dateOfBirth, $contactNumber, $email, $isActive, $upStatus,
                    $photoPath, $signaturePath
                ]);
                
                // Log the activity
                logActivity($pdo, getCurrentUserId(), "Added member", "Added member profile for $name");
                
                // Set success message if not already set
                if (!isset($_SESSION['success_message'])) {
                    $_SESSION['success_message'] = "Member added successfully";
                }
            } else {
                // Update existing member
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
                    'is_active' => $isActive,
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
                $values[] = $memberId; // Add member ID for WHERE clause
                
                // Update member
                $stmt = $pdo->prepare("UPDATE members SET $setClause WHERE id = ?");
                $stmt->execute($values);
                
                // Log the activity
                logActivity($pdo, getCurrentUserId(), "Updated member", "Updated member profile for $name (ID: $memberId)");
                
                $_SESSION['success_message'] = "Member updated successfully";
            }
            
            // Process committee assignments if this is an edit action
            if ($action === 'edit' && isset($_POST['committees'])) {
                // Get selected committees
                $selectedCommittees = $_POST['committees'] ?? [];
                $positions = $_POST['positions'] ?? [];
                $startDates = $_POST['start_dates'] ?? [];
                $endDates = $_POST['end_dates'] ?? [];
                
                // Delete existing committee assignments
                $stmt = $pdo->prepare('DELETE FROM member_committees WHERE member_id = ?');
                $stmt->execute([$memberId]);
                
                // Insert new committee assignments
                foreach ($selectedCommittees as $index => $committeeId) {
                    if (empty($committeeId)) continue;
                    
                    $position = $positions[$index] ?? '';
                    $startDate = $startDates[$index] ?? date('Y-m-d');
                    $endDate = !empty($endDates[$index]) ? $endDates[$index] : null;
                    
                    $stmt = $pdo->prepare('
                        INSERT INTO member_committees (member_id, committee_id, position, start_date, end_date)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([$memberId, $committeeId, $position, $startDate, $endDate]);
                }
                
                // Log the activity
                logActivity($pdo, getCurrentUserId(), "Updated committees", "Updated committee assignments for member (ID: $memberId)");
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to list view
            header('Location: members.php');
            exit;
        } elseif ($action === 'delete' && $memberId) {
            // Delete member and associated user
            $stmt = $pdo->prepare('SELECT user_id FROM members WHERE id = ?');
            $stmt->execute([$memberId]);
            $userId = $stmt->fetchColumn();
            
            if ($userId) {
                // Delete member first (due to foreign key constraint)
                $stmt = $pdo->prepare('DELETE FROM members WHERE id = ?');
                $stmt->execute([$memberId]);
                
                // Delete user
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                
                // Log the activity
                logActivity($pdo, getCurrentUserId(), "Deleted member", "Deleted member profile (ID: $memberId)");
                
                $_SESSION['success_message'] = "Member deleted successfully";
            } else {
                $_SESSION['error_message'] = "Member not found";
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to list view
            header('Location: members.php');
            exit;
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        error_log('Member Management Error: ' . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        
        // Redirect to list view
        header('Location: members.php');
        exit;
    }
}

// Get members or single member data based on action
$member = null;
$memberCommittees = [];
$allCommittees = [];

if ($action === 'edit' && $memberId) {
    // Get member data
    $member = getMemberById($pdo, $memberId);
    
    if (!$member) {
        $_SESSION['error_message'] = "Member not found";
        header('Location: members.php');
        exit;
    }
    
    // Get member committees
    $memberCommittees = getMemberCommittees($pdo, $memberId);
    
    // Get all committees for selection
    $allCommittees = getAllCommittees($pdo);
} elseif ($action === 'view' && $memberId) {
    // Get member data
    $member = getMemberById($pdo, $memberId);
    
    if (!$member) {
        $_SESSION['error_message'] = "Member not found";
        header('Location: members.php');
        exit;
    }
    
    // Get member committees
    $memberCommittees = getMemberCommittees($pdo, $memberId);
} elseif ($action === 'add') {
    // Get all users without member profiles
    try {
        $stmt = $pdo->query('
            SELECT u.* 
            FROM users u 
            LEFT JOIN members m ON u.id = m.user_id 
            WHERE m.id IS NULL AND u.role = "member"
            ORDER BY u.username
        ');
        $availableUsers = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Available Users Error: ' . $e->getMessage());
        $availableUsers = [];
    }
}

// Get all members for list view
$members = [];
if ($action === 'list') {
    $members = getAllMembers($pdo);
}

// Include header
include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manage Members</h1>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i> Add New Member
        </a>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Unit/College</th>
                            <th>Designation</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>UP Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No members found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($members as $m): ?>
                                <tr>
                                    <td><?php echo $m['id']; ?></td>
                                    <td>
                                        <a href="?action=view&id=<?php echo $m['id']; ?>">
                                            <?php echo htmlspecialchars($m['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($m['unit_college']); ?></td>
                                    <td><?php echo htmlspecialchars($m['designation']); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo $m['email']; ?>"><?php echo htmlspecialchars($m['email']); ?></a>
                                        <br>
                                        <small><?php echo htmlspecialchars($m['contact_number']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($m['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['up_status'] === 'in'): ?>
                                            <span class="badge bg-info">In UP</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Out of UP</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=view&id=<?php echo $m['id']; ?>" class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?action=edit&id=<?php echo $m['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-danger" title="Delete" 
                                               onclick="if(confirmDelete('Are you sure you want to delete this member? This will also delete the associated user account.')) 
                                                         window.location='?action=delete&id=<?php echo $m['id']; ?>'">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($action === 'view' && $member): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">View Member: <?php echo htmlspecialchars($member['name']); ?></h1>
        <div>
            <a href="?action=edit&id=<?php echo $member['id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Edit Member
            </a>
            <a href="members.php" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left me-2"></i> Back to List
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-primary">
                    <h5 class="card-title mb-0">Personal Information</h5>
                </div>
                <div class="card-body text-center">
                    <?php if ($member['photo_path']): ?>
                        <img src="<?php echo htmlspecialchars($member['photo_path']); ?>" alt="Member Photo" class="profile-image mb-3">
                    <?php else: ?>
                        <div class="profile-image bg-light d-flex align-items-center justify-content-center mb-3 mx-auto">
                            <i class="fas fa-user fa-4x text-muted"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h4><?php echo htmlspecialchars($member['name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($member['designation']); ?></p>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><strong>Date of Birth:</strong> <?php echo formatDate($member['date_of_birth']); ?></p>
                        <p><strong>Date of Appointment:</strong> <?php echo formatDate($member['date_of_appointment']); ?></p>
                        
                        <p>
                            <strong>Status:</strong>
                            <?php if ($member['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </p>
                        
                        <p>
                            <strong>UP Status:</strong>
                            <?php if ($member['up_status'] === 'in'): ?>
                                <span class="badge bg-info">In UP</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Out of UP</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if ($member['signature_path']): ?>
                        <hr>
                        <h6>Signature</h6>
                        <img src="<?php echo htmlspecialchars($member['signature_path']); ?>" alt="Signature" class="signature-image mt-2">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header card-header-primary">
                    <h5 class="card-title mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Email:</strong>
                        </div>
                        <div class="col-md-8">
                            <a href="mailto:<?php echo $member['email']; ?>"><?php echo htmlspecialchars($member['email']); ?></a>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Contact Number:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php echo htmlspecialchars($member['contact_number']); ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Address:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php echo nl2br(htmlspecialchars($member['address'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header card-header-primary">
                    <h5 class="card-title mb-0">Employment Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Unit/College:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php echo htmlspecialchars($member['unit_college']); ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Designation:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php echo htmlspecialchars($member['designation']); ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Chapter:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php echo htmlspecialchars($member['chapter']); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header card-header-primary">
                    <h5 class="card-title mb-0">Committee Memberships</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($memberCommittees)): ?>
                        <p class="text-muted">This member is not assigned to any committees.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Committee</th>
                                        <th>Position</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($memberCommittees as $committee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($committee['committee_name']); ?></td>
                                            <td><?php echo htmlspecialchars($committee['position'] ?? 'Member'); ?></td>
                                            <td><?php echo formatDate($committee['start_date']); ?></td>
                                            <td><?php echo $committee['end_date'] ? formatDate($committee['end_date']) : 'Present'; ?></td>
                                            <td>
                                                <?php if ($committee['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'add' || ($action === 'edit' && $member)): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?php echo $action === 'add' ? 'Add New Member' : 'Edit Member: ' . htmlspecialchars($member['name']); ?></h1>
        <a href="members.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to List
        </a>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-body">
            <form action="?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $member['id'] : ''; ?>" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="user_id" value="<?php echo $member['user_id']; ?>">
                <?php endif; ?>
                
                <div class="form-section">
                    <h5 class="form-section-title">Personal Information</h5>
                    
                    <?php if ($action === 'add'): ?>
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Existing User (Optional)</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="">Create a new user</option>
                                <?php foreach ($availableUsers ?? [] as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">If you select an existing user, a new profile will be created for them. Otherwise, a new user account will be created automatically.</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo $member ? htmlspecialchars($member['name']) : ''; ?>" required>
                            <div class="invalid-feedback">Please enter a name</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $member ? htmlspecialchars($member['email']) : ''; ?>" required>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo $member ? $member['date_of_birth'] : ''; ?>" required>
                            <div class="invalid-feedback">Please enter a date of birth</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="contact_number" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                   value="<?php echo $member ? htmlspecialchars($member['contact_number']) : ''; ?>" required>
                            <div class="invalid-feedback">Please enter a contact number</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $member ? htmlspecialchars($member['address']) : ''; ?></textarea>
                        <div class="invalid-feedback">Please enter an address</div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h5 class="form-section-title">Employment Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="unit_college" class="form-label">Unit/College</label>
                            <input type="text" class="form-control" id="unit_college" name="unit_college" 
                                   value="<?php echo $member ? htmlspecialchars($member['unit_college']) : ''; ?>" required>
                            <div class="invalid-feedback">Please enter a unit/college</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation" 
                                   value="<?php echo $member ? htmlspecialchars($member['designation']) : ''; ?>" required>
                            <div class="invalid-feedback">Please enter a designation</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="chapter" class="form-label">Chapter</label>
                            <input type="text" class="form-control" id="chapter" name="chapter" 
                                   value="<?php echo $member ? htmlspecialchars($member['chapter']) : ''; ?>" required>
                            <div class="invalid-feedback">Please enter a chapter</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="date_of_appointment" class="form-label">Date of Original Appointment</label>
                            <input type="date" class="form-control" id="date_of_appointment" name="date_of_appointment" 
                                   value="<?php echo $member ? $member['date_of_appointment'] : ''; ?>" required>
                            <div class="invalid-feedback">Please enter a date of appointment</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h5 class="form-section-title">Status Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?php echo (!$member || $member['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active Member</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">UP Status</label>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="up_status" id="up_status_in" value="in" 
                                       <?php echo (!$member || $member['up_status'] === 'in') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary" for="up_status_in">In UP</label>
                                
                                <input type="radio" class="btn-check" name="up_status" id="up_status_out" value="out" 
                                       <?php echo ($member && $member['up_status'] === 'out') ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-warning" for="up_status_out">Out of UP</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($action === 'edit'): ?>
                    <div class="form-section">
                        <h5 class="form-section-title">Committee Assignments</h5>
                        
                        <div id="committees-container">
                            <?php if (empty($memberCommittees)): ?>
                                <div class="committee-row row mb-3">
                                    <div class="col-md-4">
                                        <select class="form-select" name="committees[]">
                                            <option value="">Select a committee</option>
                                            <?php foreach ($allCommittees as $committee): ?>
                                                <option value="<?php echo $committee['id']; ?>">
                                                    <?php echo htmlspecialchars($committee['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" name="positions[]" placeholder="Position">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" class="form-control" name="start_dates[]" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="date" class="form-control" name="end_dates[]">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger remove-committee"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($memberCommittees as $index => $committee): ?>
                                    <div class="committee-row row mb-3">
                                        <div class="col-md-4">
                                            <select class="form-select" name="committees[]">
                                                <option value="">Select a committee</option>
                                                <?php foreach ($allCommittees as $c): ?>
                                                    <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $committee['committee_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($c['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="positions[]" placeholder="Position" 
                                                   value="<?php echo htmlspecialchars($committee['position'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" class="form-control" name="start_dates[]" 
                                                   value="<?php echo $committee['start_date']; ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" class="form-control" name="end_dates[]" 
                                                   value="<?php echo $committee['end_date'] ?? ''; ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger remove-committee"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" id="add-committee" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-2"></i> Add Committee
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="form-section">
                    <h5 class="form-section-title">Documents and Photos</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="photo" class="form-label">Profile Photo (1x1)</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <?php if ($member && $member['photo_path']): ?>
                                <div class="mt-2">
                                    <div class="form-text">Current photo:</div>
                                    <img src="<?php echo htmlspecialchars($member['photo_path']); ?>" alt="Current Photo" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="signature" class="form-label">Signature</label>
                            <input type="file" class="form-control" id="signature" name="signature" accept="image/*">
                            <?php if ($member && $member['signature_path']): ?>
                                <div class="mt-2">
                                    <div class="form-text">Current signature:</div>
                                    <img src="<?php echo htmlspecialchars($member['signature_path']); ?>" alt="Current Signature" class="img-thumbnail" style="max-height: 50px;">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="members.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'add' ? 'Add Member' : 'Update Member'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($action === 'edit'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Committee management
    const committeesContainer = document.getElementById('committees-container');
    const addCommitteeBtn = document.getElementById('add-committee');
    
    // Add committee row
    addCommitteeBtn.addEventListener('click', function() {
        const committeeRow = document.createElement('div');
        committeeRow.className = 'committee-row row mb-3';
        
        committeeRow.innerHTML = `
            <div class="col-md-4">
                <select class="form-select" name="committees[]">
                    <option value="">Select a committee</option>
                    <?php foreach ($allCommittees as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="positions[]" placeholder="Position">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="start_dates[]" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="end_dates[]">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger remove-committee"><i class="fas fa-times"></i></button>
            </div>
        `;
        
        committeesContainer.appendChild(committeeRow);
        
        // Add event listener to the new remove button
        const removeBtn = committeeRow.querySelector('.remove-committee');
        removeBtn.addEventListener('click', function() {
            committeeRow.remove();
        });
    });
    
    // Remove committee row
    document.querySelectorAll('.remove-committee').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.committee-row').remove();
        });
    });
});
</script>
<?php endif; ?>

<?php
// Include footer
include '../includes/footer.php';
?>
