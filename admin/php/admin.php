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
                $weeks = [];
                $firstMonday = date('Y-m-d', strtotime('monday this month'));
                if (date('j', strtotime($firstMonday)) > 7) {
                    $firstMonday = date('Y-m-d', strtotime('monday last month'));
                }
                for ($i = 0; $i < 5; $i++) {
                    $weekStart = date('Y-m-d', strtotime("$firstMonday +$i week"));
                    $weekEnd = date('Y-m-d', strtotime("$weekStart +6 days"));
                    if ($weekStart > $endOfMonth)
                        break;
                    $label = 'Week ' . ($i + 1);
                    $weeks[$label] = ['start' => $weekStart, 'end' => min($weekEnd, $endOfMonth)];
                    $data[$label] = ['sales' => 0, 'orders' => 0];
                }
                $query = "SELECT DATE(order_date) as date, SUM(total_price) as sales, COUNT(*) as orders FROM orders WHERE order_date BETWEEN '$startOfMonth' AND '$endOfMonth' AND status = 'completed' GROUP BY DATE(order_date) ORDER BY date";
                $result = $this->conn->query($query);
                $raw = [];
                while ($row = $result->fetch_assoc())
                    $raw[] = $row;
                foreach ($raw as $row) {
                    foreach ($weeks as $label => $range) {
                        if ($row['date'] >= $range['start'] && $row['date'] <= $range['end']) {
                            $data[$label]['sales'] += (float) $row['sales'];
                            $data[$label]['orders'] += (int) $row['orders'];
                            break;
                        }
                    }
                }
                $formatted = [];
                foreach ($weeks as $label => $_) {
                    $formatted[] = ['display_date' => $label, 'sales' => $data[$label]['sales'], 'orders' => $data[$label]['orders']];
                }
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
        $query = "SELECT p.Name AS product_name, p.Category, SUM(oi.quantity) AS total_sold, COUNT(DISTINCT o.order_id) AS total_orders FROM orderitems oi INNER JOIN orders o ON oi.order_id = o.order_id INNER JOIN products p ON oi.product_id = p.ProductID WHERE o.status = 'completed' GROUP BY p.ProductID, p.Name, p.Category HAVING total_sold > 0 ORDER BY total_sold DESC LIMIT 5";
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
                  JOIN products p ON oi.product_id = p.ProductID 
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
                               FROM products 
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
                     JOIN products p ON oi.product_id=p.ProductID 
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

        // Expiring products
        $expQuery = "SELECT COUNT(*) as cnt 
                     FROM products 
                     WHERE ExpirationDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                     AND Status='Available'";
        $expRes = $this->conn->query($expQuery);
        $expCnt = $expRes->fetch_assoc()['cnt'];
        if ($expCnt > 0) {
            $addInsight($insights, "⏰ {$expCnt} products expiring within 30 days.");
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