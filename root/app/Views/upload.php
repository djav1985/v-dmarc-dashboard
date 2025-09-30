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
                            <label class="form-label" for="report_files">
                                <i class="icon icon-bookmark mr-1"></i>
                                Select Report Files
                            </label>
                            <input class="form-input input-lg" type="file" id="report_files" name="report_files[]"
                                   accept=".xml,.gz,.zip" multiple required>
                            <p class="form-input-hint">
                                <i class="icon icon-check text-success mr-1"></i>
                                <strong>Supported formats:</strong> XML, GZ (gzipped XML), ZIP (containing XML)<br>
                                <i class="icon icon-resize-horizontal text-primary mr-1"></i>
                                <strong>Maximum file size:</strong> 10MB per file<br>
                                <i class="icon icon-stack text-gray mr-1"></i>
                                <strong>Tip:</strong> Upload multiple reports at once for bulk processing.
                            </p>
                        </div>

                        <div class="form-group">
                            <button class="btn btn-primary btn-lg" type="submit">
                                <i class="icon icon-upload"></i> Upload and Process Reports
                            </button>
                            <a href="/home" class="btn btn-link btn-lg ml-2">
                                <i class="icon icon-arrow-left"></i> Back to dashboard
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
                        Automated Connectors
                    </div>
                    <div class="card-subtitle">Integrate DMARC data with your existing toolchain</div>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-left">
                                <a class="timeline-icon icon-lg" href="#">
                                    <i class="icon icon-link"></i>
                                </a>
                            </div>
                            <div class="timeline-content">
                                <h5>SIEM forwarding</h5>
                                <p>Future connector will stream parsed reports to your security incident and event management platform.</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-left">
                                <a class="timeline-icon icon-lg" href="#">
                                    <i class="icon icon-send"></i>
                                </a>
                            </div>
                            <div class="timeline-content">
                                <h5>Webhook broadcasts</h5>
                                <p>Webhooks will deliver ingest summaries to downstream automation services.</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-left">
                                <a class="timeline-icon icon-lg" href="#">
                                    <i class="icon icon-folder"></i>
                                </a>
                            </div>
                            <div class="timeline-content">
                                <h5>Data lake archiving</h5>
                                <p>Roadmap work includes archiving raw uploads to long-term storage buckets.</p>
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
