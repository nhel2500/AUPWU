<?php
// Include database configuration
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin role to access this page
requireRole('admin');

// Get action from URL
$action = $_GET['action'] ?? 'list';
$electionId = $_GET['id'] ?? null;
$positionId = $_GET['position_id'] ?? null;
$candidateId = $_GET['candidate_id'] ?? null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        if ($action === 'create' || $action === 'edit') {
            // Get form data
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Simple validation
            if (empty($title) || empty($startDate) || empty($endDate)) {
                throw new Exception("Title, start date, and end date are required");
            }
            
            if ($action === 'create') {
                // Create new election
                $stmt = $pdo->prepare('
                    INSERT INTO elections (title, description, start_date, end_date, is_active)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([$title, $description, $startDate, $endDate, $isActive]);
                
                $electionId = $pdo->lastInsertId();
                
                // Log the activity
                logActivity($pdo, getCurrentUserId(), "Created election", "Created election: $title");
                
                $_SESSION['success_message'] = "Election created successfully";
            } else {
                // Update existing election
                $stmt = $pdo->prepare('
                    UPDATE elections 
                    SET title = ?, description = ?, start_date = ?, end_date = ?, is_active = ?
                    WHERE id = ?
                ');
                $stmt->execute([$title, $description, $startDate, $endDate, $isActive, $electionId]);
                
                // Log the activity
                logActivity($pdo, getCurrentUserId(), "Updated election", "Updated election (ID: $electionId)");
                
                $_SESSION['success_message'] = "Election updated successfully";
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to add positions if this is a new election
            if ($action === 'create') {
                header("Location: ?action=positions&id=$electionId");
                exit;
            } else {
                header('Location: ?action=list');
                exit;
            }
        } elseif ($action === 'positions' && $electionId) {
            // Add or update positions for an election
            $positionTitles = $_POST['position_titles'] ?? [];
            $positionDescriptions = $_POST['position_descriptions'] ?? [];
            $positionMaxWinners = $_POST['position_max_winners'] ?? [];
            $positionIds = $_POST['position_ids'] ?? [];
            
            // Get existing positions
            $stmt = $pdo->prepare('SELECT id FROM positions WHERE election_id = ?');
            $stmt->execute([$electionId]);
            $existingPositionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Process each position
            foreach ($positionTitles as $index => $title) {
                if (empty($title)) {
                    continue;
                }
                
                $description = $positionDescriptions[$index] ?? '';
                $maxWinners = intval($positionMaxWinners[$index] ?? 1);
                $positionId = $positionIds[$index] ?? null;
                
                if ($positionId) {
                    // Update existing position
                    $stmt = $pdo->prepare('
                        UPDATE positions 
                        SET title = ?, description = ?, max_winners = ?
                        WHERE id = ? AND election_id = ?
                    ');
                    $stmt->execute([$title, $description, $maxWinners, $positionId, $electionId]);
                    
                    // Remove from existing IDs list
                    $key = array_search($positionId, $existingPositionIds);
                    if ($key !== false) {
                        unset($existingPositionIds[$key]);
                    }
                } else {
                    // Create new position
                    $stmt = $pdo->prepare('
                        INSERT INTO positions (election_id, title, description, max_winners)
                        VALUES (?, ?, ?, ?)
                    ');
                    $stmt->execute([$electionId, $title, $description, $maxWinners]);
                }
            }
            
            // Delete positions that were removed
            if (!empty($existingPositionIds)) {
                $placeholders = implode(',', array_fill(0, count($existingPositionIds), '?'));
                $params = $existingPositionIds;
                $params[] = $electionId;
                
                $stmt = $pdo->prepare("DELETE FROM positions WHERE id IN ($placeholders) AND election_id = ?");
                $stmt->execute($params);
            }
            
            // Log the activity
            logActivity($pdo, getCurrentUserId(), "Updated election positions", "Updated positions for election (ID: $electionId)");
            
            $_SESSION['success_message'] = "Positions updated successfully";
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to view the election
            header("Location: ?action=view&id=$electionId");
            exit;
        } elseif ($action === 'approve_candidate' && $candidateId) {
            // Approve or disapprove a candidate
            $isApproved = isset($_POST['approve']) ? 1 : 0;
            
            $stmt = $pdo->prepare('UPDATE candidates SET is_approved = ? WHERE id = ?');
            $stmt->execute([$isApproved, $candidateId]);
            
            // Get candidate info for log
            $stmt = $pdo->prepare('
                SELECT c.election_id, c.position_id, m.name, p.title 
                FROM candidates c 
                JOIN members m ON c.member_id = m.id 
                JOIN positions p ON c.position_id = p.id 
                WHERE c.id = ?
            ');
            $stmt->execute([$candidateId]);
            $candidate = $stmt->fetch();
            
            // Log the activity
            $action = $isApproved ? "Approved" : "Disapproved";
            $message = "$action candidacy of {$candidate['name']} for position {$candidate['title']} in election {$candidate['election_id']}";
            logActivity($pdo, getCurrentUserId(), "$action candidate", $message);
            
            $_SESSION['success_message'] = "Candidate " . strtolower($action) . " successfully";
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect back
            header("Location: ?action=candidates&id={$candidate['election_id']}&position_id={$candidate['position_id']}");
            exit;
        } elseif ($action === 'delete' && $electionId) {
            // Check if votes exist
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE election_id = ?');
            $stmt->execute([$electionId]);
            $voteCount = $stmt->fetchColumn();
            
            if ($voteCount > 0) {
                throw new Exception("Cannot delete election with existing votes. Archive it instead.");
            }
            
            // Delete candidates
            $stmt = $pdo->prepare('DELETE FROM candidates WHERE election_id = ?');
            $stmt->execute([$electionId]);
            
            // Delete positions
            $stmt = $pdo->prepare('DELETE FROM positions WHERE election_id = ?');
            $stmt->execute([$electionId]);
            
            // Delete election
            $stmt = $pdo->prepare('DELETE FROM elections WHERE id = ?');
            $stmt->execute([$electionId]);
            
            // Log the activity
            logActivity($pdo, getCurrentUserId(), "Deleted election", "Deleted election (ID: $electionId)");
            
            $_SESSION['success_message'] = "Election deleted successfully";
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to list
            header('Location: ?action=list');
            exit;
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        error_log('Election Management Error: ' . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        
        // Redirect appropriately
        if ($action === 'create') {
            header('Location: ?action=create');
        } elseif ($action === 'edit' && $electionId) {
            header("Location: ?action=edit&id=$electionId");
        } elseif ($action === 'positions' && $electionId) {
            header("Location: ?action=positions&id=$electionId");
        } else {
            header('Location: ?action=list');
        }
        exit;
    }
}

// Get data based on action
$election = null;
$positions = [];
$candidates = [];
$votes = [];

if (in_array($action, ['edit', 'view', 'positions', 'candidates']) && $electionId) {
    // Get election data
    $election = getElectionById($pdo, $electionId);
    
    if (!$election) {
        $_SESSION['error_message'] = "Election not found";
        header('Location: ?action=list');
        exit;
    }
    
    // Get positions for this election
    $positions = getElectionPositions($pdo, $electionId);
    
    // Get candidates for a specific position
    if ($action === 'candidates' && $positionId) {
        // Get position details
        try {
            $stmt = $pdo->prepare('SELECT * FROM positions WHERE id = ? AND election_id = ?');
            $stmt->execute([$positionId, $electionId]);
            $position = $stmt->fetch();
            
            if (!$position) {
                $_SESSION['error_message'] = "Position not found";
                header("Location: ?action=view&id=$electionId");
                exit;
            }
            
            // Get all candidates for this position
            $stmt = $pdo->prepare('
                SELECT c.*, m.name, m.unit_college, m.designation, m.photo_path 
                FROM candidates c 
                JOIN members m ON c.member_id = m.id 
                WHERE c.position_id = ? 
                ORDER BY c.is_approved DESC, m.name ASC
            ');
            $stmt->execute([$positionId]);
            $candidates = $stmt->fetchAll();
            
            // Get votes for this position
            $stmt = $pdo->prepare('
                SELECT COUNT(*) as vote_count 
                FROM votes 
                WHERE position_id = ?
            ');
            $stmt->execute([$positionId]);
            $voteCount = $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Get Candidates Error: ' . $e->getMessage());
            $_SESSION['error_message'] = "An error occurred while loading candidates";
            header("Location: ?action=view&id=$electionId");
            exit;
        }
    }
}

// Get all elections for list view
$elections = [];
if ($action === 'list') {
    $elections = getElections($pdo);
}

// Include header
include '../includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manage Elections</h1>
        <a href="?action=create" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Create New Election
        </a>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($elections)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No elections found. Create your first election to get started.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Positions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($elections as $e): ?>
                                <?php
                                // Determine status
                                $now = new DateTime();
                                $start = new DateTime($e['start_date']);
                                $end = new DateTime($e['end_date']);
                                
                                $status = 'Inactive';
                                $badgeClass = 'bg-secondary';
                                
                                if ($e['is_active']) {
                                    if ($now < $start) {
                                        $status = 'Upcoming';
                                        $badgeClass = 'bg-info';
                                    } elseif ($now > $end) {
                                        $status = 'Completed';
                                        $badgeClass = 'bg-success';
                                    } else {
                                        $status = 'In Progress';
                                        $badgeClass = 'bg-primary';
                                    }
                                }
                                
                                // Get position count
                                $stmt = $pdo->prepare('SELECT COUNT(*) FROM positions WHERE election_id = ?');
                                $stmt->execute([$e['id']]);
                                $positionCount = $stmt->fetchColumn();
                                ?>
                                <tr>
                                    <td><?php echo $e['id']; ?></td>
                                    <td>
                                        <a href="?action=view&id=<?php echo $e['id']; ?>">
                                            <?php echo htmlspecialchars($e['title']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php echo formatDate($e['start_date']); ?> to <?php echo formatDate($e['end_date']); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                                    </td>
                                    <td><?php echo $positionCount; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=view&id=<?php echo $e['id']; ?>" class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?action=edit&id=<?php echo $e['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=positions&id=<?php echo $e['id']; ?>" class="btn btn-outline-primary" title="Manage Positions">
                                                <i class="fas fa-tasks"></i>
                                            </a>
                                            <a href="#" class="btn btn-outline-danger" title="Delete" 
                                               onclick="if(confirmDelete('Are you sure you want to delete this election? This will remove all positions and candidates.')) 
                                                         window.location='?action=delete&id=<?php echo $e['id']; ?>'">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'create' || ($action === 'edit' && $election)): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?php echo $action === 'create' ? 'Create New Election' : 'Edit Election'; ?></h1>
        <a href="?action=list" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to List
        </a>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header card-header-primary">
            <h5 class="card-title mb-0">Election Details</h5>
        </div>
        <div class="card-body">
            <form action="?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id=' . $election['id'] : ''; ?>" method="post" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="title" class="form-label">Election Title</label>
                    <input type="text" class="form-control" id="title" name="title" 
                           value="<?php echo $election ? htmlspecialchars($election['title']) : ''; ?>" required>
                    <div class="invalid-feedback">Please enter an election title</div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo $election ? htmlspecialchars($election['description'] ?? '') : ''; ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $election ? date('Y-m-d\TH:i', strtotime($election['start_date'])) : ''; ?>" required>
                        <div class="invalid-feedback">Please specify a start date</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $election ? date('Y-m-d\TH:i', strtotime($election['end_date'])) : ''; ?>" required>
                        <div class="invalid-feedback">Please specify an end date</div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                               <?php echo (!$election || $election['is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Active Election</label>
                    </div>
                    <div class="form-text">Inactive elections will not be visible to members for voting.</div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> 
                    <?php if ($action === 'create'): ?>
                        After creating the election, you'll be able to add positions that members can run for.
                    <?php else: ?>
                        You can manage positions for this election from the election details page.
                    <?php endif; ?>
                </div>
                
                <div class="text-end">
                    <a href="?action=list" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $action === 'create' ? 'Create Election' : 'Update Election'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($action === 'view' && $election): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Election: <?php echo htmlspecialchars($election['title']); ?></h1>
        <div>
            <a href="?action=edit&id=<?php echo $election['id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Edit Election
            </a>
            <a href="?action=positions&id=<?php echo $election['id']; ?>" class="btn btn-primary ms-2">
                <i class="fas fa-tasks me-2"></i> Manage Positions
            </a>
            <a href="?action=list" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left me-2"></i> Back to List
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-primary">
                    <h5 class="card-title mb-0">Election Details</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Calculate status
                    $now = new DateTime();
                    $start = new DateTime($election['start_date']);
                    $end = new DateTime($election['end_date']);
                    
                    if ($election['is_active']) {
                        if ($now < $start) {
                            $status = 'Upcoming';
                            $badgeClass = 'bg-info';
                        } elseif ($now > $end) {
                            $status = 'Completed';
                            $badgeClass = 'bg-success';
                        } else {
                            $status = 'In Progress';
                            $badgeClass = 'bg-primary';
                        }
                    } else {
                        $status = 'Inactive';
                        $badgeClass = 'bg-secondary';
                    }
                    ?>
                    
                    <div class="mb-3">
                        <h6>Status</h6>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Duration</h6>
                        <p>
                            <?php echo formatDate($election['start_date'], true); ?> to 
                            <?php echo formatDate($election['end_date'], true); ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Description</h6>
                        <p><?php echo nl2br(htmlspecialchars($election['description'] ?? 'No description provided')); ?></p>
                    </div>
                    
                    <div>
                        <h6>Created On</h6>
                        <p><?php echo formatDate($election['created_at']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Vote Statistics -->
            <div class="card shadow-sm mt-4">
                <div class="card-header card-header-primary">
                    <h5 class="card-title mb-0">Voting Statistics</h5>
                </div>
                <div class="card-body">
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
                        <h6>Eligible Voters</h6>
                        <p><?php echo $totalEligibleVoters; ?> members</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Votes Cast</h6>
                        <p><?php echo $totalVoters; ?> members have voted</p>
                    </div>
                    
                    <div>
                        <h6>Voter Turnout</h6>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $voterTurnout; ?>%">
                                <?php echo round($voterTurnout, 1); ?>%
                            </div>
                        </div>
                        <p class="small text-muted"><?php echo $totalVoters; ?> out of <?php echo $totalEligibleVoters; ?> eligible voters</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Positions</h5>
                    <a href="?action=positions&id=<?php echo $election['id']; ?>" class="btn btn-sm btn-outline-primary">
                        Manage Positions
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($positions)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No positions have been added to this election yet.
                            <a href="?action=positions&id=<?php echo $election['id']; ?>" class="alert-link">Add positions now</a>.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($positions as $pos): ?>
                                <?php
                                // Get candidate count
                                $stmt = $pdo->prepare('
                                    SELECT COUNT(*) FROM candidates 
                                    WHERE position_id = ? AND is_approved = 1
                                ');
                                $stmt->execute([$pos['id']]);
                                $candidateCount = $stmt->fetchColumn();
                                
                                // Get vote count
                                $stmt = $pdo->prepare('
                                    SELECT COUNT(*) FROM votes 
                                    WHERE position_id = ?
                                ');
                                $stmt->execute([$pos['id']]);
                                $voteCount = $stmt->fetchColumn();
                                ?>
                                <a href="?action=candidates&id=<?php echo $election['id']; ?>&position_id=<?php echo $pos['id']; ?>" 
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($pos['title']); ?></h5>
                                        <p class="mb-1 text-muted small"><?php echo htmlspecialchars($pos['description'] ?? 'No description'); ?></p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary rounded-pill"><?php echo $candidateCount; ?> candidates</span>
                                        <span class="badge bg-secondary rounded-pill"><?php echo $voteCount; ?> votes</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Election Results (if election is completed) -->
            <?php if ($status === 'Completed'): ?>
                <div class="card shadow-sm mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Election Results</h5>
                        <a href="../admin/reports.php?type=elections&election_id=<?php echo $election['id']; ?>" 
                           class="btn btn-sm btn-outline-primary">View Detailed Results</a>
                    </div>
                    <div class="card-body">
                        <?php
                        $hasResults = false;
                        foreach ($positions as $pos):
                            // Get vote counts for this position
                            $voteCounts = getVoteCounts($pdo, $election['id'], $pos['id']);
                            if (empty($voteCounts)) continue;
                            $hasResults = true;
                        ?>
                            <div class="mb-4">
                                <h6><?php echo htmlspecialchars($pos['title']); ?></h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Candidate</th>
                                                <th>Votes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($voteCounts as $index => $candidate): ?>
                                                <tr <?php echo ($index < $pos['max_winners']) ? 'class="table-success"' : ''; ?>>
                                                    <td><?php echo htmlspecialchars($candidate['name']); ?></td>
                                                    <td><?php echo $candidate['vote_count']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (!$hasResults): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> No votes have been cast in this election.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'positions' && $election): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manage Positions: <?php echo htmlspecialchars($election['title']); ?></h1>
        <a href="?action=view&id=<?php echo $election['id']; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Election
        </a>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header card-header-primary">
            <h5 class="card-title mb-0">Positions</h5>
        </div>
        <div class="card-body">
            <form action="?action=positions&id=<?php echo $election['id']; ?>" method="post" class="needs-validation" novalidate>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i> 
                    Add positions that members can run for in this election. Each position can have multiple candidates.
                </div>
                
                <div id="positions-container">
                    <?php if (empty($positions)): ?>
                        <div class="position-row card mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Position Title</label>
                                        <input type="text" class="form-control" name="position_titles[]" required>
                                        <div class="invalid-feedback">Please enter a position title</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Maximum Winners</label>
                                        <input type="number" class="form-control" name="position_max_winners[]" min="1" value="1" required>
                                        <div class="invalid-feedback">Please enter a valid number</div>
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-danger remove-position">
                                            <i class="fas fa-times me-2"></i> Remove
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Description (Optional)</label>
                                        <textarea class="form-control" name="position_descriptions[]" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($positions as $pos): ?>
                            <div class="position-row card mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Position Title</label>
                                            <input type="text" class="form-control" name="position_titles[]" 
                                                   value="<?php echo htmlspecialchars($pos['title']); ?>" required>
                                            <input type="hidden" name="position_ids[]" value="<?php echo $pos['id']; ?>">
                                            <div class="invalid-feedback">Please enter a position title</div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Maximum Winners</label>
                                            <input type="number" class="form-control" name="position_max_winners[]" 
                                                   min="1" value="<?php echo $pos['max_winners']; ?>" required>
                                            <div class="invalid-feedback">Please enter a valid number</div>
                                        </div>
                                        <div class="col-md-3 mb-3 d-flex align-items-end">
                                            <button type="button" class="btn btn-danger remove-position">
                                                <i class="fas fa-times me-2"></i> Remove
                                            </button>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description (Optional)</label>
                                            <textarea class="form-control" name="position_descriptions[]" rows="2"><?php echo htmlspecialchars($pos['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <button type="button" id="add-position" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-2"></i> Add Position
                    </button>
                </div>
                
                <div class="text-end">
                    <a href="?action=view&id=<?php echo $election['id']; ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Positions</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add new position field
        const positionsContainer = document.getElementById('positions-container');
        const addPositionBtn = document.getElementById('add-position');
        
        addPositionBtn.addEventListener('click', function() {
            const positionRow = document.createElement('div');
            positionRow.className = 'position-row card mb-3';
            
            positionRow.innerHTML = `
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Position Title</label>
                            <input type="text" class="form-control" name="position_titles[]" required>
                            <div class="invalid-feedback">Please enter a position title</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Maximum Winners</label>
                            <input type="number" class="form-control" name="position_max_winners[]" min="1" value="1" required>
                            <div class="invalid-feedback">Please enter a valid number</div>
                        </div>
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="button" class="btn btn-danger remove-position">
                                <i class="fas fa-times me-2"></i> Remove
                            </button>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description (Optional)</label>
                            <textarea class="form-control" name="position_descriptions[]" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            positionsContainer.appendChild(positionRow);
            
            // Add event listener to the new remove button
            const removeBtn = positionRow.querySelector('.remove-position');
            removeBtn.addEventListener('click', function() {
                positionRow.remove();
            });
        });
        
        // Remove position
        document.querySelectorAll('.remove-position').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.position-row').remove();
            });
        });
    });
    </script>

<?php elseif ($action === 'candidates' && $election && isset($position)): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Candidates: <?php echo htmlspecialchars($position['title']); ?></h1>
        <a href="?action=view&id=<?php echo $election['id']; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Election
        </a>
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header card-header-primary">
            <h5 class="card-title mb-0">Position Details</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h5><?php echo htmlspecialchars($position['title']); ?></h5>
                    <p><?php echo nl2br(htmlspecialchars($position['description'] ?? 'No description provided')); ?></p>
                    <p><strong>Maximum Winners:</strong> <?php echo $position['max_winners']; ?></p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge bg-secondary">
                        <i class="fas fa-users me-1"></i> <?php echo count($candidates); ?> Candidates
                    </span>
                    <span class="badge bg-primary ms-2">
                        <i class="fas fa-vote-yea me-1"></i> <?php echo $voteCount ?? 0; ?> Votes Cast
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Candidate List</h5>
            <a href="../member/vote.php?election_id=<?php echo $election['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                <i class="fas fa-external-link-alt me-2"></i> View Voting Page
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($candidates)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No candidates have applied for this position yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Unit/College</th>
                                <th>Platform</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $candidate): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($candidate['photo_path']): ?>
                                                <img src="<?php echo htmlspecialchars($candidate['photo_path']); ?>" alt="Candidate" 
                                                     class="rounded-circle me-2" width="40" height="40">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($candidate['name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($candidate['designation']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($candidate['unit_college']); ?></td>
                                    <td>
                                        <?php if ($candidate['platform']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    data-bs-toggle="modal" data-bs-target="#platformModal<?php echo $candidate['id']; ?>">
                                                <i class="fas fa-file-alt me-1"></i> View Platform
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
                                                            <p class="text-muted small">Candidate for <?php echo htmlspecialchars($position['title']); ?></p>
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
                                        <?php else: ?>
                                            <span class="text-muted">No platform provided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($candidate['is_approved']): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending Approval</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form action="?action=approve_candidate&id=<?php echo $election['id']; ?>&position_id=<?php echo $position['id']; ?>&candidate_id=<?php echo $candidate['id']; ?>" method="post" class="d-inline">
                                            <?php if ($candidate['is_approved']): ?>
                                                <button type="submit" name="approve" value="0" class="btn btn-sm btn-outline-warning" 
                                                        onclick="return confirm('Are you sure you want to revoke approval for this candidate?')">
                                                    <i class="fas fa-times me-1"></i> Revoke Approval
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="approve" value="1" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check me-1"></i> Approve
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                        
                                        <a href="../admin/members.php?action=view&id=<?php echo $candidate['member_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary ms-1">
                                            <i class="fas fa-user me-1"></i> View Profile
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
// Include footer
include '../includes/footer.php';
?>
