<?php
// include __DIR__ . '/../../config/db.php';
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

class AdminDashboard
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // Get orders count for today
    public function getOrdersToday()
    {
        $query = "SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) = CURDATE() AND status = 'completed'";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }

    // Get orders count for this week
    public function getOrdersThisWeek()
    {
        $query = "SELECT COUNT(*) as count FROM orders 
                 WHERE YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1) 
                 AND status = 'completed'";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }

    // Get total sales amount
    public function getTotalSales()
    {
        $query = "SELECT COALESCE(SUM(total_price), 0) AS total FROM orders WHERE status = 'completed'";
        $result = $this->conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $this->conn->error);
        }

        $row = $result->fetch_assoc();

        // Ensure the value is properly converted to float
        $total = 0.0;
        if (isset($row['total'])) {
            // Replace comma with dot if present
            $clean = str_replace(',', '.', $row['total']);
            $total = (float) $clean;
        }

        return number_format($total, 2, '.', '');
    }

    public function getTotalFavorites()
    {
        $query = "SELECT COUNT(*) as total_favorites FROM favorites";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['total_favorites'];
    }

    // Updated getSalesChartData with proper filtering
    public function getSalesChartData($period = 'All Time')
    {
        $data = [];
        $labels = [];

        switch ($period) {
            case 'Daily':
                // Current week (Mon–Sun)
                $start = date('Y-m-d', strtotime('monday this week'));
                for ($i = 0; $i < 7; $i++) {
                    $day = date('Y-m-d', strtotime("$start +$i day"));
                    $labels[$day] = date('D', strtotime($day)); // Mon, Tue, etc.
                    $data[$day] = ['sales' => 0, 'orders' => 0];
                }

                $query = "SELECT DATE(order_date) as date, SUM(total_price) as sales, COUNT(*) as orders 
                          FROM orders 
                          WHERE order_date BETWEEN '$start' AND DATE_ADD('$start', INTERVAL 6 DAY)
                          AND status = 'completed'
                          GROUP BY DATE(order_date)";
                break;

            case 'Weekly':
                // Current month weeks (up to 5)
                $startOfMonth = date('Y-m-01');
                $endOfMonth = date('Y-m-t');
                $weeks = [];
                $data = [];

                // Find the Monday of the first week of this month
                $firstMonday = date('Y-m-d', strtotime('monday this month'));
                if (date('j', strtotime($firstMonday)) > 7) {
                    // If "monday this month" skips to next month, adjust back one week
                    $firstMonday = date('Y-m-d', strtotime('monday last month'));
                }

                // Generate week ranges (up to 5)
                for ($i = 0; $i < 5; $i++) {
                    $weekStart = date('Y-m-d', strtotime("$firstMonday +$i week"));
                    $weekEnd = date('Y-m-d', strtotime("$weekStart +6 days"));

                    if ($weekStart > $endOfMonth)
                        break;

                    $label = 'Week ' . ($i + 1);
                    $weeks[$label] = [
                        'start' => $weekStart,
                        'end' => min($weekEnd, $endOfMonth)
                    ];
                    $data[$label] = ['sales' => 0, 'orders' => 0];
                }

                // Fetch all orders for current month
                $query = "SELECT DATE(order_date) as date, SUM(total_price) as sales, COUNT(*) as orders
                          FROM orders
                          WHERE order_date BETWEEN '$startOfMonth' AND '$endOfMonth'
                          AND status = 'completed'
                          GROUP BY DATE(order_date)
                          ORDER BY date";
                $result = $this->conn->query($query);

                $raw = [];
                while ($row = $result->fetch_assoc()) {
                    $raw[] = $row;
                }

                // Assign each order to its respective week
                foreach ($raw as $row) {
                    foreach ($weeks as $label => $range) {
                        if ($row['date'] >= $range['start'] && $row['date'] <= $range['end']) {
                            $data[$label]['sales'] += (float) $row['sales'];
                            $data[$label]['orders'] += (int) $row['orders'];
                            break;
                        }
                    }
                }

                // Prepare final result for chart
                $formatted = [];
                foreach ($weeks as $label => $_) {
                    $formatted[] = [
                        'display_date' => $label,
                        'sales' => $data[$label]['sales'],
                        'orders' => $data[$label]['orders']
                    ];
                }

                return $formatted;

            case 'Monthly':
                $year = date('Y');
                for ($m = 1; $m <= 12; $m++) {
                    $monthName = date('M', mktime(0, 0, 0, $m, 10));
                    $labels[$m] = $monthName;
                    $data[$m] = ['sales' => 0, 'orders' => 0];
                }

                $query = "SELECT MONTH(order_date) as month, SUM(total_price) as sales, COUNT(*) as orders
                          FROM orders
                          WHERE YEAR(order_date) = YEAR(CURDATE())
                          AND status = 'completed'
                          GROUP BY MONTH(order_date)";
                break;

            case 'Annually':
                $currentYear = date('Y');
                for ($y = $currentYear - 5; $y <= $currentYear; $y++) {
                    $labels[$y] = $y;
                    $data[$y] = ['sales' => 0, 'orders' => 0];
                }

                $query = "SELECT YEAR(order_date) as year, SUM(total_price) as sales, COUNT(*) as orders
                          FROM orders
                          WHERE YEAR(order_date) >= YEAR(CURDATE()) - 5
                          AND status = 'completed'
                          GROUP BY YEAR(order_date)";
                break;

            default: // All Time
                $query = "SELECT DATE(order_date) as date, SUM(total_price) as sales, COUNT(*) as orders
                          FROM orders
                          WHERE status = 'completed'
                          GROUP BY DATE(order_date)
                          ORDER BY date";
                $result = $this->conn->query($query);
                while ($row = $result->fetch_assoc()) {
                    $data[] = [
                        'display_date' => date('M d', strtotime($row['date'])),
                        'sales' => $row['sales'],
                        'orders' => $row['orders']
                    ];
                }
                return $data;
        }

        // Shared code for non-weekly modes
        $result = $this->conn->query($query);
        while ($row = $result->fetch_assoc()) {
            if ($period === 'Daily') {
                $key = $row['date'];
            } elseif ($period === 'Monthly') {
                $key = (int) $row['month'];
            } else {
                $key = $row['year'];
            }

            if (isset($data[$key])) {
                $data[$key]['sales'] = (float) $row['sales'];
                $data[$key]['orders'] = (int) $row['orders'];
            }
        }

        // Format for frontend
        $formatted = [];
        foreach ($labels as $key => $label) {
            $formatted[] = [
                'display_date' => $label,
                'sales' => $data[$key]['sales'],
                'orders' => $data[$key]['orders']
            ];
        }

        return $formatted;
    }
    public function getBestSellingProducts()
    {
        $query = "
            SELECT 
                p.Name AS product_name,
                p.Category,
                SUM(oi.quantity) AS total_sold,
                COUNT(DISTINCT o.order_id) AS total_orders
            FROM orderitems oi
            INNER JOIN orders o ON oi.order_id = o.order_id
            INNER JOIN products p ON oi.product_id = p.ProductID
            WHERE o.status = 'completed'
            GROUP BY p.ProductID, p.Name, p.Category
            HAVING total_sold > 0
            ORDER BY total_sold DESC
            LIMIT 5
        ";

        $result = $this->conn->query($query);

        if (!$result) {
            throw new Exception("Best selling products query failed: " . $this->conn->error);
        }

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'product_name' => $row['product_name'],
                'Category' => $row['Category'],
                'total_quantity' => $row['total_sold'],
                'units_sold' => $row['total_sold']
            ];
        }

        return $products;
    }

    // Get store ratings summary
    public function getStoreRatings()
    {
        $query = "SELECT 
                    COUNT(*) as total_reviews,
                    COALESCE(AVG(stars), 0) as average_rating,
                    COUNT(CASE WHEN stars = 5 THEN 1 END) as '5_star',
                    COUNT(CASE WHEN stars = 4 THEN 1 END) as '4_star',
                    COUNT(CASE WHEN stars = 3 THEN 1 END) as '3_star',
                    COUNT(CASE WHEN stars = 2 THEN 1 END) as '2_star',
                    COUNT(CASE WHEN stars = 1 THEN 1 END) as '1_star'
                  FROM ratings";

        $result = $this->conn->query($query);
        return $result->fetch_assoc();
    }

    // FIXED: Get AI insights data (updated to use new database structure)
    public function getAIInsights()
    {
        $insights = [];
        $today = date('Y-m-d');

        // Helper to ensure always 5 insights
        $addInsight = function (&$insights, $message) {
            if (count($insights) < 5) {
                $insights[] = $message;
            }
        };

        // 1. CRITICAL: Low Stock Alert (Most Urgent)
        $criticalStockQuery = "
        SELECT p.ProductID, p.Name, p.Stocks, p.Category
        FROM products p 
        WHERE p.Stocks <= 5 
        AND p.Status = 'Available'
        AND p.ParentProductID IS NULL
        ORDER BY p.Stocks ASC
        LIMIT 1
    ";
        $criticalStockResult = $this->conn->query($criticalStockQuery);
        $criticalProducts = [];
        if ($criticalStockResult && $criticalStockResult->num_rows > 0) {
            while ($row = $criticalStockResult->fetch_assoc()) {
                $criticalProducts[] = $row['Name'] . " (" . $row['Stocks'] . " left)";
            }
            $addInsight($insights, "🚨 CRITICAL: Low Stock Alert! " . count($criticalProducts). implode(", ", $criticalProducts));
        } else {
            $addInsight($insights, "🚨 CRITICAL: No products are critically low in stock.");
        }

        // 2. Revenue Performance Analysis
        $revenueQuery = "
        SELECT DATE(o.order_date) as order_day,
               SUM(oi.quantity * oi.price) as daily_revenue,
               COUNT(DISTINCT o.order_id) as order_count
        FROM orders o
        JOIN orderitems oi ON o.order_id = oi.order_id
        WHERE o.status = 'completed'
        AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(o.order_date)
        ORDER BY order_day DESC
        LIMIT 7
    ";
        $revenueResult = $this->conn->query($revenueQuery);
        $revenueData = [];
        if ($revenueResult) {
            while ($row = $revenueResult->fetch_assoc()) {
                $revenueData[$row['order_day']] = $row;
            }
        }
        $todayRevenue = $revenueData[$today]['daily_revenue'] ?? 0;
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterdayRevenue = $revenueData[$yesterday]['daily_revenue'] ?? 0;
        if ($todayRevenue > 0 && $yesterdayRevenue > 0) {
            $revenueChange = (($todayRevenue - $yesterdayRevenue) / max($yesterdayRevenue, 1)) * 100;
            if ($revenueChange > 15) {
                $addInsight($insights, "📈 EXCELLENT: Revenue increased by " . round($revenueChange) . "% today! Today: ₱" . number_format($todayRevenue) . ", Yesterday: ₱" . number_format($yesterdayRevenue));
            } elseif ($revenueChange < -15) {
                $addInsight($insights, "📉 ALERT: Revenue decreased by " . abs(round($revenueChange)) . "% today. Consider promotions or marketing strategies.");
            } else {
                $addInsight($insights, "💰 Revenue steady today: ₱" . number_format($todayRevenue) . ". Monitor daily performance.");
            }
        } else {
            $addInsight($insights, "💰 Revenue data for today or yesterday is unavailable.");
        }

        // 3. Top Performing Category with Growth Analysis
        $categoryGrowthQuery = "
        SELECT p.Category,
               COUNT(oi.order_item_id) AS current_sales,
               (SELECT COUNT(oi2.order_item_id)
                FROM orderitems oi2
                JOIN products p2 ON oi2.product_id = p2.ProductID
                JOIN orders o2 ON oi2.order_id = o2.order_id
                WHERE p2.Category = p.Category
                  AND o2.order_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                  AND o2.order_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
               ) AS previous_sales
        FROM orderitems oi
        JOIN products p ON oi.product_id = p.ProductID
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
          AND o.status = 'completed'
        GROUP BY p.Category
        ORDER BY current_sales DESC
        LIMIT 3
    ";
        $categoryResult = $this->conn->query($categoryGrowthQuery);
        if ($categoryResult && $categoryResult->num_rows > 0) {
            while ($row = $categoryResult->fetch_assoc()) {
                $growth = ($row['previous_sales'] > 0) ? (($row['current_sales'] - $row['previous_sales']) / $row['previous_sales']) * 100 : 0;
                $addInsight($insights, "🏆 TOP CATEGORY: " . $row['Category'] . " with " . $row['current_sales'] . " sales this week" . ($growth != 0 ? " (" . ($growth > 0 ? "↑" : "↓") . round(abs($growth)) . "% growth)" : ""));
            }
        } else {
            $addInsight($insights, "🏆 TOP CATEGORY: No sales data available for top categories this week.");
        }

        // 4. Abandoned Cart Alert
        $abandonedCartQuery = "
        SELECT COUNT(DISTINCT user_id) as abandoned_carts
        FROM cart 
        WHERE added_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ";
        $abandonedResult = $this->conn->query($abandonedCartQuery);
        $abandonedCount = $abandonedResult->fetch_assoc()['abandoned_carts'] ?? 0;
        if ($abandonedCount > 0) {
            $addInsight($insights, "🛒 ABANDONED CARTS: " . $abandonedCount . " carts abandoned in the last 24 hours. Consider sending recovery emails.");
        } else {
            $addInsight($insights, "🛒 ABANDONED CARTS: No abandoned carts in the last 24 hours.");
        }

        // 5. Product Expiration Alert
        $expiringQuery = "
        SELECT COUNT(*) as expiring_soon
        FROM products 
        WHERE ExpirationDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          AND Status = 'Available'
    ";
        $expiringResult = $this->conn->query($expiringQuery);
        $expiringCount = $expiringResult->fetch_assoc()['expiring_soon'] ?? 0;
        if ($expiringCount > 0) {
            $addInsight($insights, "⏰ EXPIRING PRODUCTS: " . $expiringCount . " products will expire within 30 days. Plan promotions or restocking.");
        } else {
            $addInsight($insights, "⏰ EXPIRING PRODUCTS: No products expiring within 30 days.");
        }

        // Ensure always 5 items
        while (count($insights) < 5) {
            $addInsight($insights, "ℹ️ No additional critical insights at the moment.");
        }

        return $insights;
    }


    // Get top customers
    public function getTopCustomers()
    {
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

    // Get all dashboard data with period parameter
    public function getDashboardData($period = 'All Time', $chartOnly = false)
    {
        $data = [
            'chart_data' => $this->getSalesChartData($period)
        ];

        // Only fetch other data if not chart-only request
        if (!$chartOnly) {
            $data['stats'] = [
                'orders_today' => $this->getOrdersToday(),
                'orders_week' => $this->getOrdersThisWeek(),
                'total_sales' => $this->getTotalSales(),
                'total_favorites' => $this->getTotalFavorites() 
            ];
            $data['best_sellers'] = $this->getBestSellingProducts();
            $data['ratings'] = $this->getStoreRatings();
            $data['ai_insights'] = $this->getAIInsights();
            $data['top_customers'] = $this->getTopCustomers();
        }

        return $data;
    }
}

try {
    if (!isset($conn)) {
        throw new Exception('Database connection not established');
    }

    // Get period from query parameter
    $period = isset($_GET['period']) ? $_GET['period'] : 'All Time';
    $chartOnly = isset($_GET['chartOnly']) ? filter_var($_GET['chartOnly'], FILTER_VALIDATE_BOOLEAN) : false;

    $dashboard = new AdminDashboard($conn);
    $data = $dashboard->getDashboardData($period, $chartOnly);

    echo json_encode([
        'success' => true,
        'data' => $data,
        'period' => $period
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}

if (isset($conn)) {
    $conn->close();
}
?>