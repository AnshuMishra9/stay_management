<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <?php $this->load->view('layouts/header_config'); ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
</head>

<body class="bg-secondary-subtle">

<?php $this->load->view('layouts/erp_navbar', array('active' => 'rooms')); ?>

<div class="container py-5">

    <div class="card shadow-lg border-0 rounded-4">

        <div class="card-header bg-primary text-white text-center py-4">
            <h2 class="mb-0" style="font-family: 'Calibri Bold';">
                Room Master
            </h2>
        </div>

        <div class="card-body">

            <div class="alert alert-success">
                ✅ Bootstrap is loaded successfully!
            </div>

            <h4 class="text-primary" style="font-family: 'Calibri Regular';">
                Welcome to Dashboard
            </h4>

            <p class="text-muted mt-3" style="font-family: 'Calibri Light'; font-size:18px;">
                This page is created in CodeIgniter 3 and styled using Bootstrap 5.
            </p>

            <hr>

            <div class="d-flex flex-wrap gap-2 mb-4">

                <button class="btn btn-primary">Primary</button>

                <button class="btn btn-secondary">Secondary</button>

                <button class="btn btn-success">Success</button>

                <button class="btn btn-danger">Danger</button>

                <button class="btn btn-warning">Warning</button>

                <button class="btn btn-info text-dark">Info</button>

                <button class="btn btn-dark">Dark</button>

            </div>

            <div class="row g-3">

                <div class="col-md-3">
                    <div class="p-4 bg-primary text-white rounded text-center">
                        Primary
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-4 bg-success text-white rounded text-center">
                        Success
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-4 bg-warning rounded text-center">
                        Warning
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="p-4 bg-danger text-white rounded text-center">
                        Danger
                    </div>
                </div>

            </div>

            <hr>

            <span class="badge bg-primary">Primary</span>
            <span class="badge bg-success">Success</span>
            <span class="badge bg-danger">Danger</span>
            <span class="badge bg-warning text-dark">Warning</span>
            <span class="badge bg-info text-dark">Info</span>

        </div>

    </div>

</div>

<?php $this->load->view('layouts/scripts_config'); ?>

</body>
</html>
