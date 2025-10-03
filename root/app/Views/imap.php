<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: DMARC Dashboard
 * File: imap.php
 * Description: IMAP email ingestion management interface
 */

require 'partials/header.php';
?>

<div class="columns">
    <div class="column col-12 col-lg-10 col-xl-8 col-mx-auto">
        <div class="text-center mb-2">
            <h2>
                <i class="icon icon-2x icon-mail mr-2"></i>
                Email Ingestion (IMAP)
            </h2>
            <p class="text-gray">Automated DMARC report processing from email</p>
        </div>

        <div class="card mb-2">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-wifi mr-1"></i>
                    Connection Status
                </div>
            </div>
            <div class="card-body">
                <?php if ($imap_configured): ?>
                    <div class="toast toast-success">
                        <div class="toast-body">
                            <i class="icon icon-check mr-1"></i>
                            <strong>IMAP Configured</strong>
                            <br>Server: <?= defined('IMAP_HOST') ? htmlspecialchars(IMAP_HOST) : 'Not set' ?>
                            <br>Username: <?= defined('IMAP_USERNAME') ? htmlspecialchars(IMAP_USERNAME) : 'Not set' ?>
                            <br>Mailbox: <?= defined('IMAP_MAILBOX') ? htmlspecialchars(IMAP_MAILBOX) : 'INBOX' ?>
                        </div>
                    </div>

                    <div class="columns mt-2">
                        <div class="column col-12 col-sm-6">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo App\Core\SessionManager::getInstance()->get('csrf_token'); ?>">
                                <input type="hidden" name="action" value="test_connection">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="icon icon-refresh"></i> Test Connection
                                </button>
                            </form>
                        </div>
                        <div class="column col-12 col-sm-6 mt-2 mt-sm-0">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo App\Core\SessionManager::getInstance()->get('csrf_token'); ?>">
                                <input type="hidden" name="action" value="process_emails">
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="icon icon-download"></i> Process New Reports
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="toast toast-warning">
                        <div class="toast-body">
                            <i class="icon icon-flag mr-1"></i>
                            <strong>IMAP Not Configured</strong>
                            <br>Configure IMAP settings in config.php to enable automated email ingestion
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-2">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-bookmark mr-1"></i>
                    IMAP Configuration Guide
                </div>
            </div>
            <div class="card-body">
                <p>To enable automated DMARC report processing, configure the following in your <code>config.php</code> file:</p>

                <pre class="code"><code>// IMAP settings for email ingestion
define('IMAP_HOST', 'imap.yourdomain.com');
define('IMAP_PORT', 993);
define('IMAP_USERNAME', 'dmarc@yourdomain.com');
define('IMAP_PASSWORD', 'your_password');
define('IMAP_MAILBOX', 'INBOX');
define('IMAP_SSL', true);</code></pre>

                <div class="columns mt-2">
                    <div class="column col-12 col-lg-6">
                        <div class="tile">
                            <div class="tile-content">
                                <div class="tile-title h6">
                                    <i class="icon icon-check text-success mr-1"></i>
                                    Supported Features
                                </div>
                                <div class="tile-subtitle">
                                    <ul class="list-unstyled">
                                        <li>• Automatic DMARC aggregate report processing</li>
                                        <li>• Forensic report (RUF) ingestion</li>
                                        <li>• Compressed file support (ZIP, GZ)</li>
                                        <li>• SSL/TLS encrypted connections</li>
                                        <li>• Multiple mailbox support</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="column col-12 col-lg-6 mt-2 mt-lg-0">
                        <div class="tile">
                            <div class="tile-content">
                                <div class="tile-title h6">
                                    <i class="icon icon-time text-primary mr-1"></i>
                                    Automation
                                </div>
                                <div class="tile-subtitle">
                                    <p>Set up a cron job to process emails automatically:</p>
                                    <code>*/30 * * * * cd /path/to/app && php cron.php imap</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title h5">
                    <i class="icon icon-people mr-1"></i>
                    Alternative Ingestion Methods
                </div>
                <div class="card-subtitle text-gray">Coming in future updates</div>
            </div>
            <div class="card-body">
                <div class="columns">
                    <div class="column col-12 col-md-4">
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="icon icon-2x icon-share text-primary"></i>
                            </div>
                            <p class="empty-title h6">Gmail API</p>
                            <p class="empty-subtitle">
                                OAuth2 integration for Gmail accounts
                            </p>
                        </div>
                    </div>
                    <div class="column col-12 col-md-4 mt-2 mt-md-0">
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="icon icon-2x icon-link text-primary"></i>
                            </div>
                            <p class="empty-title h6">Microsoft Graph</p>
                            <p class="empty-subtitle">
                                Office 365 / Outlook integration
                            </p>
                        </div>
                    </div>
                    <div class="column col-12 col-md-4 mt-2 mt-md-0">
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="icon icon-2x icon-upload text-primary"></i>
                            </div>
                            <p class="empty-title h6">Manual Upload</p>
                            <p class="empty-subtitle">
                                <a href="/upload" class="btn btn-sm btn-primary">Available Now</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require 'partials/footer.php';
?>
