<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
/**
 * Project: V PHP Framework
 * Author:  Vontainment <services@vontainment.com>
 * License: https://opensource.org/licenses/MIT MIT License
 * Link:    https://vontainment.com
 * Version: 3.0.0
 *
 * File: domain_groups.php
 * Description: Domain Groups management interface
 */

require 'partials/header.php';
?>

<style>
.group-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.domain-tag {
    display: inline-block;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 0.25rem 0.5rem;
    margin: 0.25rem;
    font-size: 0.875rem;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}
.stat-item {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
}
.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: #495057;
}
.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
}
</style>

<div class="columns">
    <div class="column col-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <h2>
                <i class="icon icon-2x icon-bookmark text-primary mr-2"></i>
                Domain Groups
            </h2>
            <button class="btn btn-primary" onclick="document.getElementById('create-group-modal').classList.add('active')">
                <i class="icon icon-plus"></i> New Group
            </button>
        </div>
        <p class="text-gray">Organize your domains by business units or brands for better management and reporting.</p>
    </div>
</div>

<!-- Domain Groups List -->
<div class="columns">
    <div class="column col-12 col-lg-8">
        <?php if (empty($this->data['groups'])): ?>
            <div class="empty">
                <div class="empty-icon">
                    <i class="icon icon-4x icon-bookmark text-gray"></i>
                </div>
                <p class="empty-title h5">No Domain Groups</p>
                <p class="empty-subtitle">Create your first domain group to organize your domains by business units or brands.</p>
                <div class="empty-action">
                    <button class="btn btn-primary" onclick="document.getElementById('create-group-modal').classList.add('active')">
                        <i class="icon icon-plus"></i> Create Group
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($this->data['groups'] as $group): ?>
                <div class="group-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-start">
                        <div>
                            <h4><?= htmlspecialchars($group['name']) ?></h4>
                            <?php if ($group['description']): ?>
                                <p class="text-gray"><?= htmlspecialchars($group['description']) ?></p>
                            <?php endif; ?>
                            <div class="mt-1">
                                <span class="label label-secondary"><?= $group['domain_count'] ?> domains</span>
                            </div>
                        </div>
                        <div class="dropdown dropdown-right">
                            <a href="#" class="btn btn-link dropdown-toggle" tabindex="0">
                                <i class="icon icon-more-vert"></i>
                            </a>
                            <ul class="menu">
                                <li class="menu-item">
                                    <a href="#" onclick="showAssignModal(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name']) ?>')">
                                        <i class="icon icon-plus"></i> Assign Domain
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Group Domains -->
                    <div class="mt-2">
                        <?php 
                        $groupDomains = \App\Models\DomainGroup::getGroupDomains($group['id']);
                        if (!empty($groupDomains)): 
                        ?>
                            <strong>Domains:</strong>
                            <div class="mt-1">
                                <?php foreach ($groupDomains as $domain): ?>
                                    <span class="domain-tag">
                                        <?= htmlspecialchars($domain['domain']) ?>
                                        <form method="POST" action="/domain-groups" style="display: inline;" onsubmit="return confirm('Remove this domain from the group?')">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                            <input type="hidden" name="action" value="remove_domain">
                                            <input type="hidden" name="domain_id" value="<?= $domain['id'] ?>">
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <button type="submit" class="btn btn-link btn-sm p-0 ml-1" style="color: #dc3545;">
                                                <i class="icon icon-cross"></i>
                                            </button>
                                        </form>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray">No domains assigned to this group yet.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Group Analytics -->
                    <?php 
                    $analytics = null;
                    foreach ($this->data['group_analytics'] as $ga) {
                        if ($ga['id'] == $group['id']) {
                            $analytics = $ga;
                            break;
                        }
                    }
                    if ($analytics && $analytics['total_volume'] > 0): 
                    ?>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?= number_format($analytics['total_volume']) ?></div>
                                <div class="stat-label">Messages</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= number_format($analytics['report_count']) ?></div>
                                <div class="stat-label">Reports</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= number_format($analytics['pass_rate'], 1) ?>%</div>
                                <div class="stat-label">Pass Rate</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Unassigned Domains Sidebar -->
    <div class="column col-12 col-lg-4 mt-2 mt-lg-0">
        <div class="card">
            <div class="card-header">
                <div class="card-title h6">Unassigned Domains</div>
            </div>
            <div class="card-body">
                <?php if (empty($this->data['unassigned_domains'])): ?>
                    <p class="text-gray">All domains are assigned to groups.</p>
                <?php else: ?>
                    <?php foreach ($this->data['unassigned_domains'] as $domain): ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
                            <span><?= htmlspecialchars($domain['domain']) ?></span>
                            <button class="btn btn-sm btn-primary" onclick="showAssignModal(0, '', <?= $domain['id'] ?>)">
                                Assign
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Group Modal -->
<div class="modal" id="create-group-modal">
    <a href="#close" class="modal-overlay" aria-label="Close"></a>
    <div class="modal-container">
        <div class="modal-header">
            <a href="#close" class="btn btn-clear float-right" aria-label="Close"></a>
            <div class="modal-title h5">Create New Group</div>
        </div>
        <div class="modal-body">
            <form method="POST" action="/domain-groups">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="create_group">
                
                <div class="form-group">
                    <label class="form-label" for="group_name">Group Name</label>
                    <input type="text" class="form-input" id="group_name" name="group_name" required 
                           placeholder="e.g., Corporate, Marketing, Development">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="group_description">Description (Optional)</label>
                    <textarea class="form-input" id="group_description" name="group_description" rows="3"
                              placeholder="Brief description of this group's purpose"></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Create Group</button>
                    <a href="#close" class="btn btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Domain Modal -->
<div class="modal" id="assign-domain-modal">
    <a href="#close" class="modal-overlay" aria-label="Close"></a>
    <div class="modal-container">
        <div class="modal-header">
            <a href="#close" class="btn btn-clear float-right" aria-label="Close"></a>
            <div class="modal-title h5">Assign Domain to Group</div>
        </div>
        <div class="modal-body">
            <form method="POST" action="/domain-groups">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="assign_domain">
                <input type="hidden" id="assign_group_id" name="group_id" value="">
                
                <div class="form-group">
                    <label class="form-label" for="assign_domain_id">Select Domain</label>
                    <select class="form-select" id="assign_domain_id" name="domain_id" required>
                        <option value="">Choose a domain...</option>
                        <?php foreach ($this->data['all_domains'] as $domain): ?>
                            <option value="<?= $domain['id'] ?>"><?= htmlspecialchars($domain['domain']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="assign_group_select">Select Group</label>
                    <select class="form-select" id="assign_group_select" name="group_id_select" onchange="updateGroupId(this.value)">
                        <option value="">Choose a group...</option>
                        <?php foreach ($this->data['groups'] as $group): ?>
                            <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Assign Domain</button>
                    <a href="#close" class="btn btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAssignModal(groupId = 0, groupName = '', domainId = 0) {
    const modal = document.getElementById('assign-domain-modal');
    const groupIdField = document.getElementById('assign_group_id');
    const groupSelect = document.getElementById('assign_group_select');
    const domainSelect = document.getElementById('assign_domain_id');
    
    // Set group if specified
    if (groupId > 0) {
        groupIdField.value = groupId;
        groupSelect.value = groupId;
        groupSelect.disabled = true;
    } else {
        groupIdField.value = '';
        groupSelect.value = '';
        groupSelect.disabled = false;
    }
    
    // Set domain if specified
    if (domainId > 0) {
        domainSelect.value = domainId;
    }
    
    modal.classList.add('active');
}

function updateGroupId(groupId) {
    document.getElementById('assign_group_id').value = groupId;
}

// Close modals when clicking close links
document.addEventListener('click', function(e) {
    if (e.target.getAttribute('href') === '#close') {
        e.preventDefault();
        e.target.closest('.modal').classList.remove('active');
    }
});
</script>

<?php require 'partials/footer.php'; ?>