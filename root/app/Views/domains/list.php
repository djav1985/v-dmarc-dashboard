<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container">
    <div class="columns">
        <div class="column col-12">
            <div class="d-flex">
                <h1 class="flex-1">Domains</h1>
                <button class="btn btn-primary" onclick="document.getElementById('add-domain-modal').classList.add('active')">
                    <i class="icon icon-plus mr-1"></i>Add Domain
                </button>
            </div>

            <!-- Domains Table -->
            <div class="card mt-2">
                <div class="card-body">
                    <?php if (empty($domains)): ?>
                        <div class="empty">
                            <div class="empty-icon">
                                <i class="icon icon-location icon-3x"></i>
                            </div>
                            <p class="empty-title h5">No domains configured</p>
                            <p class="empty-subtitle">Add your first domain to start monitoring DMARC reports.</p>
                            <div class="empty-action">
                                <button class="btn btn-primary" onclick="document.getElementById('add-domain-modal').classList.add('active')">
                                    Add Domain
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Brand</th>
                                    <th>DNS Status</th>
                                    <th>DMARC Policy</th>
                                    <th>Last Checked</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($domains as $domain): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($domain->domain) ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($domain->brand_name): ?>
                                                <span class="label label-rounded"><?= htmlspecialchars($domain->brand_name) ?></span>
                                            <?php else: ?>
                                                <span class="text-gray">No brand</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dns-status">
                                                <!-- DMARC Status -->
                                                <span class="label <?= $domain->dmarc_record ? 'label-success' : 'label-warning' ?>" 
                                                      title="DMARC">D</span>
                                                <!-- SPF Status -->
                                                <span class="label <?= $domain->spf_record ? 'label-success' : 'label-warning' ?>" 
                                                      title="SPF">S</span>
                                                <!-- DKIM Status -->
                                                <span class="label <?= $domain->dkim_selectors && $domain->dkim_selectors !== '[]' ? 'label-success' : 'label-warning' ?>" 
                                                      title="DKIM">K</span>
                                                <!-- MTA-STS Status -->
                                                <span class="label <?= $domain->mta_sts_enabled ? 'label-success' : 'label-secondary' ?>" 
                                                      title="MTA-STS">M</span>
                                                <!-- BIMI Status -->
                                                <span class="label <?= $domain->bimi_enabled ? 'label-success' : 'label-secondary' ?>" 
                                                      title="BIMI">B</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="label <?= 
                                                $domain->dmarc_policy === 'reject' ? 'label-success' : 
                                                ($domain->dmarc_policy === 'quarantine' ? 'label-warning' : 'label-error') 
                                            ?>">
                                                <?= htmlspecialchars(strtoupper($domain->dmarc_policy)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($domain->last_checked): ?>
                                                <small><?= date('M j, Y H:i', strtotime($domain->last_checked)) ?></small>
                                            <?php else: ?>
                                                <span class="text-gray">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($domain->is_active): ?>
                                                <span class="label label-success">Active</span>
                                            <?php else: ?>
                                                <span class="label label-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-block">
                                                <a href="/domains/<?= $domain->id ?>" class="btn btn-sm">
                                                    <i class="icon icon-search"></i>
                                                </a>
                                                <button class="btn btn-sm" onclick="validateDomain(<?= $domain->id ?>)">
                                                    <i class="icon icon-refresh"></i>
                                                </button>
                                                <button class="btn btn-sm" onclick="editDomain(<?= htmlspecialchars(json_encode($domain)) ?>)">
                                                    <i class="icon icon-edit"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Domain Modal -->
<div class="modal" id="add-domain-modal">
    <a href="#close" class="modal-overlay" onclick="document.getElementById('add-domain-modal').classList.remove('active')"></a>
    <div class="modal-container">
        <div class="modal-header">
            <a href="#close" class="btn btn-clear float-right" onclick="document.getElementById('add-domain-modal').classList.remove('active')"></a>
            <div class="modal-title h5">Add Domain</div>
        </div>
        <div class="modal-body">
            <form method="POST" action="/domains">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label" for="domain">Domain Name *</label>
                    <input type="text" class="form-input" id="domain" name="domain" 
                           placeholder="example.com" required>
                    <p class="form-input-hint">Enter the domain name without protocol (e.g., example.com)</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="brand_id">Brand</label>
                    <select class="form-select" id="brand_id" name="brand_id">
                        <option value="">No brand</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= $brand->id ?>"><?= htmlspecialchars($brand->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="button" class="btn" onclick="document.getElementById('add-domain-modal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Domain</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Domain Modal -->
<div class="modal" id="edit-domain-modal">
    <a href="#close" class="modal-overlay" onclick="document.getElementById('edit-domain-modal').classList.remove('active')"></a>
    <div class="modal-container">
        <div class="modal-header">
            <a href="#close" class="btn btn-clear float-right" onclick="document.getElementById('edit-domain-modal').classList.remove('active')"></a>
            <div class="modal-title h5">Edit Domain</div>
        </div>
        <div class="modal-body">
            <form method="POST" action="/domains">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="domain_id" id="edit_domain_id">
                
                <div class="form-group">
                    <label class="form-label">Domain Name</label>
                    <input type="text" class="form-input" id="edit_domain_name" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_brand_id">Brand</label>
                    <select class="form-select" id="edit_brand_id" name="brand_id">
                        <option value="">No brand</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= $brand->id ?>"><?= htmlspecialchars($brand->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_retention_days">Retention Days</label>
                    <input type="number" class="form-input" id="edit_retention_days" 
                           name="retention_days" min="1" max="3650" value="365">
                    <p class="form-input-hint">How long to keep DMARC reports (1-3650 days)</p>
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" id="edit_is_active" name="is_active" checked>
                        <i class="form-icon"></i> Active
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="button" class="btn" onclick="document.getElementById('edit-domain-modal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Domain</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDomain(domain) {
    document.getElementById('edit_domain_id').value = domain.id;
    document.getElementById('edit_domain_name').value = domain.domain;
    document.getElementById('edit_brand_id').value = domain.brand_id || '';
    document.getElementById('edit_retention_days').value = domain.retention_days || 365;
    document.getElementById('edit_is_active').checked = domain.is_active == 1;
    document.getElementById('edit-domain-modal').classList.add('active');
}

function validateDomain(domainId) {
    if (confirm('Validate DNS records for this domain?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/domains';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'validate';
        
        const domainInput = document.createElement('input');
        domainInput.type = 'hidden';
        domainInput.name = 'domain_id';
        domainInput.value = domainId;
        
        form.appendChild(actionInput);
        form.appendChild(domainInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>