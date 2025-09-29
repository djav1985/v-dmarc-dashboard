<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMARC Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
        }
        .feature-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-4 mb-4">
                        <i class="fas fa-shield-alt mr-3"></i>
                        DMARC Dashboard
                    </h1>
                    <p class="lead mb-4">
                        Comprehensive DMARC monitoring and analysis platform for email security
                    </p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="/demo" class="btn btn-light btn-lg px-4 me-md-2">
                            <i class="fas fa-play mr-2"></i>Try Demo
                        </a>
                        <a href="/login" class="btn btn-outline-light btn-lg px-4">
                            <i class="fas fa-sign-in-alt mr-2"></i>Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="display-5">Key Features</h2>
                    <p class="lead text-muted">Everything you need for DMARC monitoring and compliance</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <i class="fas fa-chart-line feature-icon"></i>
                        <h5>Report Analysis</h5>
                        <p class="text-muted">Parse and analyze DMARC aggregate reports (RUA) with detailed insights and trends.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <i class="fas fa-dns feature-icon"></i>
                        <h5>DNS Validation</h5>
                        <p class="text-muted">Comprehensive DNS record validation for SPF, DKIM, DMARC, MTA-STS, and BIMI.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <i class="fas fa-bell feature-icon"></i>
                        <h5>Smart Alerts</h5>
                        <p class="text-muted">Real-time notifications for policy violations, configuration issues, and threats.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <i class="fas fa-globe feature-icon"></i>
                        <h5>Domain Management</h5>
                        <p class="text-muted">Organize domains by brands, set retention policies, and track compliance.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <i class="fas fa-upload feature-icon"></i>
                        <h5>Flexible Ingestion</h5>
                        <p class="text-muted">IMAP, Microsoft Graph, Gmail API, and manual upload support with compression.</p>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 text-center p-4">
                        <i class="fas fa-users feature-icon"></i>
                        <h5>Multi-User Access</h5>
                        <p class="text-muted">Role-based access control, audit logging, and enterprise-grade security.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Section -->
    <section class="bg-light py-5">
        <div class="container text-center">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h3>Ready to get started?</h3>
                    <p class="lead text-muted mb-4">
                        Try our interactive demo to see the DMARC Dashboard in action with sample data.
                    </p>
                    <a href="/demo" class="btn btn-primary btn-lg">
                        <i class="fas fa-rocket mr-2"></i>Launch Demo
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 DMARC Dashboard - Built with V PHP Framework</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="https://github.com/djav1985/v-dmarc-dashboard" class="text-light me-3">
                        <i class="fab fa-github"></i> GitHub
                    </a>
                    <span class="text-muted">Version 1.0</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>