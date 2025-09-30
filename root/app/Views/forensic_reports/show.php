<?php
require __DIR__ . '/../partials/header.php';
?>
    <div class="columns mt-2">
        <div class="column col-12 col-lg-10 col-mx-auto">
            <div class="card">
                <div class="card-header">
                    <div class="card-title h5">Forensic report for <?= htmlspecialchars($report['domain']); ?></div>
                    <div class="card-subtitle text-gray">Received <?= htmlspecialchars($report['arrival_date']); ?></div>
                </div>
                <div class="card-body">
                    <div class="columns">
                        <div class="column col-12 col-md-6">
                            <dl class="data-list">
                                <dt>Source IP</dt>
                                <dd><?= htmlspecialchars($report['source_ip']); ?></dd>
                                <dt>Original envelope ID</dt>
                                <dd><?= htmlspecialchars($report['original_envelope_id'] ?? 'n/a'); ?></dd>
                                <dt>Authentication</dt>
                                <dd>
                                    <span class="label label-rounded label-primary">DKIM: <?= htmlspecialchars($report['dkim_result'] ?? 'n/a'); ?></span>
                                    <span class="label label-rounded label-secondary">SPF: <?= htmlspecialchars($report['spf_result'] ?? 'n/a'); ?></span>
                                </dd>
                            </dl>
                        </div>
                        <div class="column col-12 col-md-6">
                            <dl class="data-list">
                                <dt>DKIM domain/selector</dt>
                                <dd><?= htmlspecialchars(($report['dkim_domain'] ?? 'n/a') . ' / ' . ($report['dkim_selector'] ?? 'n/a')); ?></dd>
                                <dt>SPF domain</dt>
                                <dd><?= htmlspecialchars($report['spf_domain'] ?? 'n/a'); ?></dd>
                                <dt>Authentication results</dt>
                                <dd><code><?= htmlspecialchars($report['authentication_results'] ?? 'Unavailable'); ?></code></dd>
                            </dl>
                        </div>
                    </div>
                    <div class="divider" data-content="RAW MESSAGE"></div>
                    <pre class="code" style="max-height: 320px; overflow:auto;"><?= htmlspecialchars($report['raw_message'] ?? 'No raw sample captured.'); ?></pre>
                </div>
                <div class="card-footer">
                    <a class="btn btn-link" href="/forensic-reports">
                        <i class="icon icon-arrow-left"></i> Back to forensic reports
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php require __DIR__ . '/../partials/footer.php';
?>
