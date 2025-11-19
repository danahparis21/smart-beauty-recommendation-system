<?php
// Include database connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

class CashierMode
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Get all available products for sale (including variants)
   // Get all available products for sale (including variants)
    public function getProducts()
    {
        $query = "
            SELECT 
                p.ProductID,
                p.Name,
                p.Category,
                p.ParentProductID,
                p.ShadeOrVariant,
                p.Price,
                p.Stocks,
                p.Status,
                COALESCE(pm.ImagePath, '../uploads/product_images/no-image.png') as ImagePath
            FROM products p
            LEFT JOIN productmedia pm ON p.ProductID = pm.VariantProductID 
                AND pm.MediaType = 'VARIANT'
            WHERE p.ShadeOrVariant != 'PARENT_GROUP'
            AND p.Status != 'Deleted'
            AND p.Stocks >= 0
            ORDER BY 
                CASE 
                    WHEN p.Status = 'Available' THEN 1
                    WHEN p.Status = 'Low Stock' THEN 2
                    ELSE 3
                END,
                p.Name, 
                p.ShadeOrVariant
        ";

        error_log("Executing query: " . $query);

        $result = $this->conn->query($query);

        if (!$result) {
            $error = $this->conn->error;
            error_log("Query failed: " . $error);
            throw new Exception("Query failed: " . $error);
        }

        $products = [];
        $count = 0;
        
        while ($row = $result->fetch_assoc()) {
            $count++;
            
            // Format display name
            if ($row['ParentProductID']) {
                // Get parent name for better display
                $parentQuery = "SELECT Name FROM products WHERE ProductID = ?";
                $stmt = $this->conn->prepare($parentQuery);
                $stmt->bind_param("s", $row['ParentProductID']);
                $stmt->execute();
                $parentResult = $stmt->get_result();
                
                if ($parentResult && $parentRow = $parentResult->fetch_assoc()) {
                    $row['DisplayName'] = $parentRow['Name'] . ' - ' . $row['ShadeOrVariant'];
                } else {
                    $row['DisplayName'] = $row['Name'] . ' - ' . $row['ShadeOrVariant'];
                }
                $stmt->close();
            } else {
                $row['DisplayName'] = $row['Name'];
            }
            
            $products[] = $row;
        }

        error_log("Found $count products");

        // Get unique categories
        $categories = [];
        foreach ($products as $product) {
            if (!in_array($product['Category'], $categories)) {
                $categories[] = $product['Category'];
            }
        }
        sort($categories);

        return [
            'products' => $products,
            'categories' => $categories
        ];
    }

    // Process a sale
    public function processSale($saleData)
    {
        $this->conn->begin_transaction();

        try {
            // Validate all items have sufficient stock
            foreach ($saleData['items'] as $item) {
                $productId = $this->conn->real_escape_string($item['product_id']);
                $quantity = intval($item['quantity']);
                
                $stockQuery = "SELECT Stocks, Name FROM products WHERE ProductID = '$productId'";
                $stockResult = $this->conn->query($stockQuery);
                
                if (!$stockResult || $stockResult->num_rows === 0) {
                    throw new Exception("Product not found: $productId");
                }
                
                $product = $stockResult->fetch_assoc();
                if ($product['Stocks'] < $quantity) {
                    throw new Exception("Insufficient stock for {$product['Name']}. Available: {$product['Stocks']}, Requested: $quantity");
                }
            }

            // Create order record (using a temporary user ID for cashier sales)
            $tempUserId = 1; // Default admin user for cashier sales
            $totalPrice = 0;
            
            foreach ($saleData['items'] as $item) {
                $totalPrice += floatval($item['price']) * intval($item['quantity']);
            }

            $orderQuery = "INSERT INTO orders (user_id, total_price, status, order_date) 
                          VALUES (?, ?, 'completed', NOW())";
            $stmt = $this->conn->prepare($orderQuery);
            $stmt->bind_param("id", $tempUserId, $totalPrice);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create order: " . $stmt->error);
            }
            
            $orderId = $stmt->insert_id;
            $stmt->close();

            // Add order items and update stock
            foreach ($saleData['items'] as $item) {
                $productId = $this->conn->real_escape_string($item['product_id']);
                $quantity = intval($item['quantity']);
                $price = floatval($item['price']);
                
                // Add order item
                $orderItemQuery = "INSERT INTO orderitems (order_id, product_id, quantity, price) 
                                  VALUES (?, ?, ?, ?)";
                $stmt = $this->conn->prepare($orderItemQuery);
                $stmt->bind_param("isid", $orderId, $productId, $quantity, $price);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add order item: " . $stmt->error);
                }
                $stmt->close();
                
                // Update product stock
                $updateStockQuery = "UPDATE products 
                                   SET Stocks = Stocks - ? 
                                   WHERE ProductID = ? AND Stocks >= ?";
                $stmt = $this->conn->prepare($updateStockQuery);
                $stmt->bind_param("isi", $quantity, $productId, $quantity);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update stock: " . $stmt->error);
                }
                $stmt->close();
                
                // Update product status if stock becomes low or out
                $updateStatusQuery = "
                    UPDATE products 
                    SET Status = CASE 
                        WHEN Stocks <= 0 THEN 'No Stock'
                        WHEN Stocks <= 5 THEN 'Low Stock' 
                        ELSE 'Available'
                    END
                    WHERE ProductID = ?
                ";
                $stmt = $this->conn->prepare($updateStatusQuery);
                $stmt->bind_param("s", $productId);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update product status: " . $stmt->error);
                }
                $stmt->close();
            }

            // Log activity
            $activityQuery = "
                INSERT INTO activitylog (actor_type, actor_id, action, details) 
                VALUES ('admin', ?, 'Cashier sale processed', ?)
            ";
            $activityDetails = "Processed sale with order ID: $orderId, Total: ₱" . number_format($totalPrice, 2);
            $stmt = $this->conn->prepare($activityQuery);
            $stmt->bind_param("is", $tempUserId, $activityDetails);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to log activity: " . $stmt->error);
            }
            $stmt->close();

            $this->conn->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'total' => $totalPrice,
                'message' => 'Sale completed successfully'
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}

// Test database connection first
try {
    if (!isset($conn)) {
        throw new Exception('Database connection not established');
    }

    // Test if we can query the products table
    $testQuery = "SELECT COUNT(*) as count FROM products WHERE Status IN ('Available', 'Low Stock') AND Stocks > 0 LIMIT 1";
    $testResult = $conn->query($testQuery);
    
    if (!$testResult) {
        throw new Exception('Cannot query products table: ' . $conn->error);
    }

    $cashier = new CashierMode($conn);
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($action) {
        case 'get_products':
            $data = $cashier->getProducts();
            echo json_encode([
                'success' => true,
                'products' => $data['products'],
                'categories' => $data['categories'],
                'debug' => [
                    'total_products' => count($data['products']),
                    'total_categories' => count($data['categories'])
                ]
            ]);
            break;

        case 'process_sale':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['items'])) {
                throw new Exception('Invalid sale data');
            }

            $result = $cashier->processSale($input);
            echo json_encode($result);
            break;

        case 'test_connection':
            // Simple test endpoint
            $testQuery = "SELECT COUNT(*) as product_count FROM products WHERE Status IN ('Available', 'Low Stock') AND Stocks > 0";
            $result = $conn->query($testQuery);
            $row = $result->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'message' => 'Database connection successful',
                'available_products' => $row['product_count']
            ]);
            break;

        default:
            throw new Exception('Invalid action. Valid actions: get_products, process_sale, test_connection');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
?>