<?php
/**
 * Finalize Ops_Controller after split:
 *  - carry over class constants from HEAD Customers (e.g. MAX_IDENTITY_ROWS)
 *  - ensure constructor loads security helper + identity_upload_guard +
 *    booking_calculator libraries used by moved methods.
 */
$root = dirname(__DIR__);
$head = shell_exec('git show HEAD:application/controllers/Customers.php');
$f = $root.'/application/core/Ops_Controller.php';
$t = file_get_contents($f);

// constants
preg_match_all('/^\s*const\s+(\w+)\s*=\s*([^;]+);/m', $head, $cmap, PREG_SET_ORDER);
$constOut = '';
foreach ($cmap as $c) { $constOut .= "    const {$c[1]} = {$c[2]};\n"; }
if ($constOut && strpos($t, 'const ') === false) {
	$anchor = "class Ops_Controller extends Property_Controller\n{\n";
	$t = str_replace($anchor, $anchor.$constOut, $t);
	echo "constants added: ".count($cmap)."\n";
}

// constructor library/helper loads
$ctorAnchor = "        \$this->load->model('Customer_model');\n";
$needed = array(
	"        \$this->load->helper('security');\n",
	"        \$this->load->library('identity_upload_guard');\n",
	"        \$this->load->library('booking_calculator');\n",
);
foreach ($needed as $line) {
	if (strpos($t, trim($line)) === false) {
		$t = str_replace($ctorAnchor, $ctorAnchor.$line, $t);
		echo "added load: ".trim($line)."\n";
	}
}

file_put_contents($f, $t);
echo "Ops finalized\n";
