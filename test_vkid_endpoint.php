<?php

// Simple test script to check VK ID endpoint

echo "Testing VK ID endpoint...\n";

// Load environment variables
require_once __DIR__ . '/backend/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/backend');
$dotenv->load();

echo "Environment variables loaded:\n";
echo "VK_APP_ID: " . ($_ENV['VK_APP_ID'] ?? 'NOT SET') . "\n";
echo "VK_APP_SECRET: " . ($_ENV['VK_APP_SECRET'] ?? 'NOT SET') . "\n";
echo "VK_REDIRECT_URI: " . ($_ENV['VK_REDIRECT_URI'] ?? 'NOT SET') . "\n";
echo "CORS_ORIGINS: " . ($_ENV['CORS_ORIGINS'] ?? 'NOT SET') . "\n";

// Test the VkAuthService
try {
    require_once __DIR__ . '/backend/src/Services/VkAuthService.php';
    
    $vkAuth = new \App\Services\VkAuthService();
    $authUrl = $vkAuth->getAuthUrl();
    
    echo "Auth URL generated: " . $authUrl . "\n";
    
    if (empty($authUrl) || !filter_var($authUrl, FILTER_VALIDATE_URL)) {
        echo "ERROR: Auth URL is invalid or empty\n";
    } else {
        echo "SUCCESS: Auth URL is valid\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "TRACE: " . $e->getTraceAsString() . "\n";
}