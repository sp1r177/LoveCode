<?php
/**
 * Public VK callback endpoint
 * Temporary workaround for 403 errors
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Import required classes
use App\Services\VkAuthService;
use App\Services\JwtService;
use App\Models\Subscription;

try {
    // Get query parameters
    $code = $_GET['code'] ?? null;
    $error = $_GET['error'] ?? null;
    
    error_log('VK-CALLBACK.PHP: Received code: ' . ($code ? 'YES' : 'NO'));
    error_log('VK-CALLBACK.PHP: Received error: ' . ($error ?? 'NONE'));
    
    if ($error) {
        error_log('VK-CALLBACK.PHP: ERROR from VK - ' . $error);
        http_response_code(400);
        echo json_encode(['error' => $error]);
        exit;
    }
    
    if (!$code) {
        error_log('VK-CALLBACK.PHP: ERROR - Code not provided');
        http_response_code(400);
        echo json_encode(['error' => 'Code not provided']);
        exit;
    }
    
    // Create services
    $vkAuth = new VkAuthService();
    $tokenData = $vkAuth->exchangeCodeForToken($code);
    $user = $vkAuth->getOrCreateUser($tokenData);
    
    // Create subscription if not exists
    $subscription = Subscription::getActive($user['id']);
    if (!$subscription) {
        Subscription::create($user['id'], 'free');
    }
    
    // Generate JWT token
    $jwtService = new JwtService();
    $token = $jwtService->generateToken($user['id']);
    
    // Get frontend URL
    $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'https://flirt-ai.ru';
    $redirectUrl = $frontendUrl . '/auth/callback?token=' . urlencode($token);
    
    error_log('VK-CALLBACK.PHP: Redirecting to: ' . $redirectUrl);
    
    // Redirect to frontend
    header('Location: ' . $redirectUrl);
    exit;
} catch (Exception $e) {
    error_log('VK-CALLBACK.PHP: ERROR - ' . $e->getMessage());
    error_log('VK-CALLBACK.PHP: ERROR TRACE - ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}