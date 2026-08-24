<?php
/**
 * Exercises upload validation without booting CodeIgniter or writing to
 * application storage.
 */
define('BASEPATH', __DIR__.DIRECTORY_SEPARATOR);
require dirname(__DIR__).'/application/libraries/Identity_upload_guard.php';

$guard = new Identity_upload_guard();
$failures = array();
$temp_files = array();

function make_upload($name, $contents, $type = '')
{
    global $temp_files;
    $path = tempnam(sys_get_temp_dir(), 'idn_test_');
    file_put_contents($path, $contents);
    $temp_files[] = $path;
    return array(
        'name' => $name,
        'type' => $type,
        'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($path),
    );
}

function expect_result($label, $actual, $should_be_valid)
{
    global $failures;
    $passed = $should_be_valid ? $actual === NULL : is_string($actual);
    if ($passed) {
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label.' (result: '.var_export($actual, TRUE).')';
    echo "FAIL: {$label}\n";
}

// Known-good binary fixture used to verify signature detection.
$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
);
expect_result('valid PNG is accepted', $guard->validate(make_upload('front.png', $png), TRUE, FALSE), TRUE);
expect_result('valid PNG is accepted even when original name contains a path', $guard->validate(make_upload('../../front.png', $png), TRUE, FALSE), TRUE);

// Trust detected content rather than the filename or browser MIME claim.
expect_result('PNG renamed to JPG is rejected', $guard->validate(make_upload('front.jpg', $png, 'image/jpeg'), TRUE, FALSE), FALSE);
expect_result('SVG script renamed to JPG is rejected', $guard->validate(make_upload('front.jpg', '<svg onload="alert(1)"></svg>', 'image/jpeg'), TRUE, FALSE), FALSE);
expect_result('PHP extension is rejected', $guard->validate(make_upload('shell.php', '<?php echo 1; ?>'), TRUE, FALSE), FALSE);

$pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
expect_result('valid PDF signature is accepted in the first slot', $guard->validate(make_upload('document.pdf', $pdf), TRUE, FALSE), TRUE);
expect_result('PDF is rejected in the back slot', $guard->validate(make_upload('back.pdf', $pdf), FALSE, FALSE), FALSE);
expect_result('fake PDF signature is rejected', $guard->validate(make_upload('fake.pdf', 'not a pdf', 'application/pdf'), TRUE, FALSE), FALSE);

$oversized = make_upload('large.jpg', str_repeat('A', Identity_upload_guard::MAX_BYTES + 1), 'image/jpeg');
expect_result('file over 4 MiB is rejected before decoding', $guard->validate($oversized, TRUE, FALSE), FALSE);

$malformed = array('name' => array('front.jpg'), 'tmp_name' => '', 'error' => 0, 'size' => 10);
expect_result('array-shaped filename injection is rejected', $guard->validate($malformed, TRUE, FALSE), FALSE);

foreach ($temp_files as $path) {
    if (is_file($path)) { unlink($path); }
}

if ($failures) {
    fwrite(STDERR, "\n".count($failures)." identity upload security test(s) failed:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "\nAll identity upload security tests passed.\n";
