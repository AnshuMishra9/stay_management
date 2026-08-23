<?php
$base = 'http://localhost/stay_management';
$cookie = '';
function req($url, $post = null, $json = false, &$cookie = '', $hdrs = array()) {
	$ch = curl_init($url);
	$hs = $hdrs;
	if ($json) $hs[] = 'Content-Type: application/json';
	if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
	curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>$cookie,CURLOPT_HTTPHEADER=>$hs,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20));
	$raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
	$head = substr($raw,0,$hsize); $body = substr($raw,$hsize);
	if (preg_match_all('/Set-Cookie:\s*(ci_session=[^;]+)/i',$head,$m)) $cookie=$m[1][count($m[1])-1];
	return array($code,$body);
}
function ex($h,$p){ return preg_match('/'.$p.'/',$h,$m)?$m[1]:''; }

for ($i=0;$i<4;$i++) {
	list(,$b) = req($base.'/auth/send_otp','{"mobile_no":"9876543210"}',true,$cookie);
	$o = json_decode($b)->otp ?? null; if(!$o){usleep(300000);continue;}
	list(,$b2) = req($base.'/auth/verify_otp',json_encode(array('mobile_no'=>'9876543210','otp'=>(string)$o)),true,$cookie);
	if (!empty(json_decode($b2)->status)) { echo "login ok\n"; break; }
}
list($c,,) = req($base.'/inventory',null,false,$cookie);
if ($c === 307) {
	list(,$sel) = req($base.'/properties/select',null,false,$cookie);
	$tok=ex($sel,'name="session_write_token"\s+value="([^"]+)"'); preg_match('/<option\s+value="(\d+)"/',$sel,$om);$opt=$om[1];
	req($base.'/properties/switch',http_build_query(array('session_write_token'=>$tok,'property_id'=>$opt)),false,$cookie);
}
list(,$rf) = req($base.'/rooms/form',null,false,$cookie);
$propCtx = ex($rf,'name="property_context_token"\s+value="([^"]+)"');
$suffix = date('His');

list(,$fh) = req($base.'/customers/form',null,false,$cookie);
$cw = ex($fh,'name="customer_write_token"\s+value="([^"]+)"');
$phone = '99999'.$suffix;
req($base.'/customers/save', http_build_query(array('property_context_token'=>$propCtx,'customer_write_token'=>$cw,'id'=>'0','customer_name'=>'DBG T Guest','phone'=>$phone,'pincode'=>'1','country'=>'India','is_active'=>'1')),false,$cookie);
list(,$aj) = req($base."/customers/list_ajax?phone=$phone",false,false,$cookie,array('X-Property-Context-Token: '.$propCtx));
$custId = json_decode($aj)->data[0]->id ?? '';
echo "custId=$custId\n";

list(,$clh) = req($base.'/room-categories',null,false,$cookie);
preg_match('/DBG Cat[\s\S]{0,300}?room-categories\/edit\/(\d+)/',$clh,$cm);
$catId = $cm[1] ?? '';
if (!$catId) {
	req($base.'/room-categories/save',http_build_query(array('category_id'=>'','property_context_token'=>$propCtx,'category_name'=>'DBG Cat','short_code'=>'DBC','max_adults'=>'2','max_children'=>'1','smoking_allowed'=>'0','display_order'=>'96','status'=>'1')),false,$cookie);
	list(,$clh) = req($base.'/room-categories',null,false,$cookie);
	preg_match('/DBG Cat[\s\S]{0,400}?room-categories\/edit\/(\d+)/',$clh,$cm); $catId=$cm[1];
}
echo "catId=$catId\n";
if (empty($catId)) { $catId = '12'; echo "fallback catId=12\n"; }
$today=date('Y-m-d');$tomorrow=date('Y-m-d',strtotime('+1 day'));
$postFields = http_build_query(array(
	'property_context_token'=>$propCtx,'id'=>'0','customer_id'=>$custId,
	'customer_name'=>'DBG T Guest','phone'=>$phone,'pincode'=>'1','country'=>'India',
	'status_id'=>'1','booking_channel_id'=>'1','room_category_id'=>$catId,'room_id'=>'','room_quantity'=>'1',
	'scheduled_check_in_date'=>$today,'scheduled_check_out_date'=>$tomorrow,'length_of_stay'=>'1',
	'total_guest'=>'2','total_unit'=>'1','total_amount'=>'1000','amount_paid'=>'0','booking_source'=>''
));
list($code,$bb) = req($base.'/customers/booking_save',$postFields,false,$cookie);
echo "booking_save: HTTP $code\n";
if ($code===200) {
	preg_match('/<title>([^<]+)<\/title>/',$bb,$tm); echo "title: ".$tm[1]."\n";
	file_put_contents('tests/debug_booking_response.html', $bb);
	preg_match_all('/class="[^"]*error[^"]*"[^>]*>([^<]{3,200})</',$bb,$em);
	if(isset($em[1]) && count($em[1])) echo "ERRORS: ".implode(' | ',array_filter($em[1]))."\n";
	else echo "(no erp-error divs found)\n";
}

list(,$bkRaw) = req($base.'/customers/bookings_ajax',null,false,$cookie,array('X-Property-Context-Token: '.$propCtx));
$bj = json_decode($bkRaw); $bid=''; 
foreach (($bj->data??array()) as $r) { if ($r->customer_name==='DBG T Guest') { $bid=$r->id; break; } }
echo "bid=$bid\n";
if (!$bid) { echo "NO BID - aborting checkin test\n"; exit(0); }

// check-in with fresh write token
list(,$cc2) = req($base.'/customers/form',null,false,$cookie);
$cw2 = ex($cc2,'name="customer_write_token"\s+value="([^"]+)"');
$checkinPost = http_build_query(array(
	'property_context_token'=>$propCtx,'customer_write_token'=>$cw2,'booking_id'=>$bid,
	'page_context'=>'bookings','status_id'=>'2','phone'=>$phone,'customer_name'=>'DBG T Guest',
	'scheduled_check_in_date'=>$today,'scheduled_check_out_date'=>$tomorrow
));
list($ciCode,$ciBody) = req($base.'/customers/checkin_save',$checkinPost,false,$cookie);
echo "checkin: HTTP $ciCode\n";
if ($ciCode===200) { preg_match_all('/class="erp-error"[^>]*>([^<]{3,150})</',$ciBody,$em2); if(isset($em2[1]) && count($em2[1])) echo "checkin errors: ".implode(' | ',$em2[1])."\n"; }

// verify in DB directly
$m = new mysqli('127.0.0.1','root','','stay_management');
$r = $m->query("SELECT sd_id FROM booking_details WHERE id=$bid")->fetch_assoc();
echo "DB sd_id after checkin: ".$r['sd_id']."\n";
$m->close();
