<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>🏢 Vendor Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal">
        <i class="bi bi-plus-lg"></i> Add New Vendor
    </button>
</div>

<?php
$bulkFormId = 'bulkVendors';
$bulkAction = 'index.php?page=vendors';
$bulkButtons = [
    ['value' => 'delete', 'label' => 'Delete selected', 'class' => 'btn-outline-danger', 'title' => 'Delete the selected vendors?', 'text' => 'Vendors with goods-received records are kept.', 'confirm' => 'Delete', 'color' => '#dc3545'],
];
include __DIR__ . '/partials/bulk_bar.php';
?>
<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkVendors" title="Select all"></th>
                    <th>Vendor Name</th>
                    <th>Contact Person</th>
                    <th>Phone Number</th>
                    <th>Portal</th>
                    <th>Payment</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $v): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$v['id'] ?>" form="bulkVendors"></td>
                    <td class="fw-bold"><?= htmlspecialchars($v['name']) ?></td>
                    <td><?= htmlspecialchars($v['contact_person'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($v['phone'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($v['portal_active']) && !empty($v['portal_email'])): ?>
                            <span class="badge bg-success">On</span>
                            <div class="small text-muted"><?= htmlspecialchars((string)$v['portal_email']) ?></div>
                        <?php else: ?>
                            <span class="badge bg-secondary">Off</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $pay = \App\Models\Vendor::payoutDestination($v); ?>
                        <?php if (!empty($pay['ready'])): ?>
                            <span class="badge bg-success"><?= $pay['method'] === 'momo' ? 'Mobile' : 'Bank' ?></span>
                            <?php if (preg_match('/^RS_[A-Z0-9]+$/', (string)($v['payout_subaccount_id'] ?? ''))): ?>
                                <span class="badge bg-dark">Flutterwave</span>
                            <?php endif; ?>
                            <div class="small text-muted"><?= htmlspecialchars($pay['bank_name'] !== '' ? $pay['bank_name'] : $pay['masked']) ?> <?= htmlspecialchars($pay['masked']) ?></div>
                        <?php else: ?>
                            <span class="badge bg-secondary">None</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#payModal"
                            data-id="<?= (int)$v['id'] ?>"
                            data-name="<?= htmlspecialchars((string)$v['name'], ENT_QUOTES) ?>"
                            data-method="<?= htmlspecialchars((string)($v['payout_method'] ?? ''), ENT_QUOTES) ?>"
                            data-network="<?= htmlspecialchars((string)($v['payout_network'] ?? ''), ENT_QUOTES) ?>"
                            data-bank="<?= htmlspecialchars((string)($v['payout_bank'] ?? ''), ENT_QUOTES) ?>"
                            data-bank-name="<?= htmlspecialchars((string)($v['payout_bank_name'] ?? ''), ENT_QUOTES) ?>"
                            data-account="<?= htmlspecialchars((string)($v['payout_account'] ?? ''), ENT_QUOTES) ?>"
                            data-account-name="<?= htmlspecialchars((string)($v['payout_name'] ?? ''), ENT_QUOTES) ?>"
                            data-branch="<?= htmlspecialchars((string)($v['payout_branch'] ?? ''), ENT_QUOTES) ?>">Payment</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#portalModal"
                            data-id="<?= (int)$v['id'] ?>"
                            data-name="<?= htmlspecialchars((string)$v['name'], ENT_QUOTES) ?>"
                            data-email="<?= htmlspecialchars((string)($v['portal_email'] ?? ''), ENT_QUOTES) ?>"
                            data-active="<?= !empty($v['portal_active']) ? '1' : '0' ?>">Portal</button>
                        <form method="POST" action="index.php?page=vendors" class="d-inline js-confirm-form"
                            data-swal-title="Delete this vendor?"
                            data-swal-text="This cannot be undone."
                            data-swal-confirm="Delete"
                            data-swal-cancel="Cancel"
                            data-swal-icon="warning">
                            <input type="hidden" name="delete_vendor" value="1">
                            <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($vendors)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No vendors found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="vendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Vendor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=vendors">
                <div class="modal-body">
                    <input type="hidden" name="add_vendor" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Vendor Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Zambia Beef Ltd">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="e.g. Mr. Phiri">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="097...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Supplier payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=vendors">
                <div class="modal-body">
                    <input type="hidden" name="save_payout" value="1">
                    <input type="hidden" name="vendor_id" id="payVendorId" value="">
                    <input type="hidden" name="flw_payout_bank_name" id="payBankName" value="">
                    <p class="small text-muted mb-2" id="payVendorName"></p>
                    <p class="small">The supplier cannot see this. Leave the account blank and save to remove it. A developer pays a purchase order to this account.</p>
                    <?php if (!empty($payoutBankError)): ?><div class="alert alert-warning small"><?= htmlspecialchars($payoutBankError) ?></div><?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Pay by</label>
                        <select name="flw_payout_method" id="payMethod" class="form-select no-search" onchange="payMethodChanged(this.value)">
                            <option value="bank">Bank account</option>
                            <option value="momo">Mobile money</option>
                        </select>
                    </div>
                    <div class="mb-3" id="payNetworkWrap">
                        <label class="form-label">Network</label>
                        <select name="flw_payout_network" id="payNetwork" class="form-select no-search">
                            <option value="MTN">MTN</option>
                            <option value="AIRTEL">Airtel</option>
                            <option value="ZAMTEL">Zamtel</option>
                        </select>
                    </div>
                    <div class="mb-3" id="payBankWrap">
                        <label class="form-label">Bank</label>
                        <select name="flw_payout_bank" id="payBank" class="form-select" onchange="payBankChanged(this)">
                            <option value="">No payment account</option>
                            <?php foreach ($payoutBanks ?? [] as $bank): ?>
                                <option value="<?= htmlspecialchars($bank['code']) ?>" data-id="<?= htmlspecialchars($bank['id']) ?>" data-name="<?= htmlspecialchars($bank['name']) ?>" data-branches="<?= $bank['has_branches'] ? '1' : '0' ?>"><?= htmlspecialchars($bank['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="payBranchWrap">
                        <label class="form-label">Branch</label>
                        <select name="flw_payout_branch" id="payBranch" class="form-select"><option value="">Choose a branch</option></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" id="payAccountLabel">Account number</label>
                        <input type="text" name="flw_payout_account" id="payAccount" class="form-control font-monospace" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name on the account</label>
                        <input type="text" name="flw_payout_name" id="payAccountName" class="form-control" autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save payment details</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="portalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vendor portal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=vendors">
                <div class="modal-body">
                    <input type="hidden" name="save_portal" value="1">
                    <input type="hidden" name="vendor_id" id="portalVendorId" value="">
                    <p class="small text-muted mb-3" id="portalVendorName"></p>
                    <p class="small">The supplier signs in at <a href="index.php?page=vendor" target="_blank">the vendor portal</a>. Leave the password blank to keep the current one. A new login with no password is given one, shown once.</p>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="portal_email" id="portalEmail" class="form-control" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="text" name="portal_password" class="form-control" autocomplete="new-password" placeholder="At least 8 characters">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="portal_active" value="1" id="portalActive">
                        <label class="form-check-label" for="portalActive">Portal login on</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save login</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function payMethodChanged(method) {
    var momo = method === 'momo';
    document.getElementById('payNetworkWrap').classList.toggle('d-none', !momo);
    document.getElementById('payBankWrap').classList.toggle('d-none', momo);
    document.getElementById('payAccountLabel').textContent = momo ? 'Mobile number' : 'Account number';
    document.getElementById('payAccount').placeholder = momo ? '0971234567' : '';
    if (momo) document.getElementById('payBranchWrap').classList.add('d-none');
}
function payBankChanged(sel) {
    var opt = sel.options[sel.selectedIndex];
    document.getElementById('payBankName').value = opt && opt.dataset.name ? opt.dataset.name : '';
    var wrap = document.getElementById('payBranchWrap');
    var branch = document.getElementById('payBranch');
    if (!opt || opt.dataset.branches !== '1' || !opt.dataset.id) {
        wrap.classList.add('d-none');
        branch.innerHTML = '<option value="">Choose a branch</option>';
        return;
    }
    wrap.classList.remove('d-none');
    fetch('index.php?page=flutterwave&action=branches&bank_id=' + encodeURIComponent(opt.dataset.id), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var rows = Array.isArray(res.branches) ? res.branches : [];
            var keep = branch.getAttribute('data-selected') || '';
            branch.innerHTML = '<option value="">Choose a branch</option>';
            rows.forEach(function (b) {
                var o = document.createElement('option');
                o.value = b.code;
                o.textContent = b.name;
                if (b.code === keep) o.selected = true;
                branch.appendChild(o);
            });
        })
        .catch(function () {});
}
document.getElementById('payModal')?.addEventListener('show.bs.modal', function (event) {
    var btn = event.relatedTarget;
    if (!btn) return;
    document.getElementById('payVendorId').value = btn.getAttribute('data-id') || '';
    document.getElementById('payVendorName').textContent = btn.getAttribute('data-name') || '';
    var method = btn.getAttribute('data-method') === 'momo' ? 'momo' : 'bank';
    document.getElementById('payMethod').value = method;
    payMethodChanged(method);
    var network = btn.getAttribute('data-network') || 'MTN';
    document.getElementById('payNetwork').value = network;
    var bank = document.getElementById('payBank');
    bank.value = btn.getAttribute('data-bank') || '';
    document.getElementById('payBankName').value = btn.getAttribute('data-bank-name') || '';
    document.getElementById('payAccount').value = btn.getAttribute('data-account') || '';
    document.getElementById('payAccountName').value = btn.getAttribute('data-account-name') || '';
    document.getElementById('payBranch').setAttribute('data-selected', btn.getAttribute('data-branch') || '');
    if (method === 'bank' && bank.value) payBankChanged(bank);
});
document.getElementById('portalModal')?.addEventListener('show.bs.modal', function (event) {
    var btn = event.relatedTarget;
    if (!btn) return;
    document.getElementById('portalVendorId').value = btn.getAttribute('data-id') || '';
    document.getElementById('portalVendorName').textContent = btn.getAttribute('data-name') || '';
    document.getElementById('portalEmail').value = btn.getAttribute('data-email') || '';
    document.getElementById('portalActive').checked = btn.getAttribute('data-active') === '1';
});
</script>
