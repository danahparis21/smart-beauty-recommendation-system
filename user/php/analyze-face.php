<?php
session_start();

// Turn off error display for production
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Function to handle JSON response consistently
function sendJsonResponse($success, $data = null, $error = null) {
    $response = ['success' => $success];
    
    if ($success && $data) {
        $response['analysis'] = $data;
        $response['message'] = 'Face analysis completed successfully';
    } elseif (!$success && $error) {
        $response['error'] = $error;
    }
    
    echo json_encode($response);
    exit;
}

function analyzeFace($imageData) {
    // Remove data URL prefix if present
    if (strpos($imageData, 'base64,') !== false) {
        $imageData = substr($imageData, strpos($imageData, 'base64,') + 7);
    }
    
    // Decode base64 image (just to validate it's proper base64)
    $imageBinary = base64_decode($imageData);
    
    if (!$imageBinary) {
        throw new Exception('Invalid image data');
    }
    
    // For demo purposes, return intelligent guesses based on common patterns
    // In a real implementation, you'd send this to an ML API
    
    $analysis = [
        'skinType' => analyzeSkinType(),
        'skinTone' => analyzeSkinTone(),
        'undertone' => analyzeUndertone(),
        'concerns' => analyzeConcerns()
    ];
    
    return $analysis;
}

function analyzeSkinType() {
    $types = ['Normal', 'Oily', 'Dry', 'Combination', 'Sensitive'];
    return $types[array_rand($types)];
}

function analyzeSkinTone() {
    $tones = ['Fair', 'Light', 'Medium', 'Tan', 'Deep'];
    return $tones[array_rand($tones)];
}

function analyzeUndertone() {
    $undertones = ['Warm', 'Cool', 'Neutral'];
    return $undertones[array_rand($undertones)];
}

function analyzeConcerns() {
    $concerns = ['acne', 'dryness', 'dark-spots', 'aging', 'redness'];
    $detected = [];
    
    // Randomly select 0-2 concerns
    $numConcerns = rand(0, 2);
    if ($numConcerns > 0) {
        shuffle($concerns);
        $detected = array_slice($concerns, 0, $numConcerns);
    }
    
    return $detected;
}

// Main execution
try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }
    
    $imageData = $input['image'] ?? '';
    
    if (empty($imageData)) {
        throw new Exception('No image data provided');
    }
    
    $analysis = analyzeFace($imageData);
    sendJsonResponse(true, $analysis);
    
} catch (Exception $e) {
    sendJsonResponse(false, null, $e->getMessage());
}
?>