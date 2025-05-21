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

// If member profile doesn't exist, redirect to profile creation
if (!$member && basename($_SERVER['PHP_SELF']) !== 'profile.php') {
    $_SESSION['error_message'] = "Please complete your member profile first.";
    header('Location: profile.php');
    exit;
}

// Get committee memberships
$memberCommittees = [];
if ($member) {
    $memberCommittees = getMemberCommittees($pdo, $member['id']);
}

// Get active elections
$activeElections = getElections($pdo, true);

// Count elections where the member has not voted
$pendingVotes = 0;
foreach ($activeElections as $election) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) 
        FROM positions p 
        LEFT JOIN votes v ON p.id = v.position_id AND v.voter_id = ? 
        WHERE p.election_id = ? AND v.id IS NULL
    ');
    $stmt->execute([$member['id'] ?? 0, $election['id']]);
    $pendingPositions = $stmt->fetchColumn();
    
    if ($pendingPositions > 0) {
        $pendingVotes++;
    }
}

// Get upcoming elections (active but haven't started yet)
$upcomingElections = [];
$now = new DateTime();
foreach (getElections($pdo) as $election) {
    $startDate = new DateTime($election['start_date']);
    $endDate = new DateTime($election['end_date']);
    
    if ($election['is_active'] && $startDate > $now) {
        $upcomingElections[] = $election;
    }
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">My Dashboard</h1>
    <?php if ($member && !empty($activeElections)): ?>
        <a href="vote.php" class="btn btn-primary">
            <i class="fas fa-vote-yea me-2"></i> Vote Now
        </a>
    <?php endif; ?>
</div>

<?php if (!$member): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i> Welcome to AUPWU Management System! Please <a href="profile.php" class="alert-link">complete your profile</a> to gain full access to member features.
    </div>
<?php else: ?>
    <!-- Member Info Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 text-center mb-3 mb-md-0">
                    <?php if ($member['photo_path']): ?>
                        <img src="<?php echo htmlspecialchars($member['photo_path']); ?>" alt="Profile Photo" class="profile-image">
                    <?php else: ?>
                        <div class="profile-image bg-light d-flex align-items-center justify-content-center">
                            <i class="fas fa-user fa-3x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="mb-1"><?php echo htmlspecialchars($member['name']); ?></h4>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($member['designation']); ?></p>
                            
                            <div class="mb-2">
                                <span class="badge <?php echo $member['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $member['is_active'] ? 'Active Member' : 'Inactive Member'; ?>
                                </span>
                                <span class="badge <?php echo $member['up_status'] === 'in' ? 'bg-info' : 'bg-warning'; ?>">
                                    <?php echo $member['up_status'] === 'in' ? 'In UP' : 'Out of UP'; ?>
                                </span>
                            </div>
                            
                            <div>
                                <i class="fas fa-building me-2 text-muted"></i> <?php echo htmlspecialchars($member['unit_college']); ?>
                            </div>
                            <div>
                                <i class="fas fa-users me-2 text-muted"></i> <?php echo htmlspecialchars($member['chapter']); ?> Chapter
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <i class="fas fa-envelope me-2 text-muted"></i> 
                                <a href="mailto:<?php echo $member['email']; ?>"><?php echo htmlspecialchars($member['email']); ?></a>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-phone me-2 text-muted"></i> <?php echo htmlspecialchars($member['contact_number']); ?>
                            </div>
                            <div class="mb-2">
                                <i class="fas fa-calendar me-2 text-muted"></i> Member since <?php echo formatDate($member['created_at']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="profile.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-2"></i> Edit Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Committee Memberships -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">My Committees</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($memberCommittees)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> You are not assigned to any committees.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($memberCommittees as $committee): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($committee['committee_name']); ?></h6>
                                        <?php if ($committee['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($committee['position'])): ?>
                                        <p class="mb-1 text-muted">Position: <?php echo htmlspecialchars($committee['position']); ?></p>
                                    <?php endif; ?>
                                    <p class="mb-1 small">
                                        <?php echo formatDate($committee['start_date']); ?> - 
                                        <?php echo $committee['end_date'] ? formatDate($committee['end_date']) : 'Present'; ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Elections -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Elections</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activeElections) && empty($upcomingElections)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> There are no active or upcoming elections at this time.
                        </div>
                    <?php else: ?>
                        <?php if (!empty($activeElections)): ?>
                            <h6 class="card-subtitle mb-3">Active Elections</h6>
                            <div class="list-group mb-4">
                                <?php foreach ($activeElections as $election): ?>
                                    <?php
                                    // Check if member has voted for all positions in this election
                                    $stmt = $pdo->prepare('
                                        SELECT COUNT(*) 
                                        FROM positions p 
                                        LEFT JOIN votes v ON p.id = v.position_id AND v.voter_id = ? 
                                        WHERE p.election_id = ? AND v.id IS NULL
                                    ');
                                    $stmt->execute([$member['id'], $election['id']]);
                                    $pendingPositions = $stmt->fetchColumn();
                                    
                                    $status = ($pendingPositions > 0) ? 'Voting Open' : 'Voted';
                                    $badgeClass = ($pendingPositions > 0) ? 'bg-primary' : 'bg-success';
                                    ?>
                                    <a href="vote.php?election_id=<?php echo $election['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($election['title']); ?></h6>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                                        </div>
                                        <p class="mb-1 small text-muted">
                                            <?php echo formatDate($election['start_date'], true); ?> to 
                                            <?php echo formatDate($election['end_date'], true); ?>
                                        </p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($upcomingElections)): ?>
                            <h6 class="card-subtitle mb-3">Upcoming Elections</h6>
                            <div class="list-group">
                                <?php foreach ($upcomingElections as $election): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($election['title']); ?></h6>
                                            <span class="badge bg-info">Upcoming</span>
                                        </div>
                                        <p class="mb-1 small text-muted">
                                            <?php echo formatDate($election['start_date'], true); ?> to 
                                            <?php echo formatDate($election['end_date'], true); ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Activity -->
        <div class="col-md-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">My Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get recent activity for this user
                    try {
                        $stmt = $pdo->prepare('
                            SELECT * FROM activity_logs 
                            WHERE user_id = ? 
                            ORDER BY timestamp DESC 
                            LIMIT 10
                        ');
                        $stmt->execute([$userId]);
                        $activities = $stmt->fetchAll();
                    } catch (PDOException $e) {
                        error_log('Get Activity Error: ' . $e->getMessage());
                        $activities = [];
                    }
                    ?>
                    
                    <?php if (empty($activities)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No recent activity to display.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Details</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $activity): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['details'] ?? ''); ?></td>
                                            <td><?php echo formatDate($activity['timestamp'], true); ?></td>
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
<?php endif; ?>

<?php
// Include footer
include '../includes/footer.php';
?>
