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
if (!$member) {
    $_SESSION['error_message'] = "Please complete your member profile first.";
    header('Location: profile.php');
    exit;
}

// Get election ID from URL
$electionId = $_GET['election_id'] ?? null;

// Get position ID from URL
$positionId = $_GET['position_id'] ?? null;

// Process vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        $electionId = $_POST['election_id'] ?? null;
        $positionId = $_POST['position_id'] ?? null;
        $candidateId = $_POST['candidate_id'] ?? null;
        
        // Validate inputs
        if (!$electionId || !$positionId || !$candidateId) {
            throw new Exception("Missing required voting information");
        }
        
        // Check if election is active and within voting period
        $stmt = $pdo->prepare('
            SELECT * FROM elections 
            WHERE id = ? AND is_active = 1 
            AND start_date <= NOW() AND end_date >= NOW()
        ');
        $stmt->execute([$electionId]);
        $election = $stmt->fetch();
        
        if (!$election) {
            throw new Exception("This election is not active or the voting period has ended");
        }
        
        // Check if already voted for this position
        if (hasVoted($pdo, $electionId, $positionId, $member['id'])) {
            throw new Exception("You have already voted for this position");
        }
        
        // Verify candidate is valid for this position
        $stmt = $pdo->prepare('
            SELECT * FROM candidates 
            WHERE id = ? AND position_id = ? AND election_id = ? AND is_approved = 1
        ');
        $stmt->execute([$candidateId, $positionId, $electionId]);
        $candidate = $stmt->fetch();
        
        if (!$candidate) {
            throw new Exception("Invalid candidate selection");
        }
        
        // Cast vote
        $result = castVote($pdo, $electionId, $positionId, $candidateId, $member['id']);
        
        if (!$result) {
            throw new Exception("Failed to cast vote. Please try again.");
        }
        
        // Log activity
        logActivity($pdo, $userId, "Cast vote", "Voted in election ID: $electionId for position ID: $positionId");
        
        $_SESSION['success_message'] = "Your vote has been cast successfully";
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect to next position or to the election page if done
        $stmt = $pdo->prepare('
            SELECT MIN(p.id) as next_position 
            FROM positions p 
            LEFT JOIN votes v ON p.id = v.position_id AND v.voter_id = ? 
            WHERE p.election_id = ? AND v.id IS NULL
        ');
        $stmt->execute([$member['id'], $electionId]);
        $nextPosition = $stmt->fetchColumn();
        
        if ($nextPosition) {
            header("Location: vote.php?election_id=$electionId&position_id=$nextPosition");
        } else {
            header("Location: vote.php?election_id=$electionId&complete=1");
        }
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        error_log('Voting Error: ' . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        
        header("Location: vote.php?election_id=$electionId&position_id=$positionId");
        exit;
    }
}

// Check if voting is complete for this election
$votingComplete = isset($_GET['complete']) && $_GET['complete'] == 1;

// Get active elections that the user can vote in
$activeElections = [];
if (!$electionId) {
    try {
        $stmt = $pdo->query('
            SELECT * FROM elections 
            WHERE is_active = 1 
            AND start_date <= NOW() AND end_date >= NOW()
            ORDER BY start_date ASC
        ');
        $activeElections = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Active Elections Error: ' . $e->getMessage());
        $activeElections = [];
    }
}

// Get election details if election ID is provided
$election = null;
$positions = [];
$candidates = [];
$votingProgress = [];

if ($electionId) {
    // Get election details
    $election = getElectionById($pdo, $electionId);
    
    if (!$election) {
        $_SESSION['error_message'] = "Election not found";
        header('Location: vote.php');
        exit;
    }
    
    // Check if election is active and within voting period
    $now = new DateTime();
    $startDate = new DateTime($election['start_date']);
    $endDate = new DateTime($election['end_date']);
    
    if (!$election['is_active'] || $now < $startDate || $now > $endDate) {
        $_SESSION['error_message'] = "This election is not active or the voting period has ended";
        header('Location: vote.php');
        exit;
    }
    
    // Get all positions for this election
    $positions = getElectionPositions($pdo, $electionId);
    
    // Get voting progress for this member
    foreach ($positions as $position) {
        $hasVoted = hasVoted($pdo, $electionId, $position['id'], $member['id']);
        $votingProgress[] = [
            'position_id' => $position['id'],
            'position_title' => $position['title'],
            'voted' => $hasVoted
        ];
    }
    
    // If position ID is provided, get candidates for that position
    if ($positionId) {
        // Get position details
        $position = null;
        foreach ($positions as $pos) {
            if ($pos['id'] == $positionId) {
                $position = $pos;
                break;
            }
        }
        
        if (!$position) {
            $_SESSION['error_message'] = "Position not found";
            header("Location: vote.php?election_id=$electionId");
            exit;
        }
        
        // Check if already voted for this position
        if (hasVoted($pdo, $electionId, $positionId, $member['id'])) {
            $_SESSION['error_message'] = "You have already voted for this position";
            
            // Find the next unvoted position
            $nextUnvotedPosition = null;
            foreach ($votingProgress as $progress) {
                if (!$progress['voted']) {
                    $nextUnvotedPosition = $progress['position_id'];
                    break;
                }
            }
            
            if ($nextUnvotedPosition) {
                header("Location: vote.php?election_id=$electionId&position_id=$nextUnvotedPosition");
            } else {
                header("Location: vote.php?election_id=$electionId&complete=1");
            }
            exit;
        }
        
        // Get candidates for this position
        $candidates = getPositionCandidates($pdo, $positionId);
    } elseif (!$votingComplete) {
        // If no position selected and voting not complete, find the first unvoted position
        $firstUnvotedPosition = null;
        foreach ($votingProgress as $progress) {
            if (!$progress['voted']) {
                $firstUnvotedPosition = $progress['position_id'];
                break;
            }
        }
        
        if ($firstUnvotedPosition) {
            header("Location: vote.php?election_id=$electionId&position_id=$firstUnvotedPosition");
            exit;
        } else {
            // If all positions voted, mark as complete
            header("Location: vote.php?election_id=$electionId&complete=1");
            exit;
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">
        <?php if ($election): ?>
            Election: <?php echo htmlspecialchars($election['title']); ?>
        <?php else: ?>
            Available Elections
        <?php endif; ?>
    </h1>
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
</div>

<?php if (empty($activeElections) && !$election): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> There are no active elections at this time.
    </div>
<?php elseif (!$election): ?>
    <!-- List of active elections -->
    <div class="card shadow-sm">
        <div class="card-header card-header-primary">
            <h5 class="card-title mb-0">Active Elections</h5>
        </div>
        <div class="card-body">
            <div class="list-group">
                <?php foreach ($activeElections as $activeElection): ?>
                    <a href="?election_id=<?php echo $activeElection['id']; ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-1"><?php echo htmlspecialchars($activeElection['title']); ?></h5>
                            <span class="badge bg-primary">Vote Now</span>
                        </div>
                        <p class="mb-1"><?php echo htmlspecialchars($activeElection['description'] ?? ''); ?></p>
                        <small class="text-muted">
                            Voting Period: <?php echo formatDate($activeElection['start_date'], true); ?> to 
                            <?php echo formatDate($activeElection['end_date'], true); ?>
                        </small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php elseif ($votingComplete): ?>
    <!-- Voting complete view -->
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div class="mb-4">
                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
            </div>
            <h3>Thank You for Voting!</h3>
            <p class="mb-4">Your vote has been recorded successfully for all positions in this election.</p>
            
            <div class="mb-4">
                <h5>Your Voting Summary</h5>
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%">
                        100% Complete
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($votingProgress as $progress): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($progress['position_title']); ?></td>
                                    <td>
                                        <span class="badge bg-success">Voted</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div>
                <a href="dashboard.php" class="btn btn-primary me-2">
                    <i class="fas fa-home me-2"></i> Return to Dashboard
                </a>
                <a href="vote.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list me-2"></i> View Other Elections
                </a>
            </div>
        </div>
    </div>
<?php elseif ($election && $positionId && !empty($candidates)): ?>
    <!-- Voting for a specific position -->
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-primary">
                    <h5 class="card-title mb-0">Voting Progress</h5>
                </div>
                <div class="card-body">
                    <!-- Calculate progress percentage -->
                    <?php
                    $totalPositions = count($votingProgress);
                    $votedPositions = 0;
                    foreach ($votingProgress as $progress) {
                        if ($progress['voted']) {
                            $votedPositions++;
                        }
                    }
                    $progressPercentage = ($totalPositions > 0) ? round(($votedPositions / $totalPositions) * 100) : 0;
                    ?>
                    
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar bg-primary" role="progressbar" 
                             style="width: <?php echo $progressPercentage; ?>%" 
                             aria-valuenow="<?php echo $progressPercentage; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?php echo $progressPercentage; ?>%
                        </div>
                    </div>
                    
                    <div class="list-group">
                        <?php foreach ($votingProgress as $progress): ?>
                            <a href="?election_id=<?php echo $electionId; ?>&position_id=<?php echo $progress['position_id']; ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center 
                               <?php echo ($progress['position_id'] == $positionId) ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($progress['position_title']); ?>
                                <?php if ($progress['voted']): ?>
                                    <span class="badge bg-success rounded-pill">Voted</span>
                                <?php elseif ($progress['position_id'] == $positionId): ?>
                                    <span class="badge bg-light text-dark rounded-pill">Voting Now</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill">Pending</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <p class="small text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            You must vote for all positions to complete the election.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-primary">
                    <?php 
                    // Find the current position details
                    $currentPosition = null;
                    foreach ($positions as $pos) {
                        if ($pos['id'] == $positionId) {
                            $currentPosition = $pos;
                            break;
                        }
                    }
                    ?>
                    <h5 class="card-title mb-0">Vote for: <?php echo htmlspecialchars($currentPosition['title']); ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($currentPosition['description']): ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php echo nl2br(htmlspecialchars($currentPosition['description'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($candidates)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> There are no approved candidates for this position.
                        </div>
                    <?php else: ?>
                        <form action="vote.php" method="post" id="voting-form">
                            <input type="hidden" name="election_id" value="<?php echo $electionId; ?>">
                            <input type="hidden" name="position_id" value="<?php echo $positionId; ?>">
                            
                            <div class="mb-4">
                                <p>Please select one candidate for this position:</p>
                                <div class="row">
                                    <?php foreach ($candidates as $candidate): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="candidate-card">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="candidate_id" 
                                                           id="candidate<?php echo $candidate['id']; ?>" 
                                                           value="<?php echo $candidate['id']; ?>" required>
                                                    <label class="form-check-label" for="candidate<?php echo $candidate['id']; ?>">
                                                        <div class="d-flex">
                                                            <?php if ($candidate['photo_path']): ?>
                                                                <img src="<?php echo htmlspecialchars($candidate['photo_path']); ?>" 
                                                                     alt="<?php echo htmlspecialchars($candidate['name']); ?>" 
                                                                     class="candidate-photo">
                                                            <?php else: ?>
                                                                <div class="candidate-photo bg-light d-flex align-items-center justify-content-center">
                                                                    <i class="fas fa-user fa-2x text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <div>
                                                                <h5 class="mb-1"><?php echo htmlspecialchars($candidate['name']); ?></h5>
                                                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($candidate['unit_college']); ?></p>
                                                                <p class="mb-1 small"><?php echo htmlspecialchars($candidate['designation']); ?></p>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                                
                                                <?php if ($candidate['platform']): ?>
                                                    <div class="mt-2 platform-preview">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" 
                                                                data-bs-toggle="modal" data-bs-target="#platformModal<?php echo $candidate['id']; ?>">
                                                            View Platform
                                                        </button>
                                                        
                                                        <!-- Platform Modal -->
                                                        <div class="modal fade" id="platformModal<?php echo $candidate['id']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Platform Statement</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <h6><?php echo htmlspecialchars($candidate['name']); ?></h6>
                                                                        <p class="text-muted small">Candidate for <?php echo htmlspecialchars($currentPosition['title']); ?></p>
                                                                        <hr>
                                                                        <div class="platform-text">
                                                                            <?php echo nl2br(htmlspecialchars($candidate['platform'])); ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i> 
                                <strong>Important:</strong> Your vote is final and cannot be changed once submitted.
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="vote" class="btn btn-primary" onclick="return confirm('Are you sure you want to cast your vote? This action cannot be undone.')">
                                    <i class="fas fa-vote-yea me-2"></i> Cast Your Vote
                                </button>
                            </div>
                        </form>
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
