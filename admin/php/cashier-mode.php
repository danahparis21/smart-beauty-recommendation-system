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

function getPublicImagePath($dbPath)
{
    if (empty($dbPath))
        return '';

    // Handle old paths with '../'
    if (strpos($dbPath, '../') === 0) {
        // Convert ../uploads/... to /admin/uploads/...
        return str_replace('../', '/admin/', $dbPath);
    }

    // Handle new paths that already start with '/'
    if (strpos($dbPath, '/') === 0) {
        return $dbPath;
    }

    // Fallback: add leading slash
    return '/' . $dbPath;
}

class CashierMode
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

   

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
                COALESCE(pm.ImagePath, '/admin/uploads/product_images/no-image.png') as ImagePath
            FROM Products p
            LEFT JOIN ProductMedia pm ON p.ProductID = pm.VariantProductID 
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
                $parentQuery = "SELECT Name FROM Products WHERE ProductID = ?";
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
            $row['ImagePath'] = getPublicImagePath($row['ImagePath']);
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

    // Process a sale - now handles both manual and QR code orders
    public function processSale($saleData)
    {
        $this->conn->begin_transaction();

        try {
            $paymentMethod = $saleData['payment_method'] ?? 'manual';
            $orderId = $saleData['order_id'] ?? null;

            // Validate all items have sufficient stock
            foreach ($saleData['items'] as $item) {
                $productId = $this->conn->real_escape_string($item['product_id']);
                $quantity = intval($item['quantity']);
                
                $stockQuery = "SELECT Stocks, Name FROM Products WHERE ProductID = '$productId'";
                $stockResult = $this->conn->query($stockQuery);
                
                if (!$stockResult || $stockResult->num_rows === 0) {
                    throw new Exception("Product not found: $productId");
                }
                
                $product = $stockResult->fetch_assoc();
                if ($product['Stocks'] < $quantity) {
                    throw new Exception("Insufficient stock for {$product['Name']}. Available: {$product['Stocks']}, Requested: $quantity");
                }
            }

            if ($paymentMethod === 'qr' && $orderId) {
                // Handle QR code order - update existing order status
                return $this->processQrOrder($orderId, $saleData);
            } else {
                // Handle manual sale - create new order
                return $this->processManualSale($saleData);
            }

        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Process manual sale (create new order)
    private function processManualSale($saleData)
    {
        $tempUserId = 1; // Default admin user for cashier sales
        $totalPrice = 0;
        
        foreach ($saleData['items'] as $item) {
            $totalPrice += floatval($item['price']) * intval($item['quantity']);
        }

        // Create order record
        $orderQuery = "INSERT INTO orders (user_id, total_price, status, order_date, payment_method) 
                      VALUES (?, ?, 'completed', NOW(), 'manual')";
        $stmt = $this->conn->prepare($orderQuery);
        $stmt->bind_param("id", $tempUserId, $totalPrice);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create order: " . $stmt->error);
        }
        
        $orderId = $stmt->insert_id;
        $stmt->close();

        // Add order items and update stock
        $this->addOrderItemsAndUpdateStock($orderId, $saleData['items']);

        // Log activity
        $activityQuery = "
            INSERT INTO activitylog (actor_type, actor_id, action, details) 
            VALUES ('admin', ?, 'Manual sale processed', ?)
        ";
        $activityDetails = "Processed manual sale with order ID: $orderId, Total: ₱" . number_format($totalPrice, 2);
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
            'payment_method' => 'manual',
            'message' => 'Manual sale completed successfully'
        ];
    }

    // Process QR code order (update existing order)
    private function processQrOrder($orderId, $saleData)
    {
        // Verify the order exists and is not already completed
        $verifyQuery = "SELECT status FROM orders WHERE order_id = ?";
        $stmt = $this->conn->prepare($verifyQuery);
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Order not found: $orderId");
        }
        
        $order = $result->fetch_assoc();
        if ($order['status'] === 'completed') {
            throw new Exception("Order #$orderId is already completed");
        }
        $stmt->close();

        // Update order status to completed
        $updateQuery = "UPDATE orders SET status = 'completed', payment_method = 'qr' WHERE order_id = ?";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bind_param("i", $orderId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order status: " . $stmt->error);
        }
        $stmt->close();

        // Update product stock for QR order items
        $this->updateStockForItems($saleData['items']);

        // Log activity
        $tempUserId = 1; // Default admin user
        $activityQuery = "
            INSERT INTO activitylog (actor_type, actor_id, action, details) 
            VALUES ('admin', ?, 'QR order completed', ?)
        ";
        $activityDetails = "Completed QR order ID: $orderId via cashier mode";
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
            'payment_method' => 'qr',
            'message' => 'QR order completed successfully'
        ];
    }

    // Add order items and update stock (for manual sales)
    private function addOrderItemsAndUpdateStock($orderId, $items)
    {
        foreach ($items as $item) {
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
            $this->updateProductStock($productId, $quantity);
        }
    }

    // Update stock for items (for QR orders)
    private function updateStockForItems($items)
    {
        foreach ($items as $item) {
            $productId = $this->conn->real_escape_string($item['product_id']);
            $quantity = intval($item['quantity']);
            
            $this->updateProductStock($productId, $quantity);
        }
    }

    // Update product stock and status
    private function updateProductStock($productId, $quantity)
    {
        // Update product stock
        $updateStockQuery = "UPDATE Products 
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
            UPDATE Products 
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
}

// Test database connection first
try {
    if (!isset($conn)) {
        throw new Exception('Database connection not established');
    }

    // Test if we can query the products table
    $testQuery = "SELECT COUNT(*) as count FROM Products WHERE Status IN ('Available', 'Low Stock') AND Stocks > 0 LIMIT 1";
    $testResult = $conn->query($testQuery);
    
    if (!$testResult) {
        throw new Exception('Cannot query Products table: ' . $conn->error);
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
            $testQuery = "SELECT COUNT(*) as product_count FROM Products WHERE Status IN ('Available', 'Low Stock') AND Stocks > 0";
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