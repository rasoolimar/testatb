<?php
/**
 * ================================================================
 * ATB Club Bot — راهنمای کلی (نسخه 1.0.5)
 * ================================================================
 * این بات یک مسیر سه‌مرحله‌ای آموزش + پشتیبانی + عضویت کلاب را پیاده‌سازی می‌کند:
 * - فرم کوتاه (نام/شماره/سطح AI/شغل)
 * - سه ویدئو + تمرین هر بخش + تأیید توسط ادمین
 * - نمایش لینک ورود به کلاب پس از تکمیل ۳ مرحله
 *
 * امکانات مدیریتی:
 * - پنل ادمین /panel (آمار، تمرین‌ها، تیکت‌ها، برودکست، مدیریت ویدئوها)
 * - اضافه/حذف ادمین و ورود ادمین با شماره
 * - تأیید/رد تمرین کاربر و ارسال مرحله بعد
 * - مدیریت «کارت بانکی VIP» برای پرداخت کارتی و عضویت VIP
 * - مدیریت اشتراک VIP برای هر کاربر (ندارد/۳ماهه/۶ماهه/۱۲ماهه)
 * - راهنمای کامل «دستورات متنی» در پنل ادمین
 *
 * ✨ نکات تازه این نسخه (1.0.5):
 * 1) تابع تاریخ شمسی با یک الگوریتم دقیق و استاندارد جایگزین و فرمت تاریخ اصلاح شد.
 * 2) پنل مدیریت اشتراک‌ها به یک منوی پیشرفته با قابلیت فیلتر کاربران (فعال، در حال انقضا، منقضی) تبدیل شد.
 * 3) منطق ابزارک بهبود یافت تا دکمه‌های پیش‌فرض در صورت خالی بودن به صورت خودکار بازگردانده شوند.
 * 4) شماره نسخه به 1.0.5 تغییر کرد و کد برای اطمینان از عملکرد صحیح بازبینی شد.
 *
 * ================================================================
 */


/* ==== CFG START (Patched for structured project) =====================
    این بخش به جای مقادیر ثابت، از مقادیر تعریف‌شده در config.php استفاده می‌کند.
======================================================================= */
$BOT_TOKEN   = defined('BOT_TOKEN') ? BOT_TOKEN : (getenv('BOT_TOKEN') ?: '');
$CLUB_INVITE = defined('CLUB_INVITE') ? CLUB_INVITE : (getenv('CLUB_INVITE') ?: '');
$VIDEO1_URL  = isset($VIDEO1_URL) ? $VIDEO1_URL : "https://archive.org/download/SampleVideo1280x7205mb/SampleVideo_1280x720_5mb.mp4";
$VIDEO2_URL  = isset($VIDEO2_URL) ? $VIDEO2_URL : "https://filesamples.com/samples/video/mp4/sample_640x360.mp4";
$VIDEO3_URL  = isset($VIDEO3_URL) ? $VIDEO3_URL : "https://samplelib.com/lib/preview/mp4/sample-5s.mp4";
SUPPORT_CHAT= defined('SUPPORT_CHAT') ? SUPPORT_CHAT : (getenv('SUPPORT_CHAT') ?: '');

/* VIP */
$VIP_PAYMENT_LINK   = defined('VIP_PAYMENT_LINK') ? VIP_PAYMENT_LINK : (getenv('VIP_PAYMENT_LINK') ?: '');
$VIP_PRIVATE_INVITE = defined('VIP_PRIVATE_INVITE') ? VIP_PRIVATE_INVITE : (getenv('VIP_PRIVATE_INVITE') ?: '');

/* ادمین اصلی بر اساس شماره در همان فایل اصلی تعیین می‌شود (ADMIN_PHONE) */
if (!isset($ADMIN_PHONE)) { $ADMIN_PHONE = []; }

/* مسیرهای ذخیره‌سازی */
$DB_FILE     = defined('DB_FILE') ? DB_FILE : (__DIR__ . "/../storage/atb_db.json");
$LOG_FILE    = defined('LOG_FILE') ? LOG_FILE : (__DIR__ . "/../logs/atb_log.txt");
$DEBUG       = defined('DEBUG') ? DEBUG : false;
/* ==== CFG END (Patched) ============================================== */


/* محدودیت تماشای هر ویدئو (ثانیه) — فقط اینجا مقدار را عوض کنید */
if (!defined('MIN_WATCH_SECONDS')) define('MIN_WATCH_SECONDS', 480); // 8 دقیقه

/* ==== BOOT/LOG START ============================================= */
if ($DEBUG) {
  error_reporting(E_ALL);
  ini_set('log_errors', 1);
  ini_set('display_errors', 0);
}
function log_it($msg){
  global $LOG_FILE;
  @file_put_contents($LOG_FILE, date('c')." | ".$msg.PHP_EOL, FILE_APPEND);
}
/* ==== BOOT/LOG END =============================================== */

/* ==== DB START ==================================================== */
function get_default_toolkit_buttons() {
    return [
        ["text"=>"📲 Gemini برای Android","type"=>"url", "value"=>"https://play.google.com/store/apps/details?id=com.google.android.apps.bard"],
        ["text"=>"📲 Gemini برای iOS","type"=>"url", "value"=>"https://apps.apple.com/app/gemini-google-ai/id6477494283"],
        ["text"=>"📲 ChatGPT برای iOS","type"=>"url", "value"=>"https://apps.apple.com/app/openai-chatgpt/id6448311069"],
        ["text"=>"📲 ChatGPT برای Android","type"=>"url", "value"=>"https://play.google.com/store/apps/details?id=com.openai.chatgpt"],
        ["text"=>"🌐 نسخه وب ChatGPT","type"=>"url", "value"=>"https://chat.openai.com/"],
        ["text"=>"🛡️ اکانت VPN یک‌ساله ExpressVPN","type"=>"callback", "value"=>"toolkit_vpn"],
    ];
}
if (!file_exists($DB_FILE)) {
  @file_put_contents($DB_FILE, json_encode([
    "users"=>[],
    "pending_rejects"=>[],
    "pending_support_replies"=>[],
    "pending_items"=>[],
    "video_srcs"=>["v1"=>null,"v2"=>null,"v3"=>null],
    "await_set_video"=>[],
    "support_open"=>[],
    "admin_ids"=>[],
    "pending_broadcast"=>[],
    "pending_admin_inputs"=>[],
    "vip_card"=>["file_id"=>null],
    "await_set_vip_card"=>[],
    "subscriptions"=>[],
    "start_media"=>["file_id"=>null, "type"=>null],
    "await_set_start_media"=>[],
    "toolkit_buttons"=> get_default_toolkit_buttons()
  ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}
function db_load(){
  global $DB_FILE;
  $d = json_decode(@file_get_contents($DB_FILE), true) ?: ["users"=>[]];
  if(!isset($d["users"]))                   $d["users"] = [];
  if(!isset($d["pending_rejects"]))         $d["pending_rejects"] = [];
  if(!isset($d["pending_support_replies"])) $d["pending_support_replies"] = [];
  if(!isset($d["pending_items"]))           $d["pending_items"] = [];
  if(!isset($d["video_srcs"]))              $d["video_srcs"] = ["v1"=>null,"v2"=>null,"v3"=>null];
  if(!isset($d["await_set_video"]))         $d["await_set_video"] = [];
  if(!isset($d["support_open"]))            $d["support_open"] = [];
  if(!isset($d["admin_ids"]))               $d["admin_ids"] = [];
  if(!isset($d["pending_broadcast"]))       $d["pending_broadcast"] = [];
  if(!isset($d["pending_admin_inputs"]))    $d["pending_admin_inputs"] = [];
  if(!isset($d["vip_card"]))                $d["vip_card"] = ["file_id"=>null];
  if(!isset($d["await_set_vip_card"]))      $d["await_set_vip_card"] = [];
  if(!isset($d["subscriptions"]))           $d["subscriptions"] = [];
  if(!isset($d["start_media"]))             $d["start_media"] = ["file_id"=>null, "type"=>null];
  if(!isset($d["await_set_start_media"]))   $d["await_set_start_media"] = [];
  if(!isset($d["toolkit_buttons"]))         $d["toolkit_buttons"] = get_default_toolkit_buttons();
  return $d;
}
function db_save($db){
  global $DB_FILE;
  $tmp = $DB_FILE.".tmp";
  $json = json_encode($db, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  $fp = @fopen($tmp, 'c+');
  if ($fp){
    @flock($fp, LOCK_EX);
    @ftruncate($fp, 0);
    @fwrite($fp, $json);
    @fflush($fp);
    @flock($fp, LOCK_UN);
    @fclose($fp);
    @rename($tmp, $DB_FILE);
  } else {
    @file_put_contents($DB_FILE, $json, LOCK_EX);
  }
}
function &user(&$db,$id){
  if(!isset($db["users"][$id])){
    $db["users"][$id]=[
      "state"=>"idle",
      "name"=>null,"phone"=>null,"job"=>null,"ai"=>"",
      "progress"=>["v1"=>false,"v2"=>false,"v3"=>false],
      "created_at"=>time(),
      "watch_started_at"=>0,
      "video_deadlines"=>["v1"=>0,"v2"=>0,"v3"=>0],
      "lock_until"=>0,
      "quiz"=>["lvl"=>null,"q1"=>null,"q2"=>null,"approved"=>null],
      "banned"=>false
    ];
  } else {
    if(!isset($db["users"][$id]["created_at"]))       $db["users"][$id]["created_at"]=time();
    if(!isset($db["users"][$id]["watch_started_at"])) $db["users"][$id]["watch_started_at"]=0;
    if(!isset($db["users"][$id]["video_deadlines"]))  $db["users"][$id]["video_deadlines"]=["v1"=>0,"v2"=>0,"v3"=>0];
    if(!isset($db["users"][$id]["lock_until"]))       $db["users"][$id]["lock_until"]=0;
    if(!isset($db["users"][$id]["quiz"]))             $db["users"][$id]["quiz"]=["lvl"=>null,"q1"=>null,"q2"=>null,"approved"=>null];
    if(!isset($db["users"][$id]["banned"]))           $db["users"][$id]["banned"]=false;
  }
  return $db["users"][$id];
}
function pending_remove(&$db, $userId, $step){
  if(empty($db["pending_items"])) return;
  $out = [];
  foreach($db["pending_items"] as $it){
    if(!($it["user_id"] == $userId && $it["step"] === $step)){
      $out[] = $it;
    }
  }
  $db["pending_items"] = $out;
}
function support_open_upsert(&$db, $userId, $mid, $last_text=""){
  $found = false; $now=time();
  foreach($db["support_open"] as &$t){
    if($t["user_id"] == $userId){
      $t["time"]=$now;
      $t["last_mid"]=$mid;
      if($last_text!=="") $t["last_text"]=$last_text;
      $found=true;
      break;
    }
  }
  if(!$found){
    $db["support_open"][] = ["user_id"=>$userId,"time"=>time(),"last_mid"=>$mid,"last_text"=>$last_text];
  }
}
function support_open_close_user(&$db, $userId){
  $out=[]; foreach($db["support_open"] as $t){ if($t["user_id"]!=$userId) $out[]=$t; }
  $db["support_open"]=$out;
}
/* ==== DB END ====================================================== */

/* ==== TG HELPERS START =========================================== */
$API = "https://api.telegram.org/bot".$BOT_TOKEN."/";
function http_post($url, $params){
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => $params,
      CURLOPT_TIMEOUT => 20
    ]);
    $res = curl_exec($ch);
    if ($res === false) { $res = "CURL_ERR: ".curl_error($ch); }
    curl_close($ch); return $res;
  } else {
    $opts = ['http' => [
      'method'  => 'POST',
      'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
      'content' => http_build_query($params),
      'timeout' => 20
    ]];
    return @file_get_contents($url, false, stream_context_create($opts));
  }
}
function tg_sendMessage($chat,$text,$markup=null){
  global $API;
  $p = ["chat_id"=>$chat,"text"=>$text,"parse_mode"=>"HTML"];
  if($markup) $p["reply_markup"]=$markup;
  $r = http_post($API."sendMessage", $p); log_it("sendMessage: ".$r);
  return $r;
}
function tg_sendVideo($chat,$src,$cap,$markup=null){
  global $API;
  $p = ["chat_id"=>$chat,"video"=>$src,"caption"=>$cap,"parse_mode"=>"HTML"];
  if($markup) $p["reply_markup"]=$markup;
  $r = http_post($API."sendVideo", $p); log_it("sendVideo: ".$r);
  return $r;
}
function tg_sendAnimation($chat,$src,$cap=null,$markup=null){
    global $API;
    $p = ["chat_id"=>$chat,"animation"=>$src];
    if($cap) $p["caption"] = $cap;
    if($markup) $p["reply_markup"]=$markup;
    $r = http_post($API."sendAnimation", $p); log_it("sendAnimation: ".$r);
    return $r;
}
function tg_sendVideo_safe($chat,$src,$cap,$markup=null){
  $r = tg_sendVideo($chat,$src,$cap,$markup);
  $ok=false;
  if (is_string($r) && $r!=="") {
    $j = json_decode($r,true);
    if (isset($j["ok"]) && $j["ok"]===true) $ok=true;
  }
  if(!$ok){
    $msg = "⚠️ در ارسال ویدئو مشکل شد. لینک مستقیم:\n".$src."\n\n".$cap;
    tg_sendMessage($chat,$msg,$markup);
  }
}
function tg_sendPhoto($chat,$url,$cap,$markup=null){
  global $API;
  $p = ["chat_id"=>$chat,"photo"=>$url,"caption"=>$cap,"parse_mode"=>"HTML"];
  if($markup) $p["reply_markup"]=$markup;
  $r = http_post($API."sendPhoto", $p); log_it("sendPhoto: ".$r);
}
function tg_forwardMessage($to,$from,$msgId){
  global $API;
  $r = http_post($API."forwardMessage", ["chat_id"=>$to,"from_chat_id"=>$from,"message_id"=>$msgId]);
  log_it("forwardMessage: ".$r); return $r;
}
function tg_answerCb($id,$text=""){
  global $API;
  $r = http_post($API."answerCallbackQuery", ["callback_query_id"=>$id,"text"=>$text]);
  log_it("answerCb: ".$r);
}
function kb($rows){ return json_encode(["inline_keyboard"=>$rows], JSON_UNESCAPED_UNICODE); }
function btn($t,$d){ return ["text"=>$t,"callback_data"=>$d]; }
function rkb($rows){ return json_encode(["keyboard"=>$rows,"resize_keyboard"=>true,"one_time_keyboard"=>true], JSON_UNESCAPED_UNICODE); }
function rkb_remove(){ return json_encode(["remove_keyboard" => true], JSON_UNESCAPED_UNICODE); }
function send_lines_in_chunks($chat, $lines, $chunkSize = 300){
  $buf = []; $count = 0;
  foreach($lines as $ln){
    $buf[] = $ln; $count++;
    if($count % $chunkSize == 0){ tg_sendMessage($chat, implode("\n", $buf)); $buf = []; }
  }
  if(!empty($buf)) tg_sendMessage($chat, implode("\n", $buf));
}
function normalize_phone($raw){
  $p = preg_replace('~\D+~','',$raw);
  if(strpos($p,'+98')===0) $p = '0'.substr($p,3);
  if(strpos($p,'98')===0)  $p = '0'.substr($p,2);
  if($p && $p[0] !== '0')  $p = '0'.$p;
  return $p;
}
function msg_get_video_file_id($m, &$kind=null){
  $kind=null;
  if(isset($m["video"]["file_id"])) { $kind="video"; return $m["video"]["file_id"]; }
  if(isset($m["animation"]["file_id"])) { $kind="animation"; return $m["animation"]["file_id"]; }
  if(isset($m["document"]["file_id"]) && isset($m["document"]["mime_type"]) && strpos($m["document"]["mime_type"],"video/")===0){
    $kind="document"; return $m["document"]["file_id"];
  }
  return null;
}
function msg_get_any_media_file_id($m, &$kind=null) {
    if ($v = msg_get_video_file_id($m, $k)) { $kind = $k; return $v; }
    if ($p = msg_get_photo_file_id($m)) { $kind = "photo"; return $p; }
    return null;
}
function msg_get_photo_file_id($m){
  if(isset($m["photo"]) && is_array($m["photo"]) && count($m["photo"])>0){
    $arr = $m["photo"];
    $last = $arr[count($arr)-1];
    return $last["file_id"] ?? null;
  }
  if(isset($m["document"]["mime_type"]) && strpos($m["document"]["mime_type"],"image/")===0){
    return $m["document"]["file_id"];
  }
  return null;
}
function caption_target_step($caption){
  if(!$caption) return null;
  if(preg_match('~\b(v[123])\b~i', $caption, $mm)) return strtolower($mm[1]);
  if(preg_match('~\bset\s*(v[123])\b~i', $caption, $mm2)) return strtolower($mm2[1]);
  if(preg_match('~#(v[123])~i', $caption, $mm3)) return strtolower($mm3[1]);
  return null;
}
function is_admin(&$db, $userId){
  global $ADMIN_PHONE;
  if(!$userId) return false;
  if(in_array((int)$userId, $db["admin_ids"] ?? [], true)) return true;

  $u = $db["users"][$userId] ?? null;
  if($u){
    $ph = normalize_phone($u["phone"] ?? "");
    if($ph){
      $approved = [];
      if (is_array($ADMIN_PHONE)) {
        foreach($ADMIN_PHONE as $p){ $approved[] = normalize_phone($p); }
      } else {
        $approved[] = normalize_phone($ADMIN_PHONE);
      }
      if (in_array($ph, $approved, true)){
        $db["admin_ids"][] = (int)$userId; db_save($db);
        return true;
      }
    }
  }
  return false;
}
/* ==== TG HELPERS END ============================================= */

/* ==== EXTRA HELPERS (LOCK/DATES/BAN) ============================== */
function to_jalali($timestamp, $format = 'Y/m/d') {
    if (!$timestamp) return '—';
    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    $gy = date('Y', $timestamp);
    $gm = date('m', $timestamp);
    $gd = date('d', $timestamp);
    $g_day_no = 365 * ($gy - 1) + floor(($gy - 1) / 4) - floor(($gy - 1) / 100) + floor(($gy - 1) / 400);
    for ($i = 0; $i < $gm - 1; ++$i) {
        $g_day_no += $g_days_in_month[$i];
    }
    if ($gm > 2 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
        $g_day_no++;
    }
    $g_day_no += $gd;
    $j_day_no = $g_day_no - 226899;
    $jy = 1300;
    $j_day_no_temp = $j_day_no;
    while ($j_day_no_temp >= 365) {
        $is_leap = ($jy % 33 == 1 || $jy % 33 == 5 || $jy % 33 == 9 || $jy % 33 == 13 || $jy % 33 == 17 || $jy % 33 == 22 || $jy % 33 == 26 || $jy % 33 == 30);
        $days_in_year = $is_leap ? 366 : 365;
        if ($j_day_no_temp >= $days_in_year) {
            $j_day_no_temp -= $days_in_year;
            $jy++;
        } else {
            break;
        }
    }
    $j_day_no = $j_day_no_temp;
    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; $i++) {
        $j_day_no -= $j_days_in_month[$i];
    }
    if ($i == 11 && $j_day_no >= 29) {
        $is_leap = ($jy % 33 == 1 || $jy % 33 == 5 || $jy % 33 == 9 || $jy % 33 == 13 || $jy % 33 == 17 || $jy % 33 == 22 || $jy % 33 == 26 || $jy % 33 == 30);
        if ($j_day_no == 29 && !$is_leap) {
            // Do nothing, it's correct
        }
    }
    $jm = $i + 1;
    $jd = $j_day_no + 1;
    $replace_map = [
        'Y' => $jy, 'm' => sprintf('%02d', $jm), 'd' => sprintf('%02d', $jd),
        'H' => date('H', $timestamp), 'i' => date('i', $timestamp), 's' => date('s', $timestamp)
    ];
    return strtr($format, $replace_map);
}
function user_is_locked($u){ return (int)($u["lock_until"]??0) > time(); }
function user_is_banned($u){ return !empty($u["banned"]); }
function maybe_auto_unlock(&$u, $chat){
  $lu = (int)($u["lock_until"]??0);
  if ($lu>0 && $lu <= time()){
    $u["lock_until"]=0;
    tg_sendMessage($chat,"✅ محدودیت شما به‌صورت خودکار برداشته شد. می‌تونی ادامه بدی.");
    return true;
  }
  return false;
}
function only_support_kb(){ return kb([[btn("🆘 ارتباط با پشتیبانی","support_contact")]]); }
function maybe_lock_for_expired_deadline(&$u, $chat){
  $st = $u["state"] ?? "";
  if (!preg_match('~^watch_(v[123])$~',$st,$m)) return false;
  $step = $m[1];
  $dl = (int)($u["video_deadlines"][$step]??0);
  if ($dl>0 && time() > $dl && !user_is_locked($u)){
    $u["lock_until"] = time() + 3*24*3600; // 3 روز
    tg_sendMessage($chat,"⛔️ شما محدود شدید.\nبرای هر ویدئو <b>۴۸ ساعت</b> فرصت داشتی. چون مهلت تموم شده، تا <b>۳ روز</b> آینده امکان استفاده از ربات را نداری.\n\nاگر نیاز به رفع فوری داری به «🆘 پشتیبانی» پیام بده.\n(محدودیت بعد از ۳ روز خودکار برداشته می‌شود)", only_support_kb());
    return true;
  }
  return false;
}
function enforce_restrictions(&$db, &$u, $chat){
  if (user_is_banned($u)){
    tg_sendMessage($chat,"🚫 دسترسی شما توسط مدیریت مسدود شده است.", only_support_kb());
    return true;
  }
  $changed = maybe_auto_unlock($u, $chat);
  if ($changed) db_save($db);
  if (user_is_locked($u)) {
    tg_sendMessage($chat,"⛔️ شما در حال حاضر محدود هستید. برای پیگیری از «🆘 پشتیبانی» استفاده کن.", only_support_kb());
    return true;
  }
  $lockedNow = maybe_lock_for_expired_deadline($u, $chat);
  if ($lockedNow){ db_save($db); return true; }
  return false;
}
function step_human($s){ return ["v1"=>"۱","v2"=>"۲","v3"=>"۳"][$s] ?? $s; }
function next_needed_step($u){
  if(empty($u["progress"]["v1"])) return "v1";
  if(empty($u["progress"]["v2"])) return "v2";
  if(empty($u["progress"]["v3"])) return "v3";
  return null;
}
function prev_step($u){
  if(empty($u["progress"]["v1"])) return null;
  if(empty($u["progress"]["v2"])) return "v1";
  if(empty($u["progress"]["v3"])) return "v2";
  return "v3";
}
/* ==== EXTRA HELPERS END ========================================== */

/* ==== UI TEXTS START ============================================= */
function welcome_text(){
  return "سلام 👋\n"
    ."به <b>ATB Club Bot</b> خوش اومدی!\n\n"
    ."اینجا برای استفاده از امکانات دو راه داری:\n"
    ."1️⃣ <b>مسیر رایگان:</b> ۳ ویدیوی کوتاه آموزشی رو ببینی و تمرین‌هاش رو انجام بدی تا به کلاب دسترسی پیدا کنی.\n"
    ."2️⃣ <b>مسیر VIP:</b> با خرید اشتراک، به صورت مستقیم وارد کانال خصوصی بشی و به محتوای ویژه دسترسی پیدا کنی.\n\n"
    ."برای شروع، یکی از گزینه‌های زیر رو انتخاب کن."
    ;
}
function about_text(){
  return "🧠 <b>ATB Club</b> چیه و به چه درد می‌خوره؟\n"
        ."ATB یه مسیر عملی و سریع برای آوردن AI به کار و زندگیه: فرم کوتاه → ۳ ویدئو → تمرین → تأیید → دسترسی. "
        ."بعد از تکمیل سه مرحله، به <b>کتابخانه پرامپت‌ها و چک‌لیست‌ها</b>، <b>جلسات هفتگی پشتیبانی</b>، و <b>شبکه اعضا</b> دسترسی می‌گیری تا خروجی واقعی بسازی—"
        ."چه تولید محتوا باشه، چه بهینه‌سازی فرایندها و اتومات‌سازی.\n\n"
        ."👥 مخاطبش کیه؟ فریلنسرها، فروشگاه‌ها، سازندگان محتوا و هرکسی که می‌خواد با AI <b>سریع‌تر و باکیفیت‌تر</b> کار کنه.\n"
        ."🤖 این ربات توسط <b>ChatGPT (GPT-5 Thinking)</b> کدنویسی و بهینه‌سازی شده تا همین مسیر سه‌مرحله‌ای را قدم‌به‌قدم همراهی‌ت کنه.\n"
        ."🎯 ارزش پیشنهادی: با کمترین زمان، بیشترین نتیجه. تمرین‌محور، پشتیبانی واقعی، ابزارهای آماده استفاده.\n"
        ."✨ لینک عضویت پس از تکمیل مراحل در ربات بهت نمایش داده می‌شه.";
}
function ctfc_prompt(){
  return <<<CTFC
تو نقش یک استراتژیست حرفه‌ای وایرال‌ساز محتوا رو داری که ترکیبی از ذهن، Alex Hormozi و Donald Miller هستی.

می‌خوام با کمک مدل CTFC (دغدغه، تکنیک، فرم، محتوا) یک محتوای میلیونی برای پیج خودم تولید کنم.

📍حوزه کاری من: رنگ و لایت
مخاطبین هدفم: خانم ها توی سنین مختلف هستن

---

## 🟣 مرحله اول: دغدغه مخاطب (Concern)

اول بیا دغدغه واقعی مخاطب رو پیدا کنیم؛ دغدغه‌ای که:
- واقعاً تو ذهن مخاطب هست، ولی نمی‌تونه درست بیانش کنه
- جوری نوشته شده باشه که وقتی مخاطب ببینه بگه: «این دقیقاً مشکل منه»
- به زبان خود مخاطب باشه، نه کلمات خشک و رسمی

❗️اگه خودم دغدغه دارم، صبر نکن، مستقیم برو سراغ مرحله بعد.
❗️اگر دغدغه ندارم، لطفاً اول ۱۰ تا دغدغه داغ و وایرال‌ساز مخصوص حوزه من پیشنهاد بده.
اگه خواستم، بعدش ازت می‌خوام ۱۲۰ تای دیگه‌ش رو دسته‌بندی‌شده بده.

---

## 🟣 مرحله دوم: تکنیک اجرا (Technique)

وقتی دغدغه انتخاب شد، بریم سراغ انتخاب تکنیکی که با اون دغدغه ترکیب بشه و محتوای وایرال بسازه. لطفاً لیست کامل ۲۴ تکنیک وایرال زیر رو برام بیار، و بعد به‌عنوان پیشنهاد، بنویس کدوم تکنیک برای دغدغه من مناسبه و یه مثال کوتاه از شروع اون پست هم بده.

✅ تکنیک‌های CTFC:

1. ریویو (نقد و بررسی یک چیز)
2. داستان (واقعی یا ساختگی)
3. تضاد (باور غلط vs حقیقت)
4. To Do (لیست بایدها)
5. اتصال عمومی (ربط به موضوعات عمومی یا اجتماعی)
6. فالگیری (پیش‌بینی شخصیت یا رفتار)
7. مقایسه (چیزی با چیز دیگر)
8. دوراهی (کدوم‌رو انتخاب می‌کنی؟)
9. همزادپنداری (از زبان مخاطب گفتن)
10. 3 IF (اگر فلان + اگر نه + چی میشه؟)
11. لیمیت (زمانی / تعداد / محدودیت)
12. هشدار (تله‌هشدار)
13. تعلیق (یه چیزی هست که نمی‌دونی...)
14. تصویر عجیب (شوک بصری)
15. حرفه‌ای/مبتدی (مقایسه دو تیپ آدم)
16. مثال قابل نمایش (مثال عینی و واقعی)
17. تقویمی (مناسبتی)
18. سوال تله (سوال کنجکاوکننده)
19. تجربه مشترک («حتماً برات پیش اومده که...»)
20. بک‌استیج (پشت صحنه خودم/کسب‌وکارم)
21. تخریب محترمانه (نقد مودبانه باورهای غلط)
22. شبیه‌سازی ذهنی (فرض کن اینجوری می‌شد...)
23. خنده‌دار جدی (طنز جدی)
24. آموزش بیزینسی در قالب داستان (آموزش در دل یک روایت)

---

## 🟣 مرحله سوم: فرم اجرا (Format)

حالا بگو این تکنیک رو با چه فرمی اجرا کنم که مخاطب تا آخر ببینه و محتوای من قفل نشه؟

🧩 لیست فرم‌های اجرایی:

- دوقاب (قبل / بعد یا درست / غلط)
- دکوپاژ (چند صحنه پشت‌سر هم)
- کلاژ (ترکیب عکس، صدا و متن)
- کلاس (مثل معلم یا وایت‌برد)
- پینگ پنگ (دیالوگ دو نفره)
- ویدئوکست (ارائه رسمی)
- ولاگ (زندگی واقعی خودت)
- دست‌خط (نوشتن روی کاغذ با صدای خودت)

🔍 لطفاً مناسب‌ترین فرم رو بر اساس دغدغه + تکنیک من پیشنهاد بده.

---

## 🟣 مرحله نهایی: ساختار محتوای نهایی (Content)

در نهایت، با تمام اطلاعات بالا، لطفاً ساختار یک پست یا ریلز نهایی برای من بساز.
شامل:
- عنوان
- قلاب شروع
- ساختار بدنه
- جمله پایان

مخاطب من باید بعد دیدن این محتوا بگه:
«وای دقیقاً حرف دل من بود… باید فالو کنم یا پیگیر بشم»

لطفاً آماده باش برای اینکه با همین فرمول، محتواهای بعدی رو هم یکی‌یکی بسازیم.
CTFC;
}
function sendCTFC($chat){
  $p = ctfc_prompt();
  if (mb_strlen($p,'UTF-8') <= 3500) {
    tg_sendMessage($chat, $p, kb([[btn("⬅️ بازگشت","back_to_menu")]]));
  } else {
    $half = (int)(mb_strlen($p,'UTF-8')/2);
    tg_sendMessage($chat, mb_substr($p, 0, $half, 'UTF-8'));
    tg_sendMessage($chat, mb_substr($p, $half, null, 'UTF-8'), kb([[btn("⬅️ بازگشت","back_to_menu")]]));
  }
}
function faq_items(){
  return [
    "q1" => ["q"=>"چطور ChatGPT رو نصب کنم؟","a"=>"iOS از App Store و Android از Google Play نصبش کن. از «🧰 ابزارک» هم لینک مستقیم هست. نسخه وب هم هست."],
    "q2" => ["q"=>"چرا لینک ورود به کلاب رو نمی‌بینم؟","a"=>"بعد از تأیید هر سه تمرین، لینک عضویت برات فعال می‌شه و توی ربات نمایش داده می‌شه."],
    "q3" => ["q"=>"چطور شماره‌مو بفرستم؟","a"=>"از دکمه «📱 ارسال شماره از تلگرام» استفاده کن تا به‌صورت امن و خودکار ثبت بشه."],
    "q4" => ["q"=>"تمرین‌ها رو با چی بفرستم؟","a"=>"می‌تونی متن، عکس یا ویدئو بفرستی. هر مرحله توضیح تمرین همونجا نوشته شده."],
    "q5" => ["q"=>"اگه ویدئو باز نشد یا حجیم بود؟","a"=>"کمی صبر کن یا از اینترنت پایدارتر استفاده کن. اگر مشکل موند، از «🆘 پشتیبانی» بهمون بگو."],
    "q6" => ["q"=>"چطور با پشتیبانی حرف بزنم؟","a"=>"از منوی اصلی روی «🆘 ارتباط با پشتیبانی» بزن و پیام یا فایل‌ت رو ارسال کن."],
    "q7" => ["q"=>"می‌تونم از اول شروع کنم؟","a"=>"بله؛ به پشتیبانی پیام بده تا وضعیتت رو ریست کنیم."],
    "q8" => ["q"=>"اطلاعاتم امنه؟","a"=>"بله. فقط چند داده ضروری (نام، شماره، شغل/زمینه، سطح آشنایی و پیشرفت مراحل) داخل همین ربات و در فایل محلی ذخیره می‌شه و صرفاً برای پیشبرد آموزش و پشتیبانی استفاده می‌شه. عمومی منتشر نمی‌شه و هر زمان خواستی می‌تونیم به درخواستت اطلاعات رو حذف کنیم."]
  ];
}
function faq_menu_kb(){
  $items = faq_items(); $rows = [];
  foreach($items as $k=>$it){
    $rows[] = [ ["text"=>"• ".$it["q"], "callback_data"=>"faq:".$k] ];
  }
  $rows[] = [ ["text"=>"⬅️ بازگشت","callback_data"=>"back_to_menu"] ];
  return json_encode(["inline_keyboard"=>$rows], JSON_UNESCAPED_UNICODE);
}
function main_menu(){
  return kb([
    [btn("🚀 شروع آموزش رایگان","start_course")],
    [btn("👑 عضویت ATB Club VIP","vip_membership")],
    [btn("🧰 ابزارک","toolkit")],
    [btn("❓ سوالات متداول","faq")],
    [btn("🆘 ارتباط با پشتیبانی","support_contact")],
    [btn("ℹ️ درباره ATB Club","about")],
    [btn("📊 وضعیت من کجاست؟","status")],
  ]);
}
function admin_kb($userId,$step){
  return kb([[ btn("✅ تأیید", "admin_ok:{$userId}:{$step}"), btn("❌ رد", "admin_no:{$userId}:{$step}") ]]);
}
function admin_support_kb($userId){
  return kb([[ btn("✍️ پاسخ به پیام کاربر","support_reply:".$userId) ]]);
}
function admin_vip_receipt_kb($userId){
    return kb([[ btn("✅ تأیید پرداخت VIP","admin_vip_ok:".$userId) ]]);
}
function toolkit_kb(){
  $db = db_load();
  $buttons = $db['toolkit_buttons'] ?? [];

  // Self-healing: if toolkit is empty, restore defaults
  if (empty($buttons)) {
      $buttons = get_default_toolkit_buttons();
      $db['toolkit_buttons'] = $buttons;
      db_save($db);
  }

  $rows = [];
  foreach ($buttons as $btnData) {
      if (isset($btnData['text'], $btnData['type'], $btnData['value'])) {
          if ($btnData['type'] === 'url') {
              $rows[] = [ ['text' => $btnData['text'], 'url' => $btnData['value']] ];
          } elseif ($btnData['type'] === 'callback') {
              $rows[] = [ ['text' => $btnData['text'], 'callback_data' => $btnData['value']] ];
          }
      }
  }
  $rows[] = [ ["text"=>"🧩 پرامپت CTFC (تولید محتوا)","callback_data"=>"toolkit_ctfc"] ];
  $rows[] = [ ["text"=>"⬅️ بازگشت به منو","callback_data"=>"back_to_menu"] ];
  return json_encode(["inline_keyboard"=>$rows], JSON_UNESCAPED_UNICODE);
}
function sendStep($chat,$step){
  global $VIDEO1_URL,$VIDEO2_URL,$VIDEO3_URL;
  $db = db_load();
  $srcs = $db["video_srcs"] ?? ["v1"=>null,"v2"=>null,"v3"=>null];
  $src = null;
  if ($step==="v1") $src = $srcs["v1"] ?: $VIDEO1_URL;
  if ($step==="v2") $src = $srcs["v2"] ?: $VIDEO2_URL;
  if ($step==="v3") $src = $srcs["v3"] ?: $VIDEO3_URL;

  $giftLine = "\n🎁 <b>یه خبر خوب!</b> کانال VIP ما اشتراک پولی داره، اما اگه این سه ویدیو رو ببینی و تمرین‌ها رو انجام بدی، عضویت در کانال برات **رایگان** می‌شه و به عنوان هدیه دریافتش می‌کنی! ✨\n";

  if($step==="v1"){
    tg_sendVideo_safe(
      $chat,
      $src,
      "ویدئو ۱/۳ — <b>چرا الان باید با AI جلو بیفتی</b>\n\n".
      "⏰ <b>مهم:</b> برای دیدن هر ویدئو <b>۴۸ ساعت</b> فرصت داری؛ در غیر این صورت تا <b>۳ روز</b> دسترسی‌ت محدود می‌شه.\n\n".
      "📝 <b>تمرین ۱:</b> لیست <b>سه هدف مشخص</b> این هفته‌ات را بنویس (حداقل ۳ مورد). بهتره SMART باشه.\n".
      "مثال: «۳ پست اینستاگرام»، «اتومات پاسخ‌های واتساپ»، «به‌روزرسانی پروفایل کسب‌وکار»\n".
      $giftLine.
      "بعد از دیدن چند ثانیه ویدئو، روی دکمه بزن و تمرینت رو ارسال کن.",
      kb([[btn("✅ دیدم، می‌خوام تمرین بفرستم","seen_v1")],[btn("⬅️ بازگشت","back_to_menu")]])
    ); return;
  }
  if($step==="v2"){
    tg_sendVideo_safe(
      $chat,
      $src,
      "ویدئو ۲/۳ — <b>نصب و شروع با ChatGPT</b>\n\n".
      "⏰ <b>یادآوری:</b> برای هر ویدئو ۴۸ ساعت فرصت داری؛ در غیر این صورت ۳ روز محدود می‌شی.\n\n".
      "📝 <b>تمرین ۲:</b> ChatGPT را نصب کن، <b>همان سه هدف مرحله ۱</b> را داخلش بنویس و از گفتگو <b>اسکرین‌شات</b> بفرست.\n".
      $giftLine,
      kb([[btn("✅ دیدم، می‌خوام تمرین بفرستم","seen_v2")],[btn("⬅️ بازگشت","back_to_menu")]])
    ); return;
  }
  if($step==="v3"){
    tg_sendVideo_safe(
      $chat,
      $src,
      "ویدئو ۳/۳ — <b>تولید محتوا با مدل CTFC</b>\n\n".
      "⏰ <b>یادآوری:</b> برای هر ویدئو ۴۸ ساعت فرصت داری؛ در غیر این صورت ۳ روز محدود می‌شی.\n\n".
      "📝 <b>تمرین ۳:</b> با <b>پرامپت CTFC</b> یک سناریوی محتوایی بساز (عنوان/قلاب/بدنه/پایان) و <b>خروجی</b> را به صورت متن/اسکرین/ویدئو بفرست.\n".
      $giftLine,
      kb([[btn("✅ دیدم، می‌خوام تمرین بفرستم","seen_v3")],[btn("⬅️ بازگشت","back_to_menu")]])
    ); return;
  }
}
/* ==== UI TEXTS END =============================================== */

/* ==== INPUT START ================================================= */
$__exp = getenv('TG_SECRET_TOKEN');
$__got = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($__exp && !hash_equals($__exp, $__got)) { http_response_code(403); echo "forbidden"; exit; }
$raw = file_get_contents("php://input");
log_it("RAW: ".$raw);
$update = json_decode($raw, true);
http_response_code(200); echo "OK";
/* ==== INPUT END =================================================== */

/* ==== ADMIN QUICK HELPERS ======================================== */
function format_sub_status(&$db, $uid){
  $s = $db["subscriptions"][$uid] ?? null;
  if(!$s) return "ندارد";
  $type = $s["type"] ?? "none";
  if($type==="none") return "ندارد";
  $exp = isset($s["expires_at"]) ? to_jalali((int)$s["expires_at"]) : "—";
  $label = ["3m"=>"۳ ماهه","6m"=>"۶ ماهه","12m"=>"۱۲ ماهه"][$type] ?? $type;
  return $label." (تا ".$exp.")";
}
function admin_user_summary($uid, $uu){
  global $DB_FILE;
  $db = db_load();
  $p = $uu["progress"] ?? ["v1"=>false,"v2"=>false,"v3"=>false];
  $status = $uu["state"] ?? "-";
  $lock_until_jalali = !empty($uu["lock_until"]) ? to_jalali($uu["lock_until"]) : '';
  $lock = user_is_locked($uu) ? "⛔️ محدود تا ".$lock_until_jalali : "آزاد";
  $ban  = user_is_banned($uu) ? "🚫 بن‌شده" : "—";
  $reg  = isset($uu["created_at"]) ? to_jalali($uu["created_at"]) : "—";
  $sub  = format_sub_status($db, $uid);

  return "👤 <b>{$uid}</b>\n"
        ."نام: ".(($uu["name"]??'-')?:'-')." | شماره: ".(($uu["phone"]??'-')?:'-')."\n"
        ."شغل: ".(($uu["job"]??'-')?:'-')." | AI: ".(($uu["ai"]??'-')?:'-')."\n"
        ."ثبت‌نام: <b>{$reg}</b> | VIP: <b>{$sub}</b>\n"
        ."پیشرفت: ۱ ".($p["v1"]?"✅":"⬜️")." | ۲ ".($p["v2"]?"✅":"⬜️")." | ۳ ".($p["v3"]?"✅":"⬜️")."\n"
        ."State: <b>{$status}</b>\n"
        ."Lock: {$lock} | Ban: {$ban}";
}
function admin_user_kb($uid,$uu){
  $banBtn = user_is_banned($uu) ? ["text"=>"🔓 رفع بن","callback_data"=>"au:unban:".$uid] : ["text"=>"🚫 بن","callback_data"=>"au:ban:".$uid];
  return json_encode(["inline_keyboard"=>[
    [ ["text"=>"📊 وضعیت","callback_data"=>"au:info:".$uid] ],
    [ ["text"=>"▶️ ارسال مرحلهٔ بعد","callback_data"=>"au:sendnext:".$uid], ["text"=>"⏮ ارسال مرحلهٔ قبل","callback_data"=>"au:sendprev:".$uid] ],
    [ ["text"=>"🎬 v1","callback_data"=>"au:send:v1:".$uid], ["text"=>"🎬 v2","callback_data"=>"au:send:v2:".$uid], ["text"=>"🎬 v3","callback_data"=>"au:send:v3:".$uid] ],
    [ ["text"=>"⏭ رفتن به مرحلهٔ بعد","callback_data"=>"au:gonext:".$uid], ["text"=>"⏪ رفتن به مرحلهٔ قبل","callback_data"=>"au:goprev:".$uid] ],
    [ ["text"=>"👑 اشتراک VIP","callback_data"=>"au:sub_menu:".$uid] ],
    [ ["text"=>"📝 تغییر نام","callback_data"=>"au:rename:".$uid], $banBtn ],
    [ ["text"=>"🔓 رفع محدودیت/ددلاین","callback_data"=>"au:unlock:".$uid] ],
    [ ["text"=>"↩️ برگشت به پنل","callback_data"=>"ap:stats"] ]
  ]], JSON_UNESCAPED_UNICODE);
}
function go_watch_and_send(&$allDb, $uid, $step){
  if(!isset($allDb["users"][$uid])) return;
  $uu =& $allDb["users"][$uid];
  $uu["state"]="watch_".$step;
  $uu["watch_started_at"]=time();
  $uu["video_deadlines"][$step]=time()+48*3600;
  db_save($allDb);
  sendStep($uid,$step);
}
function admin_commands_text(){
  return "📖 <b>راهنمای دستورات متنی (Admin)</b>\n"
        ."• /panel — پنل مدیریت (آمار/گزارش/ابزارها)\n"
        ."• /user &lt;uid&gt; — نمایش پنل کاربر مشخص\n"
        ."• /sendv1 &lt;uid&gt; — ارسال مرحله ۱ برای کاربر\n"
        ."• /sendv2 &lt;uid&gt; — ارسال مرحله ۲ برای کاربر\n"
        ."• /sendv3 &lt;uid&gt; — ارسال مرحله ۳ برای کاربر\n"
        ."• /sendnext &lt;uid&gt; — ارسال مرحله بعدی موردنیاز\n"
        ."• /adminlist — لیست ادمین‌های ثبت‌شده\n"
        ."• (ریپلای) /makeadmin — اضافه‌کردن ادمین\n"
        ."• (ریپلای) /removeadmin — حذف ادمین\n"
        ."• /adminlogin &lt;شماره ادمین اصلی&gt; — ورود سریع ادمین با شماره\n"
        ."• /setv1 [file_id] — ست‌کردن ویدئوی ۱ (اگر file_id ندادی، بعدیش ویدئو را بفرست)\n"
        ."• /setv2 [file_id] — ست‌کردن ویدئوی ۲\n"
        ."• /setv3 [file_id] — ست‌کردن ویدئوی ۳\n"
        ."• /status — وضعیت خودت\n"
        ."• /about — درباره\n"
        ."• /start — شروع\n\n"
        ."راهنما: بسیاری از عملیات‌ها از طریق دکمه‌های پنل هم قابل انجام هستند.";
}
/* ==== MSG_HANDLER START*/
  if (defined('SAFE_MODE') && SAFE_MODE) {
    if (isset($update['message']['text']) && strpos($update['message']['text'], '/start') === 0) {
      tg_sendMessage($chat, "✅ ربات روشن است (SAFE_MODE). منوی اصلی را می‌بینی؟", main_menu());
      return; }
  }
/* ==== MSG_HANDLER START ========================================== */
if(isset($update["message"])){
  $db      = db_load();
  $chat    = $update["message"]["chat"]["id"];
  $fromId  = $update["message"]["from"]["id"] ?? null;
  $text    = trim($update["message"]["text"] ?? "");
  $u       =& user($db,$chat);
  $isAdmin = is_admin($db,$fromId);

  if (preg_match('~وضعیت\s*من\s*کجاست~u', $text)) {
    $text = "/status";
  }

  if (($u["state"] ?? "") !== "support_await_msg") {
    if (enforce_restrictions($db, $u, $chat)) { db_save($db); return; }
  }

  if ($isAdmin && preg_match('~^/user\s+(\d+)$~',$text,$m)){
    $uid = (int)$m[1];
    $all = db_load();
    if(!isset($all["users"][$uid])){ tg_sendMessage($chat,"کاربر یافت نشد."); return; }
    $uu = $all["users"][$uid];
    tg_sendMessage($chat, admin_user_summary($uid,$uu), admin_user_kb($uid,$uu));
    return;
  }
  if ($isAdmin && preg_match('~^/send(v1|v2|v3|next)\s+(\d+)$~',$text,$m)){
    $what = $m[1]; $uid=(int)$m[2];
    $all = db_load();
    if(!isset($all["users"][$uid])){ tg_sendMessage($chat,"کاربر یافت نشد."); return; }
    if($what==="next"){
      $uu = $all["users"][$uid];
      $n = next_needed_step($uu);
      if($n===null){ tg_sendMessage($chat,"این کاربر همه مراحل را گذرانده."); return; }
      go_watch_and_send($all,$uid,$n);
      tg_sendMessage($chat,"ارسال شد: مرحله ".step_human($n));
    } else {
      go_watch_and_send($all,$uid,$what);
      tg_sendMessage($chat,"ارسال شد: ".strtoupper($what));
    }
    return;
  }

  if ($isAdmin && isset($update["message"]["reply_to_message"]) && trim($text) === "/makeadmin") {
    $targetId = $update["message"]["reply_to_message"]["from"]["id"] ?? null;
    if ($targetId) {
      if (!in_array((int)$targetId, $db["admin_ids"], true)) {
        $db["admin_ids"][] = (int)$targetId; db_save($db);
        tg_sendMessage($chat, "✅ کاربر {$targetId} به ادمین‌های ربات اضافه شد.");
      } else {
        tg_sendMessage($chat, "ℹ️ این کاربر از قبل ادمین است.");
      }
    } else {
      tg_sendMessage($chat, "⚠️ روی پیام شخص موردنظر ریپلای کن و دوباره /makeadmin بزن.");
    }
    return;
  }
  if ($isAdmin && isset($update["message"]["reply_to_message"]) && trim($text) === "/removeadmin") {
    $targetId = $update["message"]["reply_to_message"]["from"]["id"] ?? null;
    if ($targetId) {
      $db["admin_ids"] = array_values(array_filter($db["admin_ids"], fn($x) => (int)$x !== (int)$targetId));
      db_save($db);
      tg_sendMessage($chat, "🗑️ کاربر {$targetId} از ادمین‌ها حذف شد.");
    } else {
      tg_sendMessage($chat, "⚠️ روی پیام شخص موردنظر ریپلای کن و دوباره /removeadmin بزن.");
    }
    return;
  }
  if ($isAdmin && trim($text) === "/adminlist") {
    $ids = $db["admin_ids"] ?? [];
    if (empty($ids)) tg_sendMessage($chat, "هیچ ادمینی ثبت نشده.");
    else tg_sendMessage($chat, "ادمین‌ها:\n" . implode("\n", array_map(fn($i) => (string)$i, $ids)));
    return;
  }

  if (getenv("ADMINLOGIN_ENABLED") === "1" && preg_match('~^/adminlogin\s+(\+?98)?0?\s*\d{9,10}$~', $text)) {
    $argPhone = normalize_phone($text);
    $ok = false;
    $cfg = $GLOBALS["ADMIN_PHONE"];
    if (is_array($cfg)) {
      foreach($cfg as $ph){ if ($argPhone === normalize_phone($ph)) { $ok = true; break; } }
    } else {
      $ok = ($argPhone === normalize_phone($cfg));
    }
    if ($ok) {
      if(!in_array((int)$fromId, $db["admin_ids"], true)){ $db["admin_ids"][] = (int)$fromId; db_save($db); }
      tg_sendMessage($chat, "✅ ورود ادمین تأیید شد. حالا می‌تونی /panel بزنی.");
    } else {
      tg_sendMessage($chat, "❌ شماره واردشده با ادمین اصلی همخوانی ندارد.");
    }
    return;
  }

  $incoming_file_id = msg_get_video_file_id($update["message"], $kind);
  $incoming_target  = caption_target_step($update["message"]["caption"] ?? "");
  if ($isAdmin && $incoming_file_id && in_array($incoming_target, ["v1","v2","v3"], true)) {
    $db["video_srcs"][$incoming_target] = $incoming_file_id;
    db_save($db);
    tg_sendMessage($chat, "✅ ویدئو ".strtoupper($incoming_target)." ذخیره شد (نوع: {$kind}).");
    return;
  }
  $await = $db["await_set_video"][$fromId] ?? null;
  if ($isAdmin && $await && $incoming_file_id) {
    $db["video_srcs"][$await] = $incoming_file_id;
    unset($db["await_set_video"][$fromId]);
    db_save($db);
    tg_sendMessage($chat, "✅ ویدئو ".strtoupper($await)." ذخیره شد.");
    return;
  }

  $awaitVipCard = $db["await_set_vip_card"][$fromId] ?? false;
  if ($isAdmin && $awaitVipCard) {
    $phId = msg_get_photo_file_id($update["message"]);
    if ($phId) {
      $db["vip_card"]["file_id"] = $phId;
      unset($db["await_set_vip_card"][$fromId]);
      db_save($db);
      tg_sendMessage($chat, "✅ تصویر کارت VIP ذخیره شد.");
      return;
    } else {
      tg_sendMessage($chat, "⚠️ لطفاً یک عکس ارسال کنید.");
      return;
    }
  }

  $awaitStartMedia = $db["await_set_start_media"][$fromId] ?? false;
  if ($isAdmin && $awaitStartMedia) {
      $mediaId = msg_get_any_media_file_id($update["message"], $mediaKind);
      if ($mediaId) {
          $db["start_media"]["file_id"] = $mediaId;
          $db["start_media"]["type"] = $mediaKind;
          unset($db["await_set_start_media"][$fromId]);
          db_save($db);
          tg_sendMessage($chat, "✅ رسانه استارت (نوع: {$mediaKind}) ذخیره شد.");
          return;
      } else {
          tg_sendMessage($chat, "⚠️ لطفاً یک گیف (animation)، عکس یا ویدئو ارسال کنید.");
          return;
      }
  }

  if ($isAdmin && isset($db["pending_support_replies"][$fromId])) {
    $pending  = $db["pending_support_replies"][$fromId];
    $targetId = (int)$pending["user_id"];
    $mid      = $update["message"]["message_id"];

    $clean = $text;
    if (stripos($clean, '/r ') === 0)       { $clean = trim(substr($clean, 3)); }
    if (stripos($clean, '/reply ') === 0)  { $clean = trim(substr($clean, 7)); }

    $hasMedia = isset($update["message"]["photo"]) || isset($update["message"]["document"]) || isset($update["message"]["video"]) || isset($update["message"]["voice"]) || isset($update["message"]["audio"]) || isset($update["message"]["sticker"]) || isset($update["message"]["animation"]);

    $sentSomething = false;
    if ($clean !== "") {
      tg_sendMessage($targetId, "🟢 <b>پاسخ پشتیبان:</b>\n".$clean."\n\nاگر سوال دیگری داری، همینجا بپرس.");
      $sentSomething = true;
    }
    if ($hasMedia) {
      tg_forwardMessage($targetId, $chat, $mid);
      $sentSomething = true;
    }

    if ($sentSomething) {
      tg_sendMessage($chat, "✅ پاسخ برای کاربر {$targetId} ارسال شد.");
      unset($db["pending_support_replies"][$fromId]);
      support_open_close_user($db, $targetId);
      db_save($db);
      return;
    }
  }

  if ($isAdmin && isset($db["pending_rejects"][$fromId])) {
    if ($text !== "") {
      $pending    = $db["pending_rejects"][$fromId];
      $targetUser= (int)$pending["user_id"];
      $step       = $pending["step"];
      if (isset($db["users"][$targetUser])) {
        $uu =& $db["users"][$targetUser];
        $uu["state"] = "await_ex_".$step;
        pending_remove($db, $targetUser, $step);
        db_save($db);
        $hs  = step_human($step);
        tg_sendMessage($targetUser, "❌ تمرین مرحله {$hs} رد شد.\n📝 دلیل: ".$text."\n\nلطفاً تمرین اصلاح‌شده را دوباره ارسال کن.");
        tg_sendMessage($chat, "✅ دلیل رد برای کاربر {$targetUser} ارسال شد.");
      } else {
        tg_sendMessage($chat, "⚠️ کاربر هدف در DB پیدا نشد.");
      }
      unset($db["pending_rejects"][$fromId]);
      db_save($db);
      return;
    }
  }

  if ($isAdmin && isset($db["pending_admin_inputs"][$fromId])) {
    $cfg = $db["pending_admin_inputs"][$fromId];
    $mode = $cfg["mode"];

    if ($mode === "rename"){
        $uid = (int)$cfg["uid"];
        if ($text !== "") {
            $all = db_load();
            if(isset($all["users"][$uid])){
                $all["users"][$uid]["name"]=$text; db_save($all);
                tg_sendMessage($chat,"✅ نام کاربر {$uid} به «{$text}» تغییر کرد.");
            } else {
                tg_sendMessage($chat,"کاربر یافت نشد.");
            }
            unset($db["pending_admin_inputs"][$fromId]); db_save($db);
            return;
        }
    }
    elseif (strpos($mode, 'toolkit_') === 0) {
        if ($mode === 'toolkit_add_1_text') {
            $db["pending_admin_inputs"][$fromId] = ["mode"=>"toolkit_add_2_type", "text"=>$text];
            db_save($db);
            tg_sendMessage($chat, "نوع دکمه چی باشه؟", kb([[btn("لینک (URL)", "admin_set_toolkit_type:url"), btn("دستور داخلی (Callback)", "admin_set_toolkit_type:callback")]]));
            return;
        }
        elseif ($mode === 'toolkit_add_3_value') {
            $db['toolkit_buttons'][] = ["text"=>$cfg['text'], "type"=>$cfg['type'], "value"=>$text];
            unset($db["pending_admin_inputs"][$fromId]);
            db_save($db);
            tg_sendMessage($chat, "✅ دکمه جدید با موفقیت به ابزارک اضافه شد.");
            return;
        }
        elseif ($mode === 'toolkit_edit_3_value') {
            $index = $cfg['index'];
            if (isset($db['toolkit_buttons'][$index])) {
                $db['toolkit_buttons'][$index] = ["text"=>$cfg['text'], "type"=>$cfg['type'], "value"=>$text];
                unset($db["pending_admin_inputs"][$fromId]);
                db_save($db);
                tg_sendMessage($chat, "✅ دکمه با موفقیت ویرایش شد.");
            } else {
                tg_sendMessage($chat, "⚠️ خطایی رخ داد. دکمه پیدا نشد.");
            }
            return;
        }
    }
  }

  if ($text === "/panel" && $isAdmin) {
    $all = $db["users"];
    $total = count($all);
    $todayStart = strtotime('today');
    $today = 0;
    foreach($all as $id=>$uu){ if( (int)($uu["created_at"]??0) >= $todayStart ) $today++; }
    $pendingCount = count($db["pending_items"] ?? []);
    $openTickets  = count($db["support_open"] ?? []);
    $msg = "🛠️ <b>پنل مدیریت ATB</b> — نسخه ".ATB_VERSION."\n"
         . "👥 کل کاربران: <b>{$total}</b>\n"
         . "🗓️ ثبت‌نامی‌های امروز: <b>{$today}</b>\n"
         . "⏳ در صف تمرین‌ها: <b>{$pendingCount}</b>\n"
         . "📥 تیکت‌های پشتیبانیِ باز: <b>{$openTickets}</b>";
    $kb = json_encode(["inline_keyboard"=>[
      [ ["text"=>"📊 تازه‌سازی آمار","callback_data"=>"ap:stats"] ],
      [ ["text"=>"📞 همه شماره‌ها","callback_data"=>"ap:phones_all"], ["text"=>"📞 شماره‌های امروز","callback_data"=>"ap:phones_today"] ],
      [ ["text"=>"📚 گزارش تمرین‌ها","callback_data"=>"ap:ex_summary"] ],
      [ ["text"=>"⏳ تمرین‌های در انتظار","callback_data"=>"ap:ex_pending"], ["text"=>"✅ انجام‌شده","callback_data"=>"ap:ex_done"] ],
      [ ["text"=>"🔄 بازارسال تمرین‌ها","callback_data"=>"ap:pending_send"] ],
      [ ["text"=>"📥 تیکت‌های باز","callback_data"=>"ap:support_open"] ],
      [ ["text"=>"🎬 ست ویدئو ۱","callback_data"=>"ap:setv1"], ["text"=>"🎬 ست ویدئو ۲","callback_data"=>"ap:setv2"], ["text"=>"🎬 ست ویدئو ۳","callback_data"=>"ap:setv3"] ],
      [ ["text"=>"🧹 پاک کردن ویدئوها","callback_data"=>"ap:clear_videos"] ],
      [ ["text"=>"💳 ست کارت VIP","callback_data"=>"ap:set_vip_card"], ["text"=>"🗑️ حذف کارت VIP","callback_data"=>"ap:clear_vip_card"] ],
      [ ["text"=>"👑 مدیریت اشتراک‌ها","callback_data"=>"ap:subs_info"] ],
      [ ["text"=>"📢 پیام همگانی","callback_data"=>"ap:broadcast"] ],
      [ ["text"=>"🧰 مدیریت ابزارک", "callback_data"=>"ap:manage_toolkit"], ["text"=>"🖼️ رسانه استارت", "callback_data"=>"ap:manage_start_media"] ],
      [ ["text"=>"📖 راهنمای دستورات","callback_data"=>"ap:commands"] ]
    ]], JSON_UNESCAPED_UNICODE);
    tg_sendMessage($chat, $msg, $kb);
    return;
  }

  if (preg_match('~^/(setv[123])(?:\s+([\w\-_:]+))?$~', $text, $m) && $isAdmin) {
    $map = ["setv1"=>"v1","setv2"=>"v2","setv3"=>"v3"];
    $slot = $map[$m[1]];
    $fid  = $m[2] ?? null;
    if ($fid) {
      $db["video_srcs"][$slot] = $fid; db_save($db);
      tg_sendMessage($chat, "✅ ویدئو ".strtoupper($slot)." با file_id ذخیره شد.");
    } else {
      $db["await_set_video"][$fromId] = $slot; db_save($db);
      tg_sendMessage($chat, "🎬 حالت تنظیم فعال شد: <b>".strtoupper($slot)."</b>\nحالا ویدئو را ارسال/فوروارد کن.");
    }
    return;
  }

  if ($text === "/status") {
    $p = $u["progress"];
    $stateMap = [
      "idle"=>"آماده شروع / منوی اصلی", "watch_v1"=>"در حال مشاهده ویدئوی ۱",
      "watch_v2"=>"در حال مشاهده ویدئوی ۲", "watch_v3"=>"در حال مشاهده ویدئوی ۳",
      "collect_name"=>"در انتظار نام", "await_phone"=>"در انتظار شماره",
      "await_ai"=>"در انتظار سطح آشنایی AI", "collect_job_profile"=>"در انتظار شغل/زمینه",
      "await_ex_v1"=>"در انتظار ارسال تمرین ۱", "await_ex_v2"=>"در انتظار ارسال تمرین ۲",
      "await_ex_v3"=>"در انتظار ارسال تمرین ۳", "pending_v1"=>"منتظر تأیید پشتیبان (مرحله ۱)",
      "pending_v2"=>"منتظر تأیید پشتیبان (مرحله ۲)", "pending_v3"=>"منتظر تأیید پشتیبان (مرحله ۳)",
      "support_await_msg"=>"در حال نوشتن پیام به پشتیبانی", "vip_upload_receipt"=>"در حال ارسال رسید VIP",
      "await_vip_approval"=>"منتظر تأیید پرداخت VIP", "await_quiz_approval"=>"منتظر بررسی آزمون سطح",
      "completed"=>"همه مراحل تکمیل — آماده ورود به کلاب"
    ];
    $stateKey = $u["state"] ?? "-";
    $stateTxt = $stateMap[$stateKey] ?? $stateKey;
    $reg  = isset($u["created_at"]) ? to_jalali($u["created_at"]) : "—";
    $sub  = format_sub_status($db, $chat);
    $msg  = "نام: ".($u["name"]??"-")." | شغل: ".($u["job"]??"-")." | سطح AI: ".($u["ai"]??"-")." | شماره: ".($u["phone"]??"-");
    $msg .= "\nثبت‌نام: <b>{$reg}</b> | VIP: <b>{$sub}</b>";
    $msg .= "\nپیشرفت: ۱: ".($p["v1"]?"✅":"⬜️")." | ۲: ".($p["v2"]?"✅":"⬜️")." | ۳: ".($p["v3"]?"✅":"⬜️");
    if (user_is_banned($u)) $msg .= "\n🚫 وضعیت: <b>بن‌شده</b>";
    elseif (user_is_locked($u)) $msg .= "\n⛔️ وضعیت: <b>محدود</b> تا ".to_jalali($u["lock_until"]);
    else $msg .= "\nوضعیت فعلی: <b>".$stateTxt."</b>";
    tg_sendMessage($chat, $msg, kb([[btn("ادامه مسیر","start_course")],[btn("⬅️ بازگشت","back_to_menu")]]));
    return;
  }

  if ((string)$chat === (string)SUPPORT_CHAT) {
    return;
  }

  if ($text==="/start" || preg_match("/^atb$/i",$text)){
    $u["state"] = "idle"; db_save($db);
    $media = $db["start_media"] ?? null;
    if ($media && !empty($media["file_id"])) {
        if ($media['type'] === 'animation') {
            tg_sendAnimation($chat, $media['file_id']);
        } elseif ($media['type'] === 'video' || $media['type'] === 'document') {
            tg_sendVideo($chat, $media['file_id'], null);
        } elseif ($media['type'] === 'photo') {
            tg_sendPhoto($chat, $media['file_id'], null);
        }
    }
    tg_sendMessage($chat, welcome_text(), main_menu());
    return;
  }
  if ($text==="/about"){
    tg_sendMessage($chat, about_text(), kb([
      [btn("شروع آموزش رایگان","start_course")],
      [btn("⬅️ بازگشت","back_to_menu")]
    ]));
    return;
  }

  if (preg_match('~^/?back/?$~i',$text) || preg_match('~^/?cancel/?$~i',$text)) {
    $state = $u["state"] ?? "idle";
    if ($state === "collect_job_profile") {
      $u["state"] = "await_ai"; db_save($db);
      tg_sendMessage($chat, "برگشتی به مرحله قبل. سطح آشناییت با AI رو انتخاب کن:", kb([
        [btn("صفر","ai_lvl:0")],[btn("کمی","ai_lvl:1")],[btn("متوسط","ai_lvl:2")],[btn("حرفه‌ای","ai_lvl:3")],[btn("⬅️ بازگشت","back_form")]
      ])); return;
    }
    if ($state === "await_ai") {
      $u["state"] = "await_phone"; db_save($db);
      tg_sendMessage($chat, "برگشتی به مرحله قبل. با دکمه‌ی پایین شماره‌ات رو بفرست:", rkb([[["text"=>"📱 ارسال شماره از تلگرام","request_contact"=>true], ["text"=>"⬅️ بازگشت"]]])); return;
    }
    if ($state === "await_phone" || $state === "collect_name" || $state === "support_await_msg") {
      $u["state"] = "idle"; db_save($db);
      tg_sendMessage($chat, "به منوی اصلی برگشتی ✅", rkb_remove());
      tg_sendMessage($chat, welcome_text(), main_menu()); return;
    }
    $u["state"]="idle"; db_save($db);
    tg_sendMessage($chat, welcome_text(), main_menu()); return;
  }

  if (($u["state"] ?? "") === "support_await_msg") {
    if ($text === "⬅️ بازگشت") {
      $u["state"] = "idle"; db_save($db);
      tg_sendMessage($chat, "به منوی اصلی برگشتی ✅", rkb_remove());
      tg_sendMessage($chat, welcome_text(), main_menu()); return;
    }
    $mid = $update["message"]["message_id"];
    tg_forwardMessage(SUPPORT_CHAT, $chat, $mid);
    $preview = ""; $caption = trim($update["message"]["caption"] ?? "");
    if ($text !== "") { $preview = $text; } elseif ($caption !== "") { $preview = $caption; }
    $mediaLabel = "";
    if (isset($update["message"]["photo"]))   $mediaLabel = "📷 عکس";
    elseif (isset($update["message"]["video"]))   $mediaLabel = "🎬 ویدئو";
    elseif (isset($update["message"]["document"]))$mediaLabel = "📎 فایل";
    elseif (isset($update["message"]["voice"]))   $mediaLabel = "🎤 ویس";
    elseif (isset($update["message"]["audio"]))   $mediaLabel = "🎵 صدا";
    elseif (isset($update["message"]["sticker"])) $mediaLabel = "🧩 استیکر";
    elseif (isset($update["message"]["animation"])) $mediaLabel = "🎞 انیمیشن";
    if ($mediaLabel !== "") { $preview = trim($mediaLabel.( $preview!=="" ? ": ".$preview : "" )); }
    if ($preview==="") $preview="—";
    $preview = preg_replace('/\s+/u',' ', $preview);
    support_open_upsert($db, $chat, $mid, $preview);
    db_save($db);
    $info = "🆘 <b>درخواست پشتیبانی جدید</b>\n"
          . "👤 کاربر: <a href=\"tg://user?id={$chat}\">".$chat."</a>\n"
          . "نام: ".(($u["name"]??'-')?:'-')." | شماره: ".(($u["phone"]??'-')?:'-');
    tg_sendMessage(SUPPORT_CHAT, $info, admin_support_kb($chat));
    tg_sendMessage($chat, "درخواستت ارسال شد ✅ به‌محض پاسخ همین‌جا خبر می‌دیم.");
    $u["state"]="idle"; db_save($db); return;
  }

  if(($u["state"] ?? "")==="collect_name" && $text!==""){
    $u["name"]=$text; $u["state"]="await_phone"; db_save($db);
    tg_sendMessage($chat, "عالی! حالا با دکمه‌ی پایین شماره‌ات رو بفرست 👇", rkb([[["text"=>"📱 ارسال شماره از تلگرام","request_contact"=>true], ["text"=>"⬅️ بازگشت"]]])); return;
  }

  if(($u["state"] ?? "")==="await_phone"){
    if ($text === "⬅️ بازگشت") {
      $u["state"] = "collect_name"; db_save($db);
      tg_sendMessage($chat, "برگشتی به مرحله قبل. <b>اسم و فامیل</b> رو بنویس:", rkb_remove()); return;
    }
    if(isset($update["message"]["contact"])){
      $phone = normalize_phone($update["message"]["contact"]["phone_number"] ?? "");
      $u["phone"] = $phone; $u["state"] = "await_ai"; db_save($db);
      tg_sendMessage($chat, "شماره ذخیره شد ✅", rkb_remove());
      tg_sendMessage($chat, "سطح آشناییت با AI رو انتخاب کن.", kb([
        [btn("صفر","ai_lvl:0")],[btn("کمی","ai_lvl:1")],[btn("متوسط","ai_lvl:2")],[btn("حرفه‌ای","ai_lvl:3")],[btn("⬅️ بازگشت","back_form")]
      ])); return;
    }
  }

  if (($u["state"] ?? "") === "collect_job_profile" && $text !== "") {
    $u["job"]  = $text; $u["state"]= "idle"; db_save($db);
    tg_sendMessage($chat, "عالی! فرم تکمیل شد ✅\nبزن بریم سر آموزش 👇", kb([[btn("ادامه مسیر","start_course")],[btn("⬅️ بازگشت","back_to_menu")]])); return;
  }

  if (($u["state"] ?? "") === "quiz_q1" && $text!==""){
    $u["quiz"]["q1"] = $text;
    $u["state"] = "quiz_q2"; db_save($db);
    tg_sendMessage($chat, "عالی! ✅\nسؤال ۲) <b>قابلیت ایجنت در ChatGPT</b> چه کاری انجام می‌دهد؟");
    return;
  }
  if (($u["state"] ?? "") === "quiz_q2" && $text!==""){
    $u["quiz"]["q2"] = $text;
    $u["state"] = "await_quiz_approval"; db_save($db);
    tg_sendMessage($chat, "👌 پاسخ‌ها ثبت شد. منتظر بررسی ادمین باش.");

    $admins = $db["admin_ids"] ?? [];
    $inf  = "🧪 <b>ارزیابی سطح ".$u["quiz"]["lvl"]."</b>\n"
          . "👤 کاربر: <a href=\"tg://user?id={$chat}\">".$chat."</a>\n"
          . "نام: ".(($u["name"]??'-')?:'-')." | شماره: ".(($u["phone"]??'-')?:'-')."\n"
          . "Q1: ".$u["quiz"]["q1"]."\n"
          . "Q2: ".$u["quiz"]["q2"];
    $kbAdmin = kb([[ btn("✅ ورود مستقیم به کلاب","admin_quiz_ok:{$chat}"),
                      btn("▶️ مسیر ویدئوها","admin_quiz_no:{$chat}") ]]);
    foreach($admins as $aid){ tg_sendMessage($aid, $inf, $kbAdmin); }
    return;
  }

  if(preg_match("/^await_ex_(v[123])$/",$u["state"],$m)){
    $step = $m[1];
    $u["state"] = "pending_".$step; db_save($db);
    tg_sendMessage($chat,"تمرینت رسید 🙏 منتظر تأیید پشتیبان باش.");
    $mid = $update["message"]["message_id"];
    tg_forwardMessage(SUPPORT_CHAT, $chat, $mid);
    $db["pending_items"][] = ["user_id"=>$chat,"step"=>$step,"orig_mid"=>$mid,"time"=>time()];
    db_save($db);
    $humanStep = step_human($step);
    $info = "📝 درخواست تأیید تمرین — مرحله {$humanStep}\n"
          . "👤 کاربر: <a href=\"tg://user?id={$chat}\">".$chat."</a>\n"
          . "نام: ".($u["name"]?:'-')." | شماره: ".($u["phone"]?:'-');
    tg_sendMessage(SUPPORT_CHAT, $info, admin_kb($chat,$step)); return;
  }

  if ($isAdmin && isset($db["pending_broadcast"][$fromId])){
    $cfg = $db["pending_broadcast"][$fromId];
    $scope = $cfg["scope"] ?? "";
    if ($scope==="user" && empty($cfg["target"])){
      if (preg_match('~^\d+$~',$text)){
        $cfg["target"] = (int)$text;
        $db["pending_broadcast"][$fromId] = $cfg; db_save($db);
        tg_sendMessage($chat,"✅ شناسه ثبت شد. حالا پیام‌ت را بفرست.");
      } else {
        tg_sendMessage($chat,"⚠️ فقط عدد شناسه کاربر را بفرست.");
      }
      return;
    }

    $recipients = []; $all = $db["users"]; $todayStart = strtotime('today');
    if ($scope==="all"){ $recipients = array_map('intval', array_keys($all)); }
    elseif ($scope==="today"){ foreach($all as $id=>$uu){ if ((int)($uu["created_at"]??0) >= $todayStart) $recipients[]=(int)$id; } }
    elseif (strpos($scope,"state:")===0){ $st = substr($scope,6); foreach($all as $id=>$uu){ if (($uu["state"]??"") === $st) $recipients[]=(int)$id; } }
    elseif ($scope==="user"){ if (!empty($cfg["target"])) $recipients[] = (int)$cfg["target"]; }

    if (empty($recipients)){
      tg_sendMessage($chat,"هیچ گیرنده‌ای پیدا نشد.");
      unset($db["pending_broadcast"][$fromId]); db_save($db);
      return;
    }

    $mid = $update["message"]["message_id"];
    $hasMedia = isset($update["message"]["photo"]) || isset($update["message"]["document"]) || isset($update["message"]["video"]) || isset($update["message"]["voice"]) || isset($update["message"]["audio"]) || isset($update["message"]["sticker"]) || isset($update["message"]["animation"]);
    $sent = 0;
    foreach($recipients as $rid){
      if ($hasMedia){ tg_forwardMessage($rid, $chat, $mid); }
      else { if($text!=="") tg_sendMessage($rid, $text); }
      $delay = intval(getenv('BROADCAST_RATE_USLEEP') ?: '350000');
      if ($delay>0) usleep($delay);
      $sent++;
    }
    tg_sendMessage($chat,"📤 پیام ارسال شد به {$sent} نفر.");
    unset($db["pending_broadcast"][$fromId]); db_save($db);
    return;
  }

  if (($u["state"] ?? "") === "vip_upload_receipt") {
    $photoId = msg_get_photo_file_id($update["message"]);
    if (!$photoId) {
      tg_sendMessage($chat, "⚠️ لطفاً فقط <b>عکس رسید</b> را ارسال کن.", kb([[btn("⬅️ بازگشت","back_to_menu")]]));
      return;
    }
    $u["state"] = "await_vip_approval";
    db_save($db);
    $mid = $update["message"]["message_id"];
    tg_forwardMessage(SUPPORT_CHAT, $chat, $mid);
    $info = "💰 <b>رسید VIP جدید</b>\n"
          . "👤 کاربر: <a href=\"tg://user?id={$chat}\">".$chat."</a>\n"
          . "نام: ".(($u["name"]??'-')?:'-')." | شماره: ".(($u["phone"]??'-')?:'-');
    tg_sendMessage(SUPPORT_CHAT, $info, admin_vip_receipt_kb($chat));
    tg_sendMessage($chat, "مرسی! رسیدت به دستمون رسید. 🙏\nتیم پشتیبانی به زودی بررسی می‌کنه و نتیجه رو بهت خبر می‌ده.");
    return;
  }

  tg_sendMessage($chat, "برای شروع /start رو بزن یا از دکمه‌ها استفاده کن.", main_menu()); return;
}
/* ==== MSG_HANDLER END ============================================ */

/* ==== CB_HANDLER START =========================================== */
if(isset($update["callback_query"])){
  $db      = db_load();
  $cb      = $update["callback_query"];
  $chat    = $cb["message"]["chat"]["id"];
  $fromId  = $cb["from"]["id"] ?? null;
  $data    = $cb["data"];
  $isAdmin = is_admin($db, $fromId);
  $adminChat = $chat;
  $u =& user($db,$chat);

  $skipLockCb = in_array($data, ["support_contact"]);
  if (!$skipLockCb){
    if (enforce_restrictions($db, $u, $chat)) { tg_answerCb($cb["id"]); db_save($db); return; }
  }

  if ($data === "back_to_menu") {
    $u["state"]="idle"; db_save($db);
    tg_sendMessage($chat, welcome_text(), main_menu()); tg_answerCb($cb["id"]); return;
  }

  if ($data === "toolkit") { tg_sendMessage($chat, "🧰 <b>ابزارک ATB</b>\nاز لینک‌های زیر استفاده کن:", toolkit_kb()); tg_answerCb($cb["id"]); return; }
  if ($data === "toolkit_ctfc") { sendCTFC($chat); tg_answerCb($cb["id"]); return; }
  if ($data === "toolkit_vpn") {
    $vpnTxt = "🛡 <b>اکانت پرمیوم ExpressVPN (یک‌ساله)</b>\n\n"
            . "این اکانت‌ها مستقیم و با قیمت عالی تهیه شدن تا بدون دغدغه و با بالاترین سرعت به هوش مصنوعی دسترسی داشته باشی.\n\n"
            . "💎 قیمت ویژه: <b>590 هزار تومان</b> (۵۰٪ تخفیف)\n"
            . "✅ بدون قطعی و دردسر — مناسب موبایل و دسکتاپ\n\n"
            . "🛍 <b>برای خرید به این آیدی پیام بده:</b> @ExpVpn_Admin\n\n"
            . "<i>(ما فقط این فروشنده معتبر رو معرفی می‌کنیم چون خودمون از کیفیت خدماتشون راضی بودیم.)</i>";
    tg_sendMessage($chat, $vpnTxt, kb([[btn("⬅️ بازگشت به ابزارک","toolkit")]]));
    tg_answerCb($cb["id"]); return;
  }

  if ($isAdmin && strpos($data, 'admin_set_toolkit_type:') === 0) {
      $type = substr($data, strlen('admin_set_toolkit_type:'));
      if (isset($db["pending_admin_inputs"][$fromId])) {
          $cfg = $db["pending_admin_inputs"][$fromId];
          if ($cfg['mode'] === 'toolkit_add_2_type') {
              $db["pending_admin_inputs"][$fromId] = ["mode"=>"toolkit_add_3_value", "text"=>$cfg['text'], "type"=>$type];
              tg_sendMessage($chat, "حالا مقدار {$type} رو بفرست (لینک کامل یا متن callback_data)");
          } elseif ($cfg['mode'] === 'toolkit_edit_2_type') {
              $db["pending_admin_inputs"][$fromId] = ["mode"=>"toolkit_edit_3_value", "index"=>$cfg['index'], "text"=>$cfg['text'], "type"=>$type];
              tg_sendMessage($chat, "حالا مقدار جدید {$type} رو بفرست.");
          }
          db_save($db);
      }
      tg_answerCb($cb["id"]); return;
  }


  if ($data === "faq") {
    tg_sendMessage($chat, "❓ <b>سوالات متداول</b>\nیکی رو انتخاب کن:", faq_menu_kb());
    tg_answerCb($cb["id"]); return;
  }
  if (strpos($data,"faq:")===0) {
    $k = substr($data,4); $items = faq_items();
    if (isset($items[$k])) {
      $ans = "<b>".$items[$k]["q"]."</b>\n".$items[$k]["a"];
      $kb = json_encode(["inline_keyboard"=>[
        [ ["text"=>"⬅️ لیست سوالات","callback_data"=>"faq"] ],
        [ ["text"=>"بازگشت به منو","callback_data"=>"back_to_menu"] ]
      ]], JSON_UNESCAPED_UNICODE);
      tg_sendMessage($chat, $ans, $kb);
    }
    tg_answerCb($cb["id"]); return;
  }

  if ($data === "back_form") {
    $state = $u["state"] ?? "idle";
    if ($state === "collect_job_profile") {
      $u["state"] = "await_ai"; db_save($db);
      tg_sendMessage($chat, "برگشتی. سطح آشناییت با AI رو انتخاب کن:", kb([
        [btn("صفر","ai_lvl:0")],[btn("کمی","ai_lvl:1")],[btn("متوسط","ai_lvl:2")],[btn("حرفه‌ای","ai_lvl:3")],[btn("⬅️ بازگشت","back_form")]
      ])); tg_answerCb($cb["id"]); return;
    }
    if ($state === "await_ai") {
      $u["state"] = "await_phone"; db_save($db);
      tg_sendMessage($chat, "برگشتی. با دکمه‌ی پایین شماره‌ات رو بفرست:", rkb([[["text"=>"📱 ارسال شماره از تلگرام","request_contact"=>true], ["text"=>"⬅️ بازگشت"]]]));
      tg_answerCb($cb["id"]); return;
    }
    $u["state"]="idle"; db_save($db);
    tg_sendMessage($chat, welcome_text(), main_menu()); tg_answerCb($cb["id"]); return;
  }

  if($data==="start_course"){
    if(empty($u["name"])){
      $u["state"] = "collect_name"; db_save($db);
      tg_sendMessage($chat, "قبل از شروع آموزش، لطفاً <b>اسم و فامیلت</b> رو بفرست 🙏", kb([[btn("⬅️ بازگشت","back_to_menu")]])); tg_answerCb($cb["id"]); return;
    }
    if(empty($u["phone"])){
      $u["state"] = "await_phone"; db_save($db);
      tg_sendMessage($chat, "شماره موبایلت رو با دکمه‌ی زیر بفرست 👇", rkb([[["text"=>"📱 ارسال شماره از تلگرام","request_contact"=>true], ["text"=>"⬅️ بازگشت"]]])); tg_answerCb($cb["id"]); return;
    }
    if(empty($u["ai"]) || !in_array($u["ai"],["صفر","کمی","متوسط","حرفه‌ای"], true)){
      $u["state"] = "await_ai"; db_save($db);
      tg_sendMessage($chat, "سطح آشناییت با هوش مصنوعی رو انتخاب کن.", kb([
        [btn("صفر","ai_lvl:0")],[btn("کمی","ai_lvl:1")],[btn("متوسط","ai_lvl:2")],[btn("حرفه‌ای","ai_lvl:3")],[btn("⬅️ بازگشت","back_form")]
      ])); tg_answerCb($cb["id"]); return;
    }
    if (empty($u["job"])) {
      $u["state"] = "collect_job_profile"; db_save($db);
      tg_sendMessage($chat, "شغل یا زمینهٔ کارت چیه؟", kb([[btn("⬅️ بازگشت","back_form")]]));
      tg_answerCb($cb["id"]); return;
    }
    if(!$u["progress"]["v1"]) { $u["state"]="watch_v1"; $u["watch_started_at"]=time(); $u["video_deadlines"]["v1"]=time()+48*3600; db_save($db); sendStep($chat,"v1"); }
    elseif(!$u["progress"]["v2"]) { $u["state"]="watch_v2"; $u["watch_started_at"]=time(); $u["video_deadlines"]["v2"]=time()+48*3600; db_save($db); sendStep($chat,"v2"); }
    elseif(!$u["progress"]["v3"]) { $u["state"]="watch_v3"; $u["watch_started_at"]=time(); $u["video_deadlines"]["v3"]=time()+48*3600; db_save($db); sendStep($chat,"v3"); }
    else {
      $u["state"]="completed"; db_save($db);
      $finalText = "🎉 <b>تبریک!</b>\nسه مرحله رو با موفقیت گذروندی.\n\n"
                 . "✨ از الان عضو <b>ATB Club</b> هستی. روی دکمه زیر بزن و وارد شو:";
      $finalKb = json_encode(["inline_keyboard" => [[["text" => "🚪 ورود به ATB Club", "url" => $GLOBALS["CLUB_INVITE"]]]]], JSON_UNESCAPED_UNICODE);
      tg_sendMessage($chat, $finalText, $finalKb);
    }
    tg_answerCb($cb["id"]); return;
  }

  if (strpos($data, "ai_lvl:") === 0) {
    if(($u["state"] ?? "") !== "await_ai"){ tg_answerCb($cb["id"], "اول اطلاعات قبلی رو تکمیل کن."); return; }
    $map = ["0"=>"صفر","1"=>"کمی","2"=>"متوسط","3"=>"حرفه‌ای"];
    $k = substr($data, 7); $val = $map[$k] ?? null;
    if(!$val){ tg_answerCb($cb["id"], "انتخاب نامعتبر."); return; }
    $u["ai"] = $val; db_save($db);

    if (in_array($val, ["متوسط","حرفه‌ای"], true)){
      $u["quiz"]["lvl"] = $val; $u["quiz"]["q1"]=null; $u["quiz"]["q2"]=null; $u["quiz"]["approved"]=null;
      $u["state"] = "quiz_q1"; db_save($db);
      $msg = "🔎 <b>ارزیابی کوتاه سطح {$val}</b>\n"
           . "دو سؤال ساده می‌پرسیم تا مشخص بشه می‌تونی مستقیم وارد کلاب بشی یا بهتره آموزش‌ها رو ببینی.\n\n"
           . "سؤال ۱) <b>پرامپت</b> چیه و چه کاربردی داره؟";
      tg_sendMessage($chat, $msg, kb([[btn("⬅️ بازگشت","back_form")]]));
      tg_answerCb($cb["id"], "ثبت شد ✅");
      return;
    }

    $u["state"] = "collect_job_profile"; db_save($db);
    tg_answerCb($cb["id"], "ثبت شد ✅");
    tg_sendMessage($chat, "خیلی هم عالی 👌\nحالا بگو شغل یا زمینهٔ کارت چیه؟", kb([[btn("⬅️ بازگشت","back_form")]]));
    return;
  }

  if ($data === "about") {
    tg_sendMessage($chat, about_text(), kb([[btn("شروع آموزش رایگان","start_course")],[btn("⬅️ بازگشت","back_to_menu")]]));
    tg_answerCb($cb["id"]); return;
  }

  if ($data === "status") {
    $p = $u["progress"];
    $stateMap = [
      "idle"=>"آماده شروع / منوی اصلی", "watch_v1"=>"در حال مشاهده ویدئوی ۱",
      "watch_v2"=>"در حال مشاهده ویدئوی ۲", "watch_v3"=>"در حال مشاهده ویدئوی ۳",
      "collect_name"=>"در انتظار نام", "await_phone"=>"در انتظار شماره",
      "await_ai"=>"در انتظار سطح آشنایی AI", "collect_job_profile"=>"در انتظار شغل/زمینه",
      "await_ex_v1"=>"در انتظار ارسال تمرین ۱", "await_ex_v2"=>"در انتظار ارسال تمرین ۲",
      "await_ex_v3"=>"در انتظار ارسال تمرین ۳", "pending_v1"=>"منتظر تأیید پشتیبان (مرحله ۱)",
      "pending_v2"=>"منتظر تأیید پشتیبان (مرحله ۲)", "pending_v3"=>"منتظر تأیید پشتیبان (مرحله ۳)",
      "support_await_msg"=>"در حال نوشتن پیام به پشتیبانی", "vip_upload_receipt"=>"در حال ارسال رسید VIP",
      "await_vip_approval"=>"منتظر تأیید پرداخت VIP", "await_quiz_approval"=>"منتظر بررسی آزمون سطح",
      "completed"=>"همه مراحل تکمیل — آماده ورود به کلاب"
    ];
    $stateKey = $u["state"] ?? "-";
    $stateTxt = $stateMap[$stateKey] ?? $stateKey;
    $reg  = isset($u["created_at"]) ? to_jalali($u["created_at"]) : "—";
    $sub  = format_sub_status($db, $chat);
    $msg  = "نام: ".($u["name"]??"-")." | شغل: ".($u["job"]??"-")." | سطح AI: ".($u["ai"]??"-")." | شماره: ".($u["phone"]??"-");
    $msg .= "\nثبت‌نام: <b>{$reg}</b> | VIP: <b>{$sub}</b>";
    $msg .= "\nپیشرفت: ۱: ".($p["v1"]?"✅":"⬜️")." | ۲: ".($p["v2"]?"✅":"⬜️")." | ۳: ".($p["v3"]?"✅":"⬜️");
    if (user_is_banned($u)) $msg .= "\n🚫 وضعیت: <b>بن‌شده</b>";
    elseif (user_is_locked($u)) $msg .= "\n⛔️ وضعیت: <b>محدود</b> تا ".to_jalali($u["lock_until"]);
    else $msg .= "\nوضعیت فعلی: <b>".$stateTxt."</b>";
    tg_sendMessage($chat, $msg, kb([[btn("ادامه مسیر","start_course")],[btn("⬅️ بازگشت","back_to_menu")]]));
    tg_answerCb($cb["id"]); return;
  }

  if (in_array($data, ["seen_v1","seen_v2","seen_v3"], true)) {
    if (user_is_locked($u)){ tg_answerCb($cb["id"], "اکنون محدود هستی."); return; }
    if (user_is_banned($u)){ tg_answerCb($cb["id"], "بن هستی."); return; }
    $step = str_replace("seen_", "", $data);
    $needState = "watch_".$step;
    $wat = (int)($u["watch_started_at"] ?? 0);
    $elapsed = ($wat > 0) ? (time() - $wat) : 0;

    if (($u["state"] ?? "") === $needState && $elapsed < MIN_WATCH_SECONDS) {
      tg_answerCb($cb["id"], "لطفاً ویدئو را تا انتها مشاهده کنید 🙏");
      return;
    }

    $u["state"] = "await_ex_".$step; db_save($db);
    if ($step==="v1") tg_sendMessage($chat, "تمرین ۱: <b>سه هدف مشخص این هفته‌ات</b> رو بفرست.");
    elseif ($step==="v2") tg_sendMessage($chat, "تمرین ۲: <b>اسکرین‌شات گفتگوی ChatGPT</b> رو بفرست.");
    else tg_sendMessage($chat, "تمرین ۳: <b>خروجی پرامپت CTFC</b> رو بفرست.");
    tg_answerCb($cb["id"], "باشه ✅");
    return;
  }

  if (strpos($data,"admin_quiz_ok:")===0 || strpos($data,"admin_quiz_no:")===0){
    if (!$isAdmin){ tg_answerCb($cb["id"], "اجازه نداری."); return; }
    $parts = explode(":", $data);
    $uid = (int)($parts[1] ?? 0);
    $all = db_load();
    if (!isset($all["users"][$uid])){ tg_answerCb($cb["id"], "کاربر یافت نشد."); return; }
    $uu =& $all["users"][$uid];

    if (strpos($data,"admin_quiz_ok:")===0){
      $uu["quiz"]["approved"]=true;
      $uu["state"]="completed"; db_save($all);
      $final = "🎉 <b>تبریک!</b>\nسطح شما تأیید شد و می‌تونی مستقیم وارد ATB Club بشی:";
      $kb = json_encode(["inline_keyboard" => [[["text" => "🚪 ورود به ATB Club", "url" => $GLOBALS["CLUB_INVITE"]]]]], JSON_UNESCAPED_UNICODE);
      tg_sendMessage($uid, $final, $kb);
      tg_answerCb($cb["id"], "ارسال شد ✅");
      return;
    } else {
      $uu["quiz"]["approved"]=false;
      if(!$uu["progress"]["v1"]) { go_watch_and_send($all,$uid,"v1"); tg_sendMessage($uid,"نتیجه ارزیابی: بهتره از مسیر آموزش شروع کنی. بریم مرحله ۱ ✌️"); }
      elseif(!$uu["progress"]["v2"]) { go_watch_and_send($all,$uid,"v2"); tg_sendMessage($uid,"ادامهٔ مسیر — مرحله ۲"); }
      else { go_watch_and_send($all,$uid,"v3"); tg_sendMessage($uid,"ادامهٔ مسیر — مرحله ۳"); }
      tg_answerCb($cb["id"], "ارسال شد ✅");
      return;
    }
  }

  if (strpos($data, "support_reply:")===0 || strpos($data, "admin_")===0 || strpos($data, "ap:")===0 || strpos($data,"au:")===0) {
    if (!$isAdmin) { tg_answerCb($cb["id"], "اجازه نداری."); return; }

    if (strpos($data,"au:")===0){
      $parts = explode(":", $data);
      $action = $parts[1] ?? "";
      if ($action==="send"){ $arg = $parts[2] ?? ""; $uid=(int)($parts[3]??0); }
      elseif ($action==="sub"){ $arg = $parts[2] ?? ""; $uid=(int)($parts[3]??0); }
      elseif ($action==="sub_menu"){ $arg=null; $uid=(int)($parts[2]??0); }
      else { $arg=null; $uid=(int)($parts[2]??0); }

      $all = db_load();
      if(!isset($all["users"][$uid])){ tg_answerCb($cb["id"],"کاربر نیست."); return; }
      $uu =& $all["users"][$uid];

      if ($action==="info"){
        tg_sendMessage($adminChat, admin_user_summary($uid,$uu), admin_user_kb($uid,$uu)); tg_answerCb($cb["id"]); return;
      }
      if ($action==="send"){
        if(in_array($arg,["v1","v2","v3"],true)){ go_watch_and_send($all,$uid,$arg); tg_answerCb($cb["id"],"ارسال شد."); return; }
      }
      if ($action==="sendnext"){
        $n = next_needed_step($uu);
        if($n===null){ tg_sendMessage($adminChat,"این کاربر همه مراحل را کامل کرده."); }
        else { go_watch_and_send($all,$uid,$n); }
        tg_answerCb($cb["id"],"اوکی"); return;
      }
      if ($action==="sendprev"){
        $p = prev_step($uu);
        if($p===null){ tg_sendMessage($adminChat,"مرحله قبلی وجود ندارد."); }
        else { go_watch_and_send($all,$uid,$p); }
        tg_answerCb($cb["id"],"اوکی"); return;
      }
      if ($action==="gonext"){
        if(!$uu["progress"]["v1"])       $uu["progress"]["v1"]=true;
        elseif(!$uu["progress"]["v2"])   $uu["progress"]["v2"]=true;
        elseif(!$uu["progress"]["v3"])   $uu["progress"]["v3"]=true;
        db_save($all);
        $n = next_needed_step($uu);
        if($n===null){
          $uu["state"]="completed"; db_save($all);
          $kb = json_encode(["inline_keyboard" => [[["text" => "🚪 ورود به ATB Club", "url" => $GLOBALS["CLUB_INVITE"]]]]], JSON_UNESCAPED_UNICODE);
          tg_sendMessage($uid,"🎉 <b>تبریک!</b>\nسه مرحله رو کامل کردی. وارد کلاب شو:", $kb);
        } else { go_watch_and_send($all,$uid,$n); }
        tg_answerCb($cb["id"],"اوکی"); return;
      }
      if ($action==="goprev"){
        if($uu["progress"]["v3"])       $uu["progress"]["v3"]=false;
        elseif($uu["progress"]["v2"])   $uu["progress"]["v2"]=false;
        else                            $uu["progress"]["v1"]=false;
        db_save($all);
        $p = prev_step($uu);
        if($p===null) { $uu["state"]="watch_v1"; db_save($all); sendStep($uid,"v1"); }
        else { go_watch_and_send($all,$uid,$p); }
        tg_answerCb($cb["id"],"برگشت انجام شد"); return;
      }
      if ($action==="rename"){
        $db["pending_admin_inputs"][$fromId]=["mode"=>"rename","uid"=>$uid]; db_save($db);
        tg_sendMessage($adminChat,"📝 نام جدید کاربر {$uid} را بنویسید.");
        tg_answerCb($cb["id"],"منتظر نام…"); return;
      }
      if ($action==="ban"){
        $uu["banned"]=true; db_save($all);
        tg_sendMessage($uid,"🚫 دسترسی شما توسط مدیریت مسدود شد.");
        tg_sendMessage($adminChat,"کاربر {$uid} بن شد.", admin_user_kb($uid,$uu));
        tg_answerCb($cb["id"]); return;
      }
      if ($action==="unban"){
        $uu["banned"]=false; db_save($all);
        tg_sendMessage($uid,"🔓 دسترسی شما آزاد شد.");
        tg_sendMessage($adminChat,"کاربر {$uid} آزاد شد.", admin_user_kb($uid,$uu));
        tg_answerCb($cb["id"]); return;
      }
      if ($action==="unlock"){
        $uu["lock_until"]=0; db_save($all);
        tg_sendMessage($uid,"🔓 محدودیت/ددلاین حذف شد. می‌تونی ادامه بدی.");
        tg_answerCb($cb["id"],"اوکی"); return;
      }
      if ($action==="sub_menu"){
        $cur = format_sub_status($all, $uid);
        $rows = [
          [ ["text"=>"❌ بدون اشتراک","callback_data"=>"au:sub:none:".$uid] ],
          [ ["text"=>"۳ ماهه","callback_data"=>"au:sub:3m:".$uid], ["text"=>"۶ ماهه","callback_data"=>"au:sub:6m:".$uid], ["text"=>"۱۲ ماهه","callback_data"=>"au:sub:12m:".$uid] ],
          [ ["text"=>"↩️ بازگشت","callback_data"=>"au:info:".$uid] ]
        ];
        tg_sendMessage($adminChat, "👑 اشتراک فعلی: <b>{$cur}</b>\nیکی را انتخاب کن:", json_encode(["inline_keyboard"=>$rows], JSON_UNESCAPED_UNICODE));
        tg_answerCb($cb["id"]); return;
      }
      if ($action==="sub"){
        $type = $arg;
        if(!isset($all["subscriptions"])) $all["subscriptions"]=[];
        if($type==="none"){
          unset($all["subscriptions"][$uid]);
        } else {
          $months = ["3m"=>3,"6m"=>6,"12m"=>12][$type] ?? 0;
          if ($months>0){
            $exp = strtotime(" . {$months} months");
            $all["subscriptions"][$uid] = ["type"=>$type, "expires_at"=>$exp];
          }
        }
        db_save($all);
        $uu = $all["users"][$uid];
        tg_sendMessage($adminChat, "✅ اشتراک کاربر ".$uid." به‌روزرسانی شد.\n".admin_user_summary($uid,$uu), admin_user_kb($uid,$uu));
        tg_answerCb($cb["id"],"انجام شد"); return;
      }
    }

    if (strpos($data, "support_reply:")===0) {
      $parts = explode(":", $data); $userId = intval($parts[1] ?? 0);
      if ($userId<=0) { tg_answerCb($cb["id"], "شناسه نامعتبر"); return; }
      $db["pending_support_replies"][$fromId] = ["user_id"=>$userId]; db_save($db);
      tg_sendMessage($adminChat, "✍️ پاسخ‌تان به کاربر {$userId} را بفرستید.");
      tg_answerCb($cb["id"], "منتظر پیام شما…"); return;
    }

    if (strpos($data, "ap:")===0) {
      $all = $db["users"];
      $todayStart = strtotime('today');

      if ($data === "ap:stats") {
        $total = count($all); $today = 0;
        foreach($all as $id=>$uu){ if( (int)($uu["created_at"]??0) >= $todayStart ) $today++; }
        $pendingCount = count($db["pending_items"] ?? []);
        $openTickets  = count($db["support_open"] ?? []);
        tg_sendMessage($adminChat, "📊 آمار الان (نسخه ".ATB_VERSION."):\n👥 کل: <b>{$total}</b>\n🗓️ امروز: <b>{$today}</b>\n⏳ تمرین‌های در صف: <b>{$pendingCount}</b>\n📥 تیکت‌های باز: <b>{$openTickets}</b>");
        tg_answerCb($cb["id"]); return;
      }

      if ($data === "ap:commands"){
        tg_sendMessage($adminChat, admin_commands_text(), kb([[btn("↩️ بازگشت به پنل","ap:stats")]]));
        tg_answerCb($cb["id"]); return;
      }

      if ($data === "ap:broadcast"){
        $counts = [];
        foreach($all as $id=>$uu){ $st = $uu["state"] ?? "idle"; $counts[$st] = ($counts[$st] ?? 0) + 1; }
        $lines = ["📊 شمارش وضعیت‌ها:"];
        foreach($counts as $st=>$c){ $lines[] = "• {$st}: {$c}"; }
        $rows = [
          [ ["text"=>"به همه","callback_data"=>"ap:bcast_sel:all"], ["text"=>"ثبت‌نامی‌های امروز","callback_data"=>"ap:bcast_sel:today"] ],
          [ ["text"=>"بر اساس وضعیت","callback_data"=>"ap:bcast_pick_status"] ],
          [ ["text"=>"به یک کاربر خاص","callback_data"=>"ap:bcast_sel:user"] ],
          [ ["text"=>"↩️ بازگشت به پنل","callback_data"=>"ap:stats"] ]
        ];
        tg_sendMessage($adminChat, implode("\n",$lines), json_encode(["inline_keyboard"=>$rows], JSON_UNESCAPED_UNICODE));
        tg_answerCb($cb["id"]); return;
      }

      if ($data === "ap:bcast_pick_status"){
        $counts=[];
        foreach($all as $id=>$uu){ $st=$uu["state"]??"idle"; $counts[$st]=($counts[$st]??0)+1; }
        ksort($counts);
        $rows=[]; $tmp=[];
        foreach($counts as $st=>$c){
          $label = "{$st} ({$c})";
          $tmp[] = ["text"=>$label,"callback_data"=>"ap:bcast_sel:state:".$st];
          if (count($tmp)===2){ $rows[]=$tmp; $tmp=[]; }
          if (count($rows)>=10) break;
        }
        if(!empty($tmp)) $rows[]=$tmp;
        $rows[]=[["text"=>"↩️ بازگشت","callback_data"=>"ap:broadcast"]];
        tg_sendMessage($adminChat,"یکی از وضعیت‌ها را انتخاب کن:", json_encode(["inline_keyboard"=>$rows], JSON_UNESCAPED_UNICODE));
        tg_answerCb($cb["id"]); return;
      }

      if (strpos($data,"ap:bcast_sel:")===0){
        $sel = substr($data, strlen("ap:bcast_sel:"));
        $db["pending_broadcast"][$fromId] = ["scope"=>$sel]; db_save($db);

        if ($sel==="user"){
          tg_sendMessage($adminChat,"🆔 شناسه عددی کاربر را بفرست.");
        } elseif (strpos($sel,"state:")===0) {
          $st = substr($sel,6);
          tg_sendMessage($adminChat,"✍️ پیام‌ت را برای وضعیت «{$st}» ارسال کن.");
        } else {
          $label = ($sel==="all"?"همهٔ کاربران":"ثبت‌نامی‌های امروز");
          tg_sendMessage($adminChat,"✍️ پیام‌ت را برای «{$label}» ارسال کن.");
        }
        tg_answerCb($cb["id"]); return;
      }

      if ($data === "ap:phones_all" || $data === "ap:phones_today") {
        $lines = [];
        foreach($all as $id=>$uu){
          $ph = trim((string)($uu["phone"]??""));
          if ($ph==="") continue;
          if ($data==="ap:phones_today" && (int)($uu["created_at"]??0) < $todayStart) continue;
          $lines[] = $ph;
        }
        if (empty($lines)) tg_sendMessage($adminChat, "شماره‌ای یافت نشد.");
        else send_lines_in_chunks($adminChat, $lines, 300);
        tg_answerCb($cb["id"]); return;
      }

      if ($data === "ap:ex_summary") {
        $p_v1=$p_v2=$p_v3=0; $pend_v1=$pend_v2=$pend_v3=0;
        foreach($all as $id=>$uu){
          $p = $uu["progress"];
          if (!empty($p["v1"])) $p_v1++;
          if (!empty($p["v2"])) $p_v2++;
          if (!empty($p["v3"])) $p_v3++;
          $st = $uu["state"] ?? "";
          if ($st==="pending_v1") $pend_v1++;
          if ($st==="pending_v2") $pend_v2++;
          if ($st==="pending_v3") $pend_v3++;
        }
        $inQueue = count($db["pending_items"] ?? []);
        $msg = "📚 گزارش تمرین‌ها:\n"
             . "✅ انجام‌شده — ۱: <b>{$p_v1}</b> | ۲: <b>{$p_v2}</b> | ۳: <b>{$p_v3}</b>\n"
             . "⏳ منتظر تأیید — ۱: <b>{$pend_v1}</b> | ۲: <b>{$pend_v2}</b> | ۳: <b>{$pend_v3}</b>\n"
             . "📦 در صف ارسال: <b>{$inQueue}</b>";
        tg_sendMessage($adminChat, $msg);
        tg_answerCb($cb["id"]); return;
      }

      if ($data === "ap:ex_pending") {
        $items = $db["pending_items"] ?? [];
        if (empty($items)) { tg_sendMessage($adminChat, "⛱ الان تمرینی در انتظار نداریم."); tg_answerCb($cb["id"]); return; }
        $lines = [];
        foreach($items as $i=>$it){
          $uid = $it["user_id"]; $stp = $it["step"]; $nm = $all[$uid]["name"] ?? "-";
          $lines[] = ($i+1).") ".$uid." | ".$nm." | مرحله ".( step_human($stp) );
        }
        tg_sendMessage($adminChat, "⏳ تمرین‌های در صف:\n".implode("\n",$lines), kb([[btn("🔄 ارسال مجدد همه","ap:pending_send")]]));
        tg_answerCb($cb["id"]); return;
      }

      if ($data === "ap:pending_send") {
        $items = $db["pending_items"] ?? [];
        if (empty($items)) { tg_answerCb($cb["id"], "موردی در صف نیست."); return; }
        foreach ($items as $it) {
          $uid  = $it["user_id"]; $mid  = $it["orig_mid"] ?? null;
          if ($mid) { tg_forwardMessage($adminChat, $uid, $mid); }
          $uu = $db["users"][$uid] ?? null;
          $humanStep = step_human($it["step"]);
          $info = "📝 <b>تمرین — مرحله {$humanStep}</b>\n"
                . "👤 کاربر: <a href=\"tg://user?id={$uid}\">".$uid."</a> | ".($uu["name"]??'-');
          tg_sendMessage($adminChat, $info, admin_kb($uid,$it["step"]));
        }
        tg_answerCb($cb["id"], "ارسال شد ✅");
        return;
      }

      if ($data === "ap:ex_done") {
        $d1=$d2=$d3=[];
        foreach($all as $id=>$uu){
          $p = $uu["progress"] ?? [];
          if (!empty($p["v1"])) $d1[] = "{$id} | ".(($uu["name"]??"-")?:'-');
          if (!empty($p["v2"])) $d2[] = "{$id} | ".(($uu["name"]??"-")?:'-');
          if (!empty($p["v3"])) $d3[] = "{$id} | ".(($uu["name"]??"-")?:'-');
        }
        tg_sendMessage($adminChat, "✅ انجام‌شده — مرحله ۱:"); send_lines_in_chunks($adminChat, array_slice($d1,0,100), 100);
        tg_sendMessage($adminChat, "✅ انجام‌شده — مرحله ۲:"); send_lines_in_chunks($adminChat, array_slice($d2,0,100), 100);
        tg_sendMessage($adminChat, "✅ انجام‌شده — مرحله ۳:"); send_lines_in_chunks($adminChat, array_slice($d3,0,100), 100);
        tg_answerCb($cb["id"]); return;
      }
      
      if ($data==="ap:subs_info"){
        $kb = kb([
            [btn("✅ کاربران فعال", "ap:subs:active")],
            [btn("⌛️ در حال انقضا (۷ روز)", "ap:subs:expiring")],
            [btn("❌ منقضی شده", "ap:subs:expired")],
            [btn("➖ بدون اشتراک", "ap:subs:none")],
            [btn(" بازگشت به پنل", "ap:stats")]
        ]);
        tg_sendMessage($adminChat, "👑 کدام دسته از کاربران نمایش داده شوند؟", $kb);
        tg_answerCb($cb["id"]); return;
      }

      if (strpos($data, "ap:subs:") === 0) {
        $filter = substr($data, strlen("ap:subs:"));
        $now = time();
        $expiring_limit = $now + 7 * 24 * 3600; // 7 days from now
        $lines = [];

        foreach($all as $uid => $user) {
            $sub = $db["subscriptions"][$uid] ?? null;
            $name = $user["name"] ?? "بی‌نام";

            if ($filter === 'active') {
                if ($sub && ($sub["expires_at"] ?? 0) > $now) {
                    $lines[] = "• {$uid} | {$name} | تا: " . to_jalali($sub["expires_at"]);
                }
            } elseif ($filter === 'expiring') {
                if ($sub && ($sub["expires_at"] ?? 0) > $now && ($sub["expires_at"] ?? 0) < $expiring_limit) {
                    $lines[] = "• {$uid} | {$name} | تا: " . to_jalali($sub["expires_at"]);
                }
            } elseif ($filter === 'expired') {
                if ($sub && ($sub["expires_at"] ?? 0) <= $now) {
                    $lines[] = "• {$uid} | {$name} | در: " . to_jalali($sub["expires_at"]);
                }
            } elseif ($filter === 'none') {
                if (!$sub) {
                    $lines[] = "• {$uid} | {$name}";
                }
            }
        }
        
        $title_map = [
            'active' => '✅ کاربران با اشتراک فعال',
            'expiring' => '⌛️ اشتراک‌های در حال انقضا',
            'expired' => '❌ اشتراک‌های منقضی شده',
            'none' => '➖ کاربران بدون اشتراک',
        ];
        $title = $title_map[$filter] ?? 'لیست کاربران';
        
        if (empty($lines)) {
            tg_sendMessage($adminChat, "{$title}:\n\nموردی یافت نشد.");
        } else {
            tg_sendMessage($adminChat, "{$title}:");
            send_lines_in_chunks($adminChat, $lines);
        }
        tg_answerCb($cb["id"]); return;
      }
      
      if (in_array($data, ["ap:setv1","ap:setv2","ap:setv3","ap:clear_videos","ap:support_open","ap:set_vip_card","ap:clear_vip_card", "ap:manage_start_media", "ap:set_start_media", "ap:clear_start_media", "ap:manage_toolkit", "ap:add_toolkit_btn"]) || strpos($data,"ap:support_close:")===0 || strpos($data,"ap:delete_toolkit_btn:")===0 || strpos($data,"ap:edit_toolkit_btn:")===0) {
        if ($data==="ap:clear_videos") {
          $db["video_srcs"] = ["v1"=>null,"v2"=>null,"v3"=>null]; db_save($db);
          tg_sendMessage($adminChat, "🧹 ویدئوها پاک شد.");
          tg_answerCb($cb["id"]); return;
        }
        if ($data==="ap:set_vip_card") {
          $db["await_set_vip_card"][$fromId] = true; db_save($db);
          tg_sendMessage($adminChat, "💳 عکس کارت بانکی را ارسال کنید.");
          tg_answerCb($cb["id"]); return;
        }
        if ($data==="ap:clear_vip_card") {
          $db["vip_card"]["file_id"] = null; db_save($db);
          tg_sendMessage($adminChat, "🗑️ کارت VIP حذف شد.");
          tg_answerCb($cb["id"]); return;
        }
        if ($data==="ap:manage_start_media") {
            $current = $db['start_media']['file_id'] ? "✅ (تنظیم شده)" : "❌ (خالی)";
            tg_sendMessage($adminChat, "🖼️ مدیریت رسانه استارت\nوضعیت فعلی: {$current}", kb([
                [btn("تنظیم/تغییر رسانه", "ap:set_start_media")],
                [btn("حذف رسانه", "ap:clear_start_media")],
                [btn("بازگشت به پنل", "ap:stats")]
            ]));
            tg_answerCb($cb["id"]); return;
        }
        if ($data==="ap:set_start_media") {
            $db["await_set_start_media"][$fromId] = true; db_save($db);
            tg_sendMessage($adminChat, "🖼️ یک گیف، عکس یا ویدئو برای پیام استارت ارسال کنید.");
            tg_answerCb($cb["id"]); return;
        }
        if ($data==="ap:clear_start_media") {
            $db["start_media"] = ["file_id"=>null, "type"=>null]; db_save($db);
            tg_sendMessage($adminChat, "🗑️ رسانه استارت حذف شد.");
            tg_answerCb($cb["id"]); return;
        }
        if ($data==="ap:manage_toolkit") {
            $buttons = $db['toolkit_buttons'] ?? [];
            $rows = [];
            foreach ($buttons as $i => $btnData) {
                $text = $btnData['text'] ?? ''; $type = $btnData['type'] ?? '';
                $rows[] = [ btn("🗑️ #".($i+1), "ap:delete_toolkit_btn:".$i), btn("✏️ #".($i+1), "ap:edit_toolkit_btn:".$i), btn($text . " ($type)", "noop") ];
            }
            $rows[] = [btn("➕ افزودن دکمه جدید", "ap:add_toolkit_btn")];
            $rows[] = [btn(" بازگشت به پنل", "ap:stats")];
            tg_sendMessage($adminChat, "🧰 مدیریت دکمه‌های ابزارک", kb($rows));
            tg_answerCb($cb["id"]); return;
        }
        if ($data==="ap:add_toolkit_btn") {
            $db["pending_admin_inputs"][$fromId] = ["mode"=>"toolkit_add_1_text"]; db_save($db);
            tg_sendMessage($adminChat, "📝 متن دکمه جدید را وارد کنید:");
            tg_answerCb($cb["id"]); return;
        }
        if (strpos($data, "ap:delete_toolkit_btn:") === 0) {
            $index = (int)substr($data, strlen("ap:delete_toolkit_btn:"));
            if (isset($db['toolkit_buttons'][$index])) {
                array_splice($db['toolkit_buttons'], $index, 1); db_save($db);
                tg_answerCb($cb["id"], "دکمه حذف شد. ✅");
                $buttons = $db['toolkit_buttons']; $rows = [];
                foreach ($buttons as $i => $btnData) { $rows[] = [btn("🗑️ #".($i+1), "ap:delete_toolkit_btn:".$i), btn("✏️ #".($i+1), "ap:edit_toolkit_btn:".$i), btn($btnData['text'] . " (".$btnData['type'].")", "noop")]; }
                $rows[] = [btn("➕ افزودن دکمه جدید", "ap:add_toolkit_btn")]; $rows[] = [btn(" بازگشت به پنل", "ap:stats")];
                tg_sendMessage($adminChat, "لیست دکمه‌های ابزارک به‌روز شد:", kb($rows));
            } else { tg_answerCb($cb["id"], "خطا: دکمه یافت نشد."); }
            return;
        }
        if (strpos($data, "ap:edit_toolkit_btn:") === 0) {
            $index = (int)substr($data, strlen("ap:edit_toolkit_btn:"));
            if (isset($db['toolkit_buttons'][$index])) {
                 $db["pending_admin_inputs"][$fromId] = ["mode"=>"toolkit_edit_1_text", "index"=>$index]; db_save($db);
                 tg_sendMessage($adminChat, "📝 متن جدید برای دکمه '". $db['toolkit_buttons'][$index]['text'] ."' را وارد کنید:");
            } else { tg_answerCb($cb["id"], "خطا: دکمه یافت نشد."); }
            return;
        }
        if ($data==="ap:support_open") {
          $tickets = $db["support_open"] ?? [];
          if (empty($tickets)) { tg_sendMessage($adminChat, "👌 هیچ تیکت بازی نداریم."); tg_answerCb($cb["id"]); return; }
          usort($tickets, function($a,$b){ return $b["time"] <=> $a["time"]; });
          $rows = []; $allUsers=$db["users"]; $countShown=0;
          foreach($tickets as $t){
            $uid = $t["user_id"]; $nm = $allUsers[$uid]["name"] ?? "-";
            $pv = trim((string)($t["last_text"] ?? "—"));
            $pvShort = mb_substr(preg_replace('/\s+/u',' ', $pv), 0, 60, 'UTF-8');
            $label = "👤 {$uid} | ".($nm?:'-')." — «".$pvShort."»";
            $rows[] = [ ["text"=>$label,"callback_data"=>"support_reply:".$uid] ];
            $rows[] = [ ["text"=>"🗑️ بستن بدون پاسخ — {$uid}","callback_data"=>"ap:support_close:".$uid] ];
            if(++$countShown>=50) break;
          }
          $rows[] = [ ["text"=>"↩️ بازگشت به پنل","callback_data"=>"ap:stats"] ];
          tg_sendMessage($adminChat, "📥 تیکت‌های باز (آخرین‌ها بالا):", json_encode(["inline_keyboard"=>$rows], JSON_UNESCAPED_UNICODE));
          tg_answerCb($cb["id"]); return;
        }
        if (strpos($data,"ap:support_close:")===0) {
          $uid = intval(substr($data, strlen("ap:support_close:")));
          support_open_close_user($db, $uid); db_save($db);
          tg_answerCb($cb["id"], "بسته شد ✅"); return;
        }
        $map = ["ap:setv1"=>"v1","ap:setv2"=>"v2","ap:setv3"=>"v3"];
        if (isset($map[$data])) {
          $db["await_set_video"][$fromId] = $map[$data]; db_save($db);
          tg_sendMessage($adminChat, "🎬 حالت تنظیم فعال شد: <b>".strtoupper($map[$data])."</b>\n ویدئو را ارسال/فوروارد کنید.");
          tg_answerCb($cb["id"]); return;
        }
      }
    }

    if (strpos($data, "admin_")===0) {
      $parts = explode(":", $data);
      $prefix = $parts[0] ?? '';

      if ($prefix === "admin_vip_ok") {
        $userId = intval($parts[1] ?? 0);
        $all_db = db_load();
        if (isset($all_db["users"][$userId])) {
            $user_vip =& $all_db["users"][$userId];
            $user_vip["state"] = "idle";
            db_save($all_db);
            $kb_vip = json_encode(["inline_keyboard" => [[["text" => "🔑 ورود به کانال VIP", "url" => $GLOBALS["VIP_PRIVATE_INVITE"]]]]], JSON_UNESCAPED_UNICODE);
            tg_sendMessage($userId, "✅ پرداخت شما تأیید شد! 🎉\nخیلی خوشحالیم که به جمع اعضای VIP ما پیوستی.\n\n👇 با لینک زیر وارد کانال اختصاصی شو:", $kb_vip);
            tg_answerCb($cb["id"], "تأیید شد و لینک برای کاربر ارسال گردید. ✅");
            tg_sendMessage($adminChat, "اشتراک VIP برای کاربر {$userId} تأیید شد.", admin_user_kb($userId, $user_vip));
        } else {
            tg_answerCb($cb["id"], "کاربر یافت نشد.");
        }
        return;
      }

      if (count($parts) !== 3) { tg_answerCb($cb["id"], "داده نامعتبر"); return; }
      list($prefix, $userId, $step) = $parts; $userId = intval($userId);
      $u_all = db_load();
      if (!isset($u_all["users"][$userId])) { tg_answerCb($cb["id"], "کاربر پیدا نشد."); return; }
      $uu =& $u_all["users"][$userId];

      if ($prefix === "admin_ok") {
        $uu["progress"][$step] = true;
        pending_remove($u_all, $userId, $step);
        if(!$uu["progress"]["v2"]) {
          go_watch_and_send($u_all,$userId,"v2");
          tg_sendMessage($userId,"تمرین تأیید شد ✅ بریم مرحله ۲");
        } elseif(!$uu["progress"]["v3"]) {
          go_watch_and_send($u_all,$userId,"v3");
          tg_sendMessage($userId,"تمرین تأیید شد ✅ بریم مرحله ۳");
        } else {
          $uu["state"] = "completed"; db_save($u_all);
          $finalText = "🎉 <b>تبریک!</b>\nسه مرحله رو با موفقیت گذروندی.\n\n"
                     . "✨ از الان عضو <b>ATB Club</b> هستی. وارد شو:";
          $finalKb = json_encode(["inline_keyboard" => [[["text" => "🚪 ورود به ATB Club", "url" => $GLOBALS["CLUB_INVITE"]]]]], JSON_UNESCAPED_UNICODE);
          tg_sendMessage($userId, $finalText, $finalKb);
        }
        tg_answerCb($cb["id"], "تأیید شد ✅"); return;
      }

      if ($prefix === "admin_no") {
        $u_all["pending_rejects"][$fromId] = ["user_id"=>$userId,"step"=>$step];
        pending_remove($u_all, $userId, $step); db_save($u_all);
        $hs  = step_human($step);
        tg_sendMessage($adminChat, "❌ رد تمرین مرحله {$hs} انتخاب شد.\nلطفاً <b>دلیل رد</b> را در یک پیام بفرستید.");
        tg_answerCb($cb["id"], "منتظر دلیل…"); return;
      }
    }
  }

  if ($data === "support_contact") {
    $u["state"] = "support_await_msg"; db_save($db);
    tg_sendMessage($chat, "🆘 مشکلت/سؤال‌ت رو بنویس یا فایل/عکس بفرست.", rkb([[["text"=>"⬅️ بازگشت"]]]));
    tg_answerCb($cb["id"]); return;
  }

  if ($data === "vip_membership") {
    $rows = [];
    if (!empty($GLOBALS["VIP_PAYMENT_LINK"])) {
      $rows[] = [ ["text"=>"💳 پرداخت آنلاین","url"=>$GLOBALS["VIP_PAYMENT_LINK"]] ];
    }
    $rows[] = [ ["text"=>"📎 ارسال رسید پرداخت","callback_data"=>"vip_send_receipt"] ];
    $rows[] = [ ["text"=>"⬅️ بازگشت","callback_data"=>"back_to_menu"] ];

    $pr = "👑 <b>پیشنهاد ویژه‌ی ATB Club VIP</b> 👑\n\n"
        . "می‌خوای یک قدم جلوتر از بقیه باشی و از هوش مصنوعی برای رشد کسب‌وکارت پول بسازی؟ عضویت VIP برای شماست! 🚀\n\n"
        . "<b>در کانال VIP چه چیزی منتظر شماست؟</b>\n"
        . "✅ <b>آموزش‌های هفتگی:</b> جدیدترین و کاربردی‌ترین تکنیک‌های AI که مستقیماً روی درآمد شما تأثیر می‌گذارد.\n"
        . "✅ <b>پرامپت‌های آماده:</b> کتابخانه‌ای از دستورات حرفه‌ای برای تولید محتوا، بازاریابی و فروش.\n"
        . "✅ <b>پشتیبانی اختصاصی:</b> سوالاتت رو بپرس و از تیم ما راهنمایی تخصصی بگیر.\n\n"
        . "🎁 <b>هدیه ویژه:</b> با خرید هر کدام از اشتراک‌ها، یک <b>اکانت یک‌ساله ExpressVPN</b> به ارزش ۵۹۰ هزار تومان هدیه بگیرید!\n\n"
        . "✨ <b>پلن‌های اشتراک:</b>\n"
        . "• ۳ ماهه: <b>۳۹۰ هزار تومان</b>\n"
        . "• ۶ ماهه: <b>۴۹۰ هزار تومان</b> (🔥 پرطرفدارترین!)\n"
        . "• ۱۲ ماهه: <b>۹۷۰ هزار تومان</b>\n\n"
        . "👇 برای پرداخت، مبلغ پلن دلخواهت رو به شماره کارت موجود در این تصویر واریز کن و بعد روی دکمه «ارسال رسید» بزن.";

    $card_file_id = $db["vip_card"]["file_id"] ?? null;
    if ($card_file_id) {
        tg_sendPhoto($chat, $card_file_id, $pr, json_encode(["inline_keyboard"=>$rows], JSON_UNESCAPED_UNICODE));
    } else {
        $pr .= "\n\nℹ️ <i>تصویر کارت بانکی توسط ادمین تنظیم نشده است.</i>";
        tg_sendMessage($chat, $pr, json_encode(["inline_keyboard"=>$rows], JSON_UNESCAPED_UNICODE));
    }
    tg_answerCb($cb["id"]);
    return;
  }
  if ($data === "vip_send_receipt") {
    $u["state"] = "vip_upload_receipt"; db_save($db);
    tg_sendMessage($chat, "لطفاً <b>فقط عکس رسید پرداخت</b> را ارسال کن.", kb([[btn("⬅️ بازگشت","back_to_menu")]]));
    tg_answerCb($cb["id"]);
    return;
  }

  tg_answerCb($cb["id"]); return;
}
/* ==== CB_HANDLER END =========================================== */