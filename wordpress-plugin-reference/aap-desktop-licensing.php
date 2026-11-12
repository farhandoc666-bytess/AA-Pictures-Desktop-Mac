<?php
/**
 * Plugin Name: A&A Pictures — Desktop Licences & Downloads
 * Description: 150 single-device offline desktop keys, independent A&A customer linking, secure Windows/macOS installers and an A&A-branded AI relay.
 * Version: 1.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: A&A Pictures
 */
if (!defined('ABSPATH')) exit;
const AADL_VERSION = '1.1.0';
function aadl_table() {global $wpdb; return $wpdb->prefix.'aadl_keys';}
function aadl_b64($raw){return rtrim(strtr(base64_encode($raw),'+/','-_'),'=');}
function aadl_unb64($x){return base64_decode(strtr($x,'-_','+/'),true);}
function aadl_private_dir(){return defined('AADL_PRIVATE_DOWNLOAD_DIR') ? AADL_PRIVATE_DOWNLOAD_DIR : dirname(rtrim(ABSPATH,'/\\')).'/aap-private-desktop-installers';}
function aadl_encrypt($key){$iv=random_bytes(12);$tag='';$secret=hash('sha256',wp_salt('auth').'|aap-desktop-key-v1',true);$data=openssl_encrypt($key,'aes-256-gcm',$secret,OPENSSL_RAW_DATA,$iv,$tag);return aadl_b64($iv.$tag.$data);}
function aadl_decrypt($value){$b=aadl_unb64($value);if($b===false||strlen($b)<29)return '';return (string)openssl_decrypt(substr($b,28),'aes-256-gcm',hash('sha256',wp_salt('auth').'|aap-desktop-key-v1',true),OPENSSL_RAW_DATA,substr($b,0,12),substr($b,12,16));}
function aadl_key_generate($sku){$letters='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';$parts=[];for($j=0;$j<5;$j++){$s='';for($i=0;$i<5;$i++)$s.=$letters[ord(random_bytes(1))%strlen($letters)];$parts[]=$s;}return 'AAP-'.strtoupper($sku[0]).'-'.implode('-',$parts);}
function aadl_install(){
 global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$cc=$wpdb->get_charset_collate();
 dbDelta('CREATE TABLE '.aadl_table()." (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 sku varchar(12) NOT NULL,
 key_hash char(64) NOT NULL,
 key_cipher text NOT NULL,
 customer_id bigint(20) unsigned DEFAULT NULL,
 order_id bigint(20) unsigned DEFAULT NULL,
 source varchar(16) NOT NULL DEFAULT 'stock',
 device_hash char(64) DEFAULT NULL,
 device_label varchar(150) DEFAULT NULL,
 status varchar(12) NOT NULL DEFAULT 'available',
 issued_at datetime DEFAULT NULL,
 activated_at datetime DEFAULT NULL,
 last_seen_at datetime DEFAULT NULL,
 created_at datetime NOT NULL,
 PRIMARY KEY  (id), UNIQUE KEY key_hash (key_hash), KEY inventory (sku,status), KEY customer_id (customer_id)
 ) $cc;");
 foreach(['script','media','bundle'] as $sku){$count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.aadl_table().' WHERE sku=%s',$sku));for($i=$count;$i<50;$i++){do{$key=aadl_key_generate($sku);$hash=hash('sha256',strtoupper($key));$unique=!(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.aadl_table().' WHERE key_hash=%s',$hash));}while(!$unique);$wpdb->insert(aadl_table(),['sku'=>$sku,'key_hash'=>$hash,'key_cipher'=>aadl_encrypt($key),'created_at'=>gmdate('Y-m-d H:i:s')]);}}
 if(!get_option('aadl_signing_private_pem')){
  $rsa=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>3072]);
  if($rsa){openssl_pkey_export($rsa,$private);$details=openssl_pkey_get_details($rsa);add_option('aadl_signing_private_pem',$private,'','no');add_option('aadl_signing_public_pem',$details['key'],'','no');}
 }
 $dir=aadl_private_dir();if(!is_dir($dir))wp_mkdir_p($dir);if(is_dir($dir)){file_put_contents($dir.'/index.html','');file_put_contents($dir.'/.htaccess',"Deny from all\nRequire all denied\n");}
 update_option('aadl_schema',AADL_VERSION,false);
}
register_activation_hook(__FILE__,'aadl_install');
add_action('admin_init',function(){if(current_user_can('manage_options')&&get_option('aadl_schema')!==AADL_VERSION)aadl_install();});
function aadl_issue($customer_id,$sku,$order_id=0,$source='manual'){
 global $wpdb;if(!in_array($sku,['script','media','bundle'],true)||$customer_id<1)return new WP_Error('bad_request','Invalid key assignment');
 $existing=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.aadl_table()." WHERE customer_id=%d AND sku=%s AND status='issued' ORDER BY id LIMIT 1",$customer_id,$sku));if($existing)return $existing;
 for($i=0;$i<7;$i++){
  $id=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.aadl_table()." WHERE sku=%s AND status='available' ORDER BY id LIMIT 1",$sku));
  if(!$id)return new WP_Error('out_of_stock','No unused keys remaining for '.esc_html($sku));
  $affected=$wpdb->query($wpdb->prepare('UPDATE '.aadl_table()." SET status='issued',customer_id=%d,order_id=%d,source=%s,issued_at=%s WHERE id=%d AND status='available'",$customer_id,$order_id,$source,gmdate('Y-m-d H:i:s'),$id));
  if($affected===1)return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.aadl_table().' WHERE id=%d',$id));
 }
 return new WP_Error('try_again','Key inventory changed while issuing. Retry.');
}
function aadl_actor_for_customer($customer){return !empty($customer->wp_user_id)?(int)$customer->wp_user_id:1000000000+(int)$customer->id;}
function aadl_assign_purchases($customer){
 if(!$customer||!function_exists('aap_store_user_orders'))return;
 $orders=aap_store_user_orders(aadl_actor_for_customer($customer));$eligible=[];
 foreach($orders as $order){if(!in_array(get_post_meta($order->ID,'_aap_status',true),['paid_verified','complimentary'],true))continue;$sku=get_post_meta($order->ID,'_aap_sku',true);if(in_array($sku,['script','media','bundle'],true)&&!isset($eligible[$sku]))$eligible[$sku]=$order->ID;}
 if(isset($eligible['bundle'])){aadl_issue((int)$customer->id,'bundle',$eligible['bundle'],'purchase');return;}
 foreach(['script','media'] as $sku)if(isset($eligible[$sku]))aadl_issue((int)$customer->id,$sku,$eligible[$sku],'purchase');
}
function aadl_customer_keys($customer_id){global $wpdb;return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.aadl_table()." WHERE customer_id=%d AND status='issued' ORDER BY id",$customer_id));}
function aadl_compatible($sku,$app){return $sku===$app||$sku==='bundle';}
function aadl_customer_has_right($row){
 if($row->source!=='purchase')return true;
 $order=get_post((int)$row->order_id);
 return $order&&$order->post_type==='aap_shop_order'&&in_array(get_post_meta($order->ID,'_aap_status',true),['paid_verified','complimentary'],true);
}
function aadl_public_key(){return (string)get_option('aadl_signing_public_pem','');}
function aadl_sign($payload){$plain=aadl_b64(wp_json_encode($payload,JSON_UNESCAPED_SLASHES));$signature='';if(!openssl_sign($plain,$signature,get_option('aadl_signing_private_pem'),OPENSSL_ALGO_SHA256))return new WP_Error('server_key','Could not sign device licence');return ['payload'=>$plain,'signature'=>aadl_b64($signature)];}
function aadl_verified_token($token){if(!is_array($token)||empty($token['payload'])||empty($token['signature']))return null;$valid=openssl_verify((string)$token['payload'],aadl_unb64((string)$token['signature']),aadl_public_key(),OPENSSL_ALGO_SHA256);if($valid!==1)return null;$json=json_decode((string)aadl_unb64($token['payload']),true);return is_array($json)?$json:null;}
function aadl_err($s,$http=400){return new WP_Error('aadl_error',$s,['status'=>$http]);}
function aadl_rate_limit(){
 $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$k='aadl_attempt_'.md5($ip);$n=(int)get_transient($k);if($n>=30)return aadl_err('Too many activation attempts. Try again later.',429);set_transient($k,$n+1,HOUR_IN_SECONDS);return true;
}
function aadl_valid_device($device){return is_string($device)&&preg_match('/^[a-f0-9]{64}$/D',$device);}
function aadl_valid_identity($r,$c){$lower=function($x){$x=trim((string)$x);return function_exists('mb_strtolower')?mb_strtolower($x):strtolower($x);};return hash_equals(strtolower(trim((string)$c->email)),strtolower(trim((string)$r['email'])))&&$lower($c->display_name)===$lower($r['username']);}
function aadl_active_row($token,$device,$app){
 global $wpdb;$p=aadl_verified_token($token);if(!$p||!aadl_valid_device($device)||!hash_equals((string)($p['device']??''),hash('sha256',$device))||!aadl_compatible((string)($p['sku']??''),$app))return aadl_err('Invalid licence for this device',403);
 $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.aadl_table().' WHERE id=%d',(int)($p['id']??0)));
 if(!$row||$row->status!=='issued'||!hash_equals((string)$row->device_hash,(string)$p['device'])||(int)$p['customer_id']!==(int)$row->customer_id||!aadl_customer_has_right($row))return aadl_err('Licence inactive or revoked',403);
 return $row;
}
add_action('rest_api_init',function(){
 register_rest_route('aadl/v1','/activate',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($r){
  $lim=aadl_rate_limit();if(is_wp_error($lim))return $lim;
  if(!is_ssl())return aadl_err('Activation requires HTTPS',503);
  $sku=sanitize_key((string)$r->get_param('app'));$device=(string)$r->get_param('device');$key=strtoupper(trim((string)$r->get_param('key')));
  if(!in_array($sku,['script','media'],true)||!aadl_valid_device($device)||!preg_match('/^AAP-[SMB]-[A-Z2-9-]{29}$/D',$key))return aadl_err('Invalid activation details');
  global $wpdb;$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.aadl_table().' WHERE key_hash=%s',hash('sha256',$key)));
  if(!$row||$row->status!=='issued'||!aadl_compatible($row->sku,$sku))return aadl_err('Key not valid for this application',403);
  $c=function_exists('aap_auth_by_id')?aap_auth_by_id($row->customer_id):null;
  if(!$c||empty($c->verified_at)||!aadl_valid_identity(['email'=>$r->get_param('email'),'username'=>$r->get_param('username')],$c)||!aadl_customer_has_right($row))return aadl_err('Account name, email and issued key do not match',403);
  $device_hash=hash('sha256',$device);
  $ok=$wpdb->query($wpdb->prepare('UPDATE '.aadl_table().' SET device_hash=%s,device_label=%s,activated_at=IFNULL(activated_at,%s),last_seen_at=%s WHERE id=%d AND status=%s AND (device_hash IS NULL OR device_hash=%s)', $device_hash,sanitize_text_field(substr((string)$r->get_param('deviceName'),0,150)),gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s'),$row->id,'issued',$device_hash));
  if(!$ok){$test=$wpdb->get_var($wpdb->prepare('SELECT device_hash FROM '.aadl_table().' WHERE id=%d',$row->id));if(!hash_equals((string)$test,$device_hash))return aadl_err('This product key is already registered to another PC. Contact support for device transfer.',409);}
  $token=aadl_sign(['v'=>1,'id'=>(int)$row->id,'sku'=>$row->sku,'customer_id'=>(int)$c->id,'name'=>$c->display_name,'email'=>$c->email,'device'=>$device_hash,'issuedAt'=>gmdate('c'),'origin'=>'https://aapctrs.com/']);
  if(is_wp_error($token))return $token;
  return ['licence'=>$token,'publicKey'=>aadl_public_key(),'account'=>['name'=>$c->display_name,'email'=>$c->email],'deviceName'=>sanitize_text_field((string)$r->get_param('deviceName'))];
 }]);
 register_rest_route('aadl/v1','/status',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($r){
  $app=sanitize_key((string)$r->get_param('app'));$row=aadl_active_row($r->get_param('licence'),(string)$r->get_param('device'),$app);
  if(is_wp_error($row))return $row;global $wpdb;$wpdb->update(aadl_table(),['last_seen_at'=>gmdate('Y-m-d H:i:s')],['id'=>$row->id]);return ['active'=>true,'time'=>gmdate('c'),'sku'=>$row->sku];
 }]);
 register_rest_route('aadl/v1','/ai',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>'aadl_ai_proxy']);
});
function aadl_ai_proxy($r){
 $row=aadl_active_row($r->get_param('licence'),(string)$r->get_param('device'),'script');if(is_wp_error($row))return $row;
 $counter='aadl_ai_'.(int)$row->id.'_'.gmdate('Ymd');$n=(int)get_transient($counter);$limit=30;if($n>=$limit)return aadl_err('Daily AI usage limit reached',429);
 $key=function_exists('aacs_ai_key')?aacs_ai_key('gemini'):(string)get_option('aacs_gemini_key','');if(!$key)return aadl_err('A&A Writing Assistant is temporarily unavailable. Please contact support.',503);
 $action=sanitize_key((string)$r->get_param('action'));$guides=['ask'=>'Help the writer with a practical, helpful response','suggest'=>'Suggest improvements and why','rewrite'=>'Rewrite preserving meaning','continue'=>'Continue the passage','analyze'=>'Analyse structure pacing and clarity','brainstorm'=>'Generate diverse creative ideas','outline'=>'Build a useful outline','characters'=>'Develop credible characters','poetry'=>'Suggest poetry improvements','rhymes'=>'Suggest rhyme options','screenplay'=>'Improve screenplay scene structure','templates'=>'Draft structured content','style'=>'Improve tone and grammar'];if(!isset($guides[$action]))return aadl_err('Unknown AI action');
 $prompt=(string)$r->get_param('prompt');$ctx=(string)$r->get_param('context');if(strlen($prompt)>12000||strlen($ctx)>24000||(!$prompt&&!$ctx))return aadl_err('Please enter a shorter writing request');
 $opts=function_exists('aacs_options')?aacs_options():[];$model=isset($opts['gemini_model'])?(string)$opts['gemini_model']:'gemini-2.5-flash';if(!preg_match('/^gemini-[a-zA-Z0-9._-]+$/D',$model))return aadl_err('A&A AI service configuration is unavailable',503);
 $json=['systemInstruction'=>['parts'=>[['text'=>'You are A&A Creative Studio writing assistant. '.$guides[$action].'. Respect the writer voice.']]],'contents'=>[['role'=>'user','parts'=>[['text'=>"Request:\n".$prompt."\n\nContext:\n".$ctx]]]],'generationConfig'=>['maxOutputTokens'=>2400,'temperature'=>0.8]];
 $res=wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent',['timeout'=>50,'headers'=>['x-goog-api-key'=>$key,'Content-Type'=>'application/json'],'body'=>wp_json_encode($json)]);
 if(is_wp_error($res))return aadl_err('The A&A Writing Assistant is temporarily unavailable. Check your connection and try again.',502);
 $http=(int)wp_remote_retrieve_response_code($res);if($http===429)return aadl_err('The A&A Writing Assistant is busy. Please try again later.',429);if($http>=400)return aadl_err('The A&A Writing Assistant could not complete the request. Please try again later.',502);
 $data=json_decode(wp_remote_retrieve_body($res),true);$text='';foreach(($data['candidates']??[]) as $c){foreach(($c['content']['parts']??[]) as $p)$text.=(string)($p['text']??'');if($text)break;}
 if(!$text)return aadl_err('The A&A Writing Assistant returned no suggestions. Try another request.',502);set_transient($counter,$n+1,DAY_IN_SECONDS+HOUR_IN_SECONDS);return ['text'=>$text,'used'=>$n+1,'limit'=>$limit];
}
// Four installer slots. Legacy Windows options continue to work after upgrades.
function aadl_os_label($os){return $os==='mac'?'macOS (Apple)':'Windows';}
function aadl_file_option($app,$os){return 'aadl_file_'.$app.($os==='mac'?'_mac':'');}
function aadl_download_file($app,$os){
 if(!in_array($app,['script','media'],true)||!in_array($os,['win','mac'],true))return false;
 $file=(string)get_option(aadl_file_option($app,$os),'');
 $base=realpath(aadl_private_dir());
 if(!$file||!$base||basename($file)!==$file)return false;
 $path=realpath($base.DIRECTORY_SEPARATOR.$file);
 return $path&&strpos($path,$base.DIRECTORY_SEPARATOR)===0&&is_file($path)?$path:false;
}
function aadl_allowed_filename($name,$os){
 if(!$name||basename($name)!==$name)return false;
 return $os==='mac'?(bool)preg_match('/\.(dmg|pkg|zip)$/i',$name):(bool)preg_match('/\.(exe|msi|zip)$/i',$name);
}
function aadl_customer_apps($customer){
 if(!$customer)return [];
 aadl_assign_purchases($customer);
 $apps=[];
 foreach(aadl_customer_keys((int)$customer->id) as $row){
  if(!aadl_customer_has_right($row))continue;
  foreach(($row->sku==='bundle'?['script','media']:[$row->sku]) as $app){
   if(!isset($apps[$app]))$apps[$app]=$row;
  }
 }
 return $apps;
}
function aadl_download_buttons($app,$allowed){
 $out='<div class="aadl-download-buttons">';
 foreach(['win','mac'] as $os){
  $label='Download for '.aadl_os_label($os);
  if($allowed&&aadl_download_file($app,$os)){
   $url=wp_nonce_url(add_query_arg(['action'=>'aadl_download','app'=>$app,'os'=>$os],admin_url('admin-post.php')),'aadl_download_'.$app.'_'.$os);
   $out.='<a class="aadl-download-action" href="'.esc_url($url).'">'.esc_html($label).'</a>';
  }else{
   $reason=$allowed?'Installer not available yet':'Available after purchase';
   $out.='<span class="aadl-download-disabled" aria-disabled="true">'.esc_html(aadl_os_label($os)).' — '.esc_html($reason).'</span>';
  }
 }
 return $out.'</div>';
}
function aadl_ui_css(){return '<style>
.aadl-store,.aadl-my-apps{margin:24px 0;padding:25px;border:1px solid #9d763c;border-radius:14px;background:#16100c;color:#f7f0e4;font-family:inherit}
.aadl-store h2,.aadl-my-apps h2,.aadl-store h3,.aadl-my-apps h3{color:#f4cf80;margin-top:0}
.aadl-store p,.aadl-my-apps p{color:#e0d0b7;line-height:1.65}
.aadl-store-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(235px,1fr));gap:16px}
.aadl-store-item{border:1px solid #6f552b;border-radius:12px;background:#211711;padding:20px}
.aadl-download-buttons{display:flex;flex-wrap:wrap;gap:9px;margin-top:14px}
.aadl-download-action,.aadl-download-disabled{display:inline-block;font-size:14px;border-radius:6px;padding:10px 12px;text-decoration:none!important}
.aadl-download-action{background:linear-gradient(120deg,#b68031,#f6ce72);color:#1e1308!important;font-weight:700}
.aadl-download-action:hover{filter:brightness(1.1)}
.aadl-download-disabled{border:1px solid #6f5840;color:#ccbda8;background:#31251e}
.aadl-my-apps code{color:#f8df92;user-select:all;overflow-wrap:anywhere}
.aadl-small{font-size:13px;color:#d6c3a0}
</style>';}
function aadl_my_apps(){
 $c=function_exists('aap_auth_customer')?aap_auth_customer():null;
 $s=aadl_ui_css().'<section class="aadl-my-apps"><h2>Desktop applications — your downloads</h2>';
 if(!$c)return $s.'<p>Sign in to your verified A&A Pictures customer account to view your licence keys and installers.</p><p><a href="'.esc_url(home_url('/aa-apps/')).'">Open My Apps</a></p></section>';
 $apps=aadl_customer_apps($c);
 $s.='<p>Windows installers (.exe) and macOS installers (.dmg) become available here when released. First activation needs internet; local writing and production work offline. AI assistance uses internet.</p>';
 if(!$apps)$s.='<p>No eligible desktop licence yet. Complete a verified purchase or contact A&A support.</p>';
 foreach($apps as $app=>$row){
  $key=aadl_decrypt($row->key_cipher);if(!$key)continue;
  $s.='<div class="aadl-store-item" style="margin:12px 0"><h3>'.esc_html($app==='script'?'Script Studio':'Media Production Studio').'</h3><p>Registered user: '.esc_html($c->display_name).'<br>Account email: '.esc_html($c->email).'<br>Product key: <code>'.esc_html($key).'</code><br>Device: '.esc_html($row->device_label?:'Not activated').'</p>';
  $s.=aadl_download_buttons($app,true).'</div>';
 }
 return $s.'<p class="aadl-small">Your licence works on one registered computer. Bundle licences unlock both applications on that computer. Device transfers require support approval.</p></section>';
}
function aadl_storefront_section(){
 $c=function_exists('aap_auth_customer')?aap_auth_customer():null;
 $apps=$c?aadl_customer_apps($c):[];
 $s=aadl_ui_css().'<section class="aadl-store" id="aa-desktop-downloads"><h2>Windows &amp; Mac desktop applications</h2><p>Purchase using the A&A Pictures Store checkout. After your payment is verified, access your licence and installers from <a href="'.esc_url(home_url('/aa-apps/')).'">My Apps</a>. No Python installation is needed.</p><div class="aadl-store-grid">';
 foreach(['script'=>'Script Studio','media'=>'Media Production Studio'] as $app=>$name){
  $has=isset($apps[$app]);
  $s.='<article class="aadl-store-item"><h3>'.esc_html($name).'</h3><p>'.($has?'Your licence is active for this application.':'Buy this application (or the bundle) using the Store purchase options to unlock your downloads.').'</p>';
  $s.=aadl_download_buttons($app,$has).'</article>';
 }
 return $s.'</div><p class="aadl-small">Bundle purchasers receive both apps for their registered computer. macOS buttons activate after a Mac installer is uploaded by the administrator. Public downloads should be Apple-signed and notarized.</p></section>';
}
add_shortcode('aap_desktop_downloads','aadl_my_apps');
add_shortcode('aap_desktop_store','aadl_storefront_section');
add_filter('the_content',function($content){
 if(!is_main_query()||!in_the_loop()||!is_page()||has_shortcode($content,'aap_desktop_store'))return $content;
 $selected=(int)get_option('aadl_store_page_id',0);
 $is_store=$selected?is_page($selected):is_page(['store','app-store','aa-app-store','aap-store','aa-store']);
 return $is_store?$content.aadl_storefront_section():$content;
},36);
add_filter('the_content',function($content){if(is_page('aa-apps')&&is_main_query()&&in_the_loop()&&!has_shortcode($content,'aap_desktop_downloads'))return $content.aadl_my_apps();return $content;},35);
function aadl_download_handler(){
 if(!function_exists('aap_auth_customer'))wp_die('Customer accounts unavailable',403);
 $c=aap_auth_customer();
 $app=sanitize_key((string)($_GET['app']??''));
 $os=sanitize_key((string)($_GET['os']??'win'));
 if(!$c||!in_array($app,['script','media'],true)||!in_array($os,['win','mac'],true))wp_die('Sign in and select a valid application',403);
 check_admin_referer('aadl_download_'.$app.'_'.$os);
 $apps=aadl_customer_apps($c);
 if(!isset($apps[$app]))wp_die('No valid desktop licence for this application',403);
 $path=aadl_download_file($app,$os);
 if(!$path)wp_die('Installer is not available yet. Contact A&A support.',404);
 $filename=basename($path);
 nocache_headers();
 header('Content-Type: application/octet-stream');
 header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'',$filename).'"');
 header('Content-Length: '.filesize($path));
 header('X-Content-Type-Options: nosniff');
 header('Cache-Control: private, no-store, no-cache, must-revalidate');
 while(ob_get_level())ob_end_clean();
 $fh=fopen($path,'rb');if(!$fh)wp_die('Unable to read installer',503);
 while(!feof($fh)){echo fread($fh,1024*1024);@flush();if(connection_aborted())break;}
 fclose($fh);exit;
}
add_action('admin_post_aadl_download','aadl_download_handler');
add_action('admin_post_nopriv_aadl_download','aadl_download_handler');
add_action('admin_post_aadl_public_key',function(){if(!current_user_can('manage_options'))wp_die('Forbidden',403);check_admin_referer('aadl_export_public_key');$pem=aadl_public_key();if(!$pem)wp_die('Generate signing key by activating this plugin first');nocache_headers();header('Content-Type: application/x-pem-file');header('Content-Disposition: attachment; filename=aa-desktop-public-key.pem');header('X-Content-Type-Options: nosniff');echo $pem;exit;});
add_action('admin_menu',function(){add_submenu_page('aap-app-orders','Desktop Licences & Downloads','Desktop Licences','manage_options','aadl-desktop','aadl_admin');});
add_action('admin_post_aadl_admin',function(){
 if(!current_user_can('manage_options'))wp_die('Not permitted',403);check_admin_referer('aadl_admin');$do=sanitize_key((string)($_POST['do']??''));global $wpdb;$msg='Settings updated';
 if($do==='assign'){
  $email=sanitize_email((string)($_POST['email']??''));$c=function_exists('aap_auth_get')?aap_auth_get($email):null;$sku=sanitize_key((string)($_POST['sku']??''));$res=$c&&!empty($c->verified_at)?aadl_issue((int)$c->id,$sku,0,'manual'):new WP_Error('not_found','Customer not found or email unverified');$msg=is_wp_error($res)?$res->get_error_message():'Issued licence #'.$res->id.' to '.$email;
 }elseif(in_array($do,['revoke','reset'],true)){
  $id=absint($_POST['id']??0);$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.aadl_table().' WHERE id=%d',$id));if($row){if($do==='revoke'){$wpdb->update(aadl_table(),['status'=>'revoked'],['id'=>$id]);$msg='Key revoked (previously activated PC can still be used without internet).';}elseif($row->status==='issued'){$wpdb->update(aadl_table(),['device_hash'=>null,'device_label'=>null,'activated_at'=>null],['id'=>$id]);$msg='Device binding reset. Previously offline devices cannot be remotely disabled.';}}
 }elseif($do==='setfiles'){
  $dir=realpath(aadl_private_dir());if(!$dir)wp_die('Private downloads folder unavailable',503);
  $problems=[];
  foreach(['script','media'] as $app)foreach(['win','mac'] as $os){
   $field='file_'.$app.'_'.$os;
   $filename=sanitize_file_name((string)($_POST[$field]??''));
   if($filename&&!aadl_allowed_filename($filename,$os)){$problems[]='Invalid '.$app.' '.$os.' installer extension';continue;}
   if($filename&&!is_file($dir.DIRECTORY_SEPARATOR.$filename)){$problems[]='Installer missing: '.$filename;continue;}
   update_option(aadl_file_option($app,$os),$filename,false);
  }
  $msg=$problems?implode('; ',$problems):'Installer filenames saved';
 }elseif($do==='setstore'){
  $page=absint($_POST['store_page_id']??0);
  if($page&&get_post_type($page)!=='page')$msg='Choose a valid WordPress page';
  else{update_option('aadl_store_page_id',$page,false);$msg='Store page selection saved';}
 }elseif($do==='upload'){
  $app=sanitize_key((string)($_POST['app']??''));
  $os=sanitize_key((string)($_POST['os']??''));
  $upload=$_FILES['installer']??null;
  $dir=realpath(aadl_private_dir());
  if(!in_array($app,['script','media'],true)||!in_array($os,['win','mac'],true)||!$upload||$upload['error']!==UPLOAD_ERR_OK||!$dir){$msg='Upload failed. Check PHP upload size and folder permissions.';}
  else{
   $name=sanitize_file_name($upload['name']);
   if(!aadl_allowed_filename($name,$os))$msg='Invalid installer type for '.aadl_os_label($os);
   elseif(!is_uploaded_file($upload['tmp_name']))$msg='Invalid uploaded file';
   elseif(!move_uploaded_file($upload['tmp_name'],$dir.DIRECTORY_SEPARATOR.$name))$msg='Cannot write installer to private folder';
   else{update_option(aadl_file_option($app,$os),$name,false);$msg='Installer uploaded: '.$app.' for '.aadl_os_label($os);}
  }

 }
 wp_safe_redirect(add_query_arg('aadl_msg',rawurlencode($msg),admin_url('admin.php?page=aadl-desktop')));exit;
});
function aadl_admin(){if(!current_user_can('manage_options'))return;global $wpdb;$counts=$wpdb->get_results('SELECT sku,status,COUNT(*) total FROM '.aadl_table().' GROUP BY sku,status');$keys=$wpdb->get_results('SELECT k.*,c.email,c.display_name FROM '.aadl_table().' k LEFT JOIN '.(function_exists('aap_auth_table')?aap_auth_table():$wpdb->prefix.'aap_customers').' c ON c.id=k.customer_id WHERE k.status!=\'available\' ORDER BY k.id DESC LIMIT 250');$base=aadl_private_dir();echo '<div class="wrap"><h1>A&A Desktop Licences & Downloads</h1><p><strong>150 unique keys:</strong> 50 Script, 50 Media, 50 Bundle. Keys are never reassigned to a different customer. One computer per key, same computer for bundle.</p>';if(isset($_GET['aadl_msg']))echo '<div class="notice notice-info"><p>'.esc_html(wp_unslash($_GET['aadl_msg'])).'</p></div>';echo '<h2>Inventory</h2><table class="widefat striped"><tr><th>Product</th><th>Status</th><th>Count</th></tr>';foreach($counts as $c)echo '<tr><td>'.esc_html($c->sku).'</td><td>'.esc_html($c->status).'</td><td>'.intval($c->total).'</td></tr>';echo '</table>';
 echo '<h2>Desktop signing key</h2><p>Download the PUBLIC key and embed it in Windows and signed macOS builds. Never include the server private key in an app. SHA-256 fingerprint: <code>'.esc_html(hash('sha256',aadl_public_key())).'</code></p><p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=aadl_public_key'),'aadl_export_public_key')).'">Download public key (.pem)</a></p>';
 echo '<h2>Issue licence manually</h2><p>Use only for verified customers entitled to a complimentary or paid desktop licence. Verified paid browser-app purchases are issued automatically on My Apps.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('aadl_admin','_wpnonce',true,false).'<input type="hidden" name="action" value="aadl_admin"><input type="hidden" name="do" value="assign"><input name="email" type="email" required placeholder="Verified customer email" size="35"><select name="sku"><option value="script">Script Studio</option><option value="media">Media Production</option><option value="bundle">Both — bundle</option></select><button class="button button-primary">Issue unused key</button></form>';
 echo '<h2>Windows &amp; macOS installer storage</h2><p>Protected server folder (outside web root): <code>'.esc_html($base).'</code>. Upload via this form or Hostinger File Manager. Do not place installers in the public Media Library. Windows: EXE/MSI/ZIP; macOS: DMG/PKG/ZIP.</p>';
 echo '<table class="widefat striped"><thead><tr><th>Application</th><th>Platform</th><th>Current file</th><th>Availability</th></tr></thead><tbody>';
 foreach(['script'=>'Script Studio','media'=>'Media Production Studio'] as $app=>$name)foreach(['win'=>'Windows','mac'=>'macOS (Apple)'] as $os=>$label){
  echo '<tr><td>'.esc_html($name).'</td><td>'.esc_html($label).'</td><td><code>'.esc_html((string)get_option(aadl_file_option($app,$os),'' )).'</code></td><td>'.(aadl_download_file($app,$os)?'Ready':'Not uploaded').'</td></tr>';
 }
 echo '</tbody></table>';
 echo '<h3>Assign filenames already uploaded by Hostinger File Manager</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('aadl_admin','_wpnonce',true,false).'<input type="hidden" name="action" value="aadl_admin"><input type="hidden" name="do" value="setfiles">';
 foreach(['script'=>'Script Studio','media'=>'Media Production'] as $app=>$name)foreach(['win'=>'Windows','mac'=>'Mac'] as $os=>$label){$option=aadl_file_option($app,$os);echo '<p><label>'.esc_html($name.' — '.$label).' <input name="file_'.esc_attr($app.'_'.$os).'" value="'.esc_attr((string)get_option($option,'' )).'" size="50"></label></p>';}
 echo '<button class="button">Save installer filenames</button></form>';
 echo '<h3>Upload a Windows or Mac installer</h3><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('aadl_admin','_wpnonce',true,false).'<input type="hidden" name="action" value="aadl_admin"><input type="hidden" name="do" value="upload"><select name="app"><option value="script">Script Studio</option><option value="media">Media Production</option></select> <select name="os"><option value="win">Windows</option><option value="mac">macOS / Apple</option></select> <input type="file" name="installer" accept=".exe,.msi,.dmg,.pkg,.zip" required> <button class="button">Upload installer</button></form>';
 echo '<h2>Store page integration</h2><p>Choose the existing Store page to automatically show the Windows &amp; Mac download section below its product checkout. Alternatively insert <code>[aap_desktop_store]</code> into any WordPress page. Existing checkout and order logic are unchanged.</p>';
 echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('aadl_admin','_wpnonce',true,false).'<input type="hidden" name="action" value="aadl_admin"><input type="hidden" name="do" value="setstore">';
 wp_dropdown_pages(['name'=>'store_page_id','selected'=>(int)get_option('aadl_store_page_id',0),'show_option_none'=>'Auto-detect common Store page names','option_none_value'=>0]);
 echo ' <button class="button button-primary">Save Store page</button></form>';
 echo '<h2>Issued licences</h2><table class="widefat striped"><thead><tr><th>ID</th><th>Customer</th><th>SKU</th><th>Product key</th><th>Device</th><th>Status</th><th>Actions</th></tr></thead><tbody>';foreach($keys as $k){$key=aadl_decrypt($k->key_cipher);echo '<tr><td>#'.(int)$k->id.'</td><td>'.esc_html(($k->display_name?:'').' / '.($k->email?:'')).'</td><td>'.esc_html($k->sku).'</td><td><code>'.esc_html($key).'</code></td><td>'.esc_html($k->device_label?:'Not activated').'</td><td>'.esc_html($k->status).'</td><td>';foreach(['reset'=>'Reset device','revoke'=>'Revoke'] as $a=>$title)if(($a==='reset'&&$k->status==='issued')||($a==='revoke'&&$k->status==='issued'))echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline">'.wp_nonce_field('aadl_admin','_wpnonce',true,false).'<input type="hidden" name="action" value="aadl_admin"><input type="hidden" name="do" value="'.$a.'"><input type="hidden" name="id" value="'.(int)$k->id.'"><button class="button" onclick="return confirm(\'Confirm '.$a.'?\')">'.$title.'</button></form> ';echo '</td></tr>';}echo '</tbody></table>';
 echo '<p><strong>Security:</strong> RSA signing private key is stored only in protected WordPress options. Back up your WP database and auth salts. Your AI service key stays on WordPress; desktop clients access a rate-limited proxy. Offline licences cannot receive remote revocation until the PC connects.</p></div>';
}
