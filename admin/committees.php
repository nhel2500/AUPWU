<?php
// Include database configuration
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin role to access this page
requireRole('admin');

// Get action from URL
$action = $_GET['action'] ?? 'list';
$committeeId = $_GET['id'] ?? null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        if ($action === 'add') {
            // Add new committee
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($name)) {
                throw new Exception("Committee name is required");
            }
            
            $stmt = $pdo->prepare('INSERT INTO committees (name, description) VALUES (?, ?)');
            $stmt->execute([$name, $description]);
            
            // Log the activity
            logActivity($pdo, getCurrentUserId(), "Added committee", "Added committee: $name");
            
            $_SESSION['success_message'] = "Committee added successfully";
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to list view
            header('Location: committees.php');
            exit;
        } elseif ($action === 'edit' && $committeeId) {
            // Update existing committee
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($name)) {
                throw new Exception("Committee name is required");
            }
            
            $stmt = $pdo->prepare('UPDATE committees SET name = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $description, $committeeId]);
            
            // Log the activity
            logActivity($pdo, getCurrentUserId(), "Updated committee", "Updated committee (ID: $committeeId) to $name");
            
            $_SESSION['success_message'] = "Committee updated successfully";
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to list view
            header('Location: committees.php');
            exit;
        } elseif ($action === 'delete' && $committeeId) {
            // Check if committee has members
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM member_committees WHERE committee_id = ?');
            $stmt->execute([$committeeId]);
            $memberCount = $stmt->fetchColumn();
            
            if ($memberCount > 0) {
                throw new Exception("Cannot delete committee with members. Remove members from the committee first.");
            }
            
            // Delete committee
            $stmt = $pdo->prepare('DELETE FROM committees WHERE id = ?');
            $stmt->execute([$committeeId]);
            
            // Log the activity
            logActivity($pdo, getCurrentUserId(), "Deleted committee", "Deleted committee (ID: $committeeId)");
            
            $_SESSION['success_message'] = "Committee deleted successfully";
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to list view
            header('Location: committees.php');
            exit;
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        error_log('Committee Management Error: ' . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        
        // Redirect to list view
        header('Location: committees.php');
        exit;
    }
}

// Get committee data based on action
$committee = null;
$committeeMembers = [];

if (($action === 'edit' || $action === 'view') && $committeeId) {
    // Get committee data
    $committee = getCommitteeById($pdo, $committeeId);
    
    if (!$committee) {
        $_SESSION['error_message'] = "Committee not found";
        header('Location: committees.php');
        exit;
    }
    
    // Get committee members
    if ($action === 'view') {
        $committeeMembers = getCommitteeMembers($pdo, $committeeId);
    }
}

// Get all committees for list view
$committees = [];
if ($action === 'list') {
    try {
        $stmt = $pdo->query('
            SELECT c.*, COUNT(mc.id) as member_count 
            FROM committees c 
            LEFT JOIN member_committees mc ON c.id = mc.committee_id AND mc.is_active = 1 
            GROUP BY c.id 
            ORDER BY c.name ASC
        ');
        $committees = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Committees Error: ' . $e->getMessage());
        $committees = [];
    }
}

// Include header
include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manage Committees</h1>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Add New Committee
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
                            <th>Description</th>
                            <th>Active Members</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($committees)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No committees found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($committees as $c): ?>
                                <tr>
                                    <td><?php echo $c['id']; ?></td>
                                    <td>
                                        <a href="?action=view&id=<?php echo $c['id']; ?>">
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['description'] ?? 'No description'); ?></td>
                                    <td><?php echo $c['member_count']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=view&id=<?php echo $c['id']; ?>" class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-danger" title="Delete" 
                                               onclick="if(confirmDelete('Are you sure you want to delete this committee? This action cannot be undone.')) 
                                                         window.location='?action=delete&id=<?php echo $c['id']; ?>'">
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

<?php elseif ($action === 'view' && $committee): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Committee: <?php echo htmlspecialchars($committee['name']); ?></h1>
        <div>
            <a href="?action=edit&id=<?php echo $committee['id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Edit Committee
            </a>
            <a href="committees.php" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left me-2"></i> Back to List
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-primary">
                    <h5 class="card-title mb-0">Committee Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Committee Name</h6>
                        <p><?php echo htmlspecialchars($committee['name']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Description</h6>
                        <p><?php echo htmlspecialchars($committee['description'] ?? 'No description available'); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Created On</h6>
                        <p><?php echo formatDate($committee['created_at']); ?></p>
                    </div>
                    
                    <div>
                        <h6>Last Updated</h6>
                        <p><?php echo formatDate($committee['updated_at']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Committee Members</h5>
                    <a href="../admin/members.php" class="btn btn-sm btn-outline-primary">Manage Members</a>
                </div>
                <div class="card-body">
                    <?php if (empty($committeeMembers)): ?>
                        <p class="text-muted">No members assigned to this committee.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Unit/College</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($committeeMembers as $member): ?>
                                        <tr>
                                            <td>
                                                <a href="../admin/members.php?action=view&id=<?php echo $member['member_id']; ?>">
                                                    <?php echo htmlspecialchars($member['name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($member['position'] ?? 'Member'); ?></td>
                                            <td><?php echo htmlspecialchars($member['unit_college']); ?></td>
                                            <td><?php echo formatDate($member['start_date']); ?></td>
                                            <td><?php echo $member['end_date'] ? formatDate($member['end_date']) : 'Present'; ?></td>
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

<?php elseif ($action === 'add' || ($action === 'edit' && $committee)): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?php echo $action === 'add' ? 'Add New Committee' : 'Edit Committee'; ?></h1>
        <a href="committees.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to List
        </a>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header card-header-primary">
            <h5 class="card-title mb-0"><?php echo $action === 'add' ? 'Committee Information' : 'Edit ' . htmlspecialchars($committee['name']); ?></h5>
        </div>
        <div class="card-body">
            <form action="?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $committee['id'] : ''; ?>" method="post" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="name" class="form-label">Committee Name</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo $committee ? htmlspecialchars($committee['name']) : ''; ?>" required>
                    <div class="invalid-feedback">Please enter a committee name</div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo $committee ? htmlspecialchars($committee['description'] ?? '') : ''; ?></textarea>
                </div>
                
                <div class="text-end">
                    <a href="committees.php" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'add' ? 'Add Committee' : 'Update Committee'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
// Include footer
include '../includes/footer.php';
?>
