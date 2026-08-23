<?php
$base = 'http://localhost/stay_management';
$cookie = '';
function rq($url, $post = null, $json = false, &$cookie = '', $hdrs = array()) {
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
	list(,$b) = rq($base.'/auth/send_otp','{"mobile_no":"9876543210"}',true,$cookie);
	$o = json_decode($b)->otp ?? null; if(!$o){usleep(300000);continue;}
	list(,$b2) = rq($base.'/auth/verify_otp',json_encode(array('mobile_no'=>'9876543210','otp'=>(string)$o)),true,$cookie);
	if (!empty(json_decode($b2)->status)) break;
}
list(,$sel) = rq($base.'/properties/select',null,false,$cookie);
$t=ex($sel,'name="session_write_token"\s+value="([^"]+)"'); preg_match('/<option\s+value="(\d+)"[^>]*>([^<]*)<option/',$sel,$om);
$o2=''; preg_match_all('/<option\s+value="(\d+)"[^>]*>([^<]*)</',$sel,$oms);
foreach ($oms[2] as $k=>$txt) { if (strpos($txt,'Hotel1')!==false) { $o2=$oms[1][$k]; break; } }
if (!$o2) $o2 = isset($oms[1][0]) ? $oms[1][0] : '1';
rq($base.'/properties/switch',http_build_query(array('session_write_token'=>$t,'property_id'=>$o2)),false,$cookie);
list(,$rf) = rq($base.'/rooms/form',null,false,$cookie);
$pc = ex($rf,'name="property_context_token"\s+value="([^"]+)"');
$sfx = date('His');

// customer
list(,$fh) = rq($base.'/customers/form',null,false,$cookie);
$cwt = ex($fh,'name="customer_write_token"\s+value="([^"]+)"');
$ph = '98888'.$sfx;
rq($base.'/customers/save', http_build_query(array('property_context_token'=>$pc,'customer_write_token'=>$cwt,'id'=>'0','customer_name'=>'FIN Guest','phone'=>$ph,'pincode'=>'1','country'=>'India','is_active'=>'1')),false,$cookie);
list(,$aj) = rq($base."/customers/list_ajax?phone=$ph",false,false,$cookie,array('X-Property-Context-Token: '.$pc));
$cid = json_decode($aj)->data[0]->id ?? '';
echo "customer=$cid\n";

// category
list(,$clh) = rq($base.'/room-categories',null,false,$cookie);
preg_match('/DBG Cat[\s\S]{0,300}?room-categories\/edit\/(\d+)/',$clh,$cm);
$catId = isset($cm[1]) ? $cm[1] : '';
if (!$catId) {
	rq($base.'/room-categories/save',http_build_query(array('category_id'=>'','property_context_token'=>$pc,'category_name'=>'DBG Cat','short_code'=>'DBCX','max_adults'=>'2','max_children'=>'1','smoking_allowed'=>'0','display_order'=>'96','status'=>'1')),false,$cookie);
	list(,$clh) = rq($base.'/room-categories',null,false,$cookie);
	preg_match('/DBG Cat[\s\S]{0,400}?room-categories\/edit\/(\d+)/',$clh,$cm); $catId=isset($cm[1])?$cm[1]:'';
}
if (empty($catId)) { $catId = '12'; }
echo "catId=$catId\n";
if (!$cid) { echo "ABORT: no customer\n"; exit(1); }

$td=date('Y-m-d');$tm=date('Y-m-d',strtotime('+1 day'));
$postData = http_build_query(array('property_context_token'=>$pc,'id'=>'0','customer_id'=>$cid,'customer_name'=>'FIN Guest','phone'=>$ph,'pincode'=>'1','country'=>'India','status_id'=>'1','booking_channel_id'=>'1','room_category_id'=>$catId,'room_id'=>'','room_quantity'=>'1','scheduled_check_in_date'=>$td,'scheduled_check_out_date'=>$tm,'length_of_stay'=>'1','total_guest'=>'1','total_unit'=>'1','total_amount'=>'500','amount_paid'=>'0','booking_source'=>''));
list($bkCode,$bkBody) = rq($base.'/customers/booking_save',$postData,false,$cookie);
echo "booking_save: $bkCode\n";
preg_match_all('/class="[^"]*error[^"]*"[^>]*>\s*([^<]{3,200})\s*</',$bkBody,$em);
if (isset($em[1]) && count(array_filter($em[1]))) { echo "SAVE ERRORS: ".implode(' | ',array_filter($em[1]))."\n"; }
else { echo "(no visible errors in response)\n"; }

list(,$bkRaw) = rq($base.'/customers/bookings_ajax',null,false,$cookie,array('X-Property-Context-Token: '.$pc));
$bjd = json_decode($bkRaw)->data ?? array();
$bkd = null; foreach ($bjd as $r) { if ($r->customer_name==='FIN Guest') { $bkd=$r; break; } }
$bid = $bkd ? $bkd->id : '';
echo "bid=$bid status=" . ($bkd ? $bkd->status_code : 'N/A') . "\n";

// CHECK-IN
list(,$ff2) = rq($base.'/customers/form',null,false,$cookie);
$cwt2 = ex($ff2,'name="customer_write_token"\s+value="([^"]+)"');
$ciData = http_build_query(array('property_context_token'=>$pc,'customer_write_token'=>$cwt2,'booking_id'=>$bid,'page_context'=>'bookings','status_id'=>'2','phone'=>$ph,'customer_name'=>'FIN Guest','scheduled_check_in_date'=>$td,'scheduled_check_out_date'=>$tm));
list($ciCode,$ciBody) = rq($base.'/customers/checkin_save',$ciData,false,$cookie);
echo "checkin_save: $ciCode\n";
if ($ciCode === 200) { preg_match_all('/class="erp-error"[^>]*>([^<]{3,180})</',$ciBody,$em); if(isset($em[1]) && count($em[1])) echo "CI ERRORS: ".implode(' | ',$em[1])."\n"; }

if ($bid) {
	$m = new mysqli('127.0.0.1','root','','stay_management');
	$r = $m->query("SELECT sd_id FROM booking_details WHERE id=$bid")->fetch_assoc();
	echo "DB sd_id after checkin: ".$r['sd_id']."\n";

	$coData = http_build_query(array('property_context_token'=>$pc,'customer_write_token'=>$cwt2,'booking_id'=>$bid,'status_id'=>'3'));
	list($coCode,) = rq($base.'/customers/checkout_save',$coData,false,$cookie);
	echo "checkout_save: $coCode\n";
	$r2 = $m->query("SELECT sd_id FROM booking_details WHERE id=$bid")->fetch_assoc();
	echo "DB sd_id after checkout: ".$r2['sd_id']."\n";
	$m->close();
}
