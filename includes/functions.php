<?php
/**
 * General utility functions for the AUPWU Management System
 */

/**
 * Get member profile data by user ID
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @return array|false Member data or false if not found
 */
function getMemberByUserId($pdo, $userId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM members WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get Member Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get member profile data by member ID
 * 
 * @param PDO $pdo Database connection
 * @param int $memberId Member ID
 * @return array|false Member data or false if not found
 */
function getMemberById($pdo, $memberId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM members WHERE id = ?');
        $stmt->execute([$memberId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get Member Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all members with optional filtering
 * 
 * @param PDO $pdo Database connection
 * @param array $filters Optional filters (key-value pairs)
 * @return array Array of member records
 */
function getAllMembers($pdo, $filters = []) {
    try {
        $sql = 'SELECT m.*, u.username, u.email as user_email, u.role 
                FROM members m 
                JOIN users u ON m.user_id = u.id';
        
        $whereClause = [];
        $params = [];
        
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if ($value !== null && $value !== '') {
                    $whereClause[] = "m.$key = ?";
                    $params[] = $value;
                }
            }
            
            if (!empty($whereClause)) {
                $sql .= ' WHERE ' . implode(' AND ', $whereClause);
            }
        }
        
        $sql .= ' ORDER BY m.name ASC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get All Members Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all committees
 * 
 * @param PDO $pdo Database connection
 * @return array Array of committee records
 */
function getAllCommittees($pdo) {
    try {
        $stmt = $pdo->query('SELECT * FROM committees ORDER BY name ASC');
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Committees Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get committee by ID
 * 
 * @param PDO $pdo Database connection
 * @param int $committeeId Committee ID
 * @return array|false Committee data or false if not found
 */
function getCommitteeById($pdo, $committeeId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM committees WHERE id = ?');
        $stmt->execute([$committeeId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get Committee Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get committees for a member
 * 
 * @param PDO $pdo Database connection
 * @param int $memberId Member ID
 * @return array Array of committee assignments
 */
function getMemberCommittees($pdo, $memberId) {
    try {
        $sql = 'SELECT mc.*, c.name as committee_name, c.description 
                FROM member_committees mc 
                JOIN committees c ON mc.committee_id = c.id 
                WHERE mc.member_id = ? 
                ORDER BY mc.is_active DESC, c.name ASC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$memberId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Member Committees Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get members in a committee
 * 
 * @param PDO $pdo Database connection
 * @param int $committeeId Committee ID
 * @return array Array of members in the committee
 */
function getCommitteeMembers($pdo, $committeeId) {
    try {
        $sql = 'SELECT mc.*, m.name, m.unit_college, m.designation 
                FROM member_committees mc 
                JOIN members m ON mc.member_id = m.id 
                WHERE mc.committee_id = ? AND mc.is_active = 1 
                ORDER BY mc.position, m.name ASC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$committeeId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Committee Members Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get active elections
 * 
 * @param PDO $pdo Database connection
 * @param bool $activeOnly Get only active elections
 * @return array Array of elections
 */
function getElections($pdo, $activeOnly = false) {
    try {
        $sql = 'SELECT * FROM elections';
        
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1 AND start_date <= NOW() AND end_date >= NOW()';
        }
        
        $sql .= ' ORDER BY start_date DESC';
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Elections Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get election by ID
 * 
 * @param PDO $pdo Database connection
 * @param int $electionId Election ID
 * @return array|false Election data or false if not found
 */
function getElectionById($pdo, $electionId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM elections WHERE id = ?');
        $stmt->execute([$electionId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Get Election Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get positions for an election
 * 
 * @param PDO $pdo Database connection
 * @param int $electionId Election ID
 * @return array Array of positions
 */
function getElectionPositions($pdo, $electionId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM positions WHERE election_id = ? ORDER BY id ASC');
        $stmt->execute([$electionId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Election Positions Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get candidates for a position
 * 
 * @param PDO $pdo Database connection
 * @param int $positionId Position ID
 * @return array Array of candidates
 */
function getPositionCandidates($pdo, $positionId) {
    try {
        $sql = 'SELECT c.*, m.name, m.unit_college, m.designation 
                FROM candidates c 
                JOIN members m ON c.member_id = m.id 
                WHERE c.position_id = ? AND c.is_approved = 1 
                ORDER BY m.name ASC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$positionId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Position Candidates Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Check if a member has voted for a position
 * 
 * @param PDO $pdo Database connection
 * @param int $electionId Election ID
 * @param int $positionId Position ID
 * @param int $memberId Member ID
 * @return bool True if already voted
 */
function hasVoted($pdo, $electionId, $positionId, $memberId) {
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM votes WHERE election_id = ? AND position_id = ? AND voter_id = ?');
        $stmt->execute([$electionId, $positionId, $memberId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Has Voted Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Cast a vote
 * 
 * @param PDO $pdo Database connection
 * @param int $electionId Election ID
 * @param int $positionId Position ID
 * @param int $candidateId Candidate ID
 * @param int $voterId Voter ID (member)
 * @return bool Success status
 */
function castVote($pdo, $electionId, $positionId, $candidateId, $voterId) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if already voted
        if (hasVoted($pdo, $electionId, $positionId, $voterId)) {
            $pdo->rollBack();
            return false;
        }
        
        // Insert vote
        $stmt = $pdo->prepare('INSERT INTO votes (election_id, position_id, candidate_id, voter_id) VALUES (?, ?, ?, ?)');
        $result = $stmt->execute([$electionId, $positionId, $candidateId, $voterId]);
        
        if ($result) {
            $pdo->commit();
            return true;
        } else {
            $pdo->rollBack();
            return false;
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Cast Vote Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get vote counts for candidates in a position
 * 
 * @param PDO $pdo Database connection
 * @param int $electionId Election ID
 * @param int $positionId Position ID
 * @return array Array of candidates with vote counts
 */
function getVoteCounts($pdo, $electionId, $positionId) {
    try {
        $sql = 'SELECT c.id, c.member_id, m.name, COUNT(v.id) as vote_count 
                FROM candidates c 
                JOIN members m ON c.member_id = m.id 
                LEFT JOIN votes v ON c.id = v.candidate_id AND v.position_id = c.position_id 
                WHERE c.election_id = ? AND c.position_id = ? AND c.is_approved = 1 
                GROUP BY c.id 
                ORDER BY vote_count DESC, m.name ASC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$electionId, $positionId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Get Vote Counts Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get demographic data for reports
 * 
 * @param PDO $pdo Database connection
 * @return array Array of demographic data
 */
function getDemographicData($pdo) {
    try {
        $data = [
            'total_members' => 0,
            'active_members' => 0,
            'inactive_members' => 0,
            'in_up' => 0,
            'out_up' => 0,
            'committees' => [],
            'units' => []
        ];
        
        // Get member counts
        $stmt = $pdo->query('SELECT COUNT(*) as total FROM members');
        $data['total_members'] = $stmt->fetch()['total'];
        
        $stmt = $pdo->query('SELECT COUNT(*) as active FROM members WHERE is_active = 1');
        $data['active_members'] = $stmt->fetch()['active'];
        
        $data['inactive_members'] = $data['total_members'] - $data['active_members'];
        
        // Get UP status counts
        $stmt = $pdo->query('SELECT COUNT(*) as in_count FROM members WHERE up_status = "in"');
        $data['in_up'] = $stmt->fetch()['in_count'];
        
        $data['out_up'] = $data['total_members'] - $data['in_up'];
        
        // Get committee membership
        $sql = 'SELECT c.name, COUNT(mc.id) as member_count 
                FROM committees c 
                LEFT JOIN member_committees mc ON c.id = mc.committee_id AND mc.is_active = 1 
                GROUP BY c.id 
                ORDER BY member_count DESC';
        $stmt = $pdo->query($sql);
        $data['committees'] = $stmt->fetchAll();
        
        // Get unit/college distribution
        $sql = 'SELECT unit_college, COUNT(*) as count 
                FROM members 
                GROUP BY unit_college 
                ORDER BY count DESC';
        $stmt = $pdo->query($sql);
        $data['units'] = $stmt->fetchAll();
        
        return $data;
    } catch (PDOException $e) {
        error_log('Get Demographic Data Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Upload and process an image file
 * 
 * @param array $file $_FILES array element
 * @param string $destination Directory to save the file
 * @param int $maxWidth Maximum width for resizing
 * @param int $maxHeight Maximum height for resizing
 * @return string|false Path to saved file or false on failure
 */
function uploadImage($file, $destination, $maxWidth = 400, $maxHeight = 400) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    // Create destination directory if it doesn't exist
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $filepath = $destination . '/' . $filename;
    
    // Process the image (resize if needed)
    $sourceImage = null;
    switch ($file['type']) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($file['tmp_name']);
            break;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Get original dimensions
    $width = imagesx($sourceImage);
    $height = imagesy($sourceImage);
    
    // Calculate new dimensions while maintaining aspect ratio
    $newWidth = $width;
    $newHeight = $height;
    
    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = ($height * $maxWidth) / $width;
    }
    
    if ($newHeight > $maxHeight) {
        $newHeight = $maxHeight;
        $newWidth = ($width * $maxHeight) / $height;
    }
    
    // Create resized image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG files
    if ($file['type'] === 'image/png') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize the image
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save the image
    switch ($file['type']) {
        case 'image/jpeg':
            imagejpeg($newImage, $filepath, 85);
            break;
        case 'image/png':
            imagepng($newImage, $filepath, 8);
            break;
        case 'image/gif':
            imagegif($newImage, $filepath);
            break;
    }
    
    // Free memory
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $filepath;
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format PHP date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'F j, Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * Sanitize and validate form input
 * 
 * @param string $input Input string
 * @return string Sanitized input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate pagination HTML
 * 
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $urlPattern URL pattern with %d placeholder for page number
 * @return string Pagination HTML
 */
function generatePagination($currentPage, $totalPages, $urlPattern) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $currentPage - 1) . '">&laquo; Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo; Previous</a></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, 1) . '">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $i) . '">' . $i . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $totalPages) . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $currentPage + 1) . '">Next &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Next &raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}
?>
