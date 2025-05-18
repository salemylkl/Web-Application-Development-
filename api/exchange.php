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
    case 'POST':
        // Create new exchange request
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['receiver_id']) || !isset($data['message'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }

        try {
            $conn = getDBConnection();
            
            // Check if user already has a pending request
            $stmt = $conn->prepare("
                SELECT id FROM exchanges 
                WHERE sender_id = ? AND receiver_id = ? 
                AND status = 'pending'
            ");
            $stmt->execute([$_SESSION['user_id'], $data['receiver_id']]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You already have a pending request with this user'
                ]);
                exit;
            }

            // Create new exchange request
            $stmt = $conn->prepare("
                INSERT INTO exchanges (sender_id, receiver_id, message, status, created_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $data['receiver_id'],
                $data['message']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Exchange request sent successfully'
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send exchange request: ' . $e->getMessage()
            ]);
        }
        break;

    case 'PUT':
        // Update exchange status (accept/reject)
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['exchange_id']) || !isset($data['status'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }

        try {
            $conn = getDBConnection();
            
            // Verify the user is the receiver
            $stmt = $conn->prepare("
                SELECT id FROM exchanges 
                WHERE id = ? AND receiver_id = ? AND status = 'pending'
            ");
            $stmt->execute([$data['exchange_id'], $_SESSION['user_id']]);
            
            if ($stmt->rowCount() === 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid exchange request or unauthorized'
                ]);
                exit;
            }

            // Update exchange status
            $stmt = $conn->prepare("
                UPDATE exchanges 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data['status'], $data['exchange_id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Exchange request ' . $data['status'] . 'ed successfully'
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update exchange request: ' . $e->getMessage()
            ]);
        }
        break;

    case 'GET':
        // Get user's exchanges
        $type = isset($_GET['type']) ? $_GET['type'] : 'all';
        
        try {
            $conn = getDBConnection();
            
            $sql = "
                SELECT e.*, 
                    sender.full_name as sender_name,
                    receiver.full_name as receiver_name
                FROM exchanges e
                JOIN users sender ON e.sender_id = sender.id
                JOIN users receiver ON e.receiver_id = receiver.id
                WHERE 1=1
            ";
            
            $params = [];
            
            switch ($type) {
                case 'sent':
                    $sql .= " AND e.sender_id = ?";
                    $params[] = $_SESSION['user_id'];
                    break;
                case 'received':
                    $sql .= " AND e.receiver_id = ?";
                    $params[] = $_SESSION['user_id'];
                    break;
                default:
                    $sql .= " AND (e.sender_id = ? OR e.receiver_id = ?)";
                    $params = [$_SESSION['user_id'], $_SESSION['user_id']];
            }
            
            $sql .= " ORDER BY e.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'exchanges' => $exchanges
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch exchanges: ' . $e->getMessage()
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