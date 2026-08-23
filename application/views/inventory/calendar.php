<?php
/**
 * Inventory availability calendar.
 * $dates[]        -> Y-m-d for each column
 * $rows[]         -> per category { name, total, avail{date=>n}, booked{date=>n} }
 * $avail_totals{} -> all-rooms available per date
 * Backend booking states are presented here as a single "Booked" status.
 * $total_rooms    -> total active rooms matching the filters
 * $room_page*     -> bounded room-page metadata; totals still cover all matches
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
$booking_status_label = function ($status) {
    $labels = array(
        'room_booked' => 'Room Booked',
        'checked_in'  => 'Checked In',
        'checked_out' => 'Checked Out',
    );
    return isset($labels[$status]) ? $labels[$status] : 'Booked';
};
$inventory_query = function (array $overrides = array()) use ($filters, $start, $room_page) {
    $query = array(
        'start'       => $start,
        'room_no'     => $filters['room_no'],
        'category_id' => $filters['category_id'],
        'page'        => $room_page,
    );
    $query = array_merge($query, $overrides);

    return http_build_query(array_filter($query, function ($value) {
        return $value !== NULL && $value !== '';
    }));
};
$navigation_query = function ($date) use ($inventory_query) {
    return $inventory_query(array('start' => $date));
};
$visible_pages = array(1, $room_page_count);
for ($page_number = max(1, $room_page - 2); $page_number <= min($room_page_count, $room_page + 2); $page_number++) {
    $visible_pages[] = $page_number;
}
$visible_pages = array_values(array_unique($visible_pages));
sort($visible_pages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <script>
        window.APP_BASE = <?= json_encode(base_url()) ?>;
        window.INVENTORY_BOOKING_CONFIG = {
            formUrl: <?= json_encode(site_url('inventory/booking_form')) ?>,
            detailUrl: <?= json_encode(site_url('inventory/booking_detail')) ?>,
            today: <?= json_encode($today) ?>
        };
    </script>
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
        .inv-room-title-mobile { display:none; }
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

        .inv-room-slot { border:0; font-family:inherit; transition:transform .12s ease, box-shadow .12s ease, outline-color .12s ease; }
        button.inv-room-slot { cursor:pointer; }
        button.inv-room-slot[data-bookable="1"]:hover { transform:translateY(-1px); box-shadow:0 5px 12px rgba(21,128,61,.2); }
        button.inv-room-slot[data-booking-id]:hover { transform:translateY(-1px); box-shadow:0 5px 12px rgba(180,83,9,.22); }
        button.inv-room-slot:focus-visible { outline:3px solid rgba(37,99,235,.38); outline-offset:2px; }
        .inv-room-slot.is-range-selected { outline:3px solid #2563eb; outline-offset:2px; transform:translateY(-1px); }
        .inv-room-slot.is-range-start, .inv-room-slot.is-range-end { background:#2563eb; color:#fff; }
        .inv-room-slot.inv-slot-past { opacity:.58; cursor:not-allowed; }
        .inv-cell-bookable { cursor:pointer; transition:background-color .12s ease, box-shadow .12s ease; }
        .inv-cell-bookable:hover { background:#f0fdf4 !important; }
        .inv-cell-bookable.is-range-selected { background:#eff6ff !important; box-shadow:inset 0 -3px 0 #2563eb; }
        .inv-cell-occupied { cursor:pointer; transition:background-color .12s ease; }
        .inv-cell-occupied:hover { background:#fffaf0 !important; }

        .inv-total-row td { background:#fbfaff; }
        .inv-total-row .inv-roomcol { background:#fbfaff; }
        .inv-total-row .inv-a { background:var(--brand-soft); color:var(--brand-dark); }
        .inv-pagination { display:flex; align-items:center; justify-content:space-between; gap:12px;
            padding:12px 16px; border-top:1px solid var(--line); background:#fff; }
        .inv-pagination-summary { color:var(--muted); font-size:.78rem; }
        .inv-page-links { display:flex; align-items:center; gap:5px; }
        .inv-page-link { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px;
            border:1px solid var(--input-brd); border-radius:7px; background:#fff; color:var(--brand-dark);
            font-size:.78rem; font-weight:700; text-decoration:none; }
        a.inv-page-link:hover { background:var(--brand-soft); border-color:#c9cff0; }
        .inv-page-link.is-current { border-color:var(--brand); background:var(--brand); color:#fff; }
        .inv-page-link.is-disabled { color:#a6adbd; cursor:not-allowed; }
        .inv-page-gap { min-width:18px; color:var(--muted); text-align:center; }
        .inv-legend { display:flex; gap:16px; align-items:center; justify-content:center; color:var(--muted); font-size:.78rem; margin-top:14px; flex-wrap:wrap; }
        .inv-legend .k { display:inline-flex; align-items:center; gap:6px; }
        .inv-swatch { width:14px; height:14px; border-radius:4px; display:inline-block; }

        /* Compact floating selection popup, with the current range controls. */
        .inv-selection-popup { position:fixed; right:18px; bottom:18px; z-index:1040;
            width:min(320px, calc(100vw - 20px)); background:#fff; border:1px solid #dce2f2;
            border-radius:12px; box-shadow:0 14px 38px rgba(24,29,68,.2); overflow:hidden; }
        .inv-selection-popup[hidden], .inv-booking-backdrop[hidden], .inv-detail-backdrop[hidden] { display:none !important; }
        .inv-selection-head { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:8px; align-items:center; padding:9px 10px 6px; }
        .inv-selection-head > div:first-child { min-width:0; }
        .inv-selection-title { color:var(--text); font-size:.88rem; font-weight:800; line-height:1.2; }
        .inv-selection-room { color:var(--brand-dark); font-size:.73rem; font-weight:700; margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .inv-selection-head-actions { display:flex; align-items:center; gap:5px; }
        .inv-selection-head-actions .erp-btn { min-height:27px; padding:4px 7px; border-radius:7px; font-size:.66rem; }
        .inv-selection-body { padding:0 12px 9px; color:var(--muted); font-size:.75rem; line-height:1.35; }
        .inv-selection-meta { color:var(--text); font-size:.73rem; font-weight:700; }
        .inv-selection-picker { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-top:6px; }
        .inv-selection-picker label { display:block; color:var(--muted); font-size:.64rem; font-weight:700; }
        .inv-selection-picker .erp-input { margin-top:2px; min-width:0; height:31px; padding:4px 6px; font-size:.7rem; }
        .inv-selection-error { margin-top:6px; padding:6px 8px; border-radius:7px; background:var(--red-soft); color:#9f1239; }

        .inv-booking-backdrop { z-index:1100; padding:20px 14px; align-items:flex-start; }
        .inv-booking-modal { max-width:1000px; max-height:calc(100vh - 40px); display:flex; flex-direction:column; overflow:hidden; }
        .inv-booking-modal .erp-modal-head { flex:0 0 auto; }
        .inv-booking-modal-body { padding:0; overflow-y:auto; }
        .inv-booking-modal-body .booking-form-card { max-width:none !important; border-radius:0; box-shadow:none; }
        .inv-booking-loading { min-height:260px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; color:var(--muted); }
        .inv-booking-load-error { margin:22px; }
        .inv-detail-backdrop { z-index:1120; padding:28px 14px; align-items:flex-start; }
        .inv-detail-modal { max-width:760px; max-height:calc(100vh - 56px); display:flex; flex-direction:column; overflow:hidden; }
        .inv-detail-head { flex:0 0 auto; align-items:flex-start; gap:14px; }
        .inv-detail-heading { min-width:0; }
        .inv-detail-heading h3 { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .inv-detail-kicker { margin-bottom:3px; color:var(--brand); font-size:.68rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .inv-detail-subtitle { margin-top:4px; color:var(--muted); font-size:.78rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .inv-detail-head-actions { display:flex; align-items:center; gap:8px; margin-left:auto; flex:0 0 auto; }
        .inv-detail-action { min-height:36px; padding:7px 13px; white-space:nowrap; }
        .inv-detail-head-actions .erp-modal-close { display:inline-flex; align-items:center; justify-content:center; min-width:40px; min-height:40px; padding:0; }
        .inv-detail-body { flex:1 1 auto; min-height:0; overflow-y:auto; overscroll-behavior:contain; }
        .inv-detail-loading { min-height:210px; display:flex; align-items:center; justify-content:center; gap:11px; color:var(--muted); }
        .inv-detail-error { margin:0; }
        .inv-detail-summary { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 14px; margin-bottom:18px; border:1px solid #e2e5f2; border-radius:13px; background:var(--head); }
        .inv-detail-guest { min-width:0; }
        .inv-detail-guest-name { color:var(--text); font-size:1rem; font-weight:800; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .inv-detail-guest-meta { margin-top:3px; color:var(--muted); font-size:.78rem; overflow-wrap:anywhere; }
        .inv-detail-section + .inv-detail-section { margin-top:20px; padding-top:18px; border-top:1px solid var(--line); }
        .inv-detail-section .erp-section-title { margin-bottom:13px; }
        .inv-detail-note { margin-top:16px; padding:10px 12px; border-radius:10px; background:#fef3c7; color:#92400e; font-size:.82rem; }
        body.inv-modal-open { overflow:hidden; }
        @media (max-width:700px) {
            table.inv-table { width:748px; min-width:748px; }
            .inv-roomcol { width:88px; min-width:88px; max-width:88px; padding:8px 7px; }
            .inv-room-title-desktop { display:none; }
            .inv-room-title-mobile { display:inline; }
            .inv-rt-name, .inv-rt-sub { display:block; max-width:74px; overflow:hidden; text-overflow:ellipsis; }
            .inv-rt-name { font-size:.78rem; }
            .inv-rt-sub { font-size:.62rem; }
            .inv-dh, .inv-cell { width:60px; min-width:60px; max-width:60px; }
            .inv-selection-popup { right:10px; bottom:10px; }
            .inv-booking-backdrop { padding:8px; }
            .inv-booking-modal { max-height:calc(100vh - 16px); }
            .inv-detail-backdrop { padding:8px; }
            .inv-detail-modal { max-height:calc(100vh - 16px); }
        }
        @media (max-width:600px) {
            /* Keep this page compact without changing the shared desktop navbar. */
            .erp-nav { padding:0 10px; }
            .erp-nav-brand { padding:8px 0; font-size:.98rem; gap:8px; }
            .erp-nav-brand svg { width:20px; height:20px; padding:5px; border-radius:8px; }
            .erp-nav-right { width:100%; padding:3px 0 8px; gap:5px; flex-wrap:nowrap;
                overflow-x:auto; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
            .erp-nav-right::-webkit-scrollbar { display:none; }
            .erp-nav-link { flex:0 0 auto; gap:5px; padding:7px 9px; border-radius:8px; font-size:.74rem; }
            .erp-nav-link svg { width:14px; height:14px; }
            .erp-nav-right .erp-btn-sm { flex:0 0 auto; padding:7px 10px; border-radius:8px; font-size:.74rem; }

            .erp-wrap { padding:8px; }
            .erp-card { border-radius:14px; }
            .erp-page-head { display:block; padding:10px 12px; }
            .erp-page-head h1 { font-size:1.06rem; }
            .erp-page-head h1 svg { width:17px; height:17px; padding:5px; }
            .inv-head-right { width:100%; margin-top:8px; gap:4px; }
            .inv-nav { width:100%; display:grid; grid-template-columns:34px minmax(0, 1fr) 34px;
                align-items:center; gap:6px; }
            .inv-nav .erp-head-total { grid-column:1 / -1; margin:0 !important; font-size:.82rem; }
            .inv-nav .erp-input { width:100%; height:34px; padding:5px 8px; font-size:.76rem; }
            .inv-navbtn { width:34px; height:34px; border-radius:8px; }
            .inv-range { text-align:center; font-size:.67rem; }
            .inv-pagination { align-items:flex-start; flex-direction:column; padding:10px 12px; }
            .inv-page-links { align-self:stretch; justify-content:center; }

            .inv-filterbar { display:grid; grid-template-columns:minmax(0, 1fr) minmax(0, 1fr);
                align-items:end; gap:7px 8px; padding:9px 12px; }
            .inv-filter-title { grid-column:1; grid-row:1; width:auto; margin:0; gap:5px;
                align-self:center; font-size:.72rem; }
            .inv-filter-title svg { width:14px; height:14px; }
            .inv-filter-room { grid-column:1; grid-row:2; }
            .inv-filter-category { grid-column:2; grid-row:2; }
            .inv-filter-field { width:auto; min-width:0; flex:none; gap:4px; }
            .inv-filter-field label { min-width:0; overflow:hidden; text-overflow:ellipsis;
                white-space:nowrap; font-size:.56rem; }
            .inv-filterbar .erp-input, .inv-filterbar .erp-select,
            .inv-filterbar .erp-ss-trigger { height:34px; padding:5px 8px; font-size:.74rem; }
            .inv-filterbar .erp-select, .inv-filterbar .erp-ss-trigger { padding-right:25px; }
            .inv-filter-actions { grid-column:2; grid-row:1; width:auto; justify-self:end; flex:none; }
            .inv-filter-actions .erp-btn { height:27px; padding:0 9px; flex:none;
                border-radius:8px; font-size:.68rem; }

            table.inv-table { width:740px; min-width:740px; }
            .inv-roomcol { width:80px; min-width:80px; max-width:80px; padding:7px 6px; }
            .inv-rt-name, .inv-rt-sub { max-width:68px; }
            .inv-scroll { -webkit-overflow-scrolling:touch; overscroll-behavior-inline:contain; }
            .inv-detail-head { padding:13px 14px; gap:8px; }
            .inv-detail-heading h3 { font-size:1rem; }
            .inv-detail-subtitle { max-width:150px; font-size:.7rem; }
            .inv-detail-head-actions { gap:4px; }
            .inv-detail-action { min-height:40px; padding:6px 9px; border-radius:8px; font-size:.72rem; }
            .inv-detail-head .erp-modal-close { min-width:40px; min-height:40px; font-size:1.45rem; }
            .inv-detail-body { padding:14px 13px; }
            .inv-detail-body .erp-detail-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px 14px; }
            .inv-detail-summary { align-items:flex-start; padding:11px 12px; margin-bottom:15px; }
            .inv-detail-summary .erp-badge { padding:4px 8px; font-size:.68rem; }
            .inv-detail-section + .inv-detail-section { margin-top:16px; padding-top:15px; }
        }
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

        <?php if ( ! empty($flash)): ?>
            <div class="erp-alert <?= $flash['type'] === 'success' ? 'erp-alert-success' : 'erp-alert-danger' ?>" role="status">
                <?= html_escape($flash['text']) ?>
            </div>
        <?php endif; ?>

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

        <!-- Compact floating selection popup with First/Last night range controls. -->
        <aside class="inv-selection-popup" id="invSelectionPopup" hidden aria-live="polite" aria-label="Selected booking range">
            <div class="inv-selection-head">
                <div>
                    <div class="inv-selection-title">New Booking</div>
                    <div class="inv-selection-room" id="invSelectionRoom"></div>
                </div>
                <div class="inv-selection-head-actions">
                    <button type="button" class="erp-btn erp-btn-ghost" id="invSelectionClear">Clear</button>
                    <button type="button" class="erp-btn erp-btn-primary" id="invCreateBooking">New Booking</button>
                </div>
            </div>
            <div class="inv-selection-body">
                <div id="invSelectionDates" hidden></div>
                <div class="inv-selection-meta" id="invSelectionNights"></div>
                <div class="inv-selection-picker">
                    <label>From
                        <input class="erp-input" type="date" id="invSelectionStartDate" readonly aria-readonly="true">
                    </label>
                    <label>Last night
                        <input class="erp-input" type="date" id="invSelectionEndDate">
                    </label>
                </div>
                <div class="inv-selection-error" id="invSelectionError" role="alert" hidden></div>
            </div>
        </aside>

        <!-- Calendar -->
        <div class="inv-scroll">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th class="inv-roomcol"><span class="inv-room-title-desktop">Room Type</span><span class="inv-room-title-mobile">Room</span></th>
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
                            <?php foreach ($dates as $d): $f = $fmt($d); $av = (int) $room['avail'][$d]; $status = $room['status'][$d]; $booking_id = isset($room['booking_id'][$d]) ? (int) $room['booking_id'][$d] : 0; $bookable = ($av === 1 && $d >= $today); $occupied_clickable = ($av === 0 && $booking_id > 0); ?>
                                <td class="inv-cell <?= $f['selected'] ? 'inv-selected' : ($f['today'] ? 'inv-today' : ($f['wknd'] ? 'inv-wknd' : '')) ?><?= $bookable ? ' inv-cell-bookable' : ($occupied_clickable ? ' inv-cell-occupied' : '') ?>">
                                    <?php if ($bookable): ?>
                                        <button type="button"
                                                class="<?= $room_cell_class($status) ?> inv-room-slot"
                                                data-room-id="<?= (int) $room['id'] ?>"
                                                data-room-no="<?= html_escape($room['room_no']) ?>"
                                                data-category-name="<?= html_escape($room['category_name'] ?: 'No Category') ?>"
                                                data-date="<?= html_escape($d) ?>"
                                                data-bookable="1"
                                                aria-label="Room <?= html_escape($room['room_no']) ?>, <?= html_escape(date('d M Y', strtotime($d))) ?>, Available. Select booking night."
                                                aria-pressed="false"
                                                title="Select this available night">1</button>
                                    <?php elseif ($occupied_clickable): ?>
                                        <button type="button"
                                                class="<?= $room_cell_class($status) ?> inv-room-slot"
                                                data-room-id="<?= (int) $room['id'] ?>"
                                                data-date="<?= html_escape($d) ?>"
                                                data-bookable="0"
                                                data-booking-id="<?= $booking_id ?>"
                                                data-booking-status="<?= html_escape($status) ?>"
                                                aria-label="Room <?= html_escape($room['room_no']) ?>, <?= html_escape(date('d M Y', strtotime($d))) ?>, <?= html_escape($booking_status_label($status)) ?>. View guest details."
                                                title="View guest and booking details">0</button>
                                    <?php else: ?>
                                        <span class="<?= $room_cell_class($status) ?> inv-room-slot <?= ($av && $d < $today) ? 'inv-slot-past' : '' ?>"
                                              data-room-id="<?= (int) $room['id'] ?>"
                                              data-date="<?= html_escape($d) ?>"
                                              data-bookable="0"
                                              aria-disabled="true"
                                              title="<?= $av ? 'Past date - not selectable' : 'Booked - not selectable' ?>"><?= $av ? '1' : '0' ?></span>
                                    <?php endif; ?>
                                    <span class="inv-bk <?= $av ? 'inv-bk-available' : 'inv-bk-booked' ?>"><?= $av ? 'Available' : 'Booked' ?></span>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($rooms)): ?>
                        <tr><td class="inv-roomcol">&mdash;</td><td colspan="<?= (int) count($dates) ?>" style="padding:20px;color:var(--muted);">
                            No active rooms found. Add rooms directly in Room Master; a category is optional.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($room_page_count > 1): ?>
            <nav class="inv-pagination" aria-label="Inventory room pages">
                <div class="inv-pagination-summary">
                    Rooms <?= (int) $room_page_start ?>&ndash;<?= (int) $room_page_end ?> of <?= (int) $filtered_room_count ?>
                </div>
                <div class="inv-page-links">
                    <?php if ($room_page > 1): ?>
                        <a class="inv-page-link" href="<?= site_url('inventory?'.$inventory_query(array('page' => $room_page - 1))) ?>" aria-label="Previous room page" title="Previous room page">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                        </a>
                    <?php else: ?>
                        <span class="inv-page-link is-disabled" aria-disabled="true" aria-label="Previous room page">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                        </span>
                    <?php endif; ?>

                    <?php $previous_page_number = 0; foreach ($visible_pages as $page_number): ?>
                        <?php if ($previous_page_number && $page_number > $previous_page_number + 1): ?>
                            <span class="inv-page-gap" aria-hidden="true">&hellip;</span>
                        <?php endif; ?>
                        <?php if ($page_number === $room_page): ?>
                            <span class="inv-page-link is-current" aria-current="page"><?= (int) $page_number ?></span>
                        <?php else: ?>
                            <a class="inv-page-link" href="<?= site_url('inventory?'.$inventory_query(array('page' => $page_number))) ?>" aria-label="Room page <?= (int) $page_number ?>"><?= (int) $page_number ?></a>
                        <?php endif; ?>
                        <?php $previous_page_number = $page_number; ?>
                    <?php endforeach; ?>

                    <?php if ($room_page < $room_page_count): ?>
                        <a class="inv-page-link" href="<?= site_url('inventory?'.$inventory_query(array('page' => $room_page + 1))) ?>" aria-label="Next room page" title="Next room page">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                        </a>
                    <?php else: ?>
                        <span class="inv-page-link is-disabled" aria-disabled="true" aria-label="Next room page">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                        </span>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>

        <div class="inv-legend">
            <span class="k"><span class="inv-swatch" style="background:var(--green-soft);"></span> Available</span>
            <span class="k"><span class="inv-swatch" style="background:#fff3d6;"></span> Booked</span>
            <span class="k"><span class="inv-swatch" style="background:var(--brand-soft);"></span> Today</span>
            <span class="k"><span class="inv-swatch" style="background:#e8f2ff;border-bottom:3px solid #2563eb;"></span> Selected date</span>
            <span class="k">Number = rooms available that day</span>
            <span class="k">Select one or more available nights in the same room to create a booking</span>
        </div>
    </div>
</div>

<!-- Occupied-room guest details. Its action is selected from the freshly loaded booking status. -->
<div class="erp-modal-backdrop inv-detail-backdrop" id="invGuestBackdrop" data-inv-booking-detail-modal hidden>
    <section class="erp-modal inv-detail-modal" id="invGuestModal" role="dialog" aria-modal="true" aria-labelledby="invGuestModalTitle" aria-describedby="invGuestModalSubtitle">
        <div class="erp-modal-head inv-detail-head">
            <div class="inv-detail-heading">
                <div class="inv-detail-kicker">Guest &amp; Booking</div>
                <h3 id="invGuestModalTitle">Booking Details</h3>
                <div class="inv-detail-subtitle" id="invGuestModalSubtitle">Loading current details&hellip;</div>
            </div>
            <div class="inv-detail-head-actions">
                <a class="erp-btn erp-btn-primary inv-detail-action" id="invGuestAction" data-inv-booking-action href="#" hidden></a>
                <button type="button" class="erp-modal-close" id="invGuestClose" data-inv-booking-detail-close aria-label="Close guest details">&times;</button>
            </div>
        </div>
        <div class="erp-modal-body inv-detail-body" id="invGuestModalBody" data-inv-booking-detail-body></div>
    </section>
</div>

<!-- The same shared New Booking form is loaded here without leaving Inventory. -->
<div class="erp-modal-backdrop inv-booking-backdrop" id="invBookingBackdrop" hidden>
    <section class="erp-modal inv-booking-modal" id="invBookingModal" role="dialog" aria-modal="true" aria-labelledby="invBookingModalTitle">
        <div class="erp-modal-head">
            <h3 id="invBookingModalTitle">New Booking from Inventory</h3>
            <button type="button" class="erp-modal-close" id="invBookingClose" aria-label="Close booking form">&times;</button>
        </div>
        <div class="inv-booking-modal-body" id="invBookingModalBody"></div>
    </section>
</div>

<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script src="<?= base_url('assets/js/booking-form.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/booking-form.js') ?>"></script>
<script src="<?= base_url('assets/js/inventory-booking.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/inventory-booking.js') ?>"></script>
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
