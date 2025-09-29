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
 * Description: V PHP Framework
 */
?>

    </main>
    
    <footer class="bg-gray p-2 mt-4">
        <div class="container grid-lg">
            <div class="columns">
                <div class="column col-6">
                    <small class="text-gray">
                        <i class="icon icon-mail"></i> DMARC Dashboard
                        <span class="ml-1">- Email Authentication Monitor</span>
                    </small>
                </div>
                <div class="column col-6 text-right">
                    <small class="text-gray">
                        Powered by V PHP Framework
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <?php App\Helpers\MessageHelper::displayAndClearMessages(); ?>
    <script src="/assets/js/footer-scripts.js"></script>
</body>
</html>
