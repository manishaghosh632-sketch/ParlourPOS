<?php
// includes/footer.php
global $base_url;
?>
            </div> <!-- End #app-content -->
        </main> <!-- End #main-scroll-area -->
    </div> <!-- End Main Content Wrapper -->

    <!-- Global JS (Fixed Path) -->
    <script src="<?= $base_url ?>/assets/js/main.js"></script>
    
    <?php
    // Automatically trigger toasts from session flash messages if they exist
    if (isset($_SESSION['flash_success'])) {
        echo "<script>window.addEventListener('load', () => showToast('".addslashes($_SESSION['flash_success'])."', 'success'));</script>";
        unset($_SESSION['flash_success']);
    }
    if (isset($_SESSION['flash_error'])) {
        echo "<script>window.addEventListener('load', () => showToast('".addslashes($_SESSION['flash_error'])."', 'error'));</script>";
        unset($_SESSION['flash_error']);
    }
    ?>
</body>
</html>