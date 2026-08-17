<?php
session_start();
header('Content-Type: application/json');
require_once 'includes/connection.php';

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON output

// ==================== GROQ API CONFIGURATION ====================
define('GROQ_API_KEY', rdv_env('GROQ_API_KEY', ''));
define('GROQ_API_URL', 'https://api.groq.com/openai/v1');
define('GROQ_MODEL', 'llama-3.3-70b-versatile'); // Active model

function callGroqAPI($messages) {
    $payload = [
        'model' => GROQ_MODEL,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 300
    ];
    
    $ch = curl_init(GROQ_API_URL . '/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        $msg = $error['error']['message'] ?? 'Unknown API error';
        throw new Exception("Groq API error (HTTP $httpCode): $msg");
    }
    
    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? "I'm sorry, I couldn't process that.";
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = trim($input['message'] ?? '');
    
    if (empty($userMessage)) {
        echo json_encode(['error' => 'Message is empty']);
        exit();
    }
    
    // Build conversation history (optional – we can keep simple for now)
    $messages = [
        ['role' => 'system', 'content' => "You are a helpful AI assistant for RD Vendora, an e-commerce platform. Answer questions about features, pricing, store building, and general e-commerce. Keep responses concise (2-3 sentences max) and friendly."],
        ['role' => 'user', 'content' => $userMessage]
    ];
    
    try {
        $reply = callGroqAPI($messages);
        echo json_encode(['reply' => $reply]);
    } catch (Exception $e) {
        error_log("Chatbot error: " . $e->getMessage());
        echo json_encode(['error' => 'AI service temporarily unavailable. Please try again later.']);
    }
    exit();
}

// Not a POST request
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit();
?>