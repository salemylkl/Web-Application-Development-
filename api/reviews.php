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

switch ($method) {
    case 'GET':
        // Get reviews for a user
        if (!isset($_GET['user_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'User ID is required'
            ]);
            exit;
        }

        try {
            $conn = getDBConnection();
            
            // Get reviews with reviewer information
            $stmt = $conn->prepare("
                SELECT 
                    r.id,
                    r.rating,
                    r.comment,
                    r.created_at,
                    u.full_name as reviewer_name,
                    u.location as reviewer_location
                FROM reviews r
                JOIN users u ON r.reviewer_id = u.id
                WHERE r.receiver_id = ?
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$_GET['user_id']]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get average rating
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_reviews,
                    COALESCE(AVG(rating), 0) as average_rating
                FROM reviews
                WHERE receiver_id = ?
            ");
            $stmt->execute([$_GET['user_id']]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'stats' => $stats
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch reviews: ' . $e->getMessage()
            ]);
        }
        break;

    case 'POST':
        // Create a new review
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['receiver_id']) || !isset($data['rating']) || !isset($data['comment'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }

        try {
            $conn = getDBConnection();
            
            // Check if there was a completed exchange between the users
            $stmt = $conn->prepare("
                SELECT id
                FROM exchanges
                WHERE status = 'completed'
                AND (
                    (sender_id = ? AND receiver_id = ?)
                    OR (sender_id = ? AND receiver_id = ?)
                )
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $data['receiver_id'],
                $data['receiver_id'],
                $_SESSION['user_id']
            ]);
            
            if (!$stmt->fetch()) {
                throw new Exception('You can only review users you have completed exchanges with');
            }

            // Check if user has already reviewed
            $stmt = $conn->prepare("
                SELECT id
                FROM reviews
                WHERE reviewer_id = ? AND receiver_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $data['receiver_id']]);
            
            if ($stmt->fetch()) {
                throw new Exception('You have already reviewed this user');
            }

            // Create review
            $stmt = $conn->prepare("
                INSERT INTO reviews (reviewer_id, receiver_id, rating, comment)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $data['receiver_id'],
                $data['rating'],
                $data['comment']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Review created successfully'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create review: ' . $e->getMessage()
            ]);
        }
        break;

    case 'PUT':
        // Update an existing review
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['review_id']) || !isset($data['rating']) || !isset($data['comment'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }

        try {
            $conn = getDBConnection();
            
            // Check if review exists and belongs to user
            $stmt = $conn->prepare("
                SELECT id
                FROM reviews
                WHERE id = ? AND reviewer_id = ?
            ");
            $stmt->execute([$data['review_id'], $_SESSION['user_id']]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Review not found or unauthorized');
            }

            // Update review
            $stmt = $conn->prepare("
                UPDATE reviews
                SET rating = ?,
                    comment = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['rating'],
                $data['comment'],
                $data['review_id']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Review updated successfully'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update review: ' . $e->getMessage()
            ]);
        }
        break;

    case 'DELETE':
        // Delete a review
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['review_id'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Review ID is required'
            ]);
            exit;
        }

        try {
            $conn = getDBConnection();
            
            // Check if review exists and belongs to user
            $stmt = $conn->prepare("
                SELECT id
                FROM reviews
                WHERE id = ? AND reviewer_id = ?
            ");
            $stmt->execute([$data['review_id'], $_SESSION['user_id']]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Review not found or unauthorized');
            }

            // Delete review
            $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$data['review_id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete review: ' . $e->getMessage()
            ]);
        }
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
}
?> 