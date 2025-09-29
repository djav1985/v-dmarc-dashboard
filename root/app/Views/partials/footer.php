<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: footer.php
 * Description: DMARC Dashboard Footer using Spectre.css
 */
?>

    <!-- Footer -->
    <footer class="bg-gray p-2 mt-2">
        <div class="container">
            <div class="columns">
                <div class="column col-6">
                    <p class="text-gray mb-0">
                        &copy; <?= date('Y') ?> DMARC Dashboard - Built with V PHP Framework
                    </p>
                </div>
                <div class="column col-6 text-right">
                    <p class="text-gray mb-0">
                        <small>
                            Version 1.0 | 
                            <a href="https://github.com/djav1985/v-dmarc-dashboard" class="text-gray">
                                <i class="icon icon-link"></i> GitHub
                            </a>
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Custom JavaScript -->
    <script src="/assets/js/footer-scripts.js"></script>
    <script>
        // Auto-dismiss toasts after 5 seconds
        setTimeout(function() {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(function(toast) {
                toast.style.display = 'none';
            });
        }, 5000);

        // Handle dropdown menus
        document.addEventListener('click', function(e) {
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            dropdownToggles.forEach(function(toggle) {
                const dropdown = toggle.parentElement;
                if (e.target === toggle || toggle.contains(e.target)) {
                    dropdown.classList.toggle('active');
                } else {
                    dropdown.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
