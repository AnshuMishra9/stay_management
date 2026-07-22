<?php
/**
 * Inventory availability calendar.
 * $dates[]        -> Y-m-d for each column
 * $rows[]         -> per category { name, total, avail{date=>n}, booked{date=>n} }
 * $avail_totals{} -> all-rooms available per date
 * $total_rooms    -> total active rooms
 * $start,$prev,$next,$end,$today -> Y-m-d
 */
$fmt = function ($d) use ($today) {
    $t = strtotime($d);
    return array(
        'wd'    => date('D', $t),
        'day'   => date('j', $t),
        'mon'   => date('M', $t),
        'today' => ($d === $today),
        'wknd'  => in_array(date('N', $t), array('6', '7')),
    );
};
$cell_class = function ($avail) {
    return $avail <= 0 ? 'inv-a inv-a-0' : 'inv-a inv-a-ok';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <style>
        .inv-nav { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .inv-nav .erp-input { width:170px; height:38px; }
        .inv-navbtn { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border:1px solid var(--input-brd); border-radius:10px; background:#fff; color:var(--brand-dark); cursor:pointer; }
        .inv-navbtn:hover { background:var(--brand-soft); border-color:#c9cff0; }
        .inv-range { color:var(--muted); font-size:.82rem; white-space:nowrap; }

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

        /* availability cells */
        .inv-cell { text-align:center; padding:10px 4px; width:60px; }
        .inv-a { display:inline-flex; align-items:center; justify-content:center; min-width:30px; height:28px;
            padding:0 7px; border-radius:8px; font-weight:800; font-size:.9rem; }
        .inv-a-ok { background:var(--green-soft); color:#15803d; }
        .inv-a-0  { background:var(--red-soft);   color:#be123c; }
        .inv-bk   { display:block; font-size:.64rem; color:var(--muted); margin-top:3px; white-space:nowrap; }

        .inv-total-row td { background:#fbfaff; }
        .inv-total-row .inv-roomcol { background:#fbfaff; }
        .inv-total-row .inv-a { background:var(--brand-soft); color:var(--brand-dark); }
        .inv-legend { display:flex; gap:16px; align-items:center; color:var(--muted); font-size:.78rem; margin-top:12px; flex-wrap:wrap; }
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
                <p class="erp-sub">Rooms available per day (total &minus; booked) across all room types</p>
            </div>
            <div class="inv-nav">
                <div class="erp-head-total" style="margin-right:6px;">Total Rooms:&nbsp; <?= (int) $total_rooms ?></div>
                <a class="inv-navbtn" href="<?= site_url('inventory?start='.$prev) ?>" title="Previous <?= (int) count($dates) ?> days">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </a>
                <input class="erp-input" type="date" id="invStart" value="<?= html_escape($start) ?>">
                <a class="inv-navbtn" href="<?= site_url('inventory?start='.$next) ?>" title="Next <?= (int) count($dates) ?> days">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </a>
            </div>
        </div>

        <div class="inv-range"><?= html_escape(date('d M Y', strtotime($start))) ?> &ndash; <?= html_escape(date('d M Y', strtotime($end))) ?></div>

        <!-- Calendar -->
        <div class="inv-scroll" style="margin-top:12px;">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th class="inv-roomcol">Room Type</th>
                        <?php foreach ($dates as $d): $f = $fmt($d); ?>
                            <th class="inv-dh <?= $f['wknd'] ? 'inv-wknd' : '' ?> <?= $f['today'] ? 'inv-today' : '' ?>">
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
                            <div class="inv-rt-sub"><?= (int) $total_rooms ?> rooms &middot; available</div>
                        </td>
                        <?php foreach ($dates as $d): $f = $fmt($d); ?>
                            <td class="inv-cell <?= $f['today'] ? 'inv-today' : ($f['wknd'] ? 'inv-wknd' : '') ?>">
                                <span class="inv-a"><?= (int) $avail_totals[$d] ?></span>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <!-- Per room type -->
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="inv-roomcol">
                                <div class="inv-rt-name"><?= html_escape($row['name']) ?></div>
                                <div class="inv-rt-sub"><?= (int) $row['total'] ?> room<?= $row['total'] == 1 ? '' : 's' ?></div>
                            </td>
                            <?php foreach ($dates as $d): $f = $fmt($d); $av = (int) $row['avail'][$d]; $bk = (int) $row['booked'][$d]; ?>
                                <td class="inv-cell <?= $f['today'] ? 'inv-today' : ($f['wknd'] ? 'inv-wknd' : '') ?>">
                                    <span class="<?= $cell_class($av) ?>" title="<?= $av ?> available &middot; <?= $bk ?> booked of <?= (int) $row['total'] ?>"><?= $av ?></span>
                                    <?php if ($bk > 0): ?><span class="inv-bk"><?= $bk ?> booked</span><?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($rows)): ?>
                        <tr><td class="inv-roomcol">—</td><td colspan="<?= (int) count($dates) ?>" style="padding:20px;color:var(--muted);">No active rooms found. Add rooms in Room Master.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="inv-legend">
            <span class="k"><span class="inv-swatch" style="background:var(--green-soft);"></span> Available</span>
            <span class="k"><span class="inv-swatch" style="background:var(--red-soft);"></span> Fully booked (0)</span>
            <span class="k"><span class="inv-swatch" style="background:var(--brand-soft);"></span> Today</span>
            <span class="k">Number = rooms available that day</span>
        </div>
    </div>
</div>

<script>
    // Date picker jumps the window to the chosen start date.
    (function () {
        var el = document.getElementById('invStart');
        if (el) {
            el.addEventListener('change', function () {
                if (el.value) { window.location = "<?= site_url('inventory') ?>?start=" + el.value; }
            });
        }
    })();
</script>
</body>
</html>
