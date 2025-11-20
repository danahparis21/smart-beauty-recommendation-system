<?php
// update_all_ratings.php - Update ALL product ratings at once

// Auto-switch between Docker and XAMPP
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

header('Content-Type: application/json');

try {
    $updateStmt = $conn->prepare("
        UPDATE products p
        SET 
            p.ProductRating = COALESCE(
                (SELECT ROUND(AVG(r.stars), 1) FROM ratings r WHERE r.product_id = p.ProductID AND r.stars > 0),
                0
            ),
            p.UpdatedAt = NOW()
        WHERE p.Status = 'Available'
    ");
    
    if ($updateStmt->execute()) {
        $affectedRows = $conn->affected_rows;
        echo json_encode([
            'success' => true,
            'message' => "Updated ratings for {$affectedRows} products",
            'affected_rows' => $affectedRows
        ]);
    } else {
        throw new Exception('Update failed: ' . $conn->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>