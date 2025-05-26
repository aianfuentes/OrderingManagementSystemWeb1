<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is not an admin
if ($_SESSION['user_role'] === 'admin') {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage - Ordering Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #e31837;
            --secondary-color: #222;
            --accent-color: #fff;
            --tab-active: #e31837;
            --tab-inactive: #555;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .topbar {
            background: var(--primary-color);
            color: var(--accent-color);
            padding: 0.7rem 0;
        }
        .topbar .container-fluid {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand-logo {
            display: flex;
            align-items: center;
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--accent-color);
            gap: 0.5rem;
        }
        .brand-logo img {
            height: 36px;
            width: 36px;
            object-fit: contain;
        }
        .address-selector {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 1rem;
            background: transparent;
            border: none;
            color: var(--accent-color);
            cursor: pointer;
        }
        .address-selector i {
            color: #ffd600;
            font-size: 1.2rem;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }
        .login-link {
            color: var(--accent-color);
            font-weight: 500;
            text-decoration: none;
            margin-right: 0.5rem;
        }
        .order-btn {
            background: #fff;
            color: var(--primary-color);
            border: none;
            border-radius: 2rem;
            padding: 0.5rem 1.3rem;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: background 0.2s, color 0.2s;
        }
        .order-btn:hover {
            background: #ffe5ea;
            color: #b3001b;
        }
        .bottom-nav {
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
        }
        .bottom-nav .container-fluid {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        .nav-tab {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--tab-inactive);
            background: none;
            border: none;
            padding: 1rem 0.5rem 0.7rem 0.5rem;
            margin-right: 1.5rem;
            border-bottom: 3px solid transparent;
            transition: color 0.2s, border-bottom 0.2s;
            cursor: pointer;
        }
        .nav-tab.active, .nav-tab:focus {
            color: var(--tab-active);
            border-bottom: 3px solid var(--tab-active);
            background: none;
        }
        @media (max-width: 991.98px) {
            .topbar .container-fluid, .bottom-nav .container-fluid {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .bottom-nav .container-fluid {
                gap: 1rem;
            }
        }
        .cart-modal .modal-content {
            border-radius: 1rem;
            border: none;
        }
        .cart-modal .modal-header {
            border-bottom: 1px solid #f0f0f0;
            padding: 1rem 1.5rem;
        }
        .cart-modal .modal-body {
            padding: 1.5rem;
        }
        .cart-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 0.5rem;
        }
        .cart-item-details {
            flex: 1;
        }
        .cart-item-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .cart-item-price {
            color: var(--primary-color);
            font-weight: 600;
        }
        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .cart-item-quantity button {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
        }
        .cart-summary {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }
        .cart-total {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Top Red Bar -->
    <div class="topbar">
        <div class="d-flex align-items-center justify-content-between" style="padding: 0.7rem 2.5rem;">
            <div class="d-flex align-items-center" style="gap:1.5rem;">
                <div class="brand-logo ms-3" style="margin-top:0.2rem;">
                    <span style="font-size:2rem; color:#fff;"><i class="fas fa-shopping-bag"></i></span>
                    <span style="font-weight:700; font-size:1.5rem; letter-spacing:1px;">Ordering System</span>
                </div>
                <button class="address-selector">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Select your Address</span>
                    <i class="fas fa-chevron-down" style="font-size:0.9em;"></i>
                </button>
            </div>
            <div class="header-actions">
                <a href="login.php" class="login-link">Register / Log in</a>
                <button class="order-btn" data-bs-toggle="modal" data-bs-target="#cartModal">
                    <i class="fas fa-shopping-cart"></i> Cart
                </button>
            </div>
        </div>
    </div>

    <!-- Cart Modal -->
    <div class="modal fade cart-modal" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cartModalLabel">Your Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="cartItems">
                        <!-- Cart items will be dynamically added here -->
                    </div>
                    <div class="cart-summary">
                        <div class="cart-total">
                            <span>Total:</span>
                            <span>₱ 0.00</span>
                        </div>
                        <button class="btn btn-primary w-100" style="background: var(--primary-color); border: none;">
                            Proceed to Checkout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New menu navigation and search section -->
    <div class="menu-nav-bar" style="background:#fff; border-bottom:1.5px solid #f0f0f0;">
        <div class="d-flex align-items-center ms-3" style="gap:2rem; padding:0 2.5rem;">
            <button class="nav-tab active" style="color:#e31837; border-bottom:3px solid #e31837; background:none; font-weight:700; font-size:1.1rem; padding:1.1rem 1.5rem 0.8rem 0;">Menu</button>
            <button class="nav-tab" style="color:#555; background:none; font-weight:600; font-size:1.1rem; padding:1.1rem 1.5rem 0.8rem 0;">Stores</button>
        </div>
    </div>

    <script>
    function updateQuantity(button, change) {
        const quantitySpan = button.parentElement.querySelector('span');
        let quantity = parseInt(quantitySpan.textContent);
        quantity = Math.max(1, quantity + change);
        quantitySpan.textContent = quantity;
        updateCartTotal();
    }

    function removeItem(button) {
        const cartItem = button.closest('.cart-item');
        cartItem.remove();
        updateCartTotal();
    }

    function updateCartTotal() {
        // This function will be implemented to calculate and update the total
        // based on the items in the cart
    }
    </script>
</body>
</html> 