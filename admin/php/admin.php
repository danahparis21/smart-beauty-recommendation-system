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
    $dateFilter = $this->getDateFilterForPeriod($period);
    $query = "SELECT COUNT(*) as count FROM orders WHERE $dateFilter AND status = 'completed'";
    $result = $this->conn->query($query);
    return $result->fetch_assoc()['count'];
}

public function getOrdersThisWeek($period = 'All Time')
{
    $dateFilter = $this->getDateFilterForPeriod($period);
    $query = "SELECT COUNT(*) as count FROM orders WHERE $dateFilter AND status = 'completed'";
    $result = $this->conn->query($query);
    return $result->fetch_assoc()['count'];
}

public function getTotalSales($period = 'All Time')
{
    $dateFilter = $this->getDateFilterForPeriod($period);
    $query = "SELECT COALESCE(SUM(total_price), 0) AS total FROM orders WHERE $dateFilter AND status = 'completed'";
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

    $recentQuery = 'SELECT sr.rating, sr.comment, sr.created_at, u.username, u.profile_photo 
                    FROM store_ratings sr 
                    LEFT JOIN users u ON sr.user_id = u.UserID 
                    ORDER BY sr.created_at DESC 
                    LIMIT 10';
    $recentResult = $this->conn->query($recentQuery);
    $recentReviews = [];
    while ($row = $recentResult->fetch_assoc()) {
        if (!empty($row['profile_photo'])) {
            if (strpos($row['profile_photo'], '/') === false && strpos($row['profile_photo'], '\\') === false) {
                $row['profile_photo'] = '../uploads/profiles/' . $row['profile_photo'];
            } else {
                $row['profile_photo'] = str_replace('\\', '/', $row['profile_photo']);
                
                if (strpos($row['profile_photo'], 'uploads/profiles/') !== false) {
                    $filename = basename($row['profile_photo']);
                    $row['profile_photo'] = '../uploads/profiles/' . $filename;
                }
            }
            $fullPath = str_replace('../', __DIR__ . '/../../', $row['profile_photo']);
            if (!file_exists($fullPath)) {
                $row['profile_photo'] = null;
            }
        }
        $recentReviews[] = $row;
    }

    $ratings['recent_reviews'] = $recentReviews;
    
    $total = $ratings['total_reviews'];
    if ($total > 0) {
        $ratings['5_star_percent'] = round(($ratings['5_star'] / $total) * 100);
        $ratings['4_star_percent'] = round(($ratings['4_star'] / $total) * 100);
        $ratings['3_star_percent'] = round(($ratings['3_star'] / $total) * 100);
        $ratings['2_star_percent'] = round(($ratings['2_star'] / $total) * 100);
        $ratings['1_star_percent'] = round(($ratings['1_star'] / $total) * 100);
    } else {
        $ratings['5_star_percent'] = 0;
        $ratings['4_star_percent'] = 0;
        $ratings['3_star_percent'] = 0;
        $ratings['2_star_percent'] = 0;
        $ratings['1_star_percent'] = 0;
    }
    
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
        if (count($insights) < 8) {
            $insights[] = $message;
        }
    };

    // 1. Critical Stock Alert
    $criticalStockQuery = "SELECT Name, Stocks 
                           FROM Products 
                           WHERE Stocks <= 5 
                           AND Status = 'Available' 
                           AND ParentProductID IS NULL 
                           ORDER BY Stocks ASC 
                           LIMIT 3";
    $critRes = $this->conn->query($criticalStockQuery);
    if ($critRes && $critRes->num_rows > 0) {
        while ($row = $critRes->fetch_assoc()) {
            $urgency = $row['Stocks'] <= 2 ? '🚨 CRITICAL' : '⚠️ WARNING';
            $addInsight($insights, "{$urgency}: {$row['Name']} has only {$row['Stocks']} units left.");
        }
    }

    // 2. Revenue Comparison - FIXED: Use proper date filter
    $dateFilter = $this->getDateFilterForPeriod($period);
    
    // Today vs Yesterday
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $revQuery = "SELECT DATE(order_date) as date, COALESCE(SUM(total_price), 0) as rev 
                 FROM orders 
                 WHERE status='completed' 
                 AND (DATE(order_date) = '$today' OR DATE(order_date) = '$yesterday')
                 GROUP BY DATE(order_date)";
    
    $revRes = $this->conn->query($revQuery);
    $revenueData = [];
    while ($row = $revRes->fetch_assoc()) {
        $revenueData[$row['date']] = $row['rev'];
    }
    
    $todayRevenue = $revenueData[$today] ?? 0;
    $yesterdayRevenue = $revenueData[$yesterday] ?? 0;
    
    if ($todayRevenue > 0 && $yesterdayRevenue > 0) {
        if ($todayRevenue > $yesterdayRevenue) {
            $pct = round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100);
            $addInsight($insights, "📈 Daily revenue increased by {$pct}% compared to yesterday.");
        } elseif ($todayRevenue < $yesterdayRevenue) {
            $pct = round((($yesterdayRevenue - $todayRevenue) / $yesterdayRevenue) * 100);
            $addInsight($insights, "📉 Daily revenue decreased by {$pct}% compared to yesterday.");
        }
    }

    // 3. Top Performing Category - FIXED: Simplified query
    $catQuery = "SELECT p.Category, SUM(oi.quantity * p.Price) as total_revenue
                 FROM orderitems oi 
                 JOIN Products p ON oi.product_id=p.ProductID 
                 JOIN orders o ON oi.order_id=o.order_id 
                 WHERE o.status='completed' AND $dateFilter 
                 GROUP BY p.Category 
                 ORDER BY total_revenue DESC 
                 LIMIT 1";
    $catRes = $this->conn->query($catQuery);
    if ($catRes && $catRes->num_rows > 0) {
        $row = $catRes->fetch_assoc();
        $revenue = number_format($row['total_revenue'], 2);
        $addInsight($insights, "🏆 {$row['Category']} is your top category with ₱{$revenue} revenue.");
    }

    // 4. Expiring Products Alert
    $expQuery = "SELECT COUNT(*) as cnt,
                        SUM(CASE WHEN ExpirationDate <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as critical_count
                 FROM Products 
                 WHERE ExpirationDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                 AND Status='Available'";
    $expRes = $this->conn->query($expQuery);
    if ($expRes) {
        $expData = $expRes->fetch_assoc();
        $expCnt = $expData['cnt'] ?? 0;
        $criticalExp = $expData['critical_count'] ?? 0;
        
        if ($criticalExp > 0) {
            $addInsight($insights, "🚨 URGENT: {$criticalExp} products expiring within 7 days!");
        } elseif ($expCnt > 0) {
            $addInsight($insights, "⏰ {$expCnt} products expiring within 30 days.");
        }
    }

    // 5. Customer Retention Insight - FIXED: Simplified query
    $retentionQuery = "SELECT COUNT(DISTINCT user_id) as returning_customers
                       FROM orders 
                       WHERE status='completed' 
                       AND user_id IN (SELECT user_id FROM orders GROUP BY user_id HAVING COUNT(*) > 1)";
    $retentionRes = $this->conn->query($retentionQuery);
    if ($retentionRes) {
        $returningRow = $retentionRes->fetch_assoc();
        $returningCustomers = $returningRow['returning_customers'] ?? 0;
        
        $totalQuery = "SELECT COUNT(DISTINCT user_id) as total_customers FROM orders WHERE status='completed'";
        $totalRes = $this->conn->query($totalQuery);
        if ($totalRes) {
            $totalRow = $totalRes->fetch_assoc();
            $totalCustomers = $totalRow['total_customers'] ?? 1; // Avoid division by zero
            
            if ($totalCustomers > 0) {
                $retentionRate = round(($returningCustomers / $totalCustomers) * 100);
                if ($retentionRate < 30) {
                    $addInsight($insights, "👥 Customer retention rate is {$retentionRate}%. Consider loyalty programs.");
                } else {
                    $addInsight($insights, "💪 Strong! {$retentionRate}% customer retention rate.");
                }
            }
        }
    }

    // 6. Peak Sales Hours
    $hourQuery = "SELECT HOUR(order_date) as hour, COUNT(*) as order_count
                  FROM orders 
                  WHERE status='completed' AND $dateFilter
                  GROUP BY HOUR(order_date)
                  ORDER BY order_count DESC 
                  LIMIT 1";
    $hourRes = $this->conn->query($hourQuery);
    if ($hourRes && $hourRes->num_rows > 0) {
        $row = $hourRes->fetch_assoc();
        $hour = $row['hour'];
        $peakHour = $hour < 12 ? $hour . ' AM' : ($hour == 12 ? '12 PM' : ($hour - 12) . ' PM');
        $addInsight($insights, "🕐 Peak sales hour: {$peakHour} with {$row['order_count']} orders.");
    }

    // 7. Best Selling Product Insight
    $bestProductQuery = "SELECT p.Name, SUM(oi.quantity) as total_sold, 
                                SUM(oi.quantity * p.Price) as total_revenue
                         FROM orderitems oi 
                         JOIN Products p ON oi.product_id = p.ProductID 
                         JOIN orders o ON oi.order_id = o.order_id 
                         WHERE o.status = 'completed' AND $dateFilter
                         GROUP BY p.ProductID, p.Name 
                         ORDER BY total_sold DESC 
                         LIMIT 1";
    $bestProductRes = $this->conn->query($bestProductQuery);
    if ($bestProductRes && $bestProductRes->num_rows > 0) {
        $row = $bestProductRes->fetch_assoc();
        $revenue = number_format($row['total_revenue'], 2);
        $addInsight($insights, "⭐ Best seller: {$row['Name']} - {$row['total_sold']} units sold (₱{$revenue}).");
    }

    // 8. New Customer Acquisition - FIXED: Simplified query
    $newCustomersQuery = "SELECT COUNT(DISTINCT user_id) as new_customers
                          FROM orders 
                          WHERE status='completed' 
                          AND order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                          AND user_id NOT IN (
                              SELECT DISTINCT user_id 
                              FROM orders 
                              WHERE order_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                              AND status='completed'
                          )";
    $newCustomersRes = $this->conn->query($newCustomersQuery);
    if ($newCustomersRes) {
        $row = $newCustomersRes->fetch_assoc();
        $newCustomers = $row['new_customers'] ?? 0;
        if ($newCustomers > 0) {
            $addInsight($insights, "🎯 {$newCustomers} new customers acquired this week!");
        }
    }

    // Fill remaining slots with rotating generic insights if needed
    if (count($insights) < 8) {
        $genericInsights = [
            '📊 Analyze customer demographics to optimize marketing campaigns.',
            '💡 Consider bundling frequently purchased products together.',
            '🌟 Promote your best-rated products on social media.',
            '🔄 Review and update product descriptions for better SEO.',
            '🎁 Launch a seasonal promotion to boost sales.',
            '📱 Ensure mobile shopping experience is optimized.',
            '⭐ Encourage customers to leave reviews for better engagement.'
        ];
        
        $needed = 8 - count($insights);
        $usedInsights = [];
        
        for ($i = 0; $i < $needed; $i++) {
            $randomInsight = $genericInsights[array_rand($genericInsights)];
            if (!in_array($randomInsight, $usedInsights)) {
                $addInsight($insights, $randomInsight);
                $usedInsights[] = $randomInsight;
            }
        }
    }

    // Limit to 8 insights maximum
    return array_slice($insights, 0, 10);
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
// Add this method to handle export requests
public function getExportData($period = 'All Time')
{
    $data = $this->getDashboardData($period, false);
    
    // Add additional data for export
    $data['export_info'] = [
        'export_date' => date('Y-m-d H:i:s'),
        'period' => $period,
        'generated_by' => $_SESSION['username'] ?? 'Admin'
    ];
    
    // Add detailed sales data for export
    $data['detailed_sales'] = $this->getDetailedSalesData($period);
    $data['product_performance'] = $this->getProductPerformanceData($period);
    $data['customer_analytics'] = $this->getCustomerAnalyticsData($period);
    
    // NEW: Add monthly and annual revenue data
    $data['monthly_revenue'] = $this->getMonthlyRevenue($period);
    $data['annual_revenue'] = $this->getAnnualRevenue($period);
    
    return $data;
}

private function getDetailedSalesData($period)
{
    $dateFilter = $this->getDateFilterForPeriod($period);
    
    $query = "SELECT 
                DATE(o.order_date) as order_date,
                o.order_id,
                o.total_price,
                o.status,
                COUNT(oi.order_item_id) as items_count,
                u.username as customer_name
              FROM orders o
              LEFT JOIN orderitems oi ON o.order_id = oi.order_id
              LEFT JOIN users u ON o.user_id = u.UserID
              WHERE o.status = 'completed' AND $dateFilter
              GROUP BY o.order_id
              ORDER BY o.order_date DESC";
              
    $result = $this->conn->query($query);
    $sales = [];
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    return $sales;
}

private function getProductPerformanceData($period)
{
    $dateFilter = $this->getDateFilterForPeriod($period);
    
    $query = "SELECT 
                p.Name as product_name,
                p.Category,
                p.Price,
                SUM(oi.quantity) as total_sold,
                SUM(oi.quantity * p.Price) as total_revenue,
                COUNT(DISTINCT o.order_id) as order_count
              FROM Products p
              LEFT JOIN orderitems oi ON p.ProductID = oi.product_id
              LEFT JOIN orders o ON oi.order_id = o.order_id AND o.status = 'completed'
              WHERE ($dateFilter OR o.order_id IS NULL)
              GROUP BY p.ProductID, p.Name, p.Category, p.Price
              HAVING total_sold > 0
              ORDER BY total_revenue DESC";
              
    $result = $this->conn->query($query);
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    return $products;
}

private function getCustomerAnalyticsData($period)
{
    $dateFilter = $this->getDateFilterForPeriod($period);
    
    $query = "SELECT 
                u.username,
                u.email,
                COUNT(o.order_id) as total_orders,
                COALESCE(SUM(o.total_price), 0) as total_spent,
                AVG(o.total_price) as avg_order_value,
                MAX(o.order_date) as last_order_date
              FROM users u
              LEFT JOIN orders o ON u.UserID = o.user_id AND o.status = 'completed' AND $dateFilter
              WHERE u.Role != 'admin'
              GROUP BY u.UserID, u.username, u.email
              HAVING total_orders > 0
              ORDER BY total_spent DESC";
              
    $result = $this->conn->query($query);
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    return $customers;
}

// Add this method to handle export requests
public function exportToCSV($period = 'All Time')
{
    $data = $this->getExportData($period);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="blessence_report_' . $period . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Export Summary
    fputcsv($output, ['BLESSENCE DASHBOARD REPORT']);
    fputcsv($output, ['Period:', $period]);
    fputcsv($output, ['Generated:', $data['export_info']['export_date']]);
    fputcsv($output, ['Generated By:', $data['export_info']['generated_by']]);
    fputcsv($output, []);
    
    // Key Metrics
    fputcsv($output, ['KEY METRICS']);
    fputcsv($output, ['Orders Today', $data['stats']['orders_today']]);
    fputcsv($output, ['Orders This Week', $data['stats']['orders_week']]);
    fputcsv($output, ['Total Revenue', 'PHP ' . number_format($data['stats']['total_sales'], 2)]);
    fputcsv($output, ['Total Favorites', $data['stats']['total_favorites']]);
    fputcsv($output, []);
    
    // Sales Overview
    fputcsv($output, ['SALES OVERVIEW']);
    fputcsv($output, ['Date', 'Sales (₱)', 'Orders']);
    foreach ($data['chart_data'] as $chart) {
        fputcsv($output, [
            $chart['display_date'],
            'PHP ' . number_format($chart['sales'], 2),
            $chart['orders']
        ]);
    }

    fputcsv($output, []);
    
    // NEW: Monthly Revenue
    fputcsv($output, ['MONTHLY REVENUE BREAKDOWN']);
    fputcsv($output, ['Month', 'Revenue (₱)']);
    foreach ($data['monthly_revenue'] as $monthly) {
        fputcsv($output, [
            $monthly['month'],
            'PHP ' . number_format($monthly['revenue'], 2)
        ]);
    }
    fputcsv($output, []);
    
    // NEW: Annual Revenue
    fputcsv($output, ['ANNUAL REVENUE BREAKDOWN']);
    fputcsv($output, ['Year', 'Revenue (₱)']);
    foreach ($data['annual_revenue'] as $annual) {
        fputcsv($output, [
            $annual['year'],
            'PHP ' . number_format($annual['revenue'], 2)
        ]);
    }
    fputcsv($output, []);
    
    // Store Ratings
    fputcsv($output, ['STORE RATINGS SUMMARY']);
    fputcsv($output, ['Total Reviews', $data['ratings']['total_reviews']]);
    fputcsv($output, ['Average Rating', number_format($data['ratings']['average_rating'], 1) . '/5']);
    fputcsv($output, []);
    fputcsv($output, ['RATING DISTRIBUTION']);
    fputcsv($output, ['5 Stars', $data['ratings']['5_star'] . ' (' . $data['ratings']['5_star_percent'] . '%)']);
    fputcsv($output, ['4 Stars', $data['ratings']['4_star'] . ' (' . $data['ratings']['4_star_percent'] . '%)']);
    fputcsv($output, ['3 Stars', $data['ratings']['3_star'] . ' (' . $data['ratings']['3_star_percent'] . '%)']);
    fputcsv($output, ['2 Stars', $data['ratings']['2_star'] . ' (' . $data['ratings']['2_star_percent'] . '%)']);
    fputcsv($output, ['1 Star', $data['ratings']['1_star'] . ' (' . $data['ratings']['1_star_percent'] . '%)']);
    fputcsv($output, []);
    
    // Recent Feedback
    fputcsv($output, ['RECENT CUSTOMER FEEDBACK']);
    fputcsv($output, ['Customer', 'Rating', 'Comment', 'Date']);
    foreach ($data['ratings']['recent_reviews'] as $review) {
        fputcsv($output, [
            $review['username'] ?? 'Anonymous',
            str_repeat('★', $review['rating']) . ' (' . $review['rating'] . '/5)',
            $review['comment'] ?? 'No comment',
            $review['created_at']
        ]);
    }
    fputcsv($output, []);
    
    // Category Performance
    fputcsv($output, ['CATEGORY PERFORMANCE']);
    fputcsv($output, ['Category', 'Items Sold']);
    foreach ($data['category_sales'] as $category) {
        fputcsv($output, [
            $category['Category'],
            $category['total_items_sold']
        ]);
    }
    fputcsv($output, []);
    
    // Product Performance
    fputcsv($output, ['TOP PERFORMING PRODUCTS']);
    fputcsv($output, ['Product', 'Category', 'Units Sold', 'Total Revenue']);
    foreach ($data['product_performance'] as $product) {
        fputcsv($output, [
            $product['product_name'],
            $product['Category'],
            $product['total_sold'],
            'PHP ' . number_format($product['total_revenue'], 2)
        ]);
    }
    fputcsv($output, []);
    
    // Customer Analytics
    fputcsv($output, ['CUSTOMER ANALYTICS']);
    fputcsv($output, ['Customer', 'Total Orders', 'Total Spent', 'Avg Order Value']);
    foreach ($data['customer_analytics'] as $customer) {
        fputcsv($output, [
            $customer['username'],
            $customer['total_orders'],
            'PHP ' . number_format($customer['total_spent'], 2),
            'PHP ' . number_format($customer['avg_order_value'], 2)
        ]);
    }
    fputcsv($output, []);
    
    // Order Status Distribution
    fputcsv($output, ['ORDER STATUS DISTRIBUTION']);
    fputcsv($output, ['Status', 'Count']);
    foreach ($data['order_status_dist'] as $status) {
        fputcsv($output, [
            $status['status'],
            $status['count']
        ]);
    }
    fputcsv($output, []);
    
    // AI Insights
    fputcsv($output, ['AI INSIGHTS']);
    foreach ($data['ai_insights'] as $insight) {
        fputcsv($output, [$insight]);
    }
    
    fclose($output);
    exit;
}
public function getMonthlyRevenue($period = 'All Time')
{
    if ($period === 'All Time') {
        $query = "SELECT 
                    DATE_FORMAT(order_date, '%Y-%m') as month,
                    COALESCE(SUM(total_price), 0) AS revenue
                  FROM orders 
                  WHERE status = 'completed'
                  GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                  ORDER BY month DESC
                  LIMIT 12";
    } else {
        $dateFilter = $this->getDateFilterForPeriod($period);
        $query = "SELECT 
                    DATE_FORMAT(order_date, '%Y-%m') as month,
                    COALESCE(SUM(total_price), 0) AS revenue
                  FROM orders 
                  WHERE status = 'completed' AND $dateFilter
                  GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                  ORDER BY month DESC
                  LIMIT 12";
    }
    
    $result = $this->conn->query($query);
    $monthlyRevenue = [];
    while ($row = $result->fetch_assoc()) {
        $monthlyRevenue[] = [
            'month' => $row['month'],
            'revenue' => number_format((float) str_replace(',', '.', $row['revenue']), 2, '.', '')
        ];
    }
    return array_reverse($monthlyRevenue); // Return in chronological order
}

public function getAnnualRevenue($period = 'All Time')
{
    if ($period === 'All Time') {
        $query = "SELECT 
                    YEAR(order_date) as year,
                    COALESCE(SUM(total_price), 0) AS revenue
                  FROM orders 
                  WHERE status = 'completed'
                  GROUP BY YEAR(order_date)
                  ORDER BY year DESC
                  LIMIT 5";
    } else {
        $dateFilter = $this->getDateFilterForPeriod($period);
        $query = "SELECT 
                    YEAR(order_date) as year,
                    COALESCE(SUM(total_price), 0) AS revenue
                  FROM orders 
                  WHERE status = 'completed' AND $dateFilter
                  GROUP BY YEAR(order_date)
                  ORDER BY year DESC
                  LIMIT 5";
    }
    
    $result = $this->conn->query($query);
    $annualRevenue = [];
    while ($row = $result->fetch_assoc()) {
        $annualRevenue[] = [
            'year' => $row['year'],
            'revenue' => number_format((float) str_replace(',', '.', $row['revenue']), 2, '.', '')
        ];
    }
    return array_reverse($annualRevenue); // Return in chronological order
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
    // Handle export requests
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $period = isset($_GET['period']) ? $_GET['period'] : 'All Time';
    $dashboard->exportToCSV($period);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'export_data') {
    $period = isset($_GET['period']) ? $_GET['period'] : 'All Time';
    $exportData = $dashboard->getExportData( $period);
    echo json_encode(['success' => true, 'data' => $exportData], JSON_PRETTY_PRINT);
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