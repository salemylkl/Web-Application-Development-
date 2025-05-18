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
        // Get user profile
        try {
            $conn = getDBConnection();
            
            // Get user data
            $stmt = $conn->prepare("
                SELECT id, full_name, email, location, bio, created_at
                FROM users
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('User not found');
            }

            // Get user's skills
            $stmt = $conn->prepare("
                SELECT s.id, s.name, s.category, us.skill_level
                FROM user_skills us
                JOIN skills s ON us.skill_id = s.id
                WHERE us.user_id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user['skills'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get user's learning goals
            $stmt = $conn->prepare("
                SELECT s.id, s.name, s.category, ul.skill_level
                FROM user_learning ul
                JOIN skills s ON ul.skill_id = s.id
                WHERE ul.user_id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user['learning'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $user
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch profile: ' . $e->getMessage()
            ]);
        }
        break;

    case 'PUT':
        // Update user profile
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_id']) || $data['user_id'] != $_SESSION['user_id']) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized'
            ]);
            exit;
        }

        try {
            $conn = getDBConnection();
            
            // Update user data
            $stmt = $conn->prepare("
                UPDATE users
                SET full_name = ?,
                    location = ?,
                    bio = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['full_name'],
                $data['location'],
                $data['bio'],
                $_SESSION['user_id']
            ]);

            // Get updated user data
            $stmt = $conn->prepare("
                SELECT id, full_name, email, location, bio, created_at
                FROM users
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $user,
                'message' => 'Profile updated successfully'
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
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