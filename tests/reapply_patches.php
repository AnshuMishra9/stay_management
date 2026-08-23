<?p
function patch_file_regex($path, $pattern, $replacement, $label)
{
	global $root, $fail;
	$f = $root.$path;
	$t = file_get_contents($f);
	if (preg_match($pattern, $t)) {
		$t = preg_replace($pattern, $replacement, $t, 1);
		file_put_contents($f, $t);
		echo "PATCHED  {$label}\n";
	} elseif (strpos($t, 'NEVER deleted from disk') !== false && $label === '_delete_identity_path no-op') {
		echo "ALREADY  {$label}\n";
	} else {
		$fail++; echo "MISSING  {$label}\n";
	}
}hp
/**
 * Re-apply today's intentional Customers-controller behaviour patches onto
 * the freshly split files (they exist in git HEAD only as the old logic).
 */

$root = dirname(__DIR__);
$fail = 0;
function patch_file($path, $old, $new, $label)
{
	global $root, $fail;
	$f = $root.$path;
	$t = file_get_contents($f);
	if (strpos($t, $old) !== FALSE) {
		$t = str_replace($old, $new, $t);
		file_put_contents($f, $t);
		echo "PATCHED  {$label}\n";
	} elseif (strpos($t, $new) !== FALSE) {
		echo "ALREADY  {$label}\n";
	} else {
		$fail++; echo "MISSING  {$label}\n";
	}
}

// ---- P1: customer delete = SOFT (deactivate), never hard-delete ----------
$customers = '/application/controllers/Customers.php';

patch_file($customers,
<<<'OLD'
        // A customer is shared by every property in the tenant. Never cascade
        // another property's booking/document history from this endpoint.
        if ($this->Customer_model->has_history($this->current_tenant_id, $id)) {
            $changed = $this->Customer_model->update(
                $this->current_tenant_id,
                $id,
                array('is_active' => 0)
            );
            if ( ! $changed || $this->db->trans_status() === FALSE || ! $this->db->trans_commit()) {
                $this->db->trans_rollback();
                return $this->_json(array(
                    'status' => FALSE,
                    'message' => 'The customer could not be deactivated.',
                ), 500);
            }
            return $this->_json(array(
                'status'  => TRUE,
                'deleted' => FALSE,
                'deactivated' => TRUE,
                'message' => 'Customer "'.$customer->customer_name.'" has stay or document history and was deactivated instead of deleted.',
            ));
        }

        $deleted = $this->Customer_model->delete($this->current_tenant_id, $id);
        if ( ! $deleted || $this->db->trans_status() === FALSE || ! $this->db->trans_commit()) {
            $this->db->trans_rollback();
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'The customer could not be deleted.',
            ), 409);
        }

        return $this->_json(array(
            'status'  => TRUE,
            'deleted' => TRUE,
            'deactivated' => FALSE,
            'message' => 'Customer "'.$customer->customer_name.'" deleted.',
        ));
OLD,
<<<'NEW'
        // A customer is never deleted. Delete always means deactivate: the
        // row (and every booking/document) stays in the database forever.
        $changed = $this->Customer_model->update(
            $this->current_tenant_id,
            $id,
            array('is_active' => 0)
        );
        if ( ! $changed || $this->db->trans_status() === FALSE || ! $this->db->trans_commit()) {
            $this->db->trans_rollback();
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'The customer could not be deactivated.',
            ), 500);
        }

        return $this->_json(array(
            'status'  => TRUE,
            'deleted' => FALSE,
            'deactivated' => TRUE,
            'message' => 'Customer "'.$customer->customer_name.'" was deactivated. No data was deleted.',
        ));
NEW,
'delete() soft-delete flow');

// ---- P2: identity files are never removed from disk -----------------------
patch_file_regex('/application/core/Ops_Controller.php',
    '/private function _delete_identity_path\(\\$path\)\s*\{[\s\S]*?\n    \}/',
    "/**\n     * Identity document files are NEVER deleted from disk (soft-delete policy).\n     * The DB row keeps its path; removed rows just get status = 0.\n     */\n    protected function _delete_identity_path(\\\$path)\n    {\n        return;\n    }",
    '_delete_identity_path no-op');

// ---- P3: identity scope fk_plant fallback — already present in HEAD/Ops ----
$opsText = file_get_contents($root.'/application/core/Ops_Controller.php');
if (strpos($opsText, 'fk_plant ?? $identity->tenant_id') !== false) {
	echo "ALREADY  identity scope fk_plant fallback (in Ops)\n";
} else {
	$fail++; echo "MISSING  identity scope fallback in Ops\n";
}

exit($fail > 0 ? 1 : 0);
