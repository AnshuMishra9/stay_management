<?php
/**
 * Inventory availability calendar.
 * $dates[]        -> Y-m-d for each column
 * $rows[]         -> per category { name, total, avail{date=>n}, booked{date=>n} }
 * $avail_totals{} -> all-rooms available per date
 * Backend booking states are presented here as a single "Booked" status.
 * $total_rooms    -> total active rooms
 * $start/$selected,$window_start,$prev,$next,$end,$today -> Y-m-d
 */
$fmt = function ($d) use ($today, $selected) {
    $t = strtotime($d);
    return array(
        'wd'    => date('D', $t),
        'day'   => date('j', $t),
        'mon'   => date('M', $t),
        'today' => ($d === $today),
        'selected' => ($d === $selected),
        'wknd'  => in_array(date('N', $t), array('6', '7')),
    );
};
$cell_class = function ($avail) {
    return $avail <= 0 ? 'inv-a inv-a-0' : 'inv-a inv-a-ok';
};
$room_cell_class = function ($status) {
    return $status === 'available' ? 'inv-a inv-a-ok' : 'inv-a inv-a-booked';
};
$navigation_query = function ($date) use ($filters) {
    $query = array(
        'start'       => $date,
        'room_no'     => $filters['room_no'],
        'category_id' => $filters['category_id'],
    );

    return http_build_query(array_filter($query, function ($value) {
        return $value !== NULL && $value !== '';
    }));
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <style>
        .inv-head-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; }
        .inv-nav { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .inv-nav .erp-input { width:170px; height:38px; }
        .inv-navbtn { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border:1px solid var(--input-brd); border-radius:10px; background:#fff; color:var(--brand-dark); cursor:pointer; }
        .inv-navbtn:hover { background:var(--brand-soft); border-color:#c9cff0; }
        .inv-range { color:var(--muted); font-size:.82rem; white-space:nowrap; text-align:right; }
        .inv-filterbar { display:flex; align-items:flex-end; gap:12px; padding:14px 20px;
            border-bottom:1px solid var(--line); background:var(--grad-soft); }
        .inv-filter-title { display:flex; align-items:center; gap:8px; align-self:center; margin-right:4px;
            color:var(--brand-dark); font-size:.86rem; font-weight:800; white-space:nowrap; }
        .inv-filter-title svg { width:17px; height:17px; }
        .inv-filter-field { display:flex; flex-direction:column; gap:6px; min-width:0; }
        .inv-filter-field label { color:var(--muted); font-size:.68rem; font-weight:800;
            letter-spacing:.05em; line-height:1; text-transform:uppercase; }
        .inv-filter-room { width:210px; }
        .inv-filter-category { width:210px; }
        .inv-filterbar .erp-input, .inv-filterbar .erp-select { height:38px; font-size:.86rem; }
        .inv-filterbar .erp-select { background-color:#fff; border-color:#d8dcf0;
            background-position:right 12px center; box-shadow:0 2px 7px rgba(56,48,126,.05); }
        .inv-filterbar .erp-select:hover { border-color:#bfc5e5; }
        .inv-filter-actions { display:flex; align-items:center; gap:8px; }
        .inv-filter-actions .erp-btn { height:38px; padding:0 16px; font-size:.86rem; }
        @media (max-width:760px) {
            .inv-head-right { width:100%; align-items:flex-start; }
            .inv-nav { justify-content:flex-start; }
            .inv-range { text-align:left; }
            .inv-filterbar { align-items:stretch; flex-wrap:wrap; }
            .inv-filter-title { width:100%; }
            .inv-filter-field { flex:1 1 190px; }
            .inv-filter-room, .inv-filter-category { width:auto; }
        }
        @media (max-width:480px) {
            .inv-filter-field, .inv-filter-actions { width:100%; flex-basis:100%; }
            .inv-filter-actions .erp-btn { flex:1; justify-content:center; }
        }

        /* horizontal scroll for the wide calendar, with a visible slim scrollbar */
        .inv-scroll { overflow-x:auto; }
        .inv-scroll::-webkit-scrollbar { height:10px; }
        .inv-scroll::-webkit-scrollbar-thumb { background:#cfd6ea; border-radius:6px; }
        .inv-scroll::-webkit-scrollbar-thumb:hover { background:#b9c1dc; }
        .inv-scroll::-webkit-scrollbar-track { background:transparent; }

        table.inv-table { width:100%; border-collapse:separate; border-spacing:0; min-width:1120px; table-layout:fixed; }
        .inv-table th, .inv-table td { border-bottom:1px solid var(--line); }
        /* sticky first column (Room Type) */
        .inv-roomcol { position:sticky; left:0; z-index:3; background:#fff; text-align:left;
            padding:12px 16px; width:220px; min-width:220px;
            border-right:1px solid var(--line); box-shadow:6px 0 8px -6px rgba(30,35,60,.14); }
        thead .inv-roomcol { z-index:5; background:var(--head); }
        .inv-rt-name { font-weight:700; color:var(--text); font-size:.92rem; white-space:nowrap; }
        .inv-rt-sub  { color:var(--muted); font-size:.75rem; margin-top:2px; white-space:nowrap; }

        /* date header cells (fixed comfortable width) */
        .inv-dh { text-align:center; padding:8px 4px; background:var(--head); width:60px;
            font-weight:600; text-transform:none; letter-spacing:normal; position:sticky; top:0; z-index:4; }
        .inv-dh .d-wd  { display:block; font-size:.68rem; color:var(--muted); font-weight:600; }
        .inv-dh .d-day { display:block; font-size:1.02rem; color:var(--text); font-weight:800; line-height:1.1; }
        .inv-dh .d-mon { display:block; font-size:.64rem; color:var(--muted); }
        .inv-wknd { background:#f3f0fb; }
        .inv-today { background:var(--brand-soft) !important; box-shadow: inset 0 -2px 0 var(--brand); }
        .inv-selected { background:#e8f2ff !important; box-shadow:inset 0 -3px 0 #2563eb; }
        .inv-selected .d-day { color:#1d4ed8; }

        /* availability cells */
        .inv-cell { text-align:center; padding:10px 4px; width:60px; }
        .inv-a { display:inline-flex; align-items:center; justify-content:center; min-width:30px; height:28px;
            padding:0 7px; border-radius:8px; font-weight:800; font-size:.9rem; }
        .inv-a-ok { background:var(--green-soft); color:#15803d; }
        .inv-a-0  { background:var(--red-soft);   color:#be123c; }
        .inv-a-booked { background:#fff3d6; color:#b45309; }
        .inv-bk   { display:block; font-size:.64rem; color:var(--muted); margin-top:3px; white-space:nowrap; }
        .inv-bk-available { color:#15803d; }
        .inv-bk-booked { color:#b45309; }

        .inv-total-row td { background:#fbfaff; }
        .inv-total-row .inv-roomcol { background:#fbfaff; }
        .inv-total-row .inv-a { background:var(--brand-soft); color:var(--brand-dark); }
        .inv-legend { display:flex; gap:16px; align-items:center; justify-content:center; color:var(--muted); font-size:.78rem; margin-top:14px; flex-wrap:wrap; }
        .inv-legend .k { display:inline-flex; align-items:center; gap:6px; }
        .inv-swatch { width:14px; height:14px; border-radius:4px; display:inline-block; }
    </style>
</head>
<body class="erp-body">

<?php $this->load->view('layouts/erp_navbar', array('active' => 'inventory')); ?>

<div class="erp-wrap">
    <div class="erp-card">

        <!-- Header -->
        <div class="erp-page-head">
            <div>
                <h1>
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v4H3zM4 7v14h16V7M9 12h6"/></svg>
                    Inventory
                </h1>
            </div>
            <div class="inv-head-right">
                <div class="inv-nav">
                    <div class="erp-head-total" style="margin-right:6px;">Total Rooms:&nbsp; <?= (int) $total_rooms ?></div>
                    <a class="inv-navbtn" href="<?= site_url('inventory?'.$navigation_query($prev)) ?>" title="Previous 6 days">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                    </a>
                    <input class="erp-input" type="date" id="invStart" value="<?= html_escape($start) ?>">
                    <a class="inv-navbtn" href="<?= site_url('inventory?'.$navigation_query($next)) ?>" title="Next 6 days">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                    </a>
                </div>
                <div class="inv-range"><?= html_escape(date('d M Y', strtotime($window_start))) ?> &ndash; <?= html_escape(date('d M Y', strtotime($end))) ?></div>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" class="inv-filterbar" id="invFilterForm">
            <input type="hidden" name="start" value="<?= html_escape($start) ?>">
            <div class="inv-filter-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16M7 12h10M10 19h4"/></svg>
                Filter Rooms
            </div>
            <div class="inv-filter-field inv-filter-room">
                <label for="invRoomName">Room Name / No.</label>
                <input class="erp-input" id="invRoomName" type="text" name="room_no" placeholder="Search room" value="<?= html_escape($filters['room_no'] ?? '') ?>">
            </div>
            <div class="inv-filter-field inv-filter-category">
                <label for="invCategory">Room Category</label>
                <select class="erp-select" id="invCategory" name="category_id" data-search="always" data-placeholder="All Categories">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c->category_id ?>" <?= (isset($filters['category_id']) && (int)$filters['category_id'] === (int)$c->category_id) ? 'selected' : '' ?>><?= html_escape($c->category_name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="inv-filter-actions">
                <a href="<?= site_url('inventory?start='.$start) ?>" class="erp-btn erp-btn-ghost">Clear</a>
            </div>
        </form>

        <!-- Calendar -->
        <div class="inv-scroll">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th class="inv-roomcol">Room Type</th>
                        <?php foreach ($dates as $d): $f = $fmt($d); ?>
                            <th class="inv-dh <?= $f['wknd'] ? 'inv-wknd' : '' ?> <?= $f['today'] ? 'inv-today' : '' ?> <?= $f['selected'] ? 'inv-selected' : '' ?>">
                                <span class="d-wd"><?= $f['wd'] ?></span>
                                <span class="d-day"><?= $f['day'] ?></span>
                                <span class="d-mon"><?= $f['mon'] ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <!-- All rooms summary -->
                    <tr class="inv-total-row">
                        <td class="inv-roomcol">
                            <div class="inv-rt-name">All Rooms</div>
                            <div class="inv-rt-sub">
                                <?php
                                    $total_available = 0;
                                    $total_booked = 0;
                                    foreach ($dates as $d) {
                                        $total_available += $avail_totals[$d];
                                        $total_booked += $booked_totals[$d]
                                            + $checked_in_totals[$d]
                                            + $checked_out_totals[$d];
                                    }
                                    echo (int) $total_rooms . ' rooms &middot; ';
                                    echo $total_available . ' Available &middot; ' . $total_booked . ' Booked';
                                ?>
                            </div>
                        </td>
                        <?php foreach ($dates as $d): $f = $fmt($d); $av = (int) $avail_totals[$d]; $bk = (int) $booked_totals[$d] + (int) $checked_in_totals[$d] + (int) $checked_out_totals[$d]; ?>
                            <td class="inv-cell <?= $f['selected'] ? 'inv-selected' : ($f['today'] ? 'inv-today' : ($f['wknd'] ? 'inv-wknd' : '')) ?>">
                                <span class="<?= $cell_class($av) ?>" title="Available rooms"><?= $av ?></span>
                                <span class="inv-bk inv-bk-available"><?= $av ?> Available</span>
                                <span class="inv-bk inv-bk-booked"><?= $bk ?> Booked</span>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Individual rooms -->
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td class="inv-roomcol">
                                <div class="inv-rt-name"><?= html_escape($room['room_no']) ?></div>
                                <div class="inv-rt-sub"><?= html_escape($room['category_name'] ?: 'No Category') ?></div>
                            </td>
                            <?php foreach ($dates as $d): $f = $fmt($d); $av = (int) $room['avail'][$d]; $status = $room['status'][$d]; ?>
                                <td class="inv-cell <?= $f['selected'] ? 'inv-selected' : ($f['today'] ? 'inv-today' : ($f['wknd'] ? 'inv-wknd' : '')) ?>">
                                    <span class="<?= $room_cell_class($status) ?>" title="<?= $av ? 'Available' : 'Booked' ?>"><?= $av ? '1' : '0' ?></span>
                                    <span class="inv-bk <?= $av ? 'inv-bk-available' : 'inv-bk-booked' ?>"><?= $av ? 'Available' : 'Booked' ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($rooms)): ?>
                        <tr><td class="inv-roomcol">—</td><td colspan="<?= (int) count($dates) ?>" style="padding:20px;color:var(--muted);">No active rooms found. Add rooms in Room Master.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="inv-legend">
            <span class="k"><span class="inv-swatch" style="background:var(--green-soft);"></span> Available</span>
            <span class="k"><span class="inv-swatch" style="background:#fff3d6;"></span> Booked</span>
            <span class="k"><span class="inv-swatch" style="background:var(--brand-soft);"></span> Today</span>
            <span class="k"><span class="inv-swatch" style="background:#e8f2ff;border-bottom:3px solid #2563eb;"></span> Selected date</span>
            <span class="k">Number = rooms available that day</span>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script>
    // Apply inventory filters automatically; no separate submit button needed.
    (function () {
        var form = document.getElementById('invFilterForm');
        var roomInput = document.getElementById('invRoomName');
        var category = document.getElementById('invCategory');
        var timer;

        if (!form) { return; }

        if (roomInput) {
            roomInput.addEventListener('input', function () {
                window.clearTimeout(timer);
                timer = window.setTimeout(function () { form.submit(); }, 400);
            });
        }

        if (category) {
            category.addEventListener('change', function () { form.submit(); });
        }
    })();

    // Keep the chosen date in the centre while preserving active filters.
    (function () {
        var el = document.getElementById('invStart');
        if (el) {
            el.addEventListener('change', function () {
                if (el.value) {
                    var params = new URLSearchParams(window.location.search);
                    params.set('start', el.value);
                    window.location = "<?= site_url('inventory') ?>?" + params.toString();
                }
            });
        }
    })();
</script>
</body>
</html>
