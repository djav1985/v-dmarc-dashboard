<?php require_once __DIR__ . '/partials/header.php'; ?>

<div class="columns">
    <div class="column col-12">
        <h2><i class="icon icon-photo"></i> Branding Settings</h2>
        <p class="text-gray">Customize the appearance and branding of your DMARC Dashboard.</p>
    </div>
</div>

<div class="columns">
    <!-- General Settings -->
    <div class="column col-12 col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title">General Branding</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/branding">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="app_name">Application Name</label>
                        <input class="form-input" type="text" id="app_name" name="app_name" 
                               value="<?= htmlspecialchars($settings['app_name'] ?? 'DMARC Dashboard') ?>">
                        <div class="form-input-hint">This appears in the header and page titles</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="company_name">Company Name</label>
                        <input class="form-input" type="text" id="company_name" name="company_name" 
                               value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>">
                        <div class="form-input-hint">Optional company name for footer branding</div>
                    </div>
                    
                    <div class="columns">
                        <div class="column col-12 col-sm-6">
                            <div class="form-group">
                                <label class="form-label" for="primary_color">Primary Color</label>
                                <input class="form-input" type="color" id="primary_color" name="primary_color"
                                       value="<?= htmlspecialchars($settings['primary_color'] ?? '#5755d9') ?>">
                            </div>
                        </div>
                        <div class="column col-12 col-sm-6 mt-2 mt-sm-0">
                            <div class="form-group">
                                <label class="form-label" for="secondary_color">Secondary Color</label>
                                <input class="form-input" type="color" id="secondary_color" name="secondary_color"
                                       value="<?= htmlspecialchars($settings['secondary_color'] ?? '#f1f3f4') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="theme_mode">Theme Mode</label>
                        <select class="form-select" id="theme_mode" name="theme_mode">
                            <option value="light" <?= ($settings['theme_mode'] ?? 'light') === 'light' ? 'selected' : '' ?>>Light</option>
                            <option value="dark" <?= ($settings['theme_mode'] ?? 'light') === 'dark' ? 'selected' : '' ?>>Dark</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="footer_text">Footer Text</label>
                        <input class="form-input" type="text" id="footer_text" name="footer_text" 
                               value="<?= htmlspecialchars($settings['footer_text'] ?? '') ?>" 
                               placeholder="Powered by V PHP Framework">
                        <div class="form-input-hint">Custom text for the footer (leave empty for default)</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" name="enable_custom_css" value="1" 
                                   <?= !empty($settings['enable_custom_css']) ? 'checked' : '' ?>>
                            Enable Custom CSS
                        </label>
                        <div class="form-input-hint">Allow injection of custom CSS styles</div>
                    </div>
                    
                    <div class="form-group" id="custom-css-group" style="<?= empty($settings['enable_custom_css']) ? 'display: none;' : '' ?>">
                        <label class="form-label" for="custom_css">Custom CSS</label>
                        <textarea class="form-input" id="custom_css" name="custom_css" rows="8" 
                                  placeholder="/* Add your custom CSS here */"><?= htmlspecialchars($settings['custom_css'] ?? '') ?></textarea>
                        <div class="form-input-hint">Advanced: Add custom CSS to override default styles</div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="icon icon-check"></i> Save Settings
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetDefaults()">
                            <i class="icon icon-refresh"></i> Reset to Defaults
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Logo Upload -->
    <div class="column col-12 col-lg-4 mt-2 mt-lg-0">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title">Logo</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($settings['app_logo_url'])) : ?>
                    <div class="text-center mb-2">
                        <img src="<?= htmlspecialchars($settings['app_logo_url']) ?>" 
                             alt="Current Logo" style="max-width: 200px; max-height: 100px;">
                        <p class="text-gray mt-1">Current Logo</p>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="/branding" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_logo">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="logo">Upload New Logo</label>
                        <input class="form-input" type="file" id="logo" name="logo" 
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="form-input-hint">
                            Max size: 2MB<br>
                            Formats: JPEG, PNG, GIF, WebP<br>
                            Recommended: 200x50px
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="icon icon-upload"></i> Upload Logo
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Preview -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Preview</h5>
            </div>
            <div class="card-body">
                <div id="color-preview" class="text-center p-2 mb-2" style="background-color: var(--primary-color); color: white; border-radius: 6px;">
                    <strong><?= htmlspecialchars($settings['app_name'] ?? 'DMARC Dashboard') ?></strong>
                </div>
                <p class="text-center text-gray">
                    Primary color preview
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Reset Confirmation Modal -->
<div class="modal" id="reset-modal">
    <a href="#close" class="modal-overlay" onclick="closeResetModal()"></a>
    <div class="modal-container">
        <div class="modal-header">
            <a href="#close" class="btn btn-clear float-right" onclick="closeResetModal()"></a>
            <div class="modal-title h5">Reset to Defaults</div>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to reset all branding settings to their default values? This action cannot be undone.</p>
            
            <form method="POST" action="/branding">
                <input type="hidden" name="action" value="reset_defaults">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                
                <div class="form-group">
                    <button type="submit" class="btn btn-error">Reset to Defaults</button>
                    <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle custom CSS visibility
document.querySelector('input[name="enable_custom_css"]').addEventListener('change', function() {
    const customCssGroup = document.getElementById('custom-css-group');
    customCssGroup.style.display = this.checked ? 'block' : 'none';
});

// Update color preview
document.getElementById('primary_color').addEventListener('change', function() {
    const preview = document.getElementById('color-preview');
    preview.style.backgroundColor = this.value;
    document.documentElement.style.setProperty('--primary-color', this.value);
});

function resetDefaults() {
    document.getElementById('reset-modal').classList.add('active');
}

function closeResetModal() {
    document.getElementById('reset-modal').classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>