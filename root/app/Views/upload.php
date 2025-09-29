<?php include __DIR__ . '/partials/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-upload mr-2"></i>Upload DMARC Reports
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Upload DMARC aggregate reports in XML, GZ, or ZIP format. The system will automatically parse and store the report data.
                    </p>

                    <form method="POST" action="/upload" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="dmarc_file" class="form-label">DMARC Report File</label>
                            <input type="file" class="form-control" id="dmarc_file" name="dmarc_file" 
                                   accept=".xml,.gz,.zip,application/xml,text/xml,application/gzip,application/zip" required>
                            <div class="form-text">
                                Supported formats: XML, GZ (gzipped XML), ZIP (containing XML)
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload mr-2"></i>Upload and Process
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">Supported File Types</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-file-code text-success mr-2"></i>XML files (.xml)</li>
                                <li><i class="fas fa-file-archive text-info mr-2"></i>Gzipped XML (.gz)</li>
                                <li><i class="fas fa-file-archive text-warning mr-2"></i>ZIP archives (.zip)</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Processing Features</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success mr-2"></i>Automatic decompression</li>
                                <li><i class="fas fa-check text-success mr-2"></i>Domain auto-creation</li>
                                <li><i class="fas fa-check text-success mr-2"></i>Duplicate detection</li>
                                <li><i class="fas fa-check text-success mr-2"></i>Error reporting</li>
                            </ul>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Note:</strong> Large files may take some time to process. The system will provide feedback once processing is complete.
                    </div>
                </div>
            </div>

            <!-- Recent Uploads (if any) -->
            <div class="card shadow mt-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">Upload your first DMARC report to see activity here.</p>
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