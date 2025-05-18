<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Handle login and registration
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['action'])) {
            switch ($data['action']) {
                case 'login':
                    handleLogin($data);
                    break;
                case 'register':
                    handleRegistration($data);
                    break;
                default:
                    sendResponse(false, 'Invalid action');
            }
        } else {
            sendResponse(false, 'No action specified');
        }
        break;
        
    default:
        sendResponse(false, 'Method not allowed');
}

// Handle user login
function handleLogin($data) {
    if (!isset($data['email']) || !isset($data['password'])) {
        sendResponse(false, 'Email and password are required');
        return;
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT id, full_name, email, password FROM users WHERE email = ?');
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($data['password'], $user['password'])) {
        // Remove password from response
        unset($user['password']);
        sendResponse(true, 'Login successful', $user);
    } else {
        sendResponse(false, 'Invalid email or password');
    }
}

// Handle user registration
function handleRegistration($data) {
    if (!isset($data['full_name']) || !isset($data['email']) || !isset($data['password'])) {
        sendResponse(false, 'All fields are required');
        return;
    }

    $conn = getDBConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        sendResponse(false, 'Email already registered');
        return;
    }

    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare('INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)');
    try {
        $stmt->execute([$data['full_name'], $data['email'], $hashedPassword]);
        $userId = $conn->lastInsertId();

        // Get the created user (without password)
        $stmt = $conn->prepare('SELECT id, full_name, email FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        sendResponse(true, 'Registration successful', $user);
    } catch (PDOException $e) {
        sendResponse(false, 'Registration failed: ' . $e->getMessage());
    }
}

// Helper function to send JSON response
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
?> 