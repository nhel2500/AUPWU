<?php
// Include database configuration
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require admin role to access this page
requireRole('admin');

// Get report type
$reportType = $_GET['type'] ?? 'demographics';

// Process date range filters
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Initialize data containers
$demographicData = [];
$unitDistribution = [];
$membershipHistory = [];
$electionResults = [];

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get demographic data
    if ($reportType === 'demographics' || $reportType === 'all') {
        $demographicData = getDemographicData($pdo);
    }
    
    // Get unit distribution
    if ($reportType === 'units' || $reportType === 'all') {
        $sql = 'SELECT unit_college, COUNT(*) as count FROM members GROUP BY unit_college ORDER BY count DESC';
        $stmt = $pdo->query($sql);
        $unitDistribution = $stmt->fetchAll();
    }
    
    // Get membership history (join dates)
    if ($reportType === 'history' || $reportType === 'all') {
        $dateFilter = '';
        $params = [];
        
        if (!empty($startDate) && !empty($endDate)) {
            $dateFilter = ' WHERE created_at BETWEEN ? AND ?';
            $params = [$startDate, $endDate . ' 23:59:59'];
        }
        
        $sql = 'SELECT DATE_FORMAT(created_at, "%Y-%m") as date, COUNT(*) as count 
                FROM members' . $dateFilter . '
                GROUP BY DATE_FORMAT(created_at, "%Y-%m") 
                ORDER BY date ASC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $membershipHistory = $stmt->fetchAll();
    }
    
    // Get election results
    if ($reportType === 'elections' || $reportType === 'all') {
        $electionId = $_GET['election_id'] ?? null;
        
        if ($electionId) {
            // Get election details
            $stmt = $pdo->prepare('SELECT * FROM elections WHERE id = ?');
            $stmt->execute([$electionId]);
            $election = $stmt->fetch();
            
            if ($election) {
                // Get positions in this election
                $stmt = $pdo->prepare('SELECT * FROM positions WHERE election_id = ? ORDER BY id');
                $stmt->execute([$electionId]);
                $positions = $stmt->fetchAll();
                
                // Get votes for each position
                foreach ($positions as $position) {
                    $stmt = $pdo->prepare('
                        SELECT c.id, c.member_id, m.name, COUNT(v.id) as vote_count 
                        FROM candidates c 
                        JOIN members m ON c.member_id = m.id 
                        LEFT JOIN votes v ON c.id = v.candidate_id 
                        WHERE c.position_id = ? AND c.is_approved = 1 
                        GROUP BY c.id 
                        ORDER BY vote_count DESC, m.name ASC
                    ');
                    $stmt->execute([$position['id']]);
                    $candidates = $stmt->fetchAll();
                    
                    // Get total votes for this position
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE position_id = ?');
                    $stmt->execute([$position['id']]);
                    $totalVotes = $stmt->fetchColumn();
                    
                    $electionResults[] = [
                        'position' => $position,
                        'candidates' => $candidates,
                        'total_votes' => $totalVotes
                    ];
                }
            }
        }
        
        // Get all elections for selection
        $stmt = $pdo->query('SELECT id, title, start_date, end_date FROM elections ORDER BY start_date DESC');
        $elections = $stmt->fetchAll();
    }
    
    // Commit transaction
    $pdo->commit();
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log('Reports Error: ' . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while generating reports: " . $e->getMessage();
}

// Include header
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Reports</h1>
    <button class="btn btn-primary btn-print no-print" onclick="window.print()">
        <i class="fas fa-print me-2"></i> Print Report
    </button>
</div>

<!-- Report Navigation -->
<ul class="nav nav-tabs mb-4 no-print">
    <li class="nav-item">
        <a class="nav-link <?php echo $reportType === 'demographics' ? 'active' : ''; ?>" href="?type=demographics">
            <i class="fas fa-chart-pie me-2"></i> Demographics
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $reportType === 'units' ? 'active' : ''; ?>" href="?type=units">
            <i class="fas fa-building me-2"></i> Unit Distribution
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $reportType === 'history' ? 'active' : ''; ?>" href="?type=history">
            <i class="fas fa-history me-2"></i> Membership History
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $reportType === 'elections' ? 'active' : ''; ?>" href="?type=elections">
            <i class="fas fa-vote-yea me-2"></i> Election Results
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $reportType === 'all' ? 'active' : ''; ?>" href="?type=all">
            <i class="fas fa-file-alt me-2"></i> All Reports
        </a>
    </li>
</ul>

<!-- Print Header - Only visible when printing -->
<div class="print-section d-none d-print-block mb-4">
    <div class="text-center">
        <h1 class="h3">All UP Workers Union (AUPWU)</h1>
        <h2 class="h4">
            <?php
            switch ($reportType) {
                case 'demographics':
                    echo 'Demographic Report';
                    break;
                case 'units':
                    echo 'Unit Distribution Report';
                    break;
                case 'history':
                    echo 'Membership History Report';
                    break;
                case 'elections':
                    echo 'Election Results Report';
                    break;
                case 'all':
                    echo 'Comprehensive Report';
                    break;
            }
            ?>
        </h2>
        <p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
        <?php if (!empty($startDate) && !empty($endDate)): ?>
            <p>Date Range: <?php echo formatDate($startDate); ?> to <?php echo formatDate($endDate); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Date Range Filter - For History Report -->
<?php if ($reportType === 'history' || $reportType === 'all'): ?>
<div class="card mb-4 report-filter-section no-print">
    <div class="card-body">
        <form class="row g-3" method="get">
            <input type="hidden" name="type" value="<?php echo $reportType; ?>">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-filter me-2"></i> Filter
                </button>
                <a href="?type=<?php echo $reportType; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-sync-alt me-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Election Selection - For Election Report -->
<?php if ($reportType === 'elections'): ?>
<div class="card mb-4 report-filter-section no-print">
    <div class="card-body">
        <form class="row g-3" method="get">
            <input type="hidden" name="type" value="elections">
            <div class="col-md-8">
                <label for="election_id" class="form-label">Select Election</label>
                <select class="form-select" id="election_id" name="election_id" onchange="this.form.submit()">
                    <option value="">-- Select an Election --</option>
                    <?php foreach ($elections ?? [] as $election): ?>
                        <option value="<?php echo $election['id']; ?>" <?php echo (isset($_GET['election_id']) && $_GET['election_id'] == $election['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($election['title']); ?> 
                            (<?php echo formatDate($election['start_date']); ?> to <?php echo formatDate($election['end_date']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <a href="?type=elections" class="btn btn-outline-secondary">
                    <i class="fas fa-sync-alt me-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Demographics Report -->
<?php if ($reportType === 'demographics' || $reportType === 'all'): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Membership Demographics</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card h-100 demographic-card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-3 text-muted">Member Status</h6>
                        <div class="report-chart-container">
                            <canvas id="memberStatusChart"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Active</td>
                                        <td><?php echo $demographicData['active_members'] ?? 0; ?></td>
                                        <td>
                                            <?php
                                            $activePercentage = 0;
                                            if (!empty($demographicData['total_members'])) {
                                                $activePercentage = round(($demographicData['active_members'] / $demographicData['total_members']) * 100);
                                            }
                                            echo $activePercentage . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Inactive</td>
                                        <td><?php echo $demographicData['inactive_members'] ?? 0; ?></td>
                                        <td>
                                            <?php
                                            $inactivePercentage = 0;
                                            if (!empty($demographicData['total_members'])) {
                                                $inactivePercentage = round(($demographicData['inactive_members'] / $demographicData['total_members']) * 100);
                                            }
                                            echo $inactivePercentage . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <tr class="table-secondary">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo $demographicData['total_members'] ?? 0; ?></strong></td>
                                        <td><strong>100%</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card h-100 demographic-card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-3 text-muted">UP Status</h6>
                        <div class="report-chart-container">
                            <canvas id="upStatusChart"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>In UP</td>
                                        <td><?php echo $demographicData['in_up'] ?? 0; ?></td>
                                        <td>
                                            <?php
                                            $inUPPercentage = 0;
                                            if (!empty($demographicData['total_members'])) {
                                                $inUPPercentage = round(($demographicData['in_up'] / $demographicData['total_members']) * 100);
                                            }
                                            echo $inUPPercentage . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Out of UP</td>
                                        <td><?php echo $demographicData['out_up'] ?? 0; ?></td>
                                        <td>
                                            <?php
                                            $outUPPercentage = 0;
                                            if (!empty($demographicData['total_members'])) {
                                                $outUPPercentage = round(($demographicData['out_up'] / $demographicData['total_members']) * 100);
                                            }
                                            echo $outUPPercentage . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <tr class="table-secondary">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo $demographicData['total_members'] ?? 0; ?></strong></td>
                                        <td><strong>100%</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-3 text-muted">Committee Membership</h6>
                        <div class="report-chart-container">
                            <canvas id="committeeChart"></canvas>
                        </div>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Committee</th>
                                        <th>Members</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($demographicData['committees'] ?? [] as $committee): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($committee['name']); ?></td>
                                            <td><?php echo $committee['member_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Unit Distribution Report -->
<?php if ($reportType === 'units' || $reportType === 'all'): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Unit/College Distribution</h5>
    </div>
    <div class="card-body">
        <div class="report-chart-container mb-4">
            <canvas id="unitDistributionChart"></canvas>
        </div>
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
                    <tr class="table-secondary">
                        <td><strong>Total</strong></td>
                        <td><strong><?php echo $totalMembers; ?></strong></td>
                        <td><strong>100%</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Membership History Report -->
<?php if ($reportType === 'history' || $reportType === 'all'): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Membership History</h5>
    </div>
    <div class="card-body">
        <div class="report-chart-container mb-4">
            <canvas id="membershipHistoryChart"></canvas>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>New Members</th>
                        <th>Cumulative</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $cumulative = 0;
                    foreach ($membershipHistory as $entry): 
                        $cumulative += $entry['count'];
                        
                        // Format date for display (YYYY-MM to Month Year)
                        $formattedDate = date('F Y', strtotime($entry['date'] . '-01'));
                    ?>
                        <tr>
                            <td><?php echo $formattedDate; ?></td>
                            <td><?php echo $entry['count']; ?></td>
                            <td><?php echo $cumulative; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Election Results Report -->
<?php if ($reportType === 'elections' || $reportType === 'all'): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Election Results</h5>
    </div>
    <div class="card-body">
        <?php if (empty($_GET['election_id']) && $reportType === 'elections'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Please select an election from the dropdown above to view results.
            </div>
        <?php elseif (empty($electionResults)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No election data available.
            </div>
        <?php else: ?>
            <?php foreach ($electionResults as $index => $positionData): ?>
                <div class="mb-5">
                    <h5><?php echo htmlspecialchars($positionData['position']['title']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($positionData['position']['description'] ?? ''); ?></p>
                    
                    <?php if ($positionData['total_votes'] > 0): ?>
                        <div class="report-chart-container mb-4" style="height: 300px;">
                            <canvas id="electionChart<?php echo $index; ?>"></canvas>
                        </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Votes</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (empty($positionData['candidates'])): 
                                ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No candidates for this position.</td>
                                    </tr>
                                <?php 
                                else:
                                    foreach ($positionData['candidates'] as $candidate): 
                                        $percentage = ($positionData['total_votes'] > 0) ? 
                                            round(($candidate['vote_count'] / $positionData['total_votes']) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($candidate['name']); ?></td>
                                        <td><?php echo $candidate['vote_count']; ?></td>
                                        <td><?php echo $percentage; ?>%</td>
                                    </tr>
                                <?php 
                                    endforeach; 
                                ?>
                                    <tr class="table-secondary">
                                        <td><strong>Total</strong></td>
                                        <td><strong><?php echo $positionData['total_votes']; ?></strong></td>
                                        <td><strong>100%</strong></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Initialize Charts -->
<script src="../js/charts.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($reportType === 'demographics' || $reportType === 'all'): ?>
    // Member Status Chart
    createMemberStatusChart('memberStatusChart', 
        <?php echo $demographicData['active_members'] ?? 0; ?>, 
        <?php echo $demographicData['inactive_members'] ?? 0; ?>
    );
    
    // UP Status Chart
    createUPStatusChart('upStatusChart', 
        <?php echo $demographicData['in_up'] ?? 0; ?>, 
        <?php echo $demographicData['out_up'] ?? 0; ?>
    );
    
    // Committee Chart
    createCommitteeMembershipChart('committeeChart', 
        <?php echo json_encode($demographicData['committees'] ?? []); ?>
    );
    <?php endif; ?>
    
    <?php if ($reportType === 'units' || $reportType === 'all'): ?>
    // Unit Distribution Chart
    createUnitDistributionChart('unitDistributionChart', 
        <?php echo json_encode($unitDistribution ?? []); ?>
    );
    <?php endif; ?>
    
    <?php if ($reportType === 'history' || $reportType === 'all'): ?>
    // Membership History Chart
    const historyData = <?php echo json_encode($membershipHistory ?? []); ?>;
    const historyLabels = historyData.map(item => {
        const date = new Date(item.date + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    const historyCounts = historyData.map(item => item.count);
    
    createCustomChart(
        'membershipHistoryChart',
        'line',
        historyLabels,
        historyCounts,
        'New Members by Month',
        {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of New Members'
                    },
                    ticks: {
                        precision: 0
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month'
                    }
                }
            }
        }
    );
    <?php endif; ?>
    
    <?php if (($reportType === 'elections' || $reportType === 'all') && !empty($electionResults)): ?>
    // Election Results Charts
    <?php foreach ($electionResults as $index => $positionData): ?>
        <?php if ($positionData['total_votes'] > 0): ?>
            const electionData<?php echo $index; ?> = <?php echo json_encode($positionData['candidates']); ?>;
            const candidateNames<?php echo $index; ?> = electionData<?php echo $index; ?>.map(item => item.name);
            const voteCounts<?php echo $index; ?> = electionData<?php echo $index; ?>.map(item => item.vote_count);
            
            createElectionResultsChart(
                'electionChart<?php echo $index; ?>',
                '<?php echo addslashes($positionData['position']['title']); ?> - Vote Distribution',
                electionData<?php echo $index; ?>
            );
        <?php endif; ?>
    <?php endforeach; ?>
    <?php endif; ?>
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>
