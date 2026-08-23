<?php
$base = 'http://localhost/stay_management';
$cookie = '';

function req($url, $post = null, $json = false, &$cookie = '', $headers = array())
{
    $ch = curl_init($url);
    $hs = $headers;
    if ($json) { $hs[] = 'Content-Type: application/json'; }
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIE         => $cookie,
        CURLOPT_HTTPHEADER     => $hs,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
    ));
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize= curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr($raw, 0, $hsize);
    $body = substr($raw, $hsize);
    if (preg_match_all('/Set-Cookie:\s*(ci_session=[^;]+)/i', $head, $m)) {
        $cookie = $m[1][count($m[1]) - 1];
    }
    return array($code, $body);
}

for ($i = 0; $i < 4; $i++) {
    list(, $b) = req($base.'/auth/send_otp', '{"mobile_no":"9876543210"}', true, $cookie);
    $otp = isset(json_decode($b)->otp) ? json_decode($b)->otp : null;
    if ($otp) {
        list(, $b2) = req($base.'/auth/verify_otp', json_encode(array('mobile_no'=>'9876543210','otp'=>(string)$otp)), true, $cookie);
        $ok = json_decode($b2)->status ?? false;
        if ($ok) { echo "login ok\n"; break; }
    }
    usleep(300000);
}

list($code,, ) = array();
$r = req($base.'/inventory', null, false, $cookie);
if ($r[0] === 307) {
    list(, $sel) = req($base.'/properties/select', null, false, $cookie);
    preg_match('/name="session_write_token"\s+value="([^"]+)"/', $sel, $tm); $tok = $tm[1];
    preg_match_all('/<option\s+value="(\d+)"[^>]*>([^<]*)<\/option>/', $sel, $oms);
    $opt = '';
    foreach ($oms[2] as $k => $txt) { if (strpos($txt, 'Hotel1') !== false) { $opt = $oms[1][$k]; break; } }
    req($base.'/properties/switch', http_build_query(array('session_write_token'=>$tok,'property_id'=>$opt)), false, $cookie);
}
$rf = req($base.'/rooms/form', null, false, $cookie)[1];
preg_match('/name="property_context_token"\s+value="([^"]+)"/', $rf, $pm);
$propCtx = $pm[1];

foreach (array('/customers/bookings_ajax','/customers/checkins_ajax','/customers/checkedouts_ajax') as $ep) {
    list($code, $body) = req($base.$ep, null, false, $cookie, array('X-Property-Context-Token: '.$propCtx));
    echo "== {$ep} == HTTP {$code} len=".strlen($body)."\n";
    echo substr(preg_replace('/\s+/',' ',$body),0,700)."\n\n";
}
