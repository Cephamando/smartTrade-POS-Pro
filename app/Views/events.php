<?php
/** @var array<string,mixed>|null $event */
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $monthEvents */
$monthTs = strtotime($month . '-01');
$prev = date('Y-m', strtotime('-1 month', $monthTs));
$next = date('Y-m', strtotime('+1 month', $monthTs));
$days = (int)date('t', $monthTs);
$pad = (int)date('N', $monthTs) - 1;
$byDay = [];
foreach ($monthEvents as $row) {
    $from = max(strtotime(date('Y-m-d', strtotime($row['starts_at']))), $monthTs);
    $to = min(strtotime(date('Y-m-d', strtotime($row['ends_at']))), strtotime(date('Y-m-t', $monthTs)));
    for ($t = $from; $t <= $to; $t += 86400) {
        $byDay[(int)date('j', $t)][] = $row;
    }
}
$dt = static fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string)$v)) : '';
$when = static fn($v) => $v ? date('D j M Y, H:i', strtotime((string)$v)) : '';
$link = static fn(array $guest) => 'index.php?page=shop&view=rsvp&token=' . $guest['token'];
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0"><i class="bi bi-calendar-event"></i> Events</h3>
    <a class="btn btn-dark fw-bold" href="index.php?page=events&new=1"><i class="bi bi-plus-lg"></i> New event</a>
</div>

<?php if (!$event): ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <a class="btn btn-sm btn-outline-secondary" href="index.php?page=events&month=<?= $prev ?>"><i class="bi bi-chevron-left"></i></a>
                    <h5 class="mb-0 fw-bold"><?= date('F Y', $monthTs) ?></h5>
                    <a class="btn btn-sm btn-outline-secondary" href="index.php?page=events&month=<?= $next ?>"><i class="bi bi-chevron-right"></i></a>
                </div>
                <div class="event-cal">
                    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $label): ?>
                    <div class="event-cal-head"><?= $label ?></div>
                    <?php endforeach; ?>
                    <?php for ($i = 0; $i < $pad; $i++): ?><div class="event-cal-day muted"></div><?php endfor; ?>
                    <?php for ($d = 1; $d <= $days; $d++): ?>
                    <div class="event-cal-day">
                        <div class="fw-bold small"><?= $d ?></div>
                        <?php foreach ($byDay[$d] ?? [] as $row): ?>
                        <a class="event-pill" href="index.php?page=events&id=<?= (int)$row['id'] ?>&month=<?= htmlspecialchars($month) ?>"><?= htmlspecialchars($row['title']) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <?php if (!empty($showNew)): ?>
        <?php $event = ['id'=>0,'title'=>'','summary'=>'','story'=>'','cover_path'=>'','starts_at'=>'','ends_at'=>'','venue_note'=>'','visibility'=>'private','status'=>'draft','capacity'=>0,'deposit_amount'=>0,'location_id'=>0]; include __DIR__ . '/partials/event_form.php'; ?>
        <?php endif; ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (!$events): ?><div class="p-4 text-muted">No events yet.</div><?php endif; ?>
                    <?php foreach ($events as $row): ?>
                    <a class="list-group-item list-group-item-action" href="index.php?page=events&id=<?= (int)$row['id'] ?>">
                        <div class="d-flex justify-content-between gap-2">
                            <strong><?= htmlspecialchars($row['title']) ?></strong>
                            <span class="badge bg-<?= $row['status'] === 'open' ? 'success' : ($row['status'] === 'cancelled' ? 'danger' : 'secondary') ?>"><?= htmlspecialchars($row['status']) ?></span>
                        </div>
                        <div class="small text-muted"><?= $when($row['starts_at']) ?> · <?= htmlspecialchars($row['visibility']) ?> · <?= (int)$row['accepted_size'] ?> accepted</div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.event-cal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; }
.event-cal-head { font-size: .72rem; font-weight: 700; color: #6c757d; text-align: center; }
.event-cal-day { min-height: 78px; background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 4px; }
.event-cal-day.muted { background: transparent; border-color: transparent; }
.event-pill { display: block; font-size: .68rem; background: #3e2723; color: #fff; border-radius: 6px; padding: 1px 4px; margin-top: 2px; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>

<?php else: ?>
<?php $id = (int)$event['id']; ?>
<p class="mb-2"><a href="index.php?page=events&month=<?= htmlspecialchars($month) ?>">&larr; All events</a></p>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-1"><?= htmlspecialchars($event['title']) ?></h4>
        <div class="text-muted"><?= $when($event['starts_at']) ?> – <?= $when($event['ends_at']) ?>
            <?php if ($event['location_name']): ?> · <?= htmlspecialchars($event['location_name']) ?><?php endif; ?>
        </div>
    </div>
    <div class="text-end">
        <span class="badge bg-<?= $event['status'] === 'open' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($event['status']) ?></span>
        <span class="badge bg-dark"><?= htmlspecialchars($event['visibility']) ?></span>
        <div class="small mt-1"><?= (int)$acceptedSize ?> accepted<?= (int)$event['capacity'] ? ' / ' . (int)$event['capacity'] : '' ?></div>
        <?php if ($event['visibility'] === 'published'): ?>
        <a class="small" href="index.php?page=shop&view=event&slug=<?= urlencode($event['slug']) ?>">Shop page</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <?php include __DIR__ . '/partials/event_form.php'; ?>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <h6 class="fw-bold">Run of show</h6>
                <ul class="list-unstyled small mb-3">
                    <?php foreach ($agenda as $item): ?>
                    <li class="d-flex justify-content-between gap-2 mb-1">
                        <span><strong><?= date('H:i', strtotime($item['starts_at'])) ?></strong> <?= htmlspecialchars($item['title']) ?><?php if ($item['notes']): ?> <span class="text-muted">— <?= htmlspecialchars($item['notes']) ?></span><?php endif; ?></span>
                        <form method="POST"><input type="hidden" name="event_id" value="<?= $id ?>"><input type="hidden" name="agenda_id" value="<?= (int)$item['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0" name="delete_agenda" value="1">Remove</button></form>
                    </li>
                    <?php endforeach; ?>
                    <?php if (!$agenda): ?><li class="text-muted">No agenda yet.</li><?php endif; ?>
                </ul>
                <form method="POST" class="row g-2">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <div class="col-5"><input type="datetime-local" name="starts_at" class="form-control form-control-sm" required></div>
                    <div class="col-7"><input name="title" class="form-control form-control-sm" placeholder="Moment" required></div>
                    <div class="col-9"><input name="notes" class="form-control form-control-sm" placeholder="Notes"></div>
                    <div class="col-3"><button class="btn btn-sm btn-dark w-100" name="add_agenda" value="1">Add</button></div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <h6 class="fw-bold">Tasks</h6>
                <?php foreach ($tasks as $task): ?>
                <form method="POST" class="d-flex justify-content-between align-items-center gap-2 small mb-1">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                    <button class="btn btn-sm btn-link text-start p-0" name="toggle_task" value="1">
                        <i class="bi bi-<?= $task['done'] ? 'check-square' : 'square' ?>"></i>
                        <span class="<?= $task['done'] ? 'text-decoration-line-through text-muted' : '' ?>"><?= htmlspecialchars($task['title']) ?></span>
                        <?php if ($task['due_at']): ?><span class="text-muted"><?= date('j M H:i', strtotime($task['due_at'])) ?></span><?php endif; ?>
                    </button>
                </form>
                <?php endforeach; ?>
                <form method="POST" class="row g-2 mt-2">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <div class="col-7"><input name="title" class="form-control form-control-sm" placeholder="Task" required></div>
                    <div class="col-5"><input type="datetime-local" name="due_at" class="form-control form-control-sm"></div>
                    <div class="col-12"><button class="btn btn-sm btn-dark" name="add_task" value="1">Add task</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="fw-bold">Choices</h6>
                <p class="small text-muted">Guests pick one item from each group. A limit of 0 means no limit.</p>
                <?php foreach ($options as $opt): ?>
                <div class="d-flex justify-content-between align-items-center small border-bottom py-1">
                    <span><?= htmlspecialchars($opt['group_name']) ?> — <?= htmlspecialchars($opt['name']) ?> · ZMW <?= number_format((float)$opt['price'], 2) ?><?= (int)$opt['capacity'] ? ' · limit ' . (int)$opt['capacity'] : '' ?></span>
                    <form method="POST"><input type="hidden" name="event_id" value="<?= $id ?>"><input type="hidden" name="option_id" value="<?= (int)$opt['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0" name="delete_option" value="1">Remove</button></form>
                </div>
                <?php endforeach; ?>
                <form method="POST" class="row g-2 mt-2">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <div class="col-3"><input name="group_name" class="form-control form-control-sm" placeholder="Meal" value="Meal"></div>
                    <div class="col-4"><input name="name" class="form-control form-control-sm" placeholder="Choice" required></div>
                    <div class="col-2"><input type="number" step="0.01" min="0" name="price" class="form-control form-control-sm" placeholder="Price" value="0"></div>
                    <div class="col-2"><input type="number" min="0" name="capacity" class="form-control form-control-sm" placeholder="Limit" value="0"></div>
                    <div class="col-1"><button class="btn btn-sm btn-dark w-100" name="add_option" value="1">+</button></div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <h6 class="fw-bold">Guests</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Name</th><th>Party</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($guests as $guest): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($guest['name']) ?><?= $guest['checked_in'] ? ' <i class="bi bi-check-circle-fill text-success" title="Checked in"></i>' : '' ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($guest['email'] ?: $guest['phone']) ?></div>
                                <a class="small" href="<?= htmlspecialchars($link($guest)) ?>">Invite link</a>
                            </td>
                            <td><?= (int)$guest['party_size'] ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($guest['status']) ?></span></td>
                            <td class="text-end text-nowrap">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="event_id" value="<?= $id ?>">
                                    <input type="hidden" name="guest_id" value="<?= (int)$guest['id'] ?>">
                                    <select name="guest_status" class="form-select form-select-sm d-inline-block no-search" style="width:auto" onchange="this.form.submit()">
                                        <?php foreach (['invited','applied','accepted','waitlisted','declined','cancelled'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $guest['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <form method="POST" class="d-inline"><input type="hidden" name="event_id" value="<?= $id ?>"><input type="hidden" name="guest_id" value="<?= (int)$guest['id'] ?>"><button class="btn btn-sm btn-outline-success" name="check_in" value="1"><?= $guest['checked_in'] ? 'Undo' : 'Check in' ?></button></form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$guests): ?><tr><td colspan="4" class="text-muted">No guests yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <h6 class="fw-bold mt-2">Invite someone</h6>
                <form method="POST" class="row g-2">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="Name" required></div>
                    <div class="col-md-4"><input type="email" name="email" class="form-control form-control-sm" placeholder="Email"></div>
                    <div class="col-md-2"><input name="phone" class="form-control form-control-sm" placeholder="Phone"></div>
                    <div class="col-md-2"><input type="number" min="1" name="party_size" class="form-control form-control-sm" value="1"></div>
                    <div class="col-12"><textarea name="body" class="form-control form-control-sm" rows="3" placeholder="Optional message. Use {name}, {event}, {when}, {link}."></textarea></div>
                    <div class="col-12"><button class="btn btn-sm btn-dark" name="invite_guest" value="1">Save invitation</button>
                        <?php if (empty($mailReady)): ?><span class="small text-muted ms-2">Mail is not set up yet. The link is still created.</span><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <h6 class="fw-bold">Mass email</h6>
                <form method="POST" class="row g-2">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <div class="col-md-4">
                        <select name="audience" class="form-select form-select-sm">
                            <option value="invited">Invited</option>
                            <option value="accepted">Accepted</option>
                            <option value="applied">Applied</option>
                            <option value="waitlisted">Waitlisted</option>
                            <option value="all">Everyone with email</option>
                        </select>
                    </div>
                    <div class="col-md-8"><input name="subject" class="form-control form-control-sm" placeholder="Subject" value="You are invited: {event}" required></div>
                    <div class="col-12"><textarea name="body" class="form-control form-control-sm" rows="4" required>Hello {name},

You are invited to {event} on {when}.
Reply here: {link}</textarea></div>
                    <div class="col-12"><button class="btn btn-sm btn-dark" name="send_mail" value="1">Send</button></div>
                </form>
                <?php if ($messages): ?>
                <div class="small mt-3">
                    <?php foreach (array_slice($messages, 0, 8) as $msg): ?>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span><?= htmlspecialchars($msg['email']) ?> · <?= htmlspecialchars($msg['subject']) ?></span>
                        <span class="badge bg-<?= $msg['status'] === 'sent' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($msg['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-3 mb-4">
            <div class="card-body">
                <h6 class="fw-bold">Payments</h6>
                <p class="small text-muted">Deposits and balances stay on this event. Online payments use Flutterwave. Cash is recorded here.</p>
                <?php foreach ($payments as $pay): ?>
                <div class="d-flex justify-content-between small border-bottom py-1">
                    <span><?= htmlspecialchars($pay['guest_name']) ?> · <?= htmlspecialchars($pay['purpose']) ?> · <?= htmlspecialchars($pay['method']) ?></span>
                    <span>ZMW <?= number_format((float)$pay['amount'], 2) ?> <span class="badge bg-<?= $pay['status'] === 'paid' ? 'success' : 'warning text-dark' ?>"><?= htmlspecialchars($pay['status']) ?></span></span>
                </div>
                <?php endforeach; ?>
                <?php if (!$payments): ?><div class="small text-muted">No payments yet.</div><?php endif; ?>
                <form method="POST" class="row g-2 mt-2">
                    <input type="hidden" name="event_id" value="<?= $id ?>">
                    <div class="col-md-4">
                        <select name="guest_id" class="form-select form-select-sm" required>
                            <option value="">Guest</option>
                            <?php foreach ($guests as $guest): ?>
                            <option value="<?= (int)$guest['id'] ?>"><?= htmlspecialchars($guest['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-sm" placeholder="Amount" required></div>
                    <div class="col-md-3">
                        <select name="purpose" class="form-select form-select-sm no-search">
                            <option value="deposit">Deposit</option>
                            <option value="balance">Balance</option>
                        </select>
                    </div>
                    <div class="col-md-3"><button class="btn btn-sm btn-dark w-100" name="cash_payment" value="1">Record cash</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
