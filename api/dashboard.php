<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to perform this action'
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $conn = getDBConnection();
        
        // Get user's teaching skills count
        $stmt = $conn->prepare("
            SELECT COUNT(*) as teaching_count
            FROM user_skills
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $teaching_count = $stmt->fetch(PDO::FETCH_ASSOC)['teaching_count'];

        // Get user's learning goals count
        $stmt = $conn->prepare("
            SELECT COUNT(*) as learning_count
            FROM user_learning
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $learning_count = $stmt->fetch(PDO::FETCH_ASSOC)['learning_count'];

        // Get user's exchange statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_exchanges,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_exchanges,
                SUM(CASE WHEN status = 'pending' AND receiver_id = ? THEN 1 ELSE 0 END) as pending_requests
            FROM exchanges
            WHERE sender_id = ? OR receiver_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        $exchange_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get user's average rating
        $stmt = $conn->prepare("
            SELECT COALESCE(AVG(rating), 0) as average_rating
            FROM reviews
            WHERE receiver_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $rating = $stmt->fetch(PDO::FETCH_ASSOC)['average_rating'];

        // Get upcoming exchanges
        $stmt = $conn->prepare("
            SELECT 
                e.id,
                e.status,
                e.created_at,
                u.full_name as partner_name,
                u.location as partner_location
            FROM exchanges e
            JOIN users u ON (
                CASE 
                    WHEN e.sender_id = ? THEN e.receiver_id = u.id
                    ELSE e.sender_id = u.id
                END
            )
            WHERE (e.sender_id = ? OR e.receiver_id = ?)
            AND e.status IN ('pending', 'accepted')
            ORDER BY e.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        $upcoming_exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get user's skills with match counts
        $stmt = $conn->prepare("
            SELECT 
                s.id,
                s.name,
                s.category,
                us.skill_level,
                (
                    SELECT COUNT(DISTINCT ul.user_id)
                    FROM user_learning ul
                    WHERE ul.skill_id = s.id
                    AND ul.user_id != ?
                ) as potential_matches
            FROM user_skills us
            JOIN skills s ON us.skill_id = s.id
            WHERE us.user_id = ?
            ORDER BY potential_matches DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $top_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'stats' => [
                    'teaching_skills' => $teaching_count,
                    'learning_goals' => $learning_count,
                    'total_exchanges' => $exchange_stats['total_exchanges'],
                    'completed_exchanges' => $exchange_stats['completed_exchanges'],
                    'pending_requests' => $exchange_stats['pending_requests'],
                    'average_rating' => round($rating, 1)
                ],
                'upcoming_exchanges' => $upcoming_exchanges,
                'top_skills' => $top_skills
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch dashboard data: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?> 