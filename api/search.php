<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    // Get search parameters
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $location = isset($_GET['location']) ? trim($_GET['location']) : '';
    $skillLevel = isset($_GET['skill_level']) ? trim($_GET['skill_level']) : '';
    $sortBy = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'rating';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Build the base query
    $sql = "SELECT DISTINCT u.*, 
            COALESCE(AVG(r.rating), 0) as rating,
            COUNT(DISTINCT r.id) as review_count
            FROM users u
            LEFT JOIN reviews r ON u.id = r.receiver_id
            LEFT JOIN user_skills us ON u.id = us.user_id
            LEFT JOIN skills s ON us.skill_id = s.id
            WHERE 1=1";

    $params = [];
    $types = '';

    // Add search conditions
    if (!empty($query)) {
        $sql .= " AND (u.full_name LIKE ? OR u.bio LIKE ? OR s.name LIKE ?)";
        $searchTerm = "%$query%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }

    if (!empty($category)) {
        $sql .= " AND s.category = ?";
        $params[] = $category;
        $types .= 's';
    }

    if (!empty($location)) {
        $sql .= " AND u.location LIKE ?";
        $params[] = "%$location%";
        $types .= 's';
    }

    if (!empty($skillLevel)) {
        $sql .= " AND us.skill_level = ?";
        $params[] = $skillLevel;
        $types .= 's';
    }

    // Group by user to avoid duplicates
    $sql .= " GROUP BY u.id";

    // Add sorting
    switch ($sortBy) {
        case 'rating':
            $sql .= " ORDER BY rating DESC";
            break;
        case 'reviews':
            $sql .= " ORDER BY review_count DESC";
            break;
        case 'name':
            $sql .= " ORDER BY u.full_name ASC";
            break;
        default:
            $sql .= " ORDER BY rating DESC";
    }

    // Add pagination
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $types .= 'ii';

    // Prepare and execute the query
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch users
    $users = [];
    while ($user = $result->fetch_assoc()) {
        // Get user skills
        $skillsStmt = $conn->prepare("
            SELECT s.name, us.skill_level
            FROM user_skills us
            JOIN skills s ON us.skill_id = s.id
            WHERE us.user_id = ?
        ");
        $skillsStmt->bind_param('i', $user['id']);
        $skillsStmt->execute();
        $skillsResult = $skillsStmt->get_result();
        
        $user['skills'] = [];
        while ($skill = $skillsResult->fetch_assoc()) {
            $user['skills'][] = $skill;
        }
        
        $users[] = $user;
    }

    // Return results
    echo json_encode([
        'success' => true,
        'results' => $users,
        'page' => $page,
        'per_page' => $perPage
    ]);

} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while performing the search'
    ]);
}
?> 