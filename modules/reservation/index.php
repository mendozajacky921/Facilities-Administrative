<?php
/**
 * modules/reservation/index.php
 * Milestone 3 - Facilities Reservation.
 * Scope: create/cancel reservations (with optional equipment),
 * a single-step approval workflow (admin/facilities_staff approve or
 * reject), and a monthly reservation calendar.
 *
 * Backing tables: team8_facilities, team8_equipment,
 * team8_reservations, team8_reservation_equipment,
 * team8_reservation_approvals.
 *
 * ROUTING CONVENTION (see docs/API.md): state-changing requests are
 * POST to index.php?page=reservation&action={create|cancel|approve|reject},
 * each CSRF-protected, each ending in redirect() back to a plain GET
 * of this page (POST/redirect/GET - refreshing the result never
 * resubmits the form). This is the project's first module with real
 * POST actions; redirect() was hardened for this in helpers.php
 * (module files run after header.php/navbar.php have already streamed
 * HTML, so index.php now buffers its whole render - see both files'
 * Milestone 3 fix notes).
 *
 * Role gate: any authenticated user can create/view/cancel their OWN
 * reservations. Only admin/facilities_staff can see the approvals
 * queue and approve/reject. This is enforced inline per-action (not a
 * blanket t8_require_role() at the top of the file) since the same
 * page serves both requesters and approvers.
 */

declare(strict_types=1);

$pageTitle = 'Facilities Reservation';

$currentUserId = t8_current_user_id();
$canApprove    = t8_has_role(['admin', 'facilities_staff']);

$action = $_GET['action'] ?? null;

/**
 * Small local helper: does [startA,endA) overlap [startB,endB)?
 * Used both for the create-time conflict check and nowhere else -
 * kept here rather than in helpers.php since it's reservation-specific.
 */
if (!function_exists('t8_ranges_overlap')) {
    function t8_ranges_overlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }
}

// ---------------------------------------------------------------
// POST actions
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== null) {
    if (!t8_csrf_verify($_POST['csrf_token'] ?? null)) {
        t8_flash_set('danger', 'Your session expired. Please try again.');
        redirect(page_url('reservation'));
    }

    if ($action === 'create') {
        $facilityId = (int) ($_POST['facility_id'] ?? 0);
        $startRaw   = trim((string) ($_POST['start_time'] ?? ''));
        $endRaw     = trim((string) ($_POST['end_time'] ?? ''));
        $purpose    = trim((string) ($_POST['purpose'] ?? ''));

        $startTs = $startRaw !== '' ? strtotime($startRaw) : false;
        $endTs   = $endRaw !== '' ? strtotime($endRaw) : false;

        if ($facilityId <= 0 || $startTs === false || $endTs === false) {
            t8_flash_set('danger', 'Please choose a facility and a valid start/end date & time.');
            redirect(page_url('reservation'));
        }

        if ($startTs >= $endTs) {
            t8_flash_set('danger', 'The end time must be after the start time.');
            redirect(page_url('reservation'));
        }

        if ($startTs < time() - 60) {
            t8_flash_set('danger', 'The start time cannot be in the past.');
            redirect(page_url('reservation'));
        }

        $startTime = date('Y-m-d H:i:s', $startTs);
        $endTime   = date('Y-m-d H:i:s', $endTs);

        // Best-effort overlap check against pending/approved bookings
        // for the same facility. Not an atomic guarantee under
        // concurrent submissions - see docs/TechDebt.md's Milestone 3
        // note for why (MySQL has no native range-exclusion constraint).
        $conflictStmt = $pdo->prepare(
            "SELECT start_time, end_time FROM team8_reservations
             WHERE facility_id = :facility_id
               AND status IN ('pending', 'approved')
               AND deleted_at IS NULL"
        );
        $conflictStmt->execute(['facility_id' => $facilityId]);
        foreach ($conflictStmt->fetchAll(PDO::FETCH_ASSOC) as $existing) {
            if (t8_ranges_overlap($startTime, $endTime, $existing['start_time'], $existing['end_time'])) {
                t8_flash_set('danger', 'That facility is already booked (or pending approval) for part of that time range.');
                redirect(page_url('reservation'));
            }
        }

        try {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare(
                'INSERT INTO team8_reservations (facility_id, user_id, start_time, end_time, purpose, status)
                 VALUES (:facility_id, :user_id, :start_time, :end_time, :purpose, :status)'
            );
            $insertStmt->execute([
                'facility_id' => $facilityId,
                'user_id'     => $currentUserId,
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'purpose'     => $purpose !== '' ? $purpose : null,
                'status'      => 'pending',
            ]);
            $reservationId = (int) $pdo->lastInsertId();

            $selectedEquipment = $_POST['equipment_selected'] ?? [];
            $equipmentQty      = $_POST['equipment_qty'] ?? [];
            if (is_array($selectedEquipment) && $selectedEquipment !== []) {
                $equipIds = array_values(array_unique(array_filter(
                    array_map('intval', $selectedEquipment),
                    static fn(int $id): bool => $id > 0
                )));

                // FIX (Milestone 3 review): quantity used to be trusted
                // straight from the form (only clamped client-side by
                // an HTML `max` attribute, and even that was wrong for
                // 0-stock items - see the display-side fix below). Look
                // up real stock levels and clamp/skip server-side so a
                // crafted request can't reserve more than exists.
                $stockByEquipmentId = [];
                if ($equipIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($equipIds), '?'));
                    $stockStmt = $pdo->prepare("SELECT id, quantity FROM team8_equipment WHERE id IN ($placeholders)");
                    $stockStmt->execute($equipIds);
                    foreach ($stockStmt->fetchAll(PDO::FETCH_ASSOC) as $stockRow) {
                        $stockByEquipmentId[(int) $stockRow['id']] = (int) $stockRow['quantity'];
                    }
                }

                $equipStmt = $pdo->prepare(
                    'INSERT INTO team8_reservation_equipment (reservation_id, equipment_id, quantity)
                     VALUES (:reservation_id, :equipment_id, :quantity)'
                );
                foreach ($equipIds as $equipmentId) {
                    $available = $stockByEquipmentId[$equipmentId] ?? 0;
                    if ($available < 1) {
                        // Unknown or out-of-stock equipment id - skip rather
                        // than insert a bogus/zero-availability row.
                        continue;
                    }
                    $qty = (int) ($equipmentQty[$equipmentId] ?? 1);
                    if ($qty < 1) {
                        $qty = 1;
                    }
                    if ($qty > $available) {
                        $qty = $available;
                    }
                    $equipStmt->execute([
                        'reservation_id' => $reservationId,
                        'equipment_id'   => $equipmentId,
                        'quantity'       => $qty,
                    ]);
                }
            }

            $pdo->commit();

            t8_audit_log($pdo, $currentUserId, 'reservation', $reservationId, 'create', null, $startTime . ' to ' . $endTime);
            t8_flash_set('success', 'Reservation request submitted and is pending approval.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Reservation create failed: ' . $e->getMessage());
            t8_flash_set('danger', 'Could not submit the reservation. Please try again.');
        }

        redirect(page_url('reservation'));
    }

    if ($action === 'cancel') {
        $reservationId = (int) ($_POST['reservation_id'] ?? 0);

        $stmt = $pdo->prepare(
            'SELECT id, user_id, status FROM team8_reservations WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $reservationId]);
        $reservation = $stmt->fetch();

        if (!$reservation) {
            t8_flash_set('danger', 'That reservation no longer exists.');
            redirect(page_url('reservation'));
        }

        $isOwner = (int) $reservation['user_id'] === $currentUserId;
        if (!$isOwner && !$canApprove) {
            t8_flash_set('danger', 'You do not have permission to cancel that reservation.');
            redirect(page_url('reservation'));
        }

        if (!in_array($reservation['status'], ['pending', 'approved'], true)) {
            t8_flash_set('warning', 'That reservation is already ' . $reservation['status'] . '.');
            redirect(page_url('reservation'));
        }

        $updateStmt = $pdo->prepare(
            "UPDATE team8_reservations SET status = 'cancelled' WHERE id = :id"
        );
        $updateStmt->execute(['id' => $reservationId]);

        t8_audit_log($pdo, $currentUserId, 'reservation', $reservationId, 'cancel', $reservation['status'], 'cancelled');
        t8_flash_set('success', 'Reservation cancelled.');
        redirect(page_url('reservation'));
    }

    if ($action === 'approve' || $action === 'reject') {
        t8_require_role(['admin', 'facilities_staff']);

        $reservationId = (int) ($_POST['reservation_id'] ?? 0);
        $remarks       = trim((string) ($_POST['remarks'] ?? ''));
        $newStatus     = $action === 'approve' ? 'approved' : 'rejected';

        if ($action === 'reject' && $remarks === '') {
            t8_flash_set('danger', 'Please provide a short reason when rejecting a reservation.');
            redirect(page_url('reservation'));
        }

        $stmt = $pdo->prepare(
            "SELECT r.id, r.user_id, r.status, r.start_time, r.end_time, f.name AS facility_name
             FROM team8_reservations r
             INNER JOIN team8_facilities f ON f.id = r.facility_id
             WHERE r.id = :id AND r.deleted_at IS NULL"
        );
        $stmt->execute(['id' => $reservationId]);
        $reservation = $stmt->fetch();

        if (!$reservation) {
            t8_flash_set('danger', 'That reservation no longer exists.');
            redirect(page_url('reservation'));
        }

        if ($reservation['status'] !== 'pending') {
            t8_flash_set('warning', 'That reservation was already ' . $reservation['status'] . '.');
            redirect(page_url('reservation'));
        }

        try {
            $pdo->beginTransaction();

            $existingApproval = $pdo->prepare(
                'SELECT id FROM team8_reservation_approvals WHERE reservation_id = :id AND step_order = 1'
            );
            $existingApproval->execute(['id' => $reservationId]);
            $approvalId = $existingApproval->fetchColumn();

            if ($approvalId) {
                $approvalStmt = $pdo->prepare(
                    'UPDATE team8_reservation_approvals
                     SET approver_id = :approver_id, status = :status, remarks = :remarks, decided_at = NOW()
                     WHERE id = :id'
                );
                $approvalStmt->execute([
                    'approver_id' => $currentUserId,
                    'status'      => $newStatus,
                    'remarks'     => $remarks !== '' ? $remarks : null,
                    'id'          => $approvalId,
                ]);
            } else {
                $approvalStmt = $pdo->prepare(
                    'INSERT INTO team8_reservation_approvals (reservation_id, approver_id, step_order, status, remarks, decided_at)
                     VALUES (:reservation_id, :approver_id, 1, :status, :remarks, NOW())'
                );
                $approvalStmt->execute([
                    'reservation_id' => $reservationId,
                    'approver_id'    => $currentUserId,
                    'status'         => $newStatus,
                    'remarks'        => $remarks !== '' ? $remarks : null,
                ]);
            }

            $updateStmt = $pdo->prepare('UPDATE team8_reservations SET status = :status WHERE id = :id');
            $updateStmt->execute(['status' => $newStatus, 'id' => $reservationId]);

            $pdo->commit();

            t8_audit_log($pdo, $currentUserId, 'reservation', $reservationId, $action, 'pending', $newStatus);

            // Best-effort notification to the requester - never blocks the decision itself.
            try {
                $when = format_date($reservation['start_time'], 'M d, Y g:i A');
                $message = $action === 'approve'
                    ? sprintf('Your reservation for %s on %s was approved.', $reservation['facility_name'], $when)
                    : sprintf('Your reservation for %s on %s was rejected.%s', $reservation['facility_name'], $when, $remarks !== '' ? ' Reason: ' . $remarks : '');
                $notifyStmt = $pdo->prepare(
                    "INSERT INTO notifications (user_id, message, status) VALUES (:user_id, :message, 'unread')"
                );
                $notifyStmt->execute(['user_id' => $reservation['user_id'], 'message' => $message]);
            } catch (PDOException $e) {
                error_log('Reservation notification write failed: ' . $e->getMessage());
            }

            t8_flash_set('success', 'Reservation ' . $newStatus . '.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Reservation ' . $action . ' failed: ' . $e->getMessage());
            t8_flash_set('danger', 'Could not update that reservation. Please try again.');
        }

        redirect(page_url('reservation'));
    }

    // Unknown action - fall through to a normal GET render below.
    t8_flash_set('danger', 'Unknown reservation action.');
    redirect(page_url('reservation'));
}

// ---------------------------------------------------------------
// GET render - data fetching
// ---------------------------------------------------------------
$dbError = null;
$facilities = [];
$equipmentList = [];
$myReservations = [];
$pendingApprovals = [];

try {
    $facilities = $pdo->query('SELECT id, name, location, capacity FROM team8_facilities ORDER BY name')
        ->fetchAll(PDO::FETCH_ASSOC);

    $equipmentList = $pdo->query('SELECT id, name, quantity, home_facility_id FROM team8_equipment ORDER BY name')
        ->fetchAll(PDO::FETCH_ASSOC);

    $myStmt = $pdo->prepare(
        "SELECT r.id, r.start_time, r.end_time, r.purpose, r.status, f.name AS facility_name
         FROM team8_reservations r
         INNER JOIN team8_facilities f ON f.id = r.facility_id
         WHERE r.user_id = :user_id AND r.deleted_at IS NULL
         ORDER BY r.start_time DESC
         LIMIT 50"
    );
    $myStmt->execute(['user_id' => $currentUserId]);
    $myReservations = $myStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($canApprove) {
        $pendingApprovals = $pdo->query(
            "SELECT r.id, r.start_time, r.end_time, r.purpose, u.full_name AS requester_name, f.name AS facility_name
             FROM team8_reservations r
             INNER JOIN team8_facilities f ON f.id = r.facility_id
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.status = 'pending' AND r.deleted_at IS NULL
             ORDER BY r.start_time ASC
             LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $dbError = 'Could not load reservation data - has database/schema.sql been imported yet?';
}

// ---- Calendar month resolution (?month=YYYY-MM, defaults to this month) ----
// FIX (Milestone 3 review): the old regex (\d{4}-\d{2}) only checked the
// SHAPE of the month, not that it was 01-12. month=2026-13 passed it,
// strtotime('2026-13-01') returned false, and date('t', false) then
// threw an uncaught TypeError (PHP 8+) - crashing the whole page for
// any visitor who edited the query string. Now validates the real
// month range and has a defensive fallback in case strtotime() still
// somehow fails.
$monthParam = (string) ($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
    $monthParam = date('Y-m');
}
$monthStart = $monthParam . '-01';
$monthStartTs = strtotime($monthStart);
if ($monthStartTs === false) {
    $monthParam = date('Y-m');
    $monthStart = $monthParam . '-01';
    $monthStartTs = strtotime($monthStart);
}
$daysInMonth = (int) date('t', $monthStartTs);
$firstWeekday = (int) date('w', $monthStartTs); // 0 (Sun) - 6 (Sat)
$monthLabel = date('F Y', $monthStartTs);
$prevMonth = date('Y-m', strtotime('-1 month', $monthStartTs));
$nextMonth = date('Y-m', strtotime('+1 month', $monthStartTs));
$today = date('Y-m-d');

$calendarDays = []; // 'Y-m-d' => [ ['facility'=>..,'status'=>..,'time'=>..], ... ]
if ($dbError === null) {
    try {
        $monthEnd = date('Y-m-d 23:59:59', strtotime('+1 month -1 second', $monthStartTs));
        $calStmt = $pdo->prepare(
            "SELECT r.start_time, r.status, f.name AS facility_name
             FROM team8_reservations r
             INNER JOIN team8_facilities f ON f.id = r.facility_id
             WHERE r.deleted_at IS NULL
               AND r.status IN ('pending', 'approved')
               AND r.start_time BETWEEN :month_start AND :month_end
             ORDER BY r.start_time ASC"
        );
        $calStmt->execute(['month_start' => $monthStart . ' 00:00:00', 'month_end' => $monthEnd]);
        foreach ($calStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $day = substr($row['start_time'], 0, 10);
            $calendarDays[$day][] = [
                'facility' => $row['facility_name'],
                'status'   => $row['status'],
                'time'     => date('g:i A', strtotime($row['start_time'])),
            ];
        }
    } catch (PDOException $e) {
        // Calendar is a bonus view - a failure here shouldn't hide the rest of the page.
    }
}
?>
<h1>Facilities Reservation</h1>
<p class="t8-help-text">Book a facility, track approval status, and see what's on the calendar.</p>

<?php if ($dbError !== null): ?>
    <div class="t8-alert t8-alert-warning"><?= e($dbError) ?></div>
<?php endif; ?>

<div class="t8-dashboard-grid t8-reservation-grid">
    <div class="t8-card">
        <div class="t8-card-header">
            <h2 class="t8-card-title">New Reservation</h2>
        </div>

        <?php if ($facilities === []): ?>
            <div class="t8-empty">No facilities are set up yet - ask an admin to add one.</div>
        <?php else: ?>
            <form method="post" action="<?= e(page_url('reservation', ['action' => 'create'])) ?>" id="t8ReservationForm" novalidate>
                <?= t8_csrf_field() ?>

                <div class="t8-field">
                    <label class="t8-label" for="facility_id">Facility</label>
                    <select class="t8-select" id="facility_id" name="facility_id" required>
                        <option value="">Select a facility...</option>
                        <?php foreach ($facilities as $facility): ?>
                            <option value="<?= e((string) $facility['id']) ?>">
                                <?= e($facility['name']) ?> - <?= e($facility['location']) ?> (cap. <?= e((string) $facility['capacity']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="t8-reservation-datetime">
                    <div class="t8-field">
                        <label class="t8-label" for="start_time">Start</label>
                        <input class="t8-input" type="datetime-local" id="start_time" name="start_time" required>
                    </div>
                    <div class="t8-field">
                        <label class="t8-label" for="end_time">End</label>
                        <input class="t8-input" type="datetime-local" id="end_time" name="end_time" required>
                    </div>
                </div>

                <div class="t8-field">
                    <label class="t8-label" for="purpose">Purpose <span class="t8-help-text">(optional)</span></label>
                    <input class="t8-input" type="text" id="purpose" name="purpose" maxlength="255" placeholder="e.g. Team quarterly planning">
                </div>

                <?php if ($equipmentList !== []): ?>
                    <div class="t8-field">
                        <label class="t8-label">Equipment <span class="t8-help-text">(optional)</span></label>
                        <div class="t8-equipment-list">
                            <?php foreach ($equipmentList as $equipment): ?>
                                <?php $inStock = (int) $equipment['quantity'] > 0; ?>
                                <label class="t8-equipment-row<?= $inStock ? '' : ' t8-equipment-row-disabled' ?>">
                                    <input type="checkbox" name="equipment_selected[]" value="<?= e((string) $equipment['id']) ?>" <?= $inStock ? '' : 'disabled' ?>>
                                    <span class="t8-equipment-name"><?= e($equipment['name']) ?></span>
                                    <?php if ($inStock): ?>
                                        <input class="t8-input t8-equipment-qty" type="number" min="1" max="<?= e((string) (int) $equipment['quantity']) ?>"
                                               value="1" name="equipment_qty[<?= e((string) $equipment['id']) ?>]" aria-label="Quantity of <?= e($equipment['name']) ?>">
                                        <span class="t8-help-text">of <?= e((string) $equipment['quantity']) ?> available</span>
                                    <?php else: ?>
                                        <input class="t8-input t8-equipment-qty" type="number" value="0" disabled aria-label="Quantity of <?= e($equipment['name']) ?>">
                                        <span class="t8-help-text">out of stock</span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <button class="t8-btn t8-btn-accent" type="submit">
                    <i class="fa-solid fa-calendar-plus"></i> Submit Request
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="t8-card">
        <div class="t8-card-header">
            <h2 class="t8-card-title"><?= e($monthLabel) ?></h2>
            <div class="t8-calendar-nav">
                <a class="t8-btn t8-btn-outline t8-btn-sm" href="<?= e(page_url('reservation', ['month' => $prevMonth])) ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <a class="t8-btn t8-btn-outline t8-btn-sm" href="<?= e(page_url('reservation', ['month' => date('Y-m')])) ?>">Today</a>
                <a class="t8-btn t8-btn-outline t8-btn-sm" href="<?= e(page_url('reservation', ['month' => $nextMonth])) ?>"><i class="fa-solid fa-chevron-right"></i></a>
            </div>
        </div>

        <div class="t8-calendar">
            <div class="t8-calendar-weekdays">
                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                    <div class="t8-calendar-weekday"><?= e($weekday) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="t8-calendar-grid">
                <?php for ($i = 0; $i < $firstWeekday; $i++): ?>
                    <div class="t8-calendar-cell t8-calendar-cell-empty"></div>
                <?php endfor; ?>
                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php
                    $dateKey = sprintf('%s-%02d', $monthParam, $day);
                    $dayReservations = $calendarDays[$dateKey] ?? [];
                    $isToday = $dateKey === $today;
                    $titleParts = array_map(
                        static fn(array $r): string => $r['time'] . ' ' . $r['facility'] . ' (' . $r['status'] . ')',
                        $dayReservations
                    );
                    ?>
                    <div class="t8-calendar-cell<?= $isToday ? ' t8-calendar-cell-today' : '' ?>"
                         <?= $titleParts !== [] ? 'title="' . e(implode(' | ', $titleParts)) . '"' : '' ?>>
                        <span class="t8-calendar-daynum"><?= $day ?></span>
                        <?php if ($dayReservations !== []): ?>
                            <span class="t8-calendar-count"><?= count($dayReservations) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($canApprove): ?>
    <div class="t8-card">
        <div class="t8-card-header">
            <h2 class="t8-card-title">Pending Approvals</h2>
        </div>
        <?php if ($pendingApprovals === []): ?>
            <div class="t8-empty">No reservations are waiting on a decision.</div>
        <?php else: ?>
            <div class="t8-table-wrap">
                <table class="t8-table">
                    <thead>
                        <tr>
                            <th>Requester</th>
                            <th>Facility</th>
                            <th>When</th>
                            <th>Purpose</th>
                            <th>Decision</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingApprovals as $pending): ?>
                            <tr>
                                <td><?= e($pending['requester_name']) ?></td>
                                <td><?= e($pending['facility_name']) ?></td>
                                <td class="t8-table-ref">
                                    <?= e(format_date($pending['start_time'], 'M d, Y g:i A')) ?> &ndash;
                                    <?= e(format_date($pending['end_time'], 'g:i A')) ?>
                                </td>
                                <td><?= e($pending['purpose'] ?? '—') ?></td>
                                <td>
                                    <div class="t8-approval-actions">
                                        <form method="post" action="<?= e(page_url('reservation', ['action' => 'approve'])) ?>">
                                            <?= t8_csrf_field() ?>
                                            <input type="hidden" name="reservation_id" value="<?= e((string) $pending['id']) ?>">
                                            <button class="t8-btn t8-btn-success t8-btn-sm" type="submit">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="post" action="<?= e(page_url('reservation', ['action' => 'reject'])) ?>" class="t8-reject-form">
                                            <?= t8_csrf_field() ?>
                                            <input type="hidden" name="reservation_id" value="<?= e((string) $pending['id']) ?>">
                                            <input class="t8-input t8-input-sm" type="text" name="remarks" placeholder="Reason (required)" required>
                                            <button class="t8-btn t8-btn-danger t8-btn-sm" type="submit">
                                                <i class="fa-solid fa-xmark"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="t8-card">
    <div class="t8-card-header">
        <h2 class="t8-card-title">My Reservations</h2>
    </div>
    <?php if ($myReservations === []): ?>
        <div class="t8-empty">You haven't requested any reservations yet.</div>
    <?php else: ?>
        <div class="t8-table-wrap">
            <table class="t8-table">
                <thead>
                    <tr>
                        <th>Facility</th>
                        <th>When</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myReservations as $reservation): ?>
                        <tr>
                            <td><?= e($reservation['facility_name']) ?></td>
                            <td class="t8-table-ref">
                                <?= e(format_date($reservation['start_time'], 'M d, Y g:i A')) ?> &ndash;
                                <?= e(format_date($reservation['end_time'], 'g:i A')) ?>
                            </td>
                            <td><?= e($reservation['purpose'] ?? '—') ?></td>
                            <td><span class="t8-badge t8-badge-<?= e($reservation['status']) ?>"><?= e($reservation['status']) ?></span></td>
                            <td>
                                <?php if (in_array($reservation['status'], ['pending', 'approved'], true)): ?>
                                    <form method="post" action="<?= e(page_url('reservation', ['action' => 'cancel'])) ?>"
                                          onsubmit="return confirm('Cancel this reservation?');">
                                        <?= t8_csrf_field() ?>
                                        <input type="hidden" name="reservation_id" value="<?= e((string) $reservation['id']) ?>">
                                        <button class="t8-btn t8-btn-outline t8-btn-sm" type="submit">
                                            <i class="fa-solid fa-ban"></i> Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
