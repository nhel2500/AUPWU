<?php
// Include database configuration
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require officer role to access this page
requireRole(['admin', 'officer']);

// Get action from URL
$action = $_GET['action'] ?? 'list';
$committeeId = $_GET['id'] ?? null;

// Get committee data based on action
$committee = null;
$committeeMembers = [];

if ($action === 'view' && $committeeId) {
    // Get committee data
    $committee = getCommitteeById($pdo, $committeeId);
    
    if (!$committee) {
        $_SESSION['error_message'] = "Committee not found";
        header('Location: committees.php');
        exit;
    }
    
    // Get committee members
    $committeeMembers = getCommitteeMembers($pdo, $committeeId);
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
        <h1 class="h3">Committees</h1>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover data-table">
                    <thead>
                        <tr>
                            <th>Committee Name</th>
                            <th>Description</th>
                            <th>Active Members</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($committees)): ?>
                            <tr>
                                <td colspan="4" class="text-center">No committees found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($committees as $c): ?>
                                <tr>
                                    <td>
                                        <a href="?action=view&id=<?php echo $c['id']; ?>">
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['description'] ?? 'No description'); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $c['member_count']; ?></span>
                                    </td>
                                    <td>
                                        <a href="?action=view&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
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
        <a href="committees.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to List
        </a>
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
                <div class="card-header card-header-primary">
                    <h5 class="card-title mb-0">Committee Members</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($committeeMembers)): ?>
                        <p class="text-muted">No members assigned to this committee.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Unit/College</th>
                                        <th>Contact</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($committeeMembers as $member): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($member['name']); ?></td>
                                            <td><?php echo htmlspecialchars($member['position'] ?? 'Member'); ?></td>
                                            <td><?php echo htmlspecialchars($member['unit_college']); ?></td>
                                            <td><?php echo htmlspecialchars($member['designation']); ?></td>
                                            <td>
                                                <?php echo formatDate($member['start_date']); ?> 
                                                <?php if ($member['end_date']): ?>
                                                    to <?php echo formatDate($member['end_date']); ?>
                                                <?php else: ?>
                                                    to Present
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
    
    <div class="card shadow-sm">
        <div class="card-header card-header-primary">
            <h5 class="card-title mb-0">Member Distribution</h5>
        </div>
        <div class="card-body">
            <?php
            // Get unit/college distribution for this committee
            try {
                $stmt = $pdo->prepare('
                    SELECT m.unit_college, COUNT(*) as count 
                    FROM member_committees mc 
                    JOIN members m ON mc.member_id = m.id 
                    WHERE mc.committee_id = ? AND mc.is_active = 1 
                    GROUP BY m.unit_college 
                    ORDER BY count DESC
                ');
                $stmt->execute([$committeeId]);
                $unitDistribution = $stmt->fetchAll();
            } catch (PDOException $e) {
                error_log('Get Unit Distribution Error: ' . $e->getMessage());
                $unitDistribution = [];
            }
            ?>
            
            <?php if (!empty($unitDistribution)): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="unitDistributionChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Unit/College</th>
                                        <th>Members</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalMembers = array_sum(array_column($unitDistribution, 'count'));
                                    foreach ($unitDistribution as $unit): 
                                        $percentage = ($totalMembers > 0) ? round(($unit['count'] / $totalMembers) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($unit['unit_college']); ?></td>
                                            <td><?php echo $unit['count']; ?></td>
                                            <td><?php echo $percentage; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <script src="../js/charts.js"></script>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Unit Distribution Chart
                    createUnitDistributionChart('unitDistributionChart', 
                        <?php echo json_encode($unitDistribution); ?>
                    );
                });
                </script>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No distribution data available for this committee.
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
// Include footer
include '../includes/footer.php';
?>
