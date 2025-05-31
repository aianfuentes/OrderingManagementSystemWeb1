<?php
require_once '../includes/auth_check.php';
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../login.php');
    exit();
}

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get sales report
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as average_order_value
    FROM orders 
    WHERE created_at BETWEEN ? AND ? AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");
$stmt->execute([$start_date, $end_date]);
$sales_report = $stmt->fetchAll();

// Get top selling products
$stmt = $pdo->prepare("
    SELECT 
        p.name,
        p.category,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.price) as total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ? AND o.status = 'completed'
    GROUP BY p.id
    ORDER BY total_quantity DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_products = $stmt->fetchAll();

// Get sales by category
$stmt = $pdo->prepare("
    SELECT 
        p.category,
        COUNT(DISTINCT o.id) as total_orders,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.price) as total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ? AND o.status = 'completed'
    GROUP BY p.category
    ORDER BY total_revenue DESC
");
$stmt->execute([$start_date, $end_date]);
$sales_by_category = $stmt->fetchAll();

// Get hourly sales distribution
$stmt = $pdo->prepare("
    SELECT 
        HOUR(created_at) as hour,
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue
    FROM orders 
    WHERE created_at BETWEEN ? AND ? AND status = 'completed'
    GROUP BY HOUR(created_at)
    ORDER BY hour
");
$stmt->execute([$start_date, $end_date]);
$hourly_sales = $stmt->fetchAll();

// Get payment method distribution
$stmt = $pdo->prepare("
    SELECT 
        payment_status as payment_method,
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue
    FROM orders 
    WHERE created_at BETWEEN ? AND ? AND status = 'completed'
    GROUP BY payment_status
");
$stmt->execute([$start_date, $end_date]);
$payment_methods = $stmt->fetchAll();

// Calculate total revenue for the period
$stmt = $pdo->prepare("
    SELECT SUM(total_amount) 
    FROM orders 
    WHERE created_at BETWEEN ? AND ? AND status = 'completed'
");
$stmt->execute([$start_date, $end_date]);
$total_revenue = $stmt->fetchColumn() ?? 0;

// Calculate total orders for the period
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders 
    WHERE created_at BETWEEN ? AND ? AND status = 'completed'
");
$stmt->execute([$start_date, $end_date]);
$total_orders = $stmt->fetchColumn() ?? 0;

// Calculate average order value
$average_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

// Calculate growth rate compared to previous period
$previous_start_date = date('Y-m-d', strtotime($start_date . ' -' . (strtotime($end_date) - strtotime($start_date)) . ' days'));
$previous_end_date = date('Y-m-d', strtotime($start_date . ' -1 day'));

$stmt = $pdo->prepare("
    SELECT SUM(total_amount) 
    FROM orders 
    WHERE created_at BETWEEN ? AND ? AND status = 'completed'
");
$stmt->execute([$previous_start_date, $previous_end_date]);
$previous_revenue = $stmt->fetchColumn() ?? 0;

$growth_rate = $previous_revenue > 0 ? (($total_revenue - $previous_revenue) / $previous_revenue) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Dashboard</title>
    <link rel="icon" href="../assets/images/products/test.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
        }
        
        body {
            background-color: #f8f9fc;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .report-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            padding: 1.5rem;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .date-filter {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #718096;
            font-size: 0.875rem;
        }

        .stat-change {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-change.positive {
            color: var(--secondary-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="report-header">
            <h2>Sales Reports & Analytics</h2>
            <div class="date-filter">
                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <button type="button" class="btn btn-success" onclick="exportReport()">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                </form>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value">₱<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-change <?php echo $growth_rate >= 0 ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-<?php echo $growth_rate >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                        <?php echo abs(round($growth_rate, 1)); ?>% vs previous period
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value">₱<?php echo number_format($average_order_value, 2); ?></div>
                    <div class="stat-label">Average Order Value</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($top_products); ?></div>
                    <div class="stat-label">Top Selling Products</div>
                </div>
            </div>
        </div>

        <!-- Sales Trend Chart -->
        <div class="report-card">
            <h4>Sales Trend</h4>
            <div class="chart-container">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>

        <!-- Sales Distribution -->
        <div class="row">
            <div class="col-md-6">
                <div class="report-card">
                    <h4>Sales by Category</h4>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="report-card">
                    <h4>Hourly Sales Distribution</h4>
                    <div class="chart-container">
                        <canvas id="hourlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="report-card">
            <h4>Top Selling Products</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td><?php echo $product['total_quantity']; ?></td>
                                <td>₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="report-card">
            <h4>Payment Method Distribution</h4>
            <div class="row">
                <?php foreach ($payment_methods as $method): ?>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $method['total_orders']; ?></div>
                            <div class="stat-label"><?php echo ucfirst($method['payment_method']); ?> Orders</div>
                            <div class="stat-value">₱<?php echo number_format($method['total_revenue'], 2); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sales Trend Chart
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column(array_reverse($sales_report), 'date')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column(array_reverse($sales_report), 'total_revenue')); ?>,
                    borderColor: '#4e73df',
                    tension: 0.1,
                    fill: false
                }, {
                    label: 'Orders',
                    data: <?php echo json_encode(array_column(array_reverse($sales_report), 'total_orders')); ?>,
                    borderColor: '#1cc88a',
                    tension: 0.1,
                    fill: false,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₱)'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Number of Orders'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($sales_by_category, 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($sales_by_category, 'total_revenue')); ?>,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#5a5c69', '#858796', '#6f42c1', '#20c9a6', '#fd7e14'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Hourly Chart
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($hourly_sales, 'hour')); ?>,
                datasets: [{
                    label: 'Orders',
                    data: <?php echo json_encode(array_column($hourly_sales, 'total_orders')); ?>,
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Orders'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Hour of Day'
                        }
                    }
                }
            }
        });

        // Export Report Function
        function exportReport() {
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            window.location.href = `export_report.php?start_date=${startDate}&end_date=${endDate}`;
        }
    </script>
</body>
</html> 