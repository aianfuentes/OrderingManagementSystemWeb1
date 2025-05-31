<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/auth_check.php';
require_once 'includes/customer_header.php';
require_once 'config/database.php';
require_once 'includes/wishlist_handler.php';

// Handle remove from wishlist
if (isset($_POST['remove_from_wishlist'])) {
    $product_id = (int)$_POST['product_id'];
    if (removeFromWishlist($_SESSION['user_id'], $product_id)) {
        $_SESSION['success_message'] = "Item removed from wishlist successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to remove item from wishlist.";
    }
    header("Location: wishlist.php");
    exit();
}

// Get wishlist items
$wishlist_items = getWishlistItems($_SESSION['user_id']);
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">My Wishlist</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="customer_dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Wishlist</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($wishlist_items)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-heart-broken fa-4x text-muted mb-3"></i>
                            <h3>Your wishlist is empty</h3>
                            <p class="text-muted">Add items to your wishlist to keep track of products you love.</p>
                            <a href="shop.php" class="btn btn-primary mt-3">
                                <i class="fas fa-shopping-bag"></i> Continue Shopping
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Stock Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wishlist_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                         class="img-size-50 mr-3">
                                                    <div>
                                                        <h5 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h5>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <?php if ($item['stock'] > 0): ?>
                                                    <span class="badge badge-success">In Stock</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Out of Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="product.php?id=<?php echo $item['product_id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-shopping-cart"></i> Buy Now
                                                    </a>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                        <button type="submit" name="remove_from_wishlist" 
                                                                class="btn btn-sm btn-danger"
                                                                onclick="return confirm('Are you sure you want to remove this item from your wishlist?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
require_once 'includes/footer.php';
?> 