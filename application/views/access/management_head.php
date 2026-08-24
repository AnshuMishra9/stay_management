<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= html_escape($page_title) ?> &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <style>
        .mgmt-shell { padding: 26px 24px 56px; max-width: 1500px; margin: 0 auto; }
        .mgmt-help { color: var(--muted); font-size: .82rem; margin-top: 2px; }
        .mgmt-required::after { content: ' *'; color: var(--red); }
        .mgmt-table { min-width: 760px; }

        /* Plant-scoped user filters. */
        .mgmt-filter-bar {
            display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;
            padding: 14px 20px; border-bottom: 1px solid var(--line);
            background: var(--grad-soft);
        }
        .mgmt-filter-bar label {
            display: block; font-size: .71rem; text-transform: uppercase;
            letter-spacing: .05em; color: var(--muted); margin-bottom: 6px; font-weight: 700;
        }
        .mgmt-filter-bar .erp-select { min-width: 260px; }

        /* Property selection cards. */
        .mgmt-property-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
            gap: 18px;
        }
        .mgmt-property-card { padding: 22px; display: flex; flex-direction: column; }
        .mgmt-property-card h2 {
            display: flex; align-items: center; gap: 10px;
            margin: 0; font-size: 1.02rem; font-weight: 800; color: var(--text);
        }
        .mgmt-property-card h2 svg {
            width: 17px; height: 17px; padding: 5px; box-sizing: content-box; flex: none;
            border-radius: 8px; background: var(--grad-soft);
            stroke: var(--brand); box-shadow: inset 0 0 0 1px rgba(109,93,246,.15);
        }

        /* Property access assignment controls. */
        .mgmt-checkbox-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px; }
        .mgmt-checkbox {
            display: flex; align-items: center; gap: 9px; cursor: pointer;
            border: 1px solid var(--input-brd); border-radius: 10px; padding: 11px 12px;
            background: #fff; font-size: .9rem; color: var(--text); font-weight: 600;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .mgmt-checkbox:hover { border-color: #c9cff0; background: var(--head); }
        .mgmt-checkbox input[type="checkbox"] { width: 17px; height: 17px; accent-color: var(--brand); flex: none; }
        .mgmt-checkbox .mgmt-help { margin-top: 0; font-weight: 500; }

        .mgmt-empty { text-align: center; padding: 46px 22px; color: var(--muted); }
        .mgmt-empty h1, .mgmt-empty h2 { color: var(--text); font-weight: 800; }

        .erp-alert ul { margin: 4px 0 0 18px; padding: 0; }

        .erp-alert-warning { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; }

        @media (max-width: 700px) {
            .mgmt-shell { padding: 16px 14px 40px; }
            .mgmt-filter-bar { padding: 12px 14px; }
            .mgmt-filter-bar .erp-select { min-width: 0; width: 100%; }
        }
    </style>
</head>
<body class="erp-body">
<?php $this->load->view('access/management_nav'); ?>
<main class="mgmt-shell">
