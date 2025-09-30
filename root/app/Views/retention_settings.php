<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Data retention settings view.
 */

require 'partials/header.php';

$settings = $this->data['settings'] ?? [];
?>

<div class="columns">
    <div class="column col-12">
        <div class="hero bg-primary text-primary-content">
            <div class="hero-body">
                <h2 class="text-light mb-0"><i class="icon icon-time mr-1"></i> Data Retention Policy</h2>
                <p class="text-light mt-2 mb-0">
                    Configure how long aggregate, forensic, and TLS reports are kept in the dashboard. Shorter retention windows reduce storage requirements and
                    limit exposure of historical data. Leave a field blank to keep the existing value.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="columns">
    <div class="column col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="card-title h5">Retention windows (days)</div>
                <div class="card-subtitle text-gray">Provide non-negative integers. Updates take effect immediately for future clean-up cycles.</div>
            </div>
            <div class="card-body">
                <form method="POST" action="/retention-settings">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <div class="columns">
                        <div class="column col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="aggregate_retention">Aggregate reports</label>
                                <input type="number" min="0" class="form-input" id="aggregate_retention" name="aggregate_reports_retention_days"
                                       value="<?= htmlspecialchars($settings['aggregate_reports_retention_days'] ?? '') ?>"
                                       placeholder="e.g. 180">
                                <small class="form-input-hint">Applies to RUA summaries and record details.</small>
                            </div>
                        </div>
                        <div class="column col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="forensic_retention">Forensic reports</label>
                                <input type="number" min="0" class="form-input" id="forensic_retention" name="forensic_reports_retention_days"
                                       value="<?= htmlspecialchars($settings['forensic_reports_retention_days'] ?? '') ?>"
                                       placeholder="e.g. 90">
                                <small class="form-input-hint">Removes detailed failure evidence after the window expires.</small>
                            </div>
                        </div>
                        <div class="column col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label" for="tls_retention">TLS reports</label>
                                <input type="number" min="0" class="form-input" id="tls_retention" name="tls_reports_retention_days"
                                       value="<?= htmlspecialchars($settings['tls_reports_retention_days'] ?? '') ?>"
                                       placeholder="e.g. 60">
                                <small class="form-input-hint">Controls SMTP TLS aggregate retention.</small>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="icon icon-check"></i> Update retention policy</button>
                </form>
            </div>
        </div>
    </div>
    <div class="column col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <div class="card-title h5">Operational notes</div>
            </div>
            <div class="card-body text-gray">
                <ul>
                    <li>Retention tasks execute during scheduled maintenance and manual CLI invocations.</li>
                    <li>Shortening a window queues eligible records for deletion on the next run.</li>
                    <li>Leaving a field blank keeps the current configured value.</li>
                    <li>Ensure backups exist before drastically lowering retention for compliance-critical data.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require 'partials/footer.php'; ?>
