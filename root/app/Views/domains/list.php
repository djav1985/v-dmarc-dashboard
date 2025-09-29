<?php include __DIR__ . '/../partials/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Domains</h1>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDomainModal">
                    <i class="fas fa-plus mr-2"></i>Add Domain
                </button>
            </div>

            <!-- Domains Table -->
            <div class="card shadow">
                <div class="card-body">
                    <?php if (empty($domains)) : ?>
                        <div class="text-center py-5">
                            <i class="fas fa-globe fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No domains configured</h5>
                            <p class="text-muted">Add your first domain to start monitoring DMARC reports.</p>
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDomainModal">
                                Add Domain
                            </button>
                        </div>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
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
                                    <?php foreach ($domains as $domain) : ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($domain->domain) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($domain->brand_name) : ?>
                                                    <span class="badge badge-info"><?= htmlspecialchars($domain->brand_name) ?></span>
                                                <?php else : ?>
                                                    <span class="text-muted">No brand</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex">
                                                    <!-- DMARC Status -->
                                                    <span class="badge badge-<?= $domain->dmarc_record ? 'success' : 'warning' ?> mr-1" 
                                                          title="DMARC">D</span>
                                                    <!-- SPF Status -->
                                                    <span class="badge badge-<?= $domain->spf_record ? 'success' : 'warning' ?> mr-1" 
                                                          title="SPF">S</span>
                                                    <!-- DKIM Status -->
                                                    <span class="badge badge-<?= $domain->dkim_selectors && $domain->dkim_selectors !== '[]' ? 'success' : 'warning' ?> mr-1" 
                                                          title="DKIM">K</span>
                                                    <!-- MTA-STS Status -->
                                                    <span class="badge badge-<?= $domain->mta_sts_enabled ? 'success' : 'secondary' ?> mr-1" 
                                                          title="MTA-STS">M</span>
                                                    <!-- BIMI Status -->
                                                    <span class="badge badge-<?= $domain->bimi_enabled ? 'success' : 'secondary' ?> mr-1" 
                                                          title="BIMI">B</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?=
                                                    $domain->dmarc_policy === 'reject' ? 'success' :
                                                    ($domain->dmarc_policy === 'quarantine' ? 'warning' : 'danger')
                                                ?>">
                                                    <?= htmlspecialchars(strtoupper($domain->dmarc_policy)) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($domain->last_checked) : ?>
                                                    <small><?= date('M j, Y H:i', strtotime($domain->last_checked)) ?></small>
                                                <?php else : ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($domain->is_active) : ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else : ?>
                                                    <span class="badge badge-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="/domains/<?= $domain->id ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-info btn-sm" 
                                                            onclick="validateDomain(<?= $domain->id ?>)">
                                                        <i class="fas fa-sync"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" 
                                                            data-toggle="modal" data-target="#editDomainModal"
                                                            onclick="editDomain(<?= htmlspecialchars(json_encode($domain)) ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/domains">
                <div class="modal-header">
                    <h5 class="modal-title">Add Domain</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="domain">Domain Name *</label>
                        <input type="text" class="form-control" id="domain" name="domain" 
                               placeholder="example.com" required>
                        <small class="form-text text-muted">Enter the domain name without protocol (e.g., example.com)</small>
                    </div>

                    <div class="form-group">
                        <label for="brand_id">Brand</label>
                        <select class="form-control" id="brand_id" name="brand_id">
                            <option value="">No brand</option>
                            <?php foreach ($brands as $brand) : ?>
                                <option value="<?= $brand->id ?>"><?= htmlspecialchars($brand->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Domain</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Domain Modal -->
<div class="modal fade" id="editDomainModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/domains">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Domain</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="domain_id" id="edit_domain_id">
                    
                    <div class="form-group">
                        <label>Domain Name</label>
                        <input type="text" class="form-control" id="edit_domain_name" readonly>
                    </div>

                    <div class="form-group">
                        <label for="edit_brand_id">Brand</label>
                        <select class="form-control" id="edit_brand_id" name="brand_id">
                            <option value="">No brand</option>
                            <?php foreach ($brands as $brand) : ?>
                                <option value="<?= $brand->id ?>"><?= htmlspecialchars($brand->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_retention_days">Retention Days</label>
                        <input type="number" class="form-control" id="edit_retention_days" 
                               name="retention_days" min="1" max="3650" value="365">
                        <small class="form-text text-muted">How long to keep DMARC reports (1-3650 days)</small>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active" checked>
                        <label class="form-check-label" for="edit_is_active">
                            Active
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
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