<?php
// Include the database connection - adjust path based on your structure
// If db.php is in smart-beauty-recommendation-system/config/db.php
include __DIR__ . '/../../config/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

class AdminDashboard {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Get orders count for today
    public function getOrdersToday() {
        $query = "SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) = CURDATE() AND status = 'completed'";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    // Get orders count for this week
    public function getOrdersThisWeek() {
        $query = "SELECT COUNT(*) as count FROM orders 
                 WHERE YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1) 
                 AND status = 'completed'";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    // Get total sales amount
    public function getTotalSales() {
        $query = "SELECT COALESCE(SUM(total_price), 0) as total FROM orders WHERE status = 'completed'";
        $result = $this->conn->query($query);
        return number_format($result->fetch_assoc()['total'], 2);
    }
    
    // Get QR codes count
    public function getQRCodeCount() {
        $query = "SELECT COUNT(*) as count FROM orders WHERE qr_code IS NOT NULL AND qr_code != ''";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    // Get sales data for chart (last 7 days)
    public function getSalesChartData() {
        $query = "SELECT 
                    DATE(order_date) as date,
                    COALESCE(SUM(total_price), 0) as sales,
                    COUNT(*) as orders
                  FROM orders 
                  WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  AND status = 'completed'
                  GROUP BY DATE(order_date)
                  ORDER BY date";
        
        $result = $this->conn->query($query);
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }
    
    // Get best selling products
    public function getBestSellingProducts() {
        $query = "SELECT 
                    p.Name as product_name,
                    p.Category,
                    COUNT(oi.order_item_id) as units_sold,
                    SUM(oi.quantity) as total_quantity
                  FROM orderitems oi
                  JOIN products p ON oi.product_id = p.ProductID
                  JOIN orders o ON oi.order_id = o.order_id
                  WHERE o.status = 'completed'
                  GROUP BY p.ProductID, p.Name, p.Category
                  ORDER BY units_sold DESC
                  LIMIT 5";
        
        $result = $this->conn->query($query);
        $products = [];
        
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        
        return $products;
    }
    
    // Get store ratings summary - FIXED column names
    public function getStoreRatings() {
        $query = "SELECT 
                    COUNT(*) as total_reviews,
                    COALESCE(AVG(stars), 0) as average_rating,
                    COUNT(CASE WHEN stars = 5 THEN 1 END) as five_star,
                    COUNT(CASE WHEN stars = 4 THEN 1 END) as four_star,
                    COUNT(CASE WHEN stars = 3 THEN 1 END) as three_star,
                    COUNT(CASE WHEN stars = 2 THEN 1 END) as two_star,
                    COUNT(CASE WHEN stars = 1 THEN 1 END) as one_star
                  FROM ratings";
        
        $result = $this->conn->query($query);
        return $result->fetch_assoc();
    }
    
    // Get AI insights data
    public function getAIInsights() {
        $insights = [];
        
        // Top category insight
        $categoryQuery = "SELECT 
                           p.Category,
                           COUNT(oi.order_item_id) as sales
                         FROM orderitems oi
                         JOIN products p ON oi.product_id = p.ProductID
                         JOIN orders o ON oi.order_id = o.order_id
                         WHERE o.status = 'completed'
                         GROUP BY p.Category
                         ORDER BY sales DESC
                         LIMIT 1";
        
        $categoryResult = $this->conn->query($categoryQuery);
        if ($categoryRow = $categoryResult->fetch_assoc()) {
            $insights[] = "🔥 " . $categoryRow['Category'] . " is your best-performing category with " . $categoryRow['sales'] . " sales";
        }
        
        // Low stock alert
        $lowStockQuery = "SELECT COUNT(*) as low_stock_count 
                         FROM products 
                         WHERE Stocks <= 10 AND Status = 'Available'";
        $lowStockResult = $this->conn->query($lowStockQuery);
        $lowStockCount = $lowStockResult->fetch_assoc()['low_stock_count'];
        
        if ($lowStockCount > 0) {
            $insights[] = "⚠️ " . $lowStockCount . " products are running low on stock";
        }
        
        // Sales trend
        $todaySales = $this->getOrdersToday();
        $yesterdayQuery = "SELECT COUNT(*) as count FROM orders 
                          WHERE DATE(order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) 
                          AND status = 'completed'";
        $yesterdayResult = $this->conn->query($yesterdayQuery);
        $yesterdaySales = $yesterdayResult->fetch_assoc()['count'];
        
        if ($todaySales > $yesterdaySales) {
            $insights[] = "📈 Sales are trending up compared to yesterday";
        } elseif ($todaySales < $yesterdaySales) {
            $insights[] = "📉 Consider promotions - sales are lower than yesterday";
        }
        
        return $insights;
    }
    
    // Get top customers
    public function getTopCustomers() {
        $query = "SELECT 
                    u.username,
                    u.first_name,
                    u.last_name,
                    COUNT(o.order_id) as total_orders,
                    COALESCE(SUM(o.total_price), 0) as total_spent
                  FROM orders o
                  JOIN users u ON o.user_id = u.UserID
                  WHERE o.status = 'completed'
                  GROUP BY u.UserID, u.username, u.first_name, u.last_name
                  ORDER BY total_spent DESC
                  LIMIT 5";
        
        $result = $this->conn->query($query);
        $customers = [];
        
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        
        return $customers;
    }
    
    // Get all dashboard data
    public function getDashboardData() {
        return [
            'stats' => [
                'orders_today' => $this->getOrdersToday(),
                'orders_week' => $this->getOrdersThisWeek(),
                'total_sales' => $this->getTotalSales(),
                'qr_codes' => $this->getQRCodeCount()
            ],
            'chart_data' => $this->getSalesChartData(),
            'best_sellers' => $this->getBestSellingProducts(),
            'ratings' => $this->getStoreRatings(),
            'ai_insights' => $this->getAIInsights(),
            'top_customers' => $this->getTopCustomers()
        ];
    }
}

try {
    // Check if connection exists
    if (!isset($conn)) {
        throw new Exception('Database connection not established');
    }
    
    $dashboard = new AdminDashboard($conn);
    $data = $dashboard->getDashboardData();
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString() // For debugging
    ], JSON_PRETTY_PRINT);
}

if (isset($conn)) {
    $conn->close();
}
?>