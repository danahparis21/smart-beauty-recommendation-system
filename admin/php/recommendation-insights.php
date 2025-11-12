<?php
header('Content-Type: application/json');

// Configuration & Connection
if (getenv('DOCKER_ENV') === 'true') {
    require_once __DIR__ . '/../../config/db_docker.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

// Get filter parameter (all, month, week)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$feedbackFilter = isset($_GET['feedback']) ? $_GET['feedback'] : 'all';

// Calculate date range based on filter
$dateCondition = "";
switch($filter) {
    case 'month':
        $dateCondition = "AND pf.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case 'week':
        $dateCondition = "AND pf.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
        break;
    default:
        $dateCondition = "";
}

try {
    // 1. Get Success Rate (Positive Feedback = rating 4 or 5, Negative = 1-3)
    $successQuery = "
        SELECT 
            COUNT(CASE WHEN UserRating >= 4 THEN 1 END) as positive,
            COUNT(CASE WHEN UserRating <= 3 THEN 1 END) as negative,
            COUNT(*) as total
        FROM productfeedback
        WHERE UserRating IS NOT NULL
        $dateCondition
    ";
    $successResult = $conn->query($successQuery);
    $successData = $successResult->fetch_assoc();
    $successRate = $successData['total'] > 0 ? 
        round(($successData['positive'] / $successData['total']) * 100) : 0;

    // 2. Total Recommendations (Total feedback with ratings)
    $totalRecommendations = $successData['total'];

    // 3. Total Customer Feedback
    $totalFeedback = $successData['total'];

    // 4. Average Match Score (Average user rating out of 5)
    $avgScoreQuery = "
        SELECT AVG(UserRating) as avg_score
        FROM productfeedback
        WHERE UserRating IS NOT NULL
        $dateCondition
    ";
    $avgScoreResult = $conn->query($avgScoreQuery);
    $avgScoreData = $avgScoreResult->fetch_assoc();
    $avgScore = $avgScoreData['avg_score'] ? round($avgScoreData['avg_score'], 1) : 0;

    // 5. Most Recommended Products (Products with most feedback)
    $productsQuery = "
        SELECT 
            p.ProductID,
            p.Name,
            p.Category,
            p.ShadeOrVariant,
            p.HexCode,
            COUNT(pf.FeedbackID) as recommendation_count,
            SUM(CASE WHEN pf.UserRating >= 4 THEN 1 ELSE 0 END) as positive_feedback,
            SUM(CASE WHEN pf.UserRating <= 3 THEN 1 ELSE 0 END) as negative_feedback,
            ROUND(AVG(pf.UserRating), 1) as avg_rating
        FROM productfeedback pf
        JOIN products p ON pf.ProductID = p.ProductID
        WHERE pf.UserRating IS NOT NULL
        $dateCondition
        GROUP BY p.ProductID
        ORDER BY recommendation_count DESC, avg_rating DESC
        LIMIT 10
    ";
    $productsResult = $conn->query($productsQuery);
    $products = [];
    while($row = $productsResult->fetch_assoc()) {
        $successPercentage = $row['recommendation_count'] > 0 ? 
            round(($row['positive_feedback'] / $row['recommendation_count']) * 100) : 0;
        
        $products[] = [
            'product_id' => $row['ProductID'],
            'name' => $row['Name'],
            'category' => $row['Category'],
            'shade' => $row['ShadeOrVariant'],
            'hex_code' => $row['HexCode'],
            'views' => $row['recommendation_count'],
            'positive' => $row['positive_feedback'],
            'negative' => $row['negative_feedback'],
            'success_rate' => $successPercentage,
            'avg_rating' => $row['avg_rating']
        ];
    }

    // 6. Customer Preferences (Analyze from product attributes of rated products)
    $preferencesQuery = "
        SELECT 
            pa.SkinTone,
            COUNT(*) as count
        FROM productfeedback pf
        JOIN productattributes pa ON pf.ProductID = pa.ProductID
        WHERE pf.UserRating IS NOT NULL
        $dateCondition
        AND pa.SkinTone IS NOT NULL
        AND pa.SkinTone != ''
        GROUP BY pa.SkinTone
    ";
    $preferencesResult = $conn->query($preferencesQuery);
    $skinTones = ['fair' => 0, 'medium' => 0, 'tan' => 0, 'deep' => 0];
    
    while($row = $preferencesResult->fetch_assoc()) {
        // Handle comma-separated skin tones
        $tones = explode(',', strtolower($row['SkinTone']));
        foreach($tones as $tone) {
            $tone = trim($tone);
            if($tone === 'fair' || $tone === 'light') {
                $skinTones['fair'] += $row['count'];
            } elseif($tone === 'medium') {
                $skinTones['medium'] += $row['count'];
            } elseif($tone === 'tan') {
                $skinTones['tan'] += $row['count'];
            } elseif($tone === 'deep') {
                $skinTones['deep'] += $row['count'];
            }
        }
    }

    // Get undertones
    $undertonesQuery = "
        SELECT 
            pa.Undertone,
            COUNT(*) as count
        FROM productfeedback pf
        JOIN productattributes pa ON pf.ProductID = pa.ProductID
        WHERE pf.UserRating IS NOT NULL
        $dateCondition
        AND pa.Undertone IS NOT NULL
        AND pa.Undertone != ''
        GROUP BY pa.Undertone
    ";
    $undertonesResult = $conn->query($undertonesQuery);
    $undertones = ['warm' => 0, 'cool' => 0, 'neutral' => 0];
    
    while($row = $undertonesResult->fetch_assoc()) {
        $key = strtolower(trim($row['Undertone']));
        if($key === 'warm') {
            $undertones['warm'] += $row['count'];
        } elseif($key === 'cool') {
            $undertones['cool'] += $row['count'];
        } elseif($key === 'neutral') {
            $undertones['neutral'] += $row['count'];
        } elseif($key === 'all') {
            // Distribute 'all' equally among the three
            $undertones['warm'] += floor($row['count'] / 3);
            $undertones['cool'] += floor($row['count'] / 3);
            $undertones['neutral'] += floor($row['count'] / 3);
        }
    }

    // 7. Recent Customer Feedback (Filter: Positive = 4-5 stars, Negative = 1-3 stars)
    $feedbackCondition = "";
    if($feedbackFilter === 'positive') {
        $feedbackCondition = "AND pf.UserRating >= 4";
    } elseif($feedbackFilter === 'negative') {
        $feedbackCondition = "AND pf.UserRating <= 3";
    }

    $recentFeedbackQuery = "
        SELECT 
            pf.FeedbackID,
            pf.UserRating,
            pf.Comment,
            pf.CreatedAt,
            p.Name as product_name,
            p.Category,
            p.ShadeOrVariant,
            u.first_name,
            u.last_name,
            u.username
        FROM productfeedback pf
        JOIN products p ON pf.ProductID = p.ProductID
        JOIN users u ON pf.UserID = u.UserID
        WHERE pf.UserRating IS NOT NULL
        $feedbackCondition
        $dateCondition
        ORDER BY pf.CreatedAt DESC
        LIMIT 15
    ";
    $recentFeedbackResult = $conn->query($recentFeedbackQuery);
    $recentFeedback = [];
    while($row = $recentFeedbackResult->fetch_assoc()) {
        $recentFeedback[] = [
            'feedback_id' => $row['FeedbackID'],
            'rating' => $row['UserRating'],
            'comment' => $row['Comment'],
            'created_at' => $row['CreatedAt'],
            'product_name' => $row['product_name'],
            'category' => $row['Category'],
            'shade' => $row['ShadeOrVariant'],
            'customer_name' => trim($row['first_name'] . ' ' . $row['last_name']) ?: $row['username'],
            'customer_initial' => strtoupper(substr($row['first_name'] ?: $row['username'], 0, 1))
        ];
    }

    // Prepare response
    $response = [
        'success' => true,
        'stats' => [
            'success_rate' => $successRate,
            'total_recommendations' => $totalRecommendations,
            'total_feedback' => $totalFeedback,
            'avg_score' => $avgScore
        ],
        'products' => $products,
        'preferences' => [
            'skin_tones' => [
                'light' => $skinTones['fair'],
                'medium' => $skinTones['medium'],
                'tan' => $skinTones['tan'],
                'deep' => $skinTones['deep']
            ],
            'undertones' => $undertones
        ],
        'recent_feedback' => $recentFeedback
    ];

    echo json_encode($response);

} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>