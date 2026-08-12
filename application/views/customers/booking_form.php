<?php
/** Full-page shell for the shared New / Edit Booking form. */
$booking = isset($booking) ? $booking : NULL;
$customer = isset($customer) ? $customer : NULL;
$is_edit = ($booking !== NULL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'New' ?> Booking &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <script>window.APP_BASE = <?= json_encode(base_url()) ?>;</script>
</head>
<body class="erp-body">

<?php $this->load->view('layouts/erp_navbar', array('active' => 'bookings', 'back' => site_url('customers/bookings'))); ?>

<div class="erp-wrap">
    <?php
    $this->load->view('customers/components/booking_form_card', array(
        'booking'           => $booking,
        'customer'          => $customer,
        'country_opts'      => $country_opts,
        'channel_opts'      => $channel_opts,
        'room_cat_opts'     => $room_cat_opts,
        'room_opts'         => $room_opts,
        'status_opts'       => $status_opts,
        'form_context'      => 'page',
        'booking_defaults'  => isset($booking_defaults) ? $booking_defaults : array(),
        'booking_form_error'=> isset($booking_form_error) ? $booking_form_error : '',
    ));
    ?>
</div>

<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script src="<?= base_url('assets/js/booking-form.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/booking-form.js') ?>"></script>
</body>
</html>
