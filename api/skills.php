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
        // Get all available skills
        try {
            $conn = getDBConnection();
            
            $stmt = $conn->prepare("
                SELECT id, name, category
                FROM skills
                ORDER BY category, name
            ");
            $stmt->execute();
            $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $skills
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch skills: ' . $e->getMessage()
            ]);
        }
        break;

    case 'POST':
        // Add a skill to user's profile
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['skill_id']) || !isset($data['skill_level']) || !isset($data['type'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }

        try {
            $conn = getDBConnection();
            
            // Check if skill exists
            $stmt = $conn->prepare("SELECT id FROM skills WHERE id = ?");
            $stmt->execute([$data['skill_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Skill not found');
            }

            // Check if user already has this skill
            $table = $data['type'] === 'teaching' ? 'user_skills' : 'user_learning';
            $stmt = $conn->prepare("
                SELECT id FROM {$table}
                WHERE user_id = ? AND skill_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $data['skill_id']]);
            if ($stmt->fetch()) {
                throw new Exception('You already have this skill in your profile');
            }

            // Add skill to user's profile
            $stmt = $conn->prepare("
                INSERT INTO {$table} (user_id, skill_id, skill_level)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $data['skill_id'],
                $data['skill_level']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Skill added successfully'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add skill: ' . $e->getMessage()
            ]);
        }
        break;

    case 'DELETE':
        // Remove a skill from user's profile
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['skill_id']) || !isset($data['type'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }

        try {
            $conn = getDBConnection();
            
            $table = $data['type'] === 'teaching' ? 'user_skills' : 'user_learning';
            $stmt = $conn->prepare("
                DELETE FROM {$table}
                WHERE user_id = ? AND skill_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $data['skill_id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Skill removed successfully'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to remove skill: ' . $e->getMessage()
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