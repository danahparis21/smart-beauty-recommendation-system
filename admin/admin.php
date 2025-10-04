<?php
// Include the database connection safely using absolute path
include __DIR__ . '/../config/db.php';

// ================== FETCH DASHBOARD STATS ==================

// Orders today
$orders_today_sql = "SELECT COUNT(*) AS orders_today 
                     FROM Orders 
                     WHERE DATE(order_date) = CURDATE()";
$orders_today = $conn->query($orders_today_sql)->fetch_assoc()['orders_today'] ?? 0;

// Orders this week
$orders_week_sql = "SELECT COUNT(*) AS orders_this_week 
                    FROM Orders 
                    WHERE YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1)";
$orders_week = $conn->query($orders_week_sql)->fetch_assoc()['orders_this_week'] ?? 0;

// Total sales
$total_sales_sql = "SELECT SUM(total_price) AS total_sales 
                    FROM Orders 
                    WHERE status = 'completed'";
$total_sales = $conn->query($total_sales_sql)->fetch_assoc()['total_sales'] ?? 0;

// ================== FETCH CHART DATA ==================

// All Time (Monthly data for current year)
$alltime_sql = "SELECT 
                    MONTH(order_date) as month,
                    SUM(total_price) as total
                FROM Orders 
                WHERE YEAR(order_date) = YEAR(CURDATE())
                    AND status = 'completed'
                GROUP BY MONTH(order_date)
                ORDER BY month";
$alltime_result = $conn->query($alltime_sql);

$alltime_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$alltime_data = array_fill(0, 12, 0);

while($row = $alltime_result->fetch_assoc()) {
    $alltime_data[$row['month'] - 1] = (float)$row['total'];
}

// Daily (Hourly data for today)
$daily_sql = "SELECT 
                HOUR(order_date) as hour,
                SUM(total_price) as total
              FROM Orders 
              WHERE DATE(order_date) = CURDATE()
                  AND status = 'completed'
              GROUP BY HOUR(order_date)
              ORDER BY hour";
$daily_result = $conn->query($daily_sql);

$daily_labels = ['12AM', '3AM', '6AM', '9AM', '12PM', '3PM', '6PM', '9PM'];
$daily_hours = [0, 3, 6, 9, 12, 15, 18, 21];
$daily_data = array_fill(0, 8, 0);

while($row = $daily_result->fetch_assoc()) {
    $hour = (int)$row['hour'];
    $index = array_search($hour, $daily_hours);
    if($index !== false) {
        $daily_data[$index] = (float)$row['total'];
    }
}

// Weekly (Last 7 days)
$weekly_sql = "SELECT 
                DAYNAME(order_date) as day_name,
                DAYOFWEEK(order_date) as day_num,
                SUM(total_price) as total
               FROM Orders 
               WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                   AND status = 'completed'
               GROUP BY DATE(order_date), DAYNAME(order_date), DAYOFWEEK(order_date)
               ORDER BY day_num";
$weekly_result = $conn->query($weekly_sql);

$weekly_labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$weekly_data = array_fill(0, 7, 0);

while($row = $weekly_result->fetch_assoc()) {
    $day_index = ($row['day_num'] - 1) % 7; // Convert MySQL DAYOFWEEK to 0-6
    $weekly_data[$day_index] = (float)$row['total'];
}

// Reorder to start with Monday
$weekly_labels_reordered = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$weekly_data_reordered = [
    $weekly_data[1], $weekly_data[2], $weekly_data[3], 
    $weekly_data[4], $weekly_data[5], $weekly_data[6], $weekly_data[0]
];

// Monthly (Weeks of current month)
$monthly_sql = "SELECT 
                    WEEK(order_date, 1) - WEEK(DATE_SUB(order_date, INTERVAL DAYOFMONTH(order_date)-1 DAY), 1) + 1 as week_num,
                    SUM(total_price) as total
                FROM Orders 
                WHERE YEAR(order_date) = YEAR(CURDATE())
                    AND MONTH(order_date) = MONTH(CURDATE())
                    AND status = 'completed'
                GROUP BY week_num
                ORDER BY week_num";
$monthly_result = $conn->query($monthly_sql);

$monthly_labels = ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'];
$monthly_data = array_fill(0, 5, 0);

while($row = $monthly_result->fetch_assoc()) {
    $week = (int)$row['week_num'] - 1;
    if($week >= 0 && $week < 5) {
        $monthly_data[$week] = (float)$row['total'];
    }
}

// Remove empty weeks from the end
while(count($monthly_data) > 0 && end($monthly_data) == 0 && count($monthly_data) > 4) {
    array_pop($monthly_data);
    array_pop($monthly_labels);
}

// Annually (Last 6 years)
$annually_sql = "SELECT 
                    YEAR(order_date) as year,
                    SUM(total_price) as total
                 FROM Orders 
                 WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 YEAR)
                     AND status = 'completed'
                 GROUP BY YEAR(order_date)
                 ORDER BY year";
$annually_result = $conn->query($annually_sql);

$annually_labels = [];
$annually_data = [];

while($row = $annually_result->fetch_assoc()) {
    $annually_labels[] = (string)$row['year'];
    $annually_data[] = (float)$row['total'];
}

// If no data, create default structure
if(empty($annually_labels)) {
    $current_year = (int)date('Y');
    for($i = 5; $i >= 0; $i--) {
        $annually_labels[] = (string)($current_year - $i);
        $annually_data[] = 0;
    }
}

// ================== BEST SELLING PRODUCTS ==================
$bestsellers_sql = "SELECT 
                        p.name as product_name,
                        p.brand,
                        p.category,
                        p.price,
                        SUM(oi.quantity) as total_sold,
                        SUM(oi.quantity * oi.price) as total_revenue,
                        COALESCE(AVG(r.stars), 0) as avg_rating,
                        COUNT(DISTINCT r.rating_id) as review_count
                    FROM OrderItems oi
                    JOIN Products p ON oi.product_id = p.product_id
                    JOIN Orders o ON oi.order_id = o.order_id
                    LEFT JOIN Ratings r ON p.product_id = r.product_id
                    WHERE o.status = 'completed'
                    GROUP BY oi.product_id, p.name, p.brand, p.category, p.price
                    ORDER BY total_sold DESC
                    LIMIT 5";
$bestsellers_result = $conn->query($bestsellers_sql);
$bestsellers = [];
if($bestsellers_result) {
    while($row = $bestsellers_result->fetch_assoc()) {
        $bestsellers[] = $row;
    }
}

// ================== STORE RATINGS ==================
$store_ratings_sql = "SELECT 
                          COUNT(*) as total_ratings,
                          AVG(stars) as average_rating,
                          SUM(CASE WHEN stars = 5 THEN 1 ELSE 0 END) as five_star,
                          SUM(CASE WHEN stars = 4 THEN 1 ELSE 0 END) as four_star,
                          SUM(CASE WHEN stars = 3 THEN 1 ELSE 0 END) as three_star,
                          SUM(CASE WHEN stars = 2 THEN 1 ELSE 0 END) as two_star,
                          SUM(CASE WHEN stars = 1 THEN 1 ELSE 0 END) as one_star
                      FROM Ratings";
$store_ratings_result = $conn->query($store_ratings_sql);
$store_ratings = $store_ratings_result->fetch_assoc();

// Recent reviews
$recent_reviews_sql = "SELECT 
                          u.name as customer_name,
                          p.name as product_name,
                          r.stars,
                          r.review,
                          r.rating_id
                      FROM Ratings r
                      JOIN Users u ON r.user_id = u.user_id
                      JOIN Products p ON r.product_id = p.product_id
                      ORDER BY r.rating_id DESC
                      LIMIT 3";
$recent_reviews_result = $conn->query($recent_reviews_sql);
$recent_reviews = [];
if($recent_reviews_result) {
    while($row = $recent_reviews_result->fetch_assoc()) {
        $recent_reviews[] = $row;
    }
}

// ================== TOP CUSTOMERS ==================
$top_customers_sql = "SELECT 
                          u.name,
                          u.email,
                          COUNT(o.order_id) as total_orders,
                          SUM(o.total_price) as total_spent,
                          MAX(o.order_date) as last_order
                      FROM Orders o
                      JOIN Users u ON o.user_id = u.user_id
                      WHERE o.status = 'completed'
                      GROUP BY o.user_id, u.name, u.email
                      ORDER BY total_spent DESC
                      LIMIT 5";
$top_customers_result = $conn->query($top_customers_sql);
$top_customers = [];
if($top_customers_result) {
    while($row = $top_customers_result->fetch_assoc()) {
        $top_customers[] = $row;
    }
}

// ================== AI INSIGHTS ==================
$ai_insights = [];

// Low stock alert (if you have inventory tracking, adjust as needed)
$low_performing_sql = "SELECT 
                          p.name,
                          p.category,
                          COALESCE(SUM(oi.quantity), 0) as total_sold
                      FROM Products p
                      LEFT JOIN OrderItems oi ON p.product_id = oi.product_id
                      LEFT JOIN Orders o ON oi.order_id = o.order_id AND o.status = 'completed'
                      GROUP BY p.product_id, p.name, p.category
                      HAVING total_sold < 5
                      ORDER BY total_sold ASC
                      LIMIT 3";
$low_performing_result = $conn->query($low_performing_sql);
$low_performing = [];
if($low_performing_result) {
    while($row = $low_performing_result->fetch_assoc()) {
        $low_performing[] = $row;
    }
}

// Revenue trend
$revenue_trend_sql = "SELECT 
                        SUM(CASE WHEN MONTH(order_date) = MONTH(CURDATE()) THEN total_price ELSE 0 END) as current_month,
                        SUM(CASE WHEN MONTH(order_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN total_price ELSE 0 END) as last_month
                      FROM Orders
                      WHERE status = 'completed'
                      AND order_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)";
$revenue_trend_result = $conn->query($revenue_trend_sql);
$revenue_trend = $revenue_trend_result->fetch_assoc();

$current_month_revenue = $revenue_trend['current_month'] ?? 0;
$last_month_revenue = $revenue_trend['last_month'] ?? 1;
$revenue_growth = $last_month_revenue > 0 ? (($current_month_revenue - $last_month_revenue) / $last_month_revenue) * 100 : 0;

// Popular category
$popular_category_sql = "SELECT 
                            p.category,
                            COUNT(oi.order_item_id) as order_count,
                            SUM(oi.quantity * oi.price) as revenue
                        FROM OrderItems oi
                        JOIN Products p ON oi.product_id = p.product_id
                        JOIN Orders o ON oi.order_id = o.order_id
                        WHERE o.status = 'completed'
                        GROUP BY p.category
                        ORDER BY order_count DESC
                        LIMIT 1";
$popular_category_result = $conn->query($popular_category_sql);
$popular_category = $popular_category_result->fetch_assoc();

$ai_insights = [
    'revenue_growth' => $revenue_growth,
    'popular_category' => $popular_category,
    'low_performing' => $low_performing
];

?>
<?php include __DIR__ . '/../admin/admin.html'; ?>
<script>
// Replace the placeholders in the HTML dynamically after load
document.addEventListener("DOMContentLoaded", () => {
  // Update stats
  document.querySelectorAll('.orders-today .stat-value').forEach(e => e.textContent = "<?= $orders_today ?>");
  document.querySelectorAll('.this-week .stat-value').forEach(e => e.textContent = "<?= $orders_week ?>");
  document.querySelectorAll('.total-sales .stat-value').forEach(e => e.textContent = "₱<?= number_format($total_sales, 2) ?>");
  
  // ================== BEST SELLING PRODUCTS ==================
  const bestsellersData = <?= json_encode($bestsellers) ?>;
  const bestsellersContainer = document.querySelector('.panel-section:nth-child(1) .placeholder-box');
  
  if(bestsellersData.length > 0) {
    let bestsellersHTML = '<div class="list-group list-group-flush">';
    bestsellersData.forEach((product, index) => {
      const stars = '★'.repeat(Math.round(product.avg_rating)) + '☆'.repeat(5 - Math.round(product.avg_rating));
      bestsellersHTML += `
        <div class="list-group-item border-0 px-0 py-3" style="background: transparent;">
          <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary" style="background: linear-gradient(135deg, #e497aa, #db8299) !important;">#${index + 1}</span>
                <h6 class="mb-0 fw-bold" style="color: #333; font-size: 0.95rem;">${product.product_name}</h6>
              </div>
              <div class="text-muted small mb-2">
                <span class="me-2"><i class="fas fa-tag"></i> ${product.brand}</span>
                <span><i class="fas fa-folder"></i> ${product.category}</span>
              </div>
              <div class="d-flex align-items-center gap-3">
                <span class="text-warning small">${stars}</span>
                <span class="badge bg-light text-dark">${product.total_sold} sold</span>
                <span class="text-success fw-bold small">₱${parseFloat(product.total_revenue).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
              </div>
            </div>
          </div>
        </div>
      `;
    });
    bestsellersHTML += '</div>';
    bestsellersContainer.innerHTML = bestsellersHTML;
  }
  
  // ================== STORE RATINGS ==================
  const storeRatings = <?= json_encode($store_ratings) ?>;
  const recentReviews = <?= json_encode($recent_reviews) ?>;
  const ratingsContainer = document.querySelector('.panel-section:nth-child(2) .placeholder-box');
  
  const avgRating = parseFloat(storeRatings.average_rating || 0).toFixed(1);
  const totalRatings = parseInt(storeRatings.total_ratings || 0);
  const fiveStar = parseInt(storeRatings.five_star || 0);
  const fourStar = parseInt(storeRatings.four_star || 0);
  const threeStar = parseInt(storeRatings.three_star || 0);
  const twoStar = parseInt(storeRatings.two_star || 0);
  const oneStar = parseInt(storeRatings.one_star || 0);
  
  let ratingsHTML = `
    <div class="text-center mb-4">
      <div class="display-4 fw-bold" style="color: #e497aa;">${avgRating}</div>
      <div class="text-warning fs-4 mb-2">${'★'.repeat(Math.round(avgRating))}${'☆'.repeat(5 - Math.round(avgRating))}</div>
      <div class="text-muted small">${totalRatings} total ratings</div>
    </div>
    
    <div class="mb-3">
      ${[
        {stars: 5, count: fiveStar},
        {stars: 4, count: fourStar},
        {stars: 3, count: threeStar},
        {stars: 2, count: twoStar},
        {stars: 1, count: oneStar}
      ].map(item => {
        const percentage = totalRatings > 0 ? (item.count / totalRatings * 100).toFixed(0) : 0;
        return `
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="small text-nowrap" style="width: 60px;">${item.stars} ★</span>
            <div class="progress flex-grow-1" style="height: 8px;">
              <div class="progress-bar" style="width: ${percentage}%; background: linear-gradient(90deg, #e497aa, #db8299);"></div>
            </div>
            <span class="small text-muted" style="width: 40px;">${item.count}</span>
          </div>
        `;
      }).join('')}
    </div>
  `;
  
  if(recentReviews.length > 0) {
    ratingsHTML += '<hr class="my-3"><div class="small"><strong>Recent Reviews:</strong></div>';
    recentReviews.forEach(review => {
      const stars = '★'.repeat(review.stars) + '☆'.repeat(5 - review.stars);
      ratingsHTML += `
        <div class="small mt-2 p-2" style="background: #f8f9fa; border-radius: 8px;">
          <div class="text-warning">${stars}</div>
          <div class="fw-bold">${review.customer_name}</div>
          <div class="text-muted">${review.product_name}</div>
          ${review.review ? `<div class="mt-1" style="font-size: 0.85rem;">"${review.review.substring(0, 80)}${review.review.length > 80 ? '...' : ''}"</div>` : ''}
        </div>
      `;
    });
  }
  
  ratingsContainer.innerHTML = ratingsHTML;
  
  // ================== AI INSIGHTS ==================
  const aiInsights = <?= json_encode($ai_insights) ?>;
  const insightsContainer = document.querySelector('.panel-section:nth-child(3) .placeholder-box');
  
  let insightsHTML = '<div class="text-start">';
  
  // Revenue Growth Insight
  const growthPercent = parseFloat(aiInsights.revenue_growth).toFixed(1);
  const growthIcon = growthPercent >= 0 ? 'fa-arrow-trend-up text-success' : 'fa-arrow-trend-down text-danger';
  const growthColor = growthPercent >= 0 ? 'success' : 'danger';
  
  insightsHTML += `
    <div class="mb-3 p-3" style="background: linear-gradient(135deg, #f8f9fa, #fff); border-radius: 10px; border-left: 4px solid var(--primary-pink);">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="fas ${growthIcon} fs-5"></i>
        <strong>Revenue Trend</strong>
      </div>
      <div class="small">
        <span class="badge bg-${growthColor}">${growthPercent > 0 ? '+' : ''}${growthPercent}%</span>
        compared to last month
      </div>
    </div>
  `;
  
  // Popular Category
  if(aiInsights.popular_category) {
    insightsHTML += `
      <div class="mb-3 p-3" style="background: linear-gradient(135deg, #f8f9fa, #fff); border-radius: 10px; border-left: 4px solid var(--secondary-pink);">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fas fa-fire text-warning fs-5"></i>
          <strong>Trending Category</strong>
        </div>
        <div class="small">
          <span class="badge" style="background: linear-gradient(135deg, #e497aa, #db8299);">${aiInsights.popular_category.category}</span>
          with ${aiInsights.popular_category.order_count} orders
        </div>
      </div>
    `;
  }
  
  // Low Performing Products
  if(aiInsights.low_performing && aiInsights.low_performing.length > 0) {
    insightsHTML += `
      <div class="mb-3 p-3" style="background: linear-gradient(135deg, #fff3cd, #fff); border-radius: 10px; border-left: 4px solid #ffc107;">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fas fa-exclamation-triangle text-warning fs-5"></i>
          <strong>Needs Attention</strong>
        </div>
        <div class="small">
          ${aiInsights.low_performing.map(p => `
            <div class="mt-2">
              • <strong>${p.name}</strong> (${p.category})<br>
              <span class="text-muted ms-3">Only ${p.total_sold} sold</span>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }
  
  insightsHTML += '</div>';
  insightsContainer.innerHTML = insightsHTML;
  
  // ================== TOP CUSTOMERS ==================
  const topCustomers = <?= json_encode($top_customers) ?>;
  const customersContainer = document.querySelector('.panel-section:nth-child(4) .placeholder-box');
  
  if(topCustomers.length > 0) {
    let customersHTML = '<div class="list-group list-group-flush">';
    topCustomers.forEach((customer, index) => {
      const initial = customer.name.charAt(0).toUpperCase();
      const colors = ['#e497aa', '#db8299', '#c36c82', '#b05a70', '#9d4e5f'];
      const bgColor = colors[index % colors.length];
      
      customersHTML += `
        <div class="list-group-item border-0 px-0 py-3" style="background: transparent;">
          <div class="d-flex align-items-center gap-3">
            <div class="flex-shrink-0">
              <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" 
                   style="width: 45px; height: 45px; background: ${bgColor}; font-size: 1.2rem;">
                ${initial}
              </div>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">#${index + 1}</span>
                <h6 class="mb-0 fw-bold" style="font-size: 0.9rem;">${customer.name}</h6>
              </div>
              <div class="small text-muted mb-1">${customer.email}</div>
              <div class="d-flex gap-3 small">
                <span><i class="fas fa-shopping-bag text-primary"></i> ${customer.total_orders} orders</span>
                <span class="text-success fw-bold"><i class="fas fa-peso-sign"></i> ${parseFloat(customer.total_spent).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
              </div>
            </div>
          </div>
        </div>
      `;
    });
    customersHTML += '</div>';
    customersContainer.innerHTML = customersHTML;
  }
  
  // Update chart data with real database values
  const chartData = {
    allTime: {
      labels: <?= json_encode($alltime_labels) ?>,
      data: <?= json_encode($alltime_data) ?>
    },
    daily: {
      labels: <?= json_encode($daily_labels) ?>,
      data: <?= json_encode($daily_data) ?>
    },
    weekly: {
      labels: <?= json_encode($weekly_labels_reordered) ?>,
      data: <?= json_encode($weekly_data_reordered) ?>
    },
    monthly: {
      labels: <?= json_encode($monthly_labels) ?>,
      data: <?= json_encode($monthly_data) ?>
    },
    annually: {
      labels: <?= json_encode($annually_labels) ?>,
      data: <?= json_encode($annually_data) ?>
    }
  };
  
  // Update the chart with real data
  const ctx = document.getElementById('salesChart');
  let salesChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartData.allTime.labels,
      datasets: [{
        label: 'Sales (₱)',
        data: chartData.allTime.data,
        borderColor: '#e497aa',
        backgroundColor: 'rgba(228, 151, 170, 0.1)',
        borderWidth: 3,
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#e497aa',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7,
        pointHoverBackgroundColor: '#db8299',
        pointHoverBorderColor: '#fff',
        pointHoverBorderWidth: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: true,
          position: 'top',
          labels: {
            color: '#666',
            font: {
              size: 12,
              weight: '600'
            },
            padding: 15,
            usePointStyle: true,
            pointStyle: 'circle'
          }
        },
        tooltip: {
          enabled: true,
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleColor: '#fff',
          bodyColor: '#fff',
          padding: 12,
          cornerRadius: 8,
          displayColors: false,
          callbacks: {
            label: function(context) {
              return '₱' + context.parsed.y.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            color: 'rgba(0, 0, 0, 0.05)',
            drawBorder: false
          },
          ticks: {
            color: '#666',
            font: {
              size: 11
            },
            callback: function(value) {
              if(value >= 1000000) {
                return '₱' + (value/1000000).toFixed(1) + 'M';
              } else if(value >= 1000) {
                return '₱' + (value/1000).toFixed(1) + 'k';
              }
              return '₱' + value;
            }
          }
        },
        x: {
          grid: {
            display: false,
            drawBorder: false
          },
          ticks: {
            color: '#666',
            font: {
              size: 11
            }
          }
        }
      },
      interaction: {
        intersect: false,
        mode: 'index'
      }
    }
  });
  
  // Period buttons functionality with real data
  const periodButtons = document.querySelectorAll('.btn-group .btn');
  periodButtons.forEach(button => {
    button.addEventListener('click', function() {
      periodButtons.forEach(btn => btn.classList.remove('active'));
      this.classList.add('active');
      
      const period = this.textContent.trim().toLowerCase().replace(' ', '');
      let newData;
      
      switch(period) {
        case 'alltime':
          newData = chartData.allTime;
          break;
        case 'daily':
          newData = chartData.daily;
          break;
        case 'weekly':
          newData = chartData.weekly;
          break;
        case 'monthly':
          newData = chartData.monthly;
          break;
        case 'annually':
          newData = chartData.annually;
          break;
        default:
          newData = chartData.allTime;
      }
      
      salesChart.data.labels = newData.labels;
      salesChart.data.datasets[0].data = newData.data;
      salesChart.update('active');
    });
  });
});
</script>