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

    <div class="container grid-lg">
        <div class="columns">
            <div class="column col-8 col-mx-auto">
                <h2>Upload DMARC Reports</h2>
                
                <div class="card">
                    <div class="card-header">
                        <div class="card-title h5">Manual Upload</div>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" class="form-horizontal">
                            <input type="hidden" name="csrf_token" value="<?php echo App\Core\SessionManager::getInstance()->get('csrf_token'); ?>">
                            
                            <div class="form-group">
                                <div class="col-3 col-sm-12">
                                    <label class="form-label" for="report_file">Select Report File</label>
                                </div>
                                <div class="col-9 col-sm-12">
                                    <input class="form-input" type="file" id="report_file" name="report_file" 
                                           accept=".xml,.gz,.zip" required>
                                    <p class="form-input-hint">
                                        Supported formats: XML, GZ (gzipped XML), ZIP (containing XML)
                                        <br>Maximum file size: 10MB
                                    </p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="col-9 col-ml-auto">
                                    <button class="btn btn-primary" type="submit">Upload and Process</button>
                                    <a href="/" class="btn btn-link">Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="divider text-center" data-content="AUTOMATED INGESTION"></div>
                
                <div class="card">
                    <div class="card-header">
                        <div class="card-title h5">Email Ingestion Setup</div>
                        <div class="card-subtitle text-gray">Coming in future updates</div>
                    </div>
                    <div class="card-body">
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="icon icon-2x icon-mail"></i>
                            </div>
                            <p class="empty-title h5">Automated Email Processing</p>
                            <p class="empty-subtitle">
                                Future versions will support:
                                <br>• IMAP mailbox monitoring
                                <br>• Gmail API integration  
                                <br>• Microsoft Graph API support
                                <br>• Automatic report parsing and storage
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
require 'partials/footer.php';
?>