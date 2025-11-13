<?php
// Create test data
$test_data = [
    'user_input' => [
        'Skin_Type' => 'oily',
        'Skin_Tone' => 'medium', 
        'Undertone' => 'neutral',
        'Skin_Concerns' => ['dark-spots'],
        'Preference' => 'matte'
    ],
    'products' => [
        [
            'id' => 'TEST001', 
            'Name' => 'Test Product', 
            'Category' => 'Foundation', 
            'Price' => 100.00, 
            'skin_type' => 'Oily', 
            'skin_tone' => 'Medium', 
            'undertone' => 'Neutral'
        ]
    ]
];

$temp_file = tempnam(sys_get_temp_dir(), 'debug_ml_');
file_put_contents($temp_file, json_encode($test_data));

// Run Python and capture ALL output
$command = 'python3 ml-recommender.py ' . escapeshellarg($temp_file) . ' 2>&1';
$output = shell_exec($command);

echo "RAW OUTPUT:\n";
echo "========================================\n";
var_dump($output);
echo "========================================\n";

// Try to decode
$decoded = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "SUCCESS: Valid JSON\n";
    print_r($decoded);
} else {
    echo "ERROR: " . json_last_error_msg() . "\n";
    echo "First 500 chars of output:\n";
    echo substr($output, 0, 500) . "\n";
}

unlink($temp_file);
?>