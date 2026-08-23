<?php
/* Add shared validation callbacks to Ops_Controller */
$f = dirname(__DIR__).'/application/core/Ops_Controller.php';
$t = file_get_contents($f);

if (strpos($t, 'function valid_stay_dates') !== false && strpos($t, 'function room_available_for_stay') !== false) {
	echo "already present\n"; exit(0);
}

$addition = <<<'PHP'

    /** Shared: checkout must be after check-in */
    public function valid_stay_dates($checkout)
    {
        $checkin = $this->_date($this->input->post('scheduled_check_in_date'));
        $checkout = $this->_date($checkout);
        if ($checkin && $checkout && $checkout > $checkin) { return TRUE; }
        $this->form_validation->set_message('valid_stay_dates', 'Scheduled Check-Out must be after Scheduled Check-In.');
        return FALSE;
    }

    /** Shared: reject room if another booking overlaps stay dates */
    public function room_available_for_stay($room_id)
    {
        if ($room_id === NULL || $room_id === '') { return TRUE; }
        $bid = (int) $this->input->post('booking_id');
        $cin = $this->_date($this->input->post('scheduled_check_in_date'));
        $cout = $this->_date($this->input->post('scheduled_check_out_date'));
        if (!$cin || !$cout || $cout <= $cin) {
            $existing = $bid ? $this->Customer_model->get_booking($this->current_tenant_id, $this->current_property_id, $bid) : NULL;
            list($cin, $cout) = $this->_room_availability_range($existing, TRUE);
        }
        if ($this->Customer_model->is_room_available($this->current_tenant_id, $this->current_property_id, (int)$room_id, $cin, $cout, $bid ?: NULL)) { return TRUE; }
        $this->form_validation->set_message('room_available_for_stay', 'Room not available for these dates.');
        return FALSE;
    }
PHP;

$pos = strrpos($t, '}');
$t = substr_replace($t, "\n".$addition."\n", $pos, 0);
file_put_contents($f, $t);
echo "added to Ops\n";
