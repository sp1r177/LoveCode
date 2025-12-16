<?php
/**
 * Public VK ID OAuth initiation endpoint
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

try {
    // Create VK auth service
    $vkAuth = new VkAuthService();
    $authUrl = $vkAuth->getAuthUrl();
    
    // Debug logging
    error_log('VKID.PHP: Generated auth URL: ' . $authUrl);
    
    if (empty($authUrl) || !filter_var($authUrl, FILTER_VALIDATE_URL)) {
        error_log('VKID.PHP: ERROR - Invalid auth URL');
        http_response_code(500);
        echo json_encode(['error' => 'VK authentication not configured']);
        exit;
    }
    
    // Redirect to VK auth URL
    header('Location: ' . $authUrl);
    exit;
} catch (Exception $e) {
    error_log('VKID.PHP: ERROR - ' . $e->getMessage());
    error_log('VKID.PHP: ERROR TRACE - ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}