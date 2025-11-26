<?php

session_start();

// Prevent caching for admin pages
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied. Admin privileges required.']));
}

$adminId = $_SESSION['user_id'];

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

    public function getOrdersToday($period = 'All Time')
    {
        if ($period === 'All Time') {
            $query = "SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) = CURDATE() AND status = 'completed'";
        } else {
            $dateFilter = $this->getDateFilterForPeriod($period);
            $query = "SELECT COUNT(*) as count FROM orders WHERE $dateFilter AND status = 'completed'";
        }
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }

    public function getOrdersThisWeek($period = 'All Time')
    {
        if ($period === 'All Time') {
            $query = "SELECT COUNT(*) as count FROM orders WHERE YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1) AND status = 'completed'";
        } else {
            $dateFilter = $this->getDateFilterForPeriod($period);
            $query = "SELECT COUNT(*) as count FROM orders WHERE $dateFilter AND status = 'completed'";
        }
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }

    public function getTotalSales($period = 'All Time')
    {
        if ($period === 'All Time') {
            $query = "SELECT COALESCE(SUM(total_price), 0) AS total FROM orders WHERE status = 'completed'";
        } else {
            $dateFilter = $this->getDateFilterForPeriod($period);
            $query = "SELECT COALESCE(SUM(total_price), 0) AS total FROM orders WHERE $dateFilter AND status = 'completed'";
        }
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        $clean = str_replace(',', '.', $row['total']);
        return number_format((float) $clean, 2, '.', '');
    }

    private function getDateFilterForPeriod($period)
    {
        switch ($period) {
            case 'Daily':
                $start = date('Y-m-d', strtotime('monday this week'));
                $end = date('Y-m-d', strtotime("$start +6 days"));
                return "DATE(order_date) BETWEEN '$start' AND '$end'";
            case 'Weekly':
                $startOfMonth = date('Y-m-01');
                $endOfMonth = date('Y-m-t');
                return "DATE(order_date) BETWEEN '$startOfMonth' AND '$endOfMonth'";
            case 'Monthly':
                $year = date('Y');
                return "YEAR(order_date) = $year";
            case 'Annually':
                $currentYear = date('Y');
                $startYear = $currentYear - 5;
                return "YEAR(order_date) BETWEEN $startYear AND $currentYear";
            default:
                return '1=1';
        }
    }

    public function getTotalFavorites()
    {
        $query = 'SELECT COUNT(*) as total_favorites FROM favorites';
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['total_favorites'];
    }

    public function getSalesChartData($period = 'All Time')
    {
        $data = [];
        $labels = [];
        switch ($period) {
            case 'Daily':
                $start = date('Y-m-d', strtotime('monday this week'));
                for ($i = 0; $i < 7; $i++) {
                    $day = date('Y-m-d', strtotime("$start +$i day"));
                    $labels[$day] = date('D', strtotime($day));
                    $data[$day] = ['sales' => 0, 'orders' => 0];
                }
                $query = "SELECT DATE(order_date) as date, SUM(total_price) as sales, COUNT(*) as orders FROM orders WHERE order_date BETWEEN '$start' AND DATE_ADD('$start', INTERVAL 6 DAY) AND status = 'completed' GROUP BY DATE(order_date)";
                break;
            case 'Weekly':
    $startOfMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');
    
    // Create 4-5 weeks covering the entire month
    $weeks = [];
    $currentDate = $startOfMonth;
    $weekNumber = 1;
    
    while ($currentDate <= $endOfMonth) {
        $weekEnd = date('Y-m-d', strtotime($currentDate . ' +6 days'));
        if ($weekEnd > $endOfMonth) {
            $weekEnd = $endOfMonth;
        }
        
        $label = 'Week ' . $weekNumber;
        $weeks[$label] = ['start' => $currentDate, 'end' => $weekEnd];
        
        // Move to next week
        $currentDate = date('Y-m-d', strtotime($weekEnd . ' +1 day'));
        $weekNumber++;
        
        // Safety break
        if ($weekNumber > 6) break;
    }
    
    // Initialize data structure
    $data = [];
    foreach ($weeks as $label => $range) {
        $data[$label] = ['sales' => 0, 'orders' => 0];
    }
    
    // Debug: Check what weeks are being generated
    error_log("Generated weeks: " . print_r($weeks, true));
    
    // Fetch orders for the entire month
    $query = "SELECT DATE(order_date) as date, SUM(total_price) as sales, COUNT(*) as orders 
              FROM orders 
              WHERE order_date BETWEEN '$startOfMonth' AND '$endOfMonth 23:59:59' 
              AND status = 'completed' 
              GROUP BY DATE(order_date) 
              ORDER BY date";
    
    $result = $this->conn->query($query);
    
    // Debug: Check query results
    $rawData = [];
    while ($row = $result->fetch_assoc()) {
        $rawData[] = $row;
    }
    error_log("Raw order data: " . print_r($rawData, true));
    
    // Assign orders to weeks
    foreach ($rawData as $row) {
        $orderDate = $row['date'];
        
        foreach ($weeks as $label => $range) {
            if ($orderDate >= $range['start'] && $orderDate <= $range['end']) {
                $data[$label]['sales'] += (float) $row['sales'];
                $data[$label]['orders'] += (int) $row['orders'];
                break;
            }
        }
    }
    
    // Format output
    $formatted = [];
    foreach ($data as $label => $stats) {
        $formatted[] = [
            'display_date' => $label,
            'sales' => $stats['sales'],
            'orders' => $stats['orders']
        ];
    }
    
    // Debug: Final output
    error_log("Final weekly data: " . print_r($formatted, true));
    
    return $formatted;
            case 'Monthly':
                $year = date('Y');
                for ($m = 1; $m <= 12; $m++) {
                    $monthName = date('M', mktime(0, 0, 0, $m, 10));
                    $labels[$m] = $monthName;
                    $data[$m] = ['sales' => 0, 'orders' => 0];
                }
                $query = "SELECT MONTH(order_date) as month, SUM(total_price) as sales, COUNT(*) as orders FROM orders WHERE YEAR(order_date) = YEAR(CURDATE()) AND status = 'completed' GROUP BY MONTH(order_date)";
                break;
            case 'Annually':
                $currentYear = date('Y');
                for ($y = $currentYear - 5; $y <= $currentYear; $y++) {
                    $labels[$y] = $y;
                    $data[$y] = ['sales' => 0, 'orders' => 0];
                }
                $query = "SELECT YEAR(order_date) as year, SUM(total_price) as sales, COUNT(*) as orders FROM orders WHERE YEAR(order_date) >= YEAR(CURDATE()) - 5 AND status = 'completed' GROUP BY YEAR(order_date)";
                break;
            default:
                $query = "SELECT DATE(order_date) as date, SUM(total_price) as sales, COUNT(*) as orders FROM orders WHERE status = 'completed' GROUP BY DATE(order_date) ORDER BY date";
                $result = $this->conn->query($query);
                while ($row = $result->fetch_assoc()) {
                    $data[] = ['display_date' => date('M d', strtotime($row['date'])), 'sales' => $row['sales'], 'orders' => $row['orders']];
                }
                return $data;
        }
        $result = $this->conn->query($query);
        while ($row = $result->fetch_assoc()) {
            $key = ($period === 'Daily') ? $row['date'] : (($period === 'Monthly') ? (int) $row['month'] : $row['year']);
            if (isset($data[$key])) {
                $data[$key]['sales'] = (float) $row['sales'];
                $data[$key]['orders'] = (int) $row['orders'];
            }
        }
        $formatted = [];
        foreach ($labels as $key => $label) {
            $formatted[] = ['display_date' => $label, 'sales' => $data[$key]['sales'], 'orders' => $data[$key]['orders']];
        }
        return $formatted;
    }

    public function getBestSellingProducts()
    {
        $query = "SELECT p.Name AS product_name, p.Category, SUM(oi.quantity) AS total_sold, COUNT(DISTINCT o.order_id) AS total_orders FROM orderitems oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN Products p ON oi.product_id = p.ProductID WHERE o.status = 'completed' GROUP BY p.ProductID, p.Name, p.Category HAVING total_sold > 0 ORDER BY total_sold DESC LIMIT 5";
        $result = $this->conn->query($query);
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

    public function getStoreRatings()
    {
        $query = "SELECT COUNT(*) as total_reviews, COALESCE(AVG(rating), 0) as average_rating,
                    COUNT(CASE WHEN rating = 5 THEN 1 END) as '5_star',
                    COUNT(CASE WHEN rating = 4 THEN 1 END) as '4_star',
                    COUNT(CASE WHEN rating = 3 THEN 1 END) as '3_star',
                    COUNT(CASE WHEN rating = 2 THEN 1 END) as '2_star',
                    COUNT(CASE WHEN rating = 1 THEN 1 END) as '1_star'
                  FROM store_ratings";
        $result = $this->conn->query($query);
        $ratings = $result->fetch_assoc();

        // ✅ FIXED: Changed user_image to profile_photo (matches database schema)
        $recentQuery = 'SELECT sr.rating, sr.comment, sr.created_at, u.username, u.profile_photo 
                        FROM store_ratings sr 
                        LEFT JOIN users u ON sr.user_id = u.UserID 
                        ORDER BY sr.created_at DESC 
                        LIMIT 2';
        $recentResult = $this->conn->query($recentQuery);
        $recentReviews = [];
        while ($row = $recentResult->fetch_assoc()) {
            $recentReviews[] = $row;
        }

        $ratings['recent_reviews'] = $recentReviews;
        return $ratings;
    }

    public function getAllReviews($ratingFilter = 'all', $sort = 'newest')
    {
        $whereClause = '1=1';

        if ($ratingFilter !== 'all') {
            $rating = intval($ratingFilter);
            $whereClause .= " AND sr.rating = $rating";
        }

        $orderBy = ($sort === 'oldest') ? 'sr.created_at ASC' : 'sr.created_at DESC';

        $query = "SELECT sr.rating, sr.comment, sr.created_at, u.username, u.profile_photo 
                  FROM store_ratings sr 
                  LEFT JOIN users u ON sr.user_id = u.UserID 
                  WHERE $whereClause 
                  ORDER BY $orderBy";

        $result = $this->conn->query($query);
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        return $reviews;
    }

    public function getCategorySales($period = 'All Time')
    {
        $dateFilter = $this->getDateFilterForPeriod($period);
        $query = "SELECT p.Category, SUM(oi.quantity) as total_items_sold 
                  FROM orderitems oi 
                  JOIN Products p ON oi.product_id = p.ProductID 
                  JOIN orders o ON oi.order_id = o.order_id 
                  WHERE o.status = 'completed' AND $dateFilter 
                  GROUP BY p.Category 
                  ORDER BY total_items_sold DESC";
        $result = $this->conn->query($query);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }

    public function getCustomerDemographics($period = 'All Time')
    {
        $dateFilter = $this->getDateFilterForPeriod($period);
        $query = "SELECT up.skin_type, COUNT(DISTINCT up.user_id) as count 
                  FROM user_preferences up 
                  JOIN users u ON up.user_id = u.UserID 
                  JOIN orders o ON u.UserID = o.user_id 
                  WHERE $dateFilter 
                  GROUP BY up.skin_type";
        $result = $this->conn->query($query);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }

    public function getOrderStatusDistribution($period = 'All Time')
    {
        $dateFilter = $this->getDateFilterForPeriod($period);
        $query = "SELECT status, COUNT(*) as count 
                  FROM orders 
                  WHERE $dateFilter 
                  GROUP BY status";
        $result = $this->conn->query($query);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }

    public function getAIInsights($period = 'All Time')
    {
        $insights = [];
        $addInsight = function (&$insights, $message) {
            if (count($insights) < 5)
                $insights[] = $message;
        };

        // Critical stock alert
        $criticalStockQuery = "SELECT Name, Stocks 
                               FROM Products 
                               WHERE Stocks <= 5 
                               AND Status = 'Available' 
                               AND ParentProductID IS NULL 
                               ORDER BY Stocks ASC 
                               LIMIT 1";
        $critRes = $this->conn->query($criticalStockQuery);
        if ($critRes && $critRes->num_rows > 0) {
            $row = $critRes->fetch_assoc();
            $addInsight($insights, '🚨 CRITICAL: Low Stock! ' . $row['Name'] . ' has only ' . $row['Stocks'] . ' left.');
        }

        // Revenue comparison
        $dateFilter = $this->getDateFilterForPeriod($period);
        $todayRevenue = 0;
        $yesterdayRevenue = 0;
        $revQuery = "SELECT DATE(order_date) as date, SUM(total_price) as rev 
                     FROM orders 
                     WHERE status='completed' AND $dateFilter 
                     GROUP BY DATE(order_date)";
        $revRes = $this->conn->query($revQuery);
        while ($row = $revRes->fetch_assoc()) {
            if ($row['date'] == date('Y-m-d')) {
                $todayRevenue = $row['rev'];
            } else {
                $yesterdayRevenue = $row['rev'];
            }
        }
        if ($todayRevenue > $yesterdayRevenue && $yesterdayRevenue > 0) {
            $pct = round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100);
            $addInsight($insights, "📈 Revenue is up {$pct}% compared to yesterday.");
        }

        // Top category
        $catQuery = "SELECT p.Category, COUNT(oi.order_item_id) as cnt 
                     FROM orderitems oi 
                     JOIN Products p ON oi.product_id=p.ProductID 
                     JOIN orders o ON oi.order_id=o.order_id 
                     WHERE o.status='completed' AND $dateFilter 
                     GROUP BY p.Category 
                     ORDER BY cnt DESC 
                     LIMIT 1";
        $catRes = $this->conn->query($catQuery);
        if ($catRes && $catRes->num_rows > 0) {
            $row = $catRes->fetch_assoc();
            $addInsight($insights, '🏆 Top Category this week: ' . $row['Category']);
        }

        // Expiring Products
        $expQuery = "SELECT COUNT(*) as cnt 
                     FROM Products 
                     WHERE ExpirationDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                     AND Status='Available'";
        $expRes = $this->conn->query($expQuery);
        $expCnt = $expRes->fetch_assoc()['cnt'];
        if ($expCnt > 0) {
            $addInsight($insights, "⏰ {$expCnt} Products expiring within 30 days.");
        }

        // Fill remaining slots
        while (count($insights) < 5) {
            $addInsight($insights, 'ℹ️ Monitor sales trends to unlock more insights.');
        }

        return $insights;
    }

    // ✅ FIXED: Changed user_image to profile_photo (matches database schema)
    public function getTopCustomers($period = 'All Time')
    {
        $dateFilter = $this->getDateFilterForPeriod($period);

        $query = "SELECT 
                    u.username, 
                    u.first_name, 
                    u.last_name, 
                    u.profile_photo, 
                    COUNT(o.order_id) AS total_orders, 
                    COALESCE(SUM(o.total_price), 0) AS total_spent
                FROM orders o
                JOIN users u ON o.user_id = u.UserID
                WHERE o.status = 'completed'
                AND u.Role != 'admin'
                AND $dateFilter
                GROUP BY u.UserID, u.username, u.first_name, u.last_name, u.profile_photo
                ORDER BY total_spent DESC
                LIMIT 5";

        $result = $this->conn->query($query);

        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }

        return $customers;
    }

    public function getRecentAdminActivity()
    {
        $query = "SELECT actor_type, actor_id, action, details, timestamp 
                  FROM activitylog 
                  WHERE actor_type = 'admin' 
                  ORDER BY timestamp DESC 
                  LIMIT 5";
        
        $result = $this->conn->query($query);
        $activities = [];
        
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'type' => $this->getActivityType($row['action']),
                'title' => $this->getActivityTitle($row['action'], $row['details']),
                'details' => $row['details'],
                'timestamp' => $row['timestamp']
            ];
        }
        
        return $activities;
    }
    
    private function getActivityType($action)
    {
        if (strpos($action, 'review') !== false) return 'reviewed';
        if (strpos($action, 'update') !== false) return 'updated';
        if (strpos($action, 'create') !== false) return 'created';
        if (strpos($action, 'cancel') !== false) return 'cancelled';
        if (strpos($action, 'process') !== false) return 'processed';
        return 'general';
    }
    
    private function getActivityTitle($action, $details)
    {
        if (strpos($action, 'review') !== false) return 'Reviewed a request';
        if (strpos($action, 'update') !== false) return 'Updated information';
        if (strpos($action, 'create') !== false) return 'Created new entry';
        if (strpos($action, 'cancel') !== false) return 'Cancelled booking';
        if (strpos($action, 'process') !== false) return 'Processed transaction';
        return 'Performed action';
    }
    
    public function getExpiringProductsDates()
{
    $query = "SELECT ProductID, Name, Category, Stocks, ExpirationDate 
              FROM Products 
              WHERE ExpirationDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) 
              AND Status = 'Available'
              ORDER BY ExpirationDate ASC";
    
    $result = $this->conn->query($query);
    $productsByDate = [];
    
    while ($row = $result->fetch_assoc()) {
        $date = $row['ExpirationDate'];
        // Format date to match JavaScript expectation (YYYY-MM-DD)
        $formattedDate = date('Y-m-d', strtotime($date));
        
        if (!isset($productsByDate[$formattedDate])) {
            $productsByDate[$formattedDate] = [];
        }
        
        $productsByDate[$formattedDate][] = [
            'ProductID' => $row['ProductID'],
            'Name' => $row['Name'],
            'Category' => $row['Category'],
            'Stocks' => $row['Stocks'],
            'ExpirationDate' => $formattedDate
        ];
    }
    
    return $productsByDate;
}

// Update the getDashboardData method to use the new function:
public function getDashboardData($period = 'All Time', $chartOnly = false)
{
    $data = ['chart_data' => $this->getSalesChartData($period)];

    if (!$chartOnly) {
        $data['stats'] = [
            'orders_today' => $this->getOrdersToday($period),
            'orders_week' => $this->getOrdersThisWeek($period),
            'total_sales' => $this->getTotalSales($period),
            'total_favorites' => $this->getTotalFavorites()
        ];
        $data['best_sellers'] = $this->getBestSellingProducts();
        $data['ratings'] = $this->getStoreRatings();
        $data['ai_insights'] = $this->getAIInsights($period);
        $data['top_customers'] = $this->getTopCustomers($period);
        $data['category_sales'] = $this->getCategorySales($period);
        $data['customer_demographics'] = $this->getCustomerDemographics($period);
        $data['order_status_dist'] = $this->getOrderStatusDistribution($period);
        
        // NEW: Add recent activity and expiring products
        $data['recent_activity'] = $this->getRecentAdminActivity();
        $data['expiring_dates'] = $this->getExpiringProductsDates(); // FIXED: Changed to 'expiring_dates' to match JavaScript
    }

    return $data;
}
}

try {
    if (!isset($conn)) {
        throw new Exception('Database connection not established');
    }

    $dashboard = new AdminDashboard($conn);

    // HANDLE SPECIFIC ACTION REQUESTS (Like getting reviews)
    if (isset($_GET['action']) && $_GET['action'] === 'get_reviews') {
        $rating = isset($_GET['rating']) ? $_GET['rating'] : 'all';
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
        $reviews = $dashboard->getAllReviews($rating, $sort);
        echo json_encode(['success' => true, 'reviews' => $reviews], JSON_PRETTY_PRINT);
        exit;
    }

    // DEFAULT DASHBOARD LOAD
    $period = isset($_GET['period']) ? $_GET['period'] : 'All Time';
    $chartOnly = isset($_GET['chartOnly']) ? filter_var($_GET['chartOnly'], FILTER_VALIDATE_BOOLEAN) : false;
    $data = $dashboard->getDashboardData($period, $chartOnly);
    echo json_encode(['success' => true, 'data' => $data, 'period' => $period], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
}

if (isset($conn)) {
    $conn->close();
}
?>