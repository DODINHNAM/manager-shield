<?php
// Run with: php tests/standard.php
const ABSPATH = __DIR__;
const DAY_IN_SECONDS = 86400;
class JsonResult extends Exception { public $data; public function __construct($data) {$this->data=$data;} }
function add_action($name, $handler) {$GLOBALS['handler']=$handler;}
function wp_send_json($data, $status=200) {throw new JsonResult($data);}
function sanitize_text_field($v) {return (string)$v;}
function wp_unslash($v) {return $v;}
function esc_url_raw($v) {return $v;}
function absint($v) {return abs((int)$v);}
function wp_parse_url($v,$part) {return parse_url($v,$part);}
function wplazy_paypal_require_allowed_merchant() {}
function wp_generate_password() {return 'random-state';}
function home_url() {return 'https://proxy.example/';}
function add_query_arg($a,$b,$c=null) {if (!is_array($a)) {$a=[$a=>$b];$b=$c;} return $b . (str_contains($b,'?')?'&':'?') . http_build_query($a);}
function set_transient($k,$v,$ttl) {$GLOBALS['store'][$k]=$v;return true;}
function get_transient($k) {return $GLOBALS['store'][$k]??false;}
function is_wp_error($v) {return false;}
function wp_die($v,...$args) {throw new JsonResult(['denied'=>true]);}
function wp_redirect($url) {throw new JsonResult(['redirect'=>$url]);}
function call_paypal_api($data,$method,$url) {$GLOBALS['calls'][]=[$method,$url,$data];return array_shift($GLOBALS['responses']);}
function wplazy_paypal_proxy_payment_data($v,$action) {return [];}
function wplazy_record_payment_event($data) {}
require __DIR__ . '/../inc/standard.php';
function run_case($query,$responses=[]) {$_GET=$query;$_SERVER['REQUEST_METHOD']='POST';$GLOBALS['responses']=$responses;$GLOBALS['calls']=[];try {($GLOBALS['handler'])();}catch(JsonResult $r){return $r->data;}return [];}
function check($condition,$label) {if (!$condition) throw new Exception($label);echo "PASS $label\n";}
$base=['merchant_site'=>'https://merchant.example/','order_id'=>12,'order_key'=>'wc_secret'];
$amount=['currency_code'=>'USD','value'=>'10.00'];
$create=$base+['lazy-process'=>1,'request_type'=>'get_redirect_url','intent'=>'CAPTURE','purchase_units'=>['amount'=>$amount]];
$r=run_case($create,[['id'=>'PP1','links'=>[['rel'=>'payer-action','href'=>'https://www.paypal.com/checkoutnow?token=PP1']]]]);
check(($r['status']??'')==='success','create and approval URL');
check(str_contains($GLOBALS['calls'][0][2]['payment_source']['paypal']['experience_context']['return_url'],'proxy.example'),'return through proxy');
$r=run_case(['lazy-standard-return'=>1,'state'=>'random-state','token'=>'PP1','cancel'=>1]);
check(str_contains($r['redirect']??'','cancel=1') && str_contains($r['redirect']??'','key=wc_secret'),'cancel returns to matching merchant order');
$r=run_case(['lazy-standard-return'=>1,'state'=>'random-state','token'=>'WRONG']);
check(!empty($r['denied']),'reject wrong return token');
$approved=['id'=>'PP1','intent'=>'CAPTURE','status'=>'APPROVED','purchase_units'=>[['amount'=>$amount]]];
$completed=$approved;$completed['purchase_units'][0]['payments']['captures']=[['id'=>'TX1','status'=>'COMPLETED']];
$capture=$base+['lazy-pp-capture-payment'=>1,'payment_id'=>'PP1'];
$r=run_case($capture,[$approved,[], $completed]);
check(($r['transaction_id']??'')==='TX1','capture after approval');
$r=run_case($capture,[$completed]);
check(($r['success']??false) && count($GLOBALS['calls'])===1,'replay reads existing payment without capture');
$bad=$capture;$bad['order_key']='wrong';$r=run_case($bad);
check(empty($r['success']) && !$GLOBALS['calls'],'reject mismatched order key before API');
$pending=$completed;$pending['purchase_units'][0]['payments']['captures'][0]['status']='PENDING';
$r=run_case($capture,[$pending]);check(empty($r['success']),'pending capture is not success');
$r=run_case($create,[null]);check(($r['status']??'')==='failed','invalid create response');
$auth=$create;$auth['intent']='AUTHORIZE';
run_case($auth,[['id'=>'PP2','links'=>[['rel'=>'approve','href'=>'https://www.paypal.com/checkoutnow?token=PP2']]]]);
$a=$approved;$a['id']='PP2';$a['intent']='AUTHORIZE';
$done=$a;$done['purchase_units'][0]['payments']['authorizations']=[['id'=>'AUTH1','status'=>'CREATED']];
$r=run_case($base+['lazy-pp-authorize-payment'=>1,'payment_id'=>'PP2'],[$a,[], $done]);
check(($r['transaction_id']??'')==='AUTH1','authorize after approval');
$r=run_case(['checkout'=>'yes']);check(!$r && !$GLOBALS['calls'],'Smart Button checkout not intercepted');
