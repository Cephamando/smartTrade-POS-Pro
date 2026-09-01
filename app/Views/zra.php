<?php
$sdcId = $zraSettings['vsdc_sdcId'] ?? '';
$initialized = $sdcId !== '' || (($zraSettings['vsdc_device_installed'] ?? '') === '1');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1"><i class="bi bi-shield-check text-success"></i> ZRA Smart Invoice</h3>
        <p class="text-muted mb-0">Sandbox VSDC handshake, item registration, and fiscal retry queue.</p>
    </div>
    <span class="badge <?= $initialized ? 'bg-success' : 'bg-warning text-dark' ?> fs-6">
        <?= $initialized ? 'Device initialized' : 'Not initialized' ?>
    </span>
</div>

<?php if (!empty($zraLastResult)): ?>
    <?php
        $layer = $zraLastResult['layer'] ?? 'vsdc';
        $alertClass = $layer === 'ok' ? 'alert-success' : (in_array($layer, ['zra', 'client'], true) ? 'alert-warning' : 'alert-danger');
    ?>
    <div class="alert <?= $alertClass ?> shadow-sm">
        <div class="fw-bold mb-1">Last VSDC response (<?= htmlspecialchars((string)($zraLastResult['resultCd'] ?? 'n/a')) ?>)</div>
        <div><?= htmlspecialchars((string)($zraLastResult['resultMsg'] ?? '')) ?></div>
        <?php if (!empty($zraLastResult['hint'])): ?>
            <div class="small mt-2"><?= htmlspecialchars($zraLastResult['hint']) ?></div>
        <?php endif; ?>
        <?php if (!empty($zraLastResult['errors']) && is_array($zraLastResult['errors'])): ?>
            <ul class="small mb-0 mt-2">
                <?php foreach (array_slice($zraLastResult['errors'], 0, 12) as $err): ?>
                    <li><?= htmlspecialchars((string)$err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">VSDC service</div>
                <div class="fw-bold <?= !empty($zraPing['ok']) ? 'text-success' : 'text-danger' ?>">
                    <?= htmlspecialchars($zraPing['message'] ?? 'Unknown') ?>
                </div>
                <div class="small text-muted mt-1"><?= htmlspecialchars($zraConfig['baseUrl']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Pending sales</div>
                <div class="fw-bold fs-4"><?= (int)$zraSummary['pending_sales'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Fiscalized sales</div>
                <div class="fw-bold fs-4 text-success"><?= (int)$zraSummary['synced_sales'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Unsynced products</div>
                <div class="fw-bold fs-4"><?= (int)$zraSummary['unsynced_products'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Device registration</h5>
                <table class="table table-sm">
                    <tr><th class="w-50">TPIN</th><td><?= htmlspecialchars($zraConfig['tpin']) ?></td></tr>
                    <tr><th>Branch ID</th><td><?= htmlspecialchars($zraConfig['bhfId']) ?></td></tr>
                    <tr><th>Device serial</th><td><?= htmlspecialchars($zraConfig['dvcSrlNo']) ?></td></tr>
                    <tr><th>Taxpayer</th><td><?= htmlspecialchars($zraSettings['vsdc_taxprNm'] ?? '—') ?></td></tr>
                    <tr><th>SDC ID</th><td><?= htmlspecialchars($sdcId ?: '—') ?></td></tr>
                    <tr><th>MRC No</th><td><?= htmlspecialchars($zraSettings['vsdc_mrcNo'] ?? '—') ?></td></tr>
                    <tr><th>Last CIS invoice</th><td><?= htmlspecialchars($zraSettings['vsdc_last_sale_no'] ?? '0') ?></td></tr>
                    <tr><th>Standard codes</th><td><?= (int)$zraSummary['standard_codes'] ?></td></tr>
                    <tr><th>UNSPSC classes</th><td><?= (int)$zraSummary['item_classes'] ?></td></tr>
                </table>
                <p class="small text-muted mb-3">
                    Credentials come from <code>.env</code> (<code>ZRA_TPIN</code>, <code>ZRA_BHF_ID</code>, <code>ZRA_DVC_SRL_NO</code>).
                    They must match Device Management on the ZRA sandbox portal.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post">
                        <input type="hidden" name="zra_action" value="test_connection">
                        <button class="btn btn-outline-secondary">Test connection</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="zra_action" value="initialize">
                        <button class="btn btn-success fw-bold" <?= $initialized ? 'disabled' : '' ?>>
                            <i class="bi bi-plug"></i> Initialize device
                        </button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="zra_action" value="sync_codes">
                        <button class="btn btn-outline-primary">Sync standard codes</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="zra_action" value="sync_classes">
                        <button class="btn btn-outline-primary">Sync UNSPSC classes</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="zra_action" value="sync_items">
                        <button class="btn btn-outline-dark">Register products</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="alert alert-info small">
            After a successful handshake, click <strong>Sync standard codes</strong>, then
            <strong>Sync UNSPSC classes</strong>, then <strong>Register products</strong>.
            Item class must be a <strong>level-4 UNSPSC</strong> from that list (for example
            <code>50202201</code> Beer). Family codes such as <code>50101500</code> are rejected.
            Do not click Initialize again, and do not recreate the <code>vsdc</code> container
            or remove the <code>vsdc_webapps</code> volume — that deletes the API keys.
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Unfiscalized paid sales</h5>
                    <form method="post">
                        <input type="hidden" name="zra_action" value="retry_sales">
                        <button class="btn btn-sm btn-primary">Retry failed / pending</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>When</th>
                                <th>Customer</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$pendingSales): ?>
                            <tr><td colspan="6" class="text-muted">No paid sales waiting for ZRA.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($pendingSales as $row): ?>
                            <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td class="small"><?= htmlspecialchars($row['created_at']) ?></td>
                                <td><?= htmlspecialchars($row['customer_name'] ?: 'Walk-in') ?></td>
                                <td class="text-end"><?= number_format((float)$row['final_total'], 2) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($row['zra_status']) ?></span>
                                    <?php if (!empty($row['zra_last_error'])): ?>
                                        <div class="small text-danger"><?= htmlspecialchars($row['zra_last_error']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="zra_action" value="fiscalize_one">
                                        <input type="hidden" name="sale_id" value="<?= (int)$row['id'] ?>">
                                        <button class="btn btn-sm btn-outline-success">Push</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Recent fiscal receipts</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Sale</th>
                                <th>Rcpt No</th>
                                <th>SDC</th>
                                <th class="text-end">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$recentSynced): ?>
                            <tr><td colspan="5" class="text-muted">No fiscalized receipts yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentSynced as $row): ?>
                            <tr>
                                <td>#<?= (int)$row['id'] ?></td>
                                <td><?= htmlspecialchars((string)$row['zra_rcpt_no']) ?></td>
                                <td><?= htmlspecialchars((string)$row['zra_sdc_id']) ?></td>
                                <td class="text-end"><?= number_format((float)$row['final_total'], 2) ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-dark" href="index.php?page=receipt&sale_id=<?= (int)$row['id'] ?>" target="_blank">Receipt</a>
                                    <?php
                                    $rowQr = $zra->receiptQrUrl($row['zra_rcpt_sign'] ?? null, $row['zra_qr_code_url'] ?? null);
                                    if (!empty($rowQr)):
                                    ?>
                                        <a class="btn btn-sm btn-outline-success" href="<?= htmlspecialchars($rowQr) ?>" target="_blank">QR</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
