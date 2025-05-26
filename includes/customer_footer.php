            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">FoodExpress</h5>
                    <p class="text-muted">Delicious food delivered to your doorstep. Order now and enjoy the best dining experience!</p>
                </div>
                <div class="col-md-3">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="homepage.php" class="text-muted">Home</a></li>
                        <li><a href="my_orders.php" class="text-muted">My Orders</a></li>
                        <li><a href="logout.php" class="text-muted">Logout</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5 class="mb-3">Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-phone mr-2"></i> +1 234 567 890</li>
                        <li><i class="fas fa-envelope mr-2"></i> support@foodexpress.com</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i> 123 Food Street, City</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> FoodExpress. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-right">
                    <a href="#" class="text-muted mr-3"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-muted mr-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-muted mr-3"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Scripts -->
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Auto-hide alerts after 5 seconds
            $('.alert').delay(5000).fadeOut(500);
            
            // Smooth scroll for anchor links
            $('a[href^="#"]').on('click', function(e) {
                e.preventDefault();
                var target = $(this.hash);
                if (target.length) {
                    $('html, body').animate({
                        scrollTop: target.offset().top - 70
                    }, 1000);
                }
            });
        });
    </script>
</body>
</html> 