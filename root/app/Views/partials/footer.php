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
 * Description: Enhanced footer with branding support
 */

use App\Core\BrandingManager;

$branding = BrandingManager::getInstance();
$brandingVars = $branding->getBrandingVars();
?>

</main>

<footer class="bg-gray p-2 mt-4">
    <div class="container grid-lg">
        <div class="columns footer-columns">
            <div class="column col-12 col-md-6">
                <small class="text-gray">
                    <i class="icon icon-mail"></i> <?= htmlspecialchars($brandingVars['app_name']) ?>
                    <?php if (!empty($brandingVars['company_name'])): ?>
                        <span class="ml-1">- <?= htmlspecialchars($brandingVars['company_name']) ?></span>
                    <?php else: ?>
                        <span class="ml-1">- Email Authentication Monitor</span>
                    <?php endif; ?>
                </small>
            </div>
            <div class="column col-12 col-md-6 footer-right">
                <small class="text-gray">
                    <?php if (!empty($brandingVars['footer_text'])): ?>
                        <?= htmlspecialchars($brandingVars['footer_text']) ?>
                    <?php else: ?>
                        Powered by V PHP Framework
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>
</footer>

<script src="/assets/js/footer-scripts.js"></script>
</body>

</html>
