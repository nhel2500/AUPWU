<?php
// Include database configuration
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin role to access this page
requireRole('admin');

// Get demographic data for dashboard
$demographicData = getDemographicData($pdo);

// Get recent members (limit to 5)
try {
    $stmt = $pdo->query('
        SELECT m.*, u.username 
        FROM members m 
        JOIN users u ON m.user_id = u.id 
        ORDER BY m.created_at DESC 
        LIMIT 5
    ');
    $recentMembers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get Recent Members Error: ' . $e->getMessage());
    $recentMembers = [];
}

// Get recent activity logs (limit to 10)
try {
    $stmt = $pdo->query('
        SELECT l.*, u.username 
        FROM activity_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        ORDER BY l.timestamp DESC 
        LIMIT 10
    ');
    $recentActivity = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get Recent Activity Error: ' . $e->getMessage());
    $recentActivity = [];
}

// Get active elections count
try {
    $stmt = $pdo->query('
        SELECT COUNT(*) as count 
        FROM elections 
        WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()
    ');
    $activeElections = $stmt->fetch()['count'];
} catch (PDOException $e) {
    error_log('Get Active Elections Error: ' . $e->getMessage());
    $activeElections = 0;
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Admin Dashboard</h1>
    <span class="badge bg-primary">Admin Access</span>
</div>

<!-- Statistics Row -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-number"><?php echo $demographicData['total_members']; ?></div>
            <div class="stat-title">Total Members</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-number"><?php echo $demographicData['active_members']; ?></div>
            <div class="stat-title">Active Members</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($demographicData['committees']); ?></div>
            <div class="stat-title">Committees</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-number"><?php echo $activeElections; ?></div>
            <div class="stat-title">Active Elections</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Members -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Members</h5>
                <a href="members.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Unit/College</th>
                                <th>Status</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentMembers)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No members found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <a href="members.php?id=<?php echo $member['id']; ?>">
                                                <?php echo htmlspecialchars($member['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['unit_college']); ?></td>
                                        <td>
                                            <?php if ($member['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($member['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Activity</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivity)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No activity found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <tr>
                                        <td>
                                            <?php echo $activity['username'] ? htmlspecialchars($activity['username']) : 'System'; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                        <td><?php echo formatDate($activity['timestamp'], true); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Member Status Chart -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Member Status</h5>
            </div>
            <div class="card-body">
                <canvas id="memberStatusChart" height="250"></canvas>
            </div>
        </div>
    </div>

    <!-- UP Status Chart -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">UP Status</h5>
            </div>
            <div class="card-body">
                <canvas id="upStatusChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Committee Distribution -->
    <div class="col-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Committee Distribution</h5>
                <a href="committees.php" class="btn btn-sm btn-outline-primary">Manage Committees</a>
            </div>
            <div class="card-body">
                <div style="height: 300px">
                    <canvas id="committeeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Links -->
    <div class="col-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="members.php?action=add" class="btn btn-outline-primary w-100 p-3">
                            <i class="fas fa-user-plus mb-2 d-block fs-3"></i>
                            Add New Member
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="committees.php" class="btn btn-outline-primary w-100 p-3">
                            <i class="fas fa-users-cog mb-2 d-block fs-3"></i>
                            Manage Committees
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="voting.php?action=create" class="btn btn-outline-primary w-100 p-3">
                            <i class="fas fa-vote-yea mb-2 d-block fs-3"></i>
                            Create Election
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="reports.php" class="btn btn-outline-primary w-100 p-3">
                            <i class="fas fa-chart-bar mb-2 d-block fs-3"></i>
                            Generate Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Initialize Charts -->
<script src="../js/charts.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Member Status Chart
    createMemberStatusChart('memberStatusChart', 
        <?php echo $demographicData['active_members']; ?>, 
        <?php echo $demographicData['inactive_members']; ?>
    );
    
    // UP Status Chart
    createUPStatusChart('upStatusChart', 
        <?php echo $demographicData['in_up']; ?>, 
        <?php echo $demographicData['out_up']; ?>
    );
    
    // Committee Chart
    createCommitteeMembershipChart('committeeChart', 
        <?php echo json_encode(array_slice($demographicData['committees'], 0, 10)); ?>
    );
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>
