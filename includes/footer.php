    </div>
    <!-- ./wrapper -->

    <!-- Footer -->
    <footer class="footer mt-5">
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
                        <li><i class="fas fa-map-marker-alt mr-2"></i> 123 Food Street,General Santos City</li>
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

    <!-- REQUIRED SCRIPTS -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- overlayScrollbars -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/overlayscrollbars/1.13.0/js/jquery.overlayScrollbars.min.js"></script>
    <!-- AdminLTE App -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom scripts -->
    <script>
        $(document).ready(function() {
            // Initialize the sidebar
            $('[data-widget="treeview"]').Treeview('init');
            
            // Enable tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Enable popovers
            $('[data-toggle="popover"]').popover();
        });
    </script>
</body>
</html> 