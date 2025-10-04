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
                        SUM(oi.quantity) as total_sold,
                        SUM(oi.quantity * oi.price) as total_revenue
                    FROM OrderItems oi
                    JOIN Products p ON oi.product_id = p.product_id
                    JOIN Orders o ON oi.order_id = o.order_id
                    WHERE o.status = 'completed'
                    GROUP BY oi.product_id, p.name, p.brand, p.category
                    ORDER BY total_sold DESC
                    LIMIT 5";
$bestsellers_result = $conn->query($bestsellers_sql);
$bestsellers = [];
if($bestsellers_result) {
    while($row = $bestsellers_result->fetch_assoc()) {
        $bestsellers[] = $row;
    }
}

// ================== TOP CUSTOMERS ==================
$top_customers_sql = "SELECT 
                          u.name,
                          u.email,
                          COUNT(o.order_id) as total_orders,
                          SUM(o.total_price) as total_spent
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

?>
<?php include __DIR__ . '/../admin/admin.html'; ?>
<script>
// Replace the placeholders in the HTML dynamically after load
document.addEventListener("DOMContentLoaded", () => {
  // Update stats
  document.querySelectorAll('.orders-today .stat-value').forEach(e => e.textContent = "<?= $orders_today ?>");
  document.querySelectorAll('.this-week .stat-value').forEach(e => e.textContent = "<?= $orders_week ?>");
  document.querySelectorAll('.total-sales .stat-value').forEach(e => e.textContent = "₱<?= number_format($total_sales, 2) ?>");
  
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