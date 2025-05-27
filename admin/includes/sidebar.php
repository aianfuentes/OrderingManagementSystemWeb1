<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 280px;
        background: linear-gradient(180deg, #4e73df 0%, #224abe 100%);
        padding: 1.5rem;
        color: white;
        transition: all 0.3s ease;
        z-index: 1000;
        box-shadow: 4px 0 10px rgba(0,0,0,0.1);
    }

    .sidebar-header {
        padding: 1rem 0;
        margin-bottom: 2rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 1rem;
        text-decoration: none;
        color: white;
    }

    .sidebar-brand img {
        width: 40px;
        height: 40px;
        border-radius: 8px;
    }

    .sidebar-brand h2 {
        font-size: 1.25rem;
        margin: 0;
        font-weight: 600;
    }

    .nav-item {
        margin-bottom: 0.5rem;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.875rem 1rem;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .nav-link:hover {
        background: rgba(255,255,255,0.1);
        color: white;
    }

    .nav-link.active {
        background: rgba(255,255,255,0.2);
        color: white;
        font-weight: 500;
    }

    .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 1.1rem;
    }

    .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .user-details {
        flex: 1;
    }

    .user-name {
        font-weight: 500;
        margin: 0;
        font-size: 0.9rem;
    }

    .user-role {
        font-size: 0.8rem;
        opacity: 0.8;
    }

    .logout-btn {
        width: 100%;
        padding: 0.75rem;
        background: rgba(255,255,255,0.1);
        border: none;
        border-radius: 8px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .logout-btn:hover {
        background: rgba(255,255,255,0.2);
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
    }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <img src="../assets/images/products/test.png" alt="Logo">
            <h2>Admin Panel</h2>
        </a>
    </div>

    <nav>
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="manage_products.php" class="nav-link <?php echo $current_page === 'manage_products.php' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="manage_orders.php" class="nav-link <?php echo $current_page === 'manage_orders.php' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="manage_customers.php" class="nav-link <?php echo $current_page === 'manage_customers.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="reports.php" class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <h3 class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?? 'Admin'); ?></h3>
                <div class="user-role">Administrator</div>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<script>
    // Mobile sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'btn btn-primary d-md-none position-fixed';
        toggleBtn.style.cssText = 'top: 1rem; left: 1rem; z-index: 1001;';
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.appendChild(toggleBtn);

        toggleBtn.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    });
</script> 