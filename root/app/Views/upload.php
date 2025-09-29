<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container">
    <div class="columns">
        <div class="column col-8 col-mx-auto">
            <div class="card">
                <div class="card-header">
                    <div class="card-title h5">
                        <i class="icon icon-upload mr-1"></i>Upload DMARC Reports
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-gray">
                        Upload DMARC aggregate reports in XML, GZ, or ZIP format. The system will automatically parse and store the report data.
                    </p>

                    <form method="POST" action="/upload" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label" for="dmarc_file">DMARC Report File</label>
                            <input type="file" class="form-input" id="dmarc_file" name="dmarc_file" 
                                   accept=".xml,.gz,.zip,application/xml,text/xml,application/gzip,application/zip" required>
                            <p class="form-input-hint">
                                Supported formats: XML, GZ (gzipped XML), ZIP (containing XML)
                            </p>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg btn-block">
                                <i class="icon icon-upload mr-1"></i>Upload and Process
                            </button>
                        </div>
                    </form>

                    <div class="divider text-center" data-content="FEATURES"></div>

                    <div class="columns">
                        <div class="column col-6">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title h6">Supported File Types</div>
                                </div>
                                <div class="card-body">
                                    <div class="tile tile-centered">
                                        <div class="tile-icon">
                                            <div class="example-tile-icon">
                                                <i class="icon icon-bookmark text-success"></i>
                                            </div>
                                        </div>
                                        <div class="tile-content">
                                            <div class="tile-title">XML files (.xml)</div>
                                        </div>
                                    </div>
                                    <div class="tile tile-centered">
                                        <div class="tile-icon">
                                            <div class="example-tile-icon">
                                                <i class="icon icon-share text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="tile-content">
                                            <div class="tile-title">Gzipped XML (.gz)</div>
                                        </div>
                                    </div>
                                    <div class="tile tile-centered">
                                        <div class="tile-icon">
                                            <div class="example-tile-icon">
                                                <i class="icon icon-folder text-warning"></i>
                                            </div>
                                        </div>
                                        <div class="tile-content">
                                            <div class="tile-title">ZIP archives (.zip)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="column col-6">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title h6">Processing Features</div>
                                </div>
                                <div class="card-body">
                                    <div class="tile tile-centered">
                                        <div class="tile-icon">
                                            <div class="example-tile-icon">
                                                <i class="icon icon-check text-success"></i>
                                            </div>
                                        </div>
                                        <div class="tile-content">
                                            <div class="tile-title">Automatic decompression</div>
                                        </div>
                                    </div>
                                    <div class="tile tile-centered">
                                        <div class="tile-icon">
                                            <div class="example-tile-icon">
                                                <i class="icon icon-check text-success"></i>
                                            </div>
                                        </div>
                                        <div class="tile-content">
                                            <div class="tile-title">Domain auto-creation</div>
                                        </div>
                                    </div>
                                    <div class="tile tile-centered">
                                        <div class="tile-icon">
                                            <div class="example-tile-icon">
                                                <i class="icon icon-check text-success"></i>
                                            </div>
                                        </div>
                                        <div class="tile-content">
                                            <div class="tile-title">Duplicate detection</div>
                                        </div>
                                    </div>
                                    <div class="tile tile-centered">
                                        <div class="tile-icon">
                                            <div class="example-tile-icon">
                                                <i class="icon icon-check text-success"></i>
                                            </div>
                                        </div>
                                        <div class="tile-content">
                                            <div class="tile-title">Error reporting</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="toast">
                        <button class="btn btn-clear float-right"></button>
                        <strong>Note:</strong> Large files may take some time to process. The system will provide feedback once processing is complete.
                    </div>
                </div>
            </div>

            <!-- Recent Uploads (placeholder) -->
            <div class="card mt-2">
                <div class="card-header">
                    <div class="card-title h5">Recent Activity</div>
                </div>
                <div class="card-body">
                    <div class="empty">
                        <div class="empty-icon">
                            <i class="icon icon-inbox icon-2x"></i>
                        </div>
                        <p class="empty-title h5">No recent uploads</p>
                        <p class="empty-subtitle">Upload your first DMARC report to see activity here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('dmarc_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const validTypes = ['application/xml', 'text/xml', 'application/gzip', 'application/zip'];
        const validExtensions = ['.xml', '.gz', '.zip'];
        
        const hasValidType = validTypes.includes(file.type);
        const hasValidExtension = validExtensions.some(ext => file.name.toLowerCase().endsWith(ext));
        
        if (!hasValidType && !hasValidExtension) {
            alert('Please select a valid DMARC report file (XML, GZ, or ZIP format)');
            e.target.value = '';
            return;
        }
        
        if (file.size > 50 * 1024 * 1024) { // 50MB limit
            alert('File size too large. Please select a file smaller than 50MB.');
            e.target.value = '';
            return;
        }
    }
});
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>