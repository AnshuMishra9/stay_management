<?php
/**
 * FULL RESET of the split: restore HEAD Customers.php, then re-run the
 * deterministic partition so every method lands exactly once.
 */
$root = dirname(__DIR__);
$head = shell_exec('git show HEAD:application/controllers/Customers.php');
if ( ! $head) { fwrite(STDERR, "git show failed\n"); exit(1); }
file_put_contents($root.'/application/controllers/Customers.php', $head);
echo "Customers.php restored from HEAD (" . strlen($head) . " bytes)\n";
