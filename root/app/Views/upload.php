<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Project: DMARC Dashboard
 * File: upload.php
 * Description: DMARC report upload interface
 */

require 'partials/header.php';

$rawUsername = isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';
$displayUsername = trim($rawUsername) !== '' ? $rawUsername : 'User';
$displayUsername = htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8');
?>

    <div class="columns">
        <div class="column col-12 col-lg-8 col-xl-6 col-mx-auto">
            <div class="text-center mb-2">
                <h2>
                    <i class="icon icon-upload mr-2"></i>
                    Upload DMARC Reports
                </h2>
                <p class="text-gray">Process your DMARC aggregate and forensic reports</p>
            </div>
            
            <div class="card mb-2">
                <div class="card-header">
                    <div class="card-title h5">
                        <i class="icon icon-edit mr-1"></i>
                        Manual Upload
                    </div>
                    <div class="card-subtitle">
                        Upload XML, GZ, or ZIP files containing DMARC reports
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo App\Core\SessionManager::getInstance()->get('csrf_token'); ?>">
                        
                        <div class="form-group">
                            <label class="form-label" for="report_file">
                                <i class="icon icon-bookmark mr-1"></i>
                                Select Report File
                            </label>
                            <input class="form-input input-lg" type="file" id="report_file" name="report_file" 
                                   accept=".xml,.gz,.zip" required>
                            <p class="form-input-hint">
                                <i class="icon icon-check text-success mr-1"></i>
                                <strong>Supported formats:</strong> XML, GZ (gzipped XML), ZIP (containing XML)<br>
                                <i class="icon icon-resize-horizontal text-primary mr-1"></i>
                                <strong>Maximum file size:</strong> 10MB
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <button class="btn btn-primary btn-lg" type="submit">
                                <i class="icon icon-upload"></i> Upload and Process Report
                            </button>
                            <a href="/home" class="btn btn-link btn-lg ml-2">
                                <i class="icon icon-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="divider text-center" data-content="AUTOMATED INGESTION"></div>
            
            <div class="card">
                <div class="card-header">
                    <div class="card-title h5">
                        <i class="icon icon-mail mr-1"></i>
                        Email Ingestion Setup
                    </div>
                    <div class="card-subtitle text-gray">
                        <i class="icon icon-time mr-1"></i>
                        Coming in Phase 2
                    </div>
                </div>
                <div class="card-body">
                    <div class="empty">
                        <div class="empty-icon">
                            <i class="icon icon-3x icon-mail text-primary"></i>
                        </div>
                        <p class="empty-title h5">Automated Email Processing</p>
                        <p class="empty-subtitle">
                            <strong>Phase 2 will include:</strong>
                        </p>
                        <div class="columns">
                            <div class="column col-12 col-sm-6">
                                <div class="tile tile-centered">
                                    <div class="tile-content">
                                        <div class="tile-title">
                                            <i class="icon icon-mail text-success mr-1"></i>
                                            IMAP Integration
                                        </div>
                                        <small class="tile-subtitle text-gray">
                                            Monitor mailboxes automatically
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="column col-12 col-sm-6 mt-2 mt-sm-0">
                                <div class="tile tile-centered">
                                    <div class="tile-content">
                                        <div class="tile-title">
                                            <i class="icon icon-share text-primary mr-1"></i>
                                            API Integration
                                        </div>
                                        <small class="tile-subtitle text-gray">
                                            Gmail & Microsoft Graph APIs
                                        </small>
                                    </div>
                                </div>
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