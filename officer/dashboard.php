<?php
// Include database configuration
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require officer role to access this page
requireRole(['admin', 'officer']);

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

// Get active elections
$activeElections = getElections($pdo, true);

// Get committees (limited to 5)
try {
    $stmt = $pdo->query('
        SELECT c.*, COUNT(mc.id) as member_count 
        FROM committees c 
        LEFT JOIN member_committees mc ON c.id = mc.committee_id AND mc.is_active = 1 
        GROUP BY c.id 
        ORDER BY member_count DESC 
        LIMIT 5
    ');
    $committees = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get Committees Error: ' . $e->getMessage());
    $committees = [];
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Officer Dashboard</h1>
    <span class="badge bg-primary">Officer Access</span>
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
            <div class="stat-number"><?php echo $demographicData['in_up']; ?></div>
            <div class="stat-title">In UP</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($activeElections); ?></div>
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
                <a href="reports.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                                        <td><?php echo htmlspecialchars($member['name']); ?></td>
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
    
    <!-- Active Elections -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Active Elections</h5>
                <a href="reports.php?type=elections" class="btn btn-sm btn-outline-primary">View Results</a>
            </div>
            <div class="card-body">
                <?php if (empty($activeElections)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No active elections at this time.
                    </div>
                <?php else: ?>
                    <?php foreach ($activeElections as $election): ?>
                        <?php
                        // Calculate total eligible voters
                        $stmt = $pdo->query('SELECT COUNT(*) FROM members WHERE is_active = 1');
                        $totalEligibleVoters = $stmt->fetchColumn();
                        
                        // Calculate total votes cast
                        $stmt = $pdo->prepare('
                            SELECT COUNT(DISTINCT voter_id) 
                            FROM votes 
                            WHERE election_id = ?
                        ');
                        $stmt->execute([$election['id']]);
                        $totalVoters = $stmt->fetchColumn();
                        
                        // Calculate voter turnout
                        $voterTurnout = ($totalEligibleVoters > 0) ? ($totalVoters / $totalEligibleVoters) * 100 : 0;
                        ?>
                        <div class="mb-3">
                            <h6><?php echo htmlspecialchars($election['title']); ?></h6>
                            <p class="small text-muted">
                                <?php echo formatDate($election['start_date'], true); ?> to 
                                <?php echo formatDate($election['end_date'], true); ?>
                            </p>
                            <div class="d-flex justify-content-between mb-1">
                                <small>Voter Turnout</small>
                                <small><?php echo round($voterTurnout, 1); ?>%</small>
                            </div>
                            <div class="progress mb-3" style="height: 10px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $voterTurnout; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
    
    <!-- Top Committees -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Top Committees</h5>
                <a href="committees.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($committees)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No committees found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Committee</th>
                                    <th>Members</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($committees as $committee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($committee['name']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $committee['member_count']; ?></span>
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
                        <a href="committees.php" class="btn btn-outline-primary w-100 p-3">
                            <i class="fas fa-users-cog mb-2 d-block fs-3"></i>
                            Manage Committees
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="reports.php" class="btn btn-outline-primary w-100 p-3">
                            <i class="fas fa-chart-bar mb-2 d-block fs-3"></i>
                            View Reports
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="reports.php?type=units" class="btn btn-outline-primary w-100 p-3">
                            <i class="fas fa-building mb-2 d-block fs-3"></i>
                            Unit Distribution
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../member/vote.php" class="btn btn-outline-primary w-100 p-3">
                            <i class="fas fa-vote-yea mb-2 d-block fs-3"></i>
                            Elections
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
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>
