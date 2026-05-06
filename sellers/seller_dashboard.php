<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_execution_time', 120);
ini_set('memory_limit', '128M');

$db = getDB();
$check_file_path = $db->query("SHOW COLUMNS FROM admin_messages LIKE 'file_path'");
if (!$check_file_path || $check_file_path->num_rows === 0) $db->query("ALTER TABLE admin_messages ADD COLUMN file_path VARCHAR(500) DEFAULT NULL AFTER message");
$check_file_type = $db->query("SHOW COLUMNS FROM admin_messages LIKE 'file_type'");
if (!$check_file_type || $check_file_type->num_rows === 0) $db->query("ALTER TABLE admin_messages ADD COLUMN file_type VARCHAR(100) DEFAULT NULL AFTER file_path");
$check_locked = $db->query("SHOW COLUMNS FROM sellers LIKE 'locked'");
if (!$check_locked || $check_locked->num_rows === 0) $db->query("ALTER TABLE sellers ADD COLUMN locked TINYINT(1) NOT NULL DEFAULT 0");
$check_contract_signed = $db->query("SHOW COLUMNS FROM sellers LIKE 'contract_signed'");
if (!$check_contract_signed || $check_contract_signed->num_rows === 0) $db->query("ALTER TABLE sellers ADD COLUMN contract_signed TINYINT(1) NOT NULL DEFAULT 0");
$check_contract_signed_at = $db->query("SHOW COLUMNS FROM sellers LIKE 'contract_signed_at'");
if (!$check_contract_signed_at || $check_contract_signed_at->num_rows === 0) $db->query("ALTER TABLE sellers ADD COLUMN contract_signed_at DATETIME DEFAULT NULL");
$db->query("CREATE TABLE IF NOT EXISTS admin_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$new_columns = ["profile_completed TINYINT(1) NOT NULL DEFAULT 0","shop_logo VARCHAR(255) DEFAULT NULL","shop_banner VARCHAR(255) DEFAULT NULL","bank_account_details TEXT DEFAULT NULL","business_type VARCHAR(100) DEFAULT NULL","tax_info VARCHAR(255) DEFAULT NULL","alt_phone VARCHAR(50) DEFAULT NULL","whatsapp VARCHAR(50) DEFAULT NULL","emergency_contact VARCHAR(100) DEFAULT NULL","agreement_accepted TINYINT(1) NOT NULL DEFAULT 0","agreement_accepted_at DATETIME DEFAULT NULL","bank_holder_name VARCHAR(255) DEFAULT NULL","bank_cheque_image VARCHAR(255) DEFAULT NULL"];
foreach ($new_columns as $col_def) { $col_name = explode(' ', trim($col_def))[0]; $check_col = $db->query("SHOW COLUMNS FROM sellers LIKE '$col_name'"); if (!$check_col || $check_col->num_rows === 0) $db->query("ALTER TABLE sellers ADD COLUMN $col_def"); }

// ========== AJAX ==========
if (isset($_GET['ajax'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    set_error_handler(function($errno,$errstr,$errfile,$errline){echo json_encode(['error'=>"PHP Error: $errstr in $errfile on line $errline"]);exit;});
    $db = getDB();
    $seller_id = (int)($_SESSION['seller_id'] ?? 0);
    if (!$seller_id) { echo json_encode(['error'=>'Not logged in']); exit; }

    if ($_GET['ajax'] === 'get_messages') {
        $last_id = (int)($_GET['last_id'] ?? 0);
        $stmt = $db->prepare("SELECT id, seller_id, message, file_path, file_type, is_admin, type, is_seen, created_at, (TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 5 AND is_admin = 0) AS can_edit_delete FROM admin_messages WHERE seller_id = ? AND id > ? ORDER BY id ASC");
        $stmt->bind_param('ii', $seller_id, $last_id); $stmt->execute();
        $res = $stmt->get_result(); $msgs = [];
        while ($m = $res->fetch_assoc()) {
            $m['created_at'] = date('d M, g:i A', strtotime($m['created_at']));
            $m['can_edit_delete'] = (bool)$m['can_edit_delete']; $m['is_admin'] = (bool)$m['is_admin']; $m['is_seen'] = (bool)$m['is_seen'];
            if (!empty($m['file_path'])) { $fp = $m['file_path']; if (substr($fp,0,1)!='/') $fp='/'.$fp; $m['file_url']=$fp; $m['file_name']=basename($fp); if (empty($m['file_type'])) { $ext=strtolower(pathinfo($fp,PATHINFO_EXTENSION)); $m['file_type']=in_array($ext,['jpg','jpeg','png','gif','webp','bmp','svg'])?'image/'.$ext:'application/octet-stream'; } } else { $m['file_url']=$m['file_name']=$m['file_type']=null; }
            $msgs[] = $m;
        }
        $stmt->close();
        $db->query("UPDATE admin_messages SET is_seen=1 WHERE seller_id=$seller_id AND is_admin=1 AND is_seen=0");
        echo json_encode(['messages'=>$msgs]); exit;
    }
    if ($_GET['ajax'] === 'send_message') {
        $message=trim($_POST['message']??''); $send_sms=!empty($_POST['send_sms']); $file_path=null; $file_type=null;
        $upload_dir=__DIR__.'/chat_uploads/'; if (!is_dir($upload_dir)) mkdir($upload_dir,0755,true);
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error']===UPLOAD_ERR_OK) {
            $ext=strtolower(pathinfo(basename($_FILES['attachment']['name']),PATHINFO_EXTENSION));
            if (!in_array($ext,['jpg','jpeg','png','gif','pdf','doc','docx','txt','zip','mp4','webm','webp'])){echo json_encode(['error'=>'File type not allowed.']);exit;}
            if ($_FILES['attachment']['size']>5*1024*1024){echo json_encode(['error'=>'Max 5MB']);exit;}
            $safe_name=time().'_'.bin2hex(random_bytes(8)).'.'.$ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'],$upload_dir.$safe_name)){$file_path=dirname($_SERVER['SCRIPT_NAME']).'/chat_uploads/'.$safe_name;$file_type=$_FILES['attachment']['type'];}else{echo json_encode(['error'=>'Upload failed']);exit;}
        } elseif (isset($_FILES['attachment'])&&$_FILES['attachment']['error']!==UPLOAD_ERR_NO_FILE){echo json_encode(['error'=>'Upload error: '.$_FILES['attachment']['error']]);exit;}
        if (empty($message)&&!$file_path){echo json_encode(['error'=>'Empty message']);exit;}
        $type=$send_sms?'sms':'message';
        $stmt=$db->prepare("INSERT INTO admin_messages (seller_id,message,file_path,file_type,is_admin,type) VALUES (?,?,?,?,0,?)");
        $stmt->bind_param('issss',$seller_id,$message,$file_path,$file_type,$type);
        if ($stmt->execute()){$new_id=$db->insert_id;$stmt->close();echo json_encode(['success'=>true,'message_id'=>$new_id,'file_url'=>$file_path,'file_name'=>$file_path?basename($file_path):null,'file_type'=>$file_type]);}
        else echo json_encode(['success'=>false,'error'=>$db->error]); exit;
    }
    if ($_GET['ajax']==='delete_message'){$msg_id=(int)($_POST['message_id']??0);if(!$msg_id){echo json_encode(['success'=>false]);exit;}$stmt=$db->prepare("DELETE FROM admin_messages WHERE id=? AND seller_id=? AND is_admin=0 AND TIMESTAMPDIFF(MINUTE,created_at,NOW())<5");$stmt->bind_param('ii',$msg_id,$seller_id);$stmt->execute();$ok=$stmt->affected_rows>0;$stmt->close();echo json_encode(['success'=>$ok]);exit;}
    if ($_GET['ajax']==='edit_message'){$msg_id=(int)($_POST['message_id']??0);$message=trim($_POST['message']??'');if(!$msg_id||!$message){echo json_encode(['success'=>false]);exit;}$stmt=$db->prepare("UPDATE admin_messages SET message=? WHERE id=? AND seller_id=? AND is_admin=0 AND TIMESTAMPDIFF(MINUTE,created_at,NOW())<5");$stmt->bind_param('sii',$message,$msg_id,$seller_id);$stmt->execute();$ok=$stmt->affected_rows>0;$stmt->close();echo json_encode(['success'=>$ok]);exit;}
    if ($_GET['ajax']==='update_typing'){$is_typing=!empty($_POST['typing'])?1:0;$db->query("INSERT INTO admin_typing (seller_id,is_admin,typing,updated_at) VALUES ($seller_id,0,$is_typing,NOW()) ON DUPLICATE KEY UPDATE typing=VALUES(typing),updated_at=NOW()");echo json_encode(['success'=>true]);exit;}
    if ($_GET['ajax']==='get_typing'){$res=$db->query("SELECT typing,updated_at FROM admin_typing WHERE seller_id=$seller_id AND is_admin=1");$row=$res?$res->fetch_assoc():null;$typing=$row&&$row['typing']&&strtotime($row['updated_at'])>time()-5;echo json_encode(['typing'=>$typing]);exit;}
    if ($_GET['ajax']==='sign_contract'){$now=date('Y-m-d H:i:s');$stmt=$db->prepare("UPDATE sellers SET contract_signed=1,contract_signed_at=? WHERE id=? AND contract_signed=0");$stmt->bind_param('si',$now,$seller_id);if($stmt->execute()&&$stmt->affected_rows>0)echo json_encode(['success'=>true,'signed_at'=>$now]);else echo json_encode(['success'=>false,'message'=>'Already signed or update failed']);$stmt->close();exit;}
    echo json_encode(['error'=>'Unknown action']); exit;
}

if (isset($_GET['delete'])) {
    $seller_id=(int)($_SESSION['seller_id']??0);
    if ($seller_id){$db=getDB();$lock_check=$db->prepare("SELECT locked FROM sellers WHERE id=?");$lock_check->bind_param('i',$seller_id);$lock_check->execute();$lock_result=$lock_check->get_result()->fetch_assoc();$lock_check->close();if(!$lock_result||$lock_result['locked']!=1){$id=(int)$_GET['delete'];$stmt=$db->prepare("DELETE FROM jerseys WHERE id=? AND seller_id=?");$stmt->bind_param('ii',$id,$seller_id);$stmt->execute();$stmt->close();}}
    header('Location: seller_dashboard.php'); exit;
}

require_once __DIR__ . '/../sellers/sidenav.php';
if (!isset($_SESSION['seller_id'])) { header('Location: register.php'); exit; }

$db = getDB(); $seller_id = (int)$_SESSION['seller_id'];
$stmt = $db->prepare("SELECT full_name,shop_name,status,locked,contract_signed,contract_signed_at,email,phone,shop_category,pan_number,shop_address,shop_description,created_at,nagarikta_front,nagarikta_back,passport_photo,admin_signature,admin_stamp,admin_remarks,profile_completed,shop_logo,shop_banner,bank_account_details,business_type,tax_info,alt_phone,whatsapp,emergency_contact,bank_holder_name,bank_cheque_image FROM sellers WHERE id=?");
$stmt->bind_param('i',$seller_id); $stmt->execute(); $seller=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$seller){session_destroy();header('Location: register.php');exit;}

$is_locked         = !empty($seller['locked'])&&$seller['locked']==1;
$seller_name       = $seller['full_name'];
$shop_name         = $seller['shop_name'];
$status            = $seller['status']??'pending';
$profile_completed = (bool)($seller['profile_completed']??0);
$is_verified       = ($profile_completed&&$status==='approved'&&!$is_locked);
$_SESSION['status']= $status;

$passport_img  = !empty($seller['passport_photo'])?"../publics/uploads/passport/".htmlspecialchars($seller['passport_photo']):"https://ui-avatars.com/api/?background=C0392B&color=fff&name=".urlencode($seller['full_name']);
$signature_img = !empty($seller['admin_signature'])?"../publics/uploads/admin_signatures/".htmlspecialchars($seller['admin_signature']):"";
$stamp_img     = !empty($seller['admin_stamp'])?"../publics/uploads/admin_stamps/".htmlspecialchars($seller['admin_stamp']):"";
$admin_remarks = htmlspecialchars($seller['admin_remarks']??'');
$showOfficialDocBtn = ($status==='approved'&&!$is_locked&&(!empty($seller['admin_signature'])||!empty($seller['admin_stamp'])));

$global_rules_pdf=null;
$stmt=$db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key='seller_rules_pdf'");
$stmt->execute();$row=$stmt->get_result()->fetch_assoc();if($row&&!empty($row['setting_value']))$global_rules_pdf=$row['setting_value'];$stmt->close();

$total_jerseys=$total_sell=$total_top=0;
$chartLabels=[];$chartData=[];$recentJerseys=[];$hasCreatedAt=false;$category_counts=[];

if ($is_verified) {
    $stmt=$db->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id=?");$stmt->bind_param('i',$seller_id);$stmt->execute();$total_jerseys=$stmt->get_result()->fetch_assoc()['cnt']??0;$stmt->close();
    $stmt=$db->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id=? AND sell='Yes'");$stmt->bind_param('i',$seller_id);$stmt->execute();$total_sell=$stmt->get_result()->fetch_assoc()['cnt']??0;$stmt->close();
    $stmt=$db->prepare("SELECT COUNT(*) as cnt FROM jerseys WHERE seller_id=? AND is_top=1");$stmt->bind_param('i',$seller_id);$stmt->execute();$total_top=$stmt->get_result()->fetch_assoc()['cnt']??0;$stmt->close();

    $catQuery=$db->prepare("SELECT LOWER(TRIM(sport_type)) as type,COUNT(*) as count FROM jerseys WHERE seller_id=? AND sport_type IS NOT NULL AND sport_type!='' GROUP BY LOWER(TRIM(sport_type))");
    $catQuery->bind_param('i',$seller_id);$catQuery->execute();$catRes=$catQuery->get_result();$foundCategories=false;
    while ($rowCat=$catRes->fetch_assoc()){$foundCategories=true;$rawType=$rowCat['type'];if(strpos($rawType,'jersey')!==false)$category_counts['Jersey']=($category_counts['Jersey']??0)+$rowCat['count'];elseif(strpos($rawType,'bat')!==false)$category_counts['Bat']=($category_counts['Bat']??0)+$rowCat['count'];elseif(strpos($rawType,'ball')!==false)$category_counts['Ball']=($category_counts['Ball']??0)+$rowCat['count'];elseif(strpos($rawType,'shoe')!==false||strpos($rawType,'boot')!==false)$category_counts['Shoes']=($category_counts['Shoes']??0)+$rowCat['count'];else $category_counts['Accessory']=($category_counts['Accessory']??0)+$rowCat['count'];}
    $catQuery->close();
    if (!$foundCategories){$tq=$db->prepare("SELECT title FROM jerseys WHERE seller_id=?");$tq->bind_param('i',$seller_id);$tq->execute();$tr=$tq->get_result();while($rowT=$tr->fetch_assoc()){$t=strtolower($rowT['title']);if(strpos($t,'jersey')!==false)$category_counts['Jersey']=($category_counts['Jersey']??0)+1;elseif(strpos($t,'bat')!==false)$category_counts['Bat']=($category_counts['Bat']??0)+1;elseif(strpos($t,'ball')!==false)$category_counts['Ball']=($category_counts['Ball']??0)+1;elseif(strpos($t,'shoe')!==false||strpos($t,'boot')!==false)$category_counts['Shoes']=($category_counts['Shoes']??0)+1;else $category_counts['Accessory']=($category_counts['Accessory']??0)+1;}$tq->close();}
    if (empty($category_counts))$category_counts=['Jersey'=>0,'Bat'=>0,'Ball'=>0,'Accessory'=>0];

    $hasCreatedAt=$db->query("SHOW COLUMNS FROM jerseys LIKE 'created_at'")->num_rows>0;
    if ($hasCreatedAt){$mq=$db->prepare("SELECT DATE_FORMAT(created_at,'%b %Y') as month_label,COUNT(*) as jersey_count FROM jerseys WHERE seller_id=? AND created_at IS NOT NULL GROUP BY YEAR(created_at),MONTH(created_at),DATE_FORMAT(created_at,'%b %Y') ORDER BY MIN(created_at) DESC LIMIT 6");$mq->bind_param('i',$seller_id);$mq->execute();$result=$mq->get_result();if($result&&$result->num_rows>0){$rows=array_reverse($result->fetch_all(MYSQLI_ASSOC));foreach($rows as $row){$chartLabels[]=$row['month_label'];$chartData[]=(int)$row['jersey_count'];}}else{$chartLabels=['No Data'];$chartData=[0];}$mq->close();}
    else{$sq=$db->prepare("SELECT sport_type,COUNT(*) as count FROM jerseys WHERE seller_id=? AND sport_type IS NOT NULL AND sport_type!='' GROUP BY sport_type ORDER BY count DESC LIMIT 5");$sq->bind_param('i',$seller_id);$sq->execute();$result=$sq->get_result();if($result&&$result->num_rows>0){while($row=$result->fetch_assoc()){$chartLabels[]=$row['sport_type'];$chartData[]=(int)$row['count'];}}else{$chartLabels=['Jerseys'];$chartData=[$total_jerseys];}$sq->close();}

    $rq=$db->prepare("SELECT id,title,sport_type,price,sell,is_top,image FROM jerseys WHERE seller_id=? ORDER BY id DESC LIMIT 5");$rq->bind_param('i',$seller_id);$rq->execute();$recentJerseys=$rq->get_result()->fetch_all(MYSQLI_ASSOC);$rq->close();
}

$stmt=$db->prepare("SELECT COUNT(*) as cnt FROM admin_messages WHERE seller_id=? AND is_admin=1 AND is_seen=0");$stmt->bind_param('i',$seller_id);$stmt->execute();$unread_count=$stmt->get_result()->fetch_assoc()['cnt']??0;$stmt->close();
$body_class=($is_locked||!$is_verified)?'locked':'';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Seller Dashboard | SportGhar</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
    --primary:#C0392B;--primary-dk:#962d22;--primary-lt:#fdf1ef;
    --gold:#C9922A;--bg:#F5F4F0;--surface:#FFFFFF;
    --border:#E8E4DC;--text:#1C1612;--text-muted:#8A7D72;
    --sidebar-w:240px;--radius:18px;
    --shadow:0 2px 14px rgba(0,0,0,0.06);--shadow-lg:0 8px 32px rgba(0,0,0,0.1);
}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
.main{margin-left:var(--sidebar-w);padding:28px 24px;min-height:100vh;transition:margin-left 0.3s;}

/* HEADER */
.dash-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;}
.dash-header h1{font-family:'Fraunces',serif;font-size:1.75rem;font-weight:700;}
.header-btns{display:flex;gap:8px;flex-wrap:wrap;}
.btn-h{background:white;border:1.5px solid var(--primary);color:var(--primary);padding:7px 18px;border-radius:100px;font-weight:700;font-size:0.78rem;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:7px;text-decoration:none;}
.btn-h:hover{background:var(--primary);color:white;}
.btn-h.solid{background:var(--primary);color:white;}
.btn-h.solid:hover{background:var(--primary-dk);}

/* MAIN GRID: left content | right side panel */
.dash-grid{
    display:grid;
    grid-template-columns:1fr 300px;
    gap:18px;
    align-items:start;
}

/* ── STATS ROW ── */
.stats-row{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px;
    margin-bottom:16px;
}
.stat-card{
    background:var(--surface);
    border-radius:var(--radius);
    padding:1.1rem 0.8rem 1rem;
    box-shadow:var(--shadow);
    text-align:center;
    display:flex;flex-direction:column;align-items:center;gap:3px;
    transition:transform 0.2s,box-shadow 0.2s;
    position:relative;overflow:hidden;
}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--primary),var(--gold));opacity:0;transition:opacity 0.2s;}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);}
.stat-card:hover::after{opacity:1;}
.stat-icon{font-size:1.4rem;background:var(--primary-lt);width:42px;height:42px;line-height:42px;border-radius:50%;display:inline-block;}
.stat-val{font-family:'Fraunces',serif;font-size:1.85rem;font-weight:800;color:var(--primary);line-height:1.1;}
.stat-lbl{font-size:0.67rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);letter-spacing:0.5px;}

/* Mini category pills shown under Total Products card */
.cat-pills{display:flex;flex-wrap:wrap;gap:3px;justify-content:center;margin-top:3px;}
.cat-pill{font-size:0.58rem;background:#f0ebe4;color:var(--primary);padding:2px 7px;border-radius:20px;font-weight:700;white-space:nowrap;}

/* ── CHART ── */
.chart-wrap{background:transparent;padding:2px 0 6px;margin-bottom:14px;}
.chart-wrap h3{font-family:'Fraunces',serif;font-size:1.05rem;margin-bottom:8px;display:flex;align-items:center;gap:7px;}
.chart-wrap canvas{max-height:210px;width:100%!important;}

/* ── GUIDELINES ── */
.guidelines-card{background:white;border-radius:var(--radius);padding:18px 20px;box-shadow:var(--shadow);position:relative;overflow:hidden;}
.guidelines-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--primary),var(--gold));}
.guidelines-card h4{font-family:'Fraunces',serif;font-size:1.05rem;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:9px;}
.rules-container{display:flex;flex-wrap:wrap;gap:4px 24px;}
.rule-col{flex:1;min-width:180px;}
.rule-item{display:flex;align-items:flex-start;gap:9px;margin-bottom:10px;}
.rule-number{display:inline-flex;align-items:center;justify-content:center;width:23px;height:23px;background:var(--primary);color:white;border-radius:6px;font-size:10px;font-weight:800;flex-shrink:0;margin-top:2px;}
.rule-text{font-size:12px;line-height:1.5;font-weight:500;}
.rule-text small{display:block;font-size:10.5px;color:var(--text-muted);margin-top:1px;}
.guidelines-footer{margin-top:10px;padding-top:10px;border-top:1px dashed var(--border);font-size:11px;color:var(--text-muted);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;}
.pdf-link{display:inline-flex;align-items:center;gap:5px;background:var(--primary-lt);padding:4px 12px;border-radius:100px;text-decoration:none;color:var(--primary);font-weight:700;font-size:11px;}
.pdf-link:hover{background:var(--primary);color:white;}

/* ═══════════════════════════════════════
   RIGHT SIDE PANEL
   Night sky card + breakdown + recent
   ALL transparent / no card bg / no border
═══════════════════════════════════════ */
.right-panel{display:flex;flex-direction:column;gap:18px;}

/* Night Sky Card */
.night-card{
    border-radius:var(--radius);overflow:hidden;position:relative;
    min-height:190px;
    background:linear-gradient(170deg,#060d2e 0%,#0c1845 25%,#0f2255 50%,#091535 75%,#060d2e 100%);
    display:flex;align-items:flex-end;padding:18px 16px 16px;
}
/* Stars */
.night-card::before{
    content:'';position:absolute;inset:0;
    background-image:
        radial-gradient(1.2px 1.2px at 8% 12%,rgba(255,255,255,.95) 0%,transparent 100%),
        radial-gradient(.8px .8px at 18% 6%,rgba(255,255,255,.75) 0%,transparent 100%),
        radial-gradient(1.5px 1.5px at 28% 18%,rgba(255,255,255,.9) 0%,transparent 100%),
        radial-gradient(.8px .8px at 38% 5%,rgba(255,255,255,.65) 0%,transparent 100%),
        radial-gradient(1px 1px at 50% 14%,rgba(255,255,255,.8) 0%,transparent 100%),
        radial-gradient(1.8px 1.8px at 62% 8%,rgba(255,240,180,.95) 0%,transparent 100%),
        radial-gradient(.8px .8px at 74% 16%,rgba(255,255,255,.7) 0%,transparent 100%),
        radial-gradient(1px 1px at 85% 4%,rgba(255,255,255,.85) 0%,transparent 100%),
        radial-gradient(.8px .8px at 94% 11%,rgba(255,255,255,.6) 0%,transparent 100%),
        radial-gradient(1px 1px at 13% 32%,rgba(255,255,255,.55) 0%,transparent 100%),
        radial-gradient(1.2px 1.2px at 24% 42%,rgba(255,255,255,.7) 0%,transparent 100%),
        radial-gradient(2px 2px at 35% 28%,rgba(200,200,255,.85) 0%,transparent 100%),
        radial-gradient(.8px .8px at 46% 38%,rgba(255,255,255,.5) 0%,transparent 100%),
        radial-gradient(1px 1px at 58% 25%,rgba(255,255,255,.7) 0%,transparent 100%),
        radial-gradient(1.5px 1.5px at 70% 35%,rgba(255,255,255,.6) 0%,transparent 100%),
        radial-gradient(.8px .8px at 80% 22%,rgba(255,255,255,.75) 0%,transparent 100%),
        radial-gradient(1px 1px at 91% 30%,rgba(255,255,255,.55) 0%,transparent 100%),
        radial-gradient(2.5px 2.5px at 15% 20%,rgba(255,245,200,1) 0%,transparent 100%),
        radial-gradient(2px 2px at 55% 10%,rgba(180,200,255,.95) 0%,transparent 100%),
        radial-gradient(1.5px 1.5px at 42% 45%,rgba(255,210,150,.8) 0%,transparent 100%),
        radial-gradient(.7px .7px at 4% 50%,rgba(255,255,255,.45) 0%,transparent 100%),
        radial-gradient(.7px .7px at 97% 45%,rgba(255,255,255,.5) 0%,transparent 100%);
    pointer-events:none;
}
/* Moon */
.night-card::after{
    content:'';position:absolute;top:12px;right:16px;
    width:34px;height:34px;border-radius:50%;
    background:radial-gradient(circle,#fffde7 0%,#fff8b0 40%,rgba(255,248,176,.15) 100%);
    box-shadow:0 0 16px 7px rgba(255,248,150,.35),0 0 38px 14px rgba(255,240,100,.12);
    pointer-events:none;
}
/* Mountain silhouette */
.mountains{position:absolute;bottom:0;left:0;right:0;height:65px;pointer-events:none;}
/* Shooting star animation */
.shooting-star{
    position:absolute;top:18%;left:20%;
    width:60px;height:1.5px;
    background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.9),rgba(255,255,255,0));
    border-radius:2px;
    animation:shoot 4s ease-in-out infinite;
    transform-origin:left center;
}
@keyframes shoot{0%,70%,100%{opacity:0;transform:translateX(-20px) rotate(-25deg);}30%{opacity:1;transform:translateX(80px) rotate(-25deg);}60%{opacity:0;transform:translateX(120px) rotate(-25deg);}}
.night-content{position:relative;z-index:2;color:white;}
.night-content h4{font-family:'Fraunces',serif;font-size:.95rem;font-weight:700;margin-bottom:3px;text-shadow:0 2px 8px rgba(0,0,0,.5);}
.night-content p{font-size:.68rem;opacity:.7;}
.night-stats{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;}
.night-stat-pill{display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,.12);backdrop-filter:blur(8px);padding:3px 10px;border-radius:100px;font-size:.65rem;font-weight:700;color:white;}

/* ── BREAKDOWN (transparent, no bg, no border) ── */
.breakdown-wrap{padding:0;}
.section-title{font-family:'Fraunces',serif;font-size:1rem;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:6px;color:var(--text);}
.cat-row{display:flex;align-items:center;padding:6px 0;border-bottom:1px dashed rgba(0,0,0,.06);}
.cat-row:last-child{border-bottom:none;}
.cat-emoji{font-size:1rem;width:24px;text-align:center;flex-shrink:0;}
.cat-name-txt{font-weight:600;font-size:.82rem;flex:1;margin:0 8px;}
.cat-bar-wrap{flex:1.2;height:5px;background:rgba(0,0,0,.07);border-radius:3px;overflow:hidden;margin-right:8px;}
.cat-bar{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--primary),var(--gold));transition:width .7s cubic-bezier(.4,0,.2,1);}
.cat-num{font-size:.72rem;font-weight:800;color:var(--primary);min-width:20px;text-align:right;}

/* ── RECENT (transparent, no bg, no border) ── */
.recent-wrap{padding:0;}
.recent-list{list-style:none;}
.recent-list li{display:flex;justify-content:space-between;align-items:center;padding:5.5px 0;border-bottom:1px solid rgba(0,0,0,.05);}
.recent-list li:last-child{border-bottom:none;}
.recent-list li a{text-decoration:none;color:var(--text);font-weight:500;font-size:.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px;}
.rbadge{font-size:.6rem;padding:2px 8px;border-radius:30px;font-weight:700;white-space:nowrap;background:#E8F5E9;color:#2e7d32;}
.rbadge.draft{background:#f5f0e8;color:#8A7D72;}
.manage-all{display:block;text-align:center;margin-top:8px;color:var(--primary);font-weight:700;text-decoration:none;font-size:.78rem;}
.manage-all:hover{text-decoration:underline;}

/* FOOTER */
.dash-footer{margin-top:28px;padding:16px 12px 12px;border-top:1px solid var(--border);text-align:center;color:var(--text-muted);font-size:12px;}
.dash-footer .fl{display:flex;justify-content:center;gap:20px;margin-bottom:5px;flex-wrap:wrap;}
.dash-footer a{color:var(--primary);text-decoration:none;font-weight:600;}

/* ── UNVERIFIED ── */
.unverified-split{display:flex;flex-wrap:wrap;min-height:calc(100vh - 80px);background:var(--bg);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-lg);}
.unverified-left{flex:1.2;background:linear-gradient(145deg,#1c3e6e 0%,#2b5a8c 40%,#68b0e0 100%);position:relative;display:flex;align-items:flex-end;justify-content:center;padding:40px;min-height:400px;overflow:hidden;}
.unverified-left::before{content:'';position:absolute;top:0;left:0;width:100%;height:100%;background:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" opacity="0.2"><path fill="white" d="M0,200 C150,150 300,280 450,250 C600,220 750,120 900,150 C1050,180 1150,280 1200,250 L1200,800 L0,800 Z"/></svg>') no-repeat bottom;background-size:cover;pointer-events:none;}
.sky-clouds{position:absolute;width:100%;height:100%;background:radial-gradient(ellipse at 30% 50%,rgba(255,255,245,.3) 0%,transparent 70%);}
.unverified-left .illustration{position:relative;z-index:2;text-align:center;color:white;max-width:360px;}
.unverified-left .illustration h3{font-family:'Fraunces',serif;font-size:2rem;margin-bottom:12px;}
.unverified-right{flex:1;background:white;padding:56px 48px;display:flex;flex-direction:column;justify-content:center;}
.unverified-right h2{font-family:'Fraunces',serif;font-size:2.2rem;font-weight:800;margin-bottom:16px;}
.unverified-right h2 span{color:var(--primary);}
.status-badge{display:inline-block;padding:8px 18px;border-radius:60px;font-weight:800;font-size:.8rem;margin-bottom:20px;}
.status-badge.pending{background:#FFF5E0;color:#C97E00;border-left:3px solid #F0A500;}
.status-badge.rejected{background:#FFF1F0;color:#C0392B;border-left:3px solid #C0392B;}
.status-badge.not-started{background:#EFF6FF;color:#1E4A76;border-left:3px solid #1E4A76;}
.info-text{font-size:.9rem;margin:8px 0 20px;color:#5F6C7A;}
.verify-button{background:var(--primary);color:white;padding:14px 28px;border:none;border-radius:100px;font-weight:800;font-size:1rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:12px;transition:all .2s;width:fit-content;margin:12px 0 20px;}
.verify-button:hover{background:var(--primary-dk);transform:translateY(-3px);}
.support-note{font-size:.8rem;color:var(--text-muted);border-top:1px solid var(--border);padding-top:20px;margin-top:12px;}
.support-note span{color:var(--primary);font-weight:700;cursor:pointer;text-decoration:underline;}

/* MODALS */
.pdf-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:10000;align-items:center;justify-content:center;}
.pdf-modal.show{display:flex;}
.pdf-modal-content{background:white;width:90vw;height:90vh;border-radius:20px;overflow:hidden;position:relative;}
.pdf-modal-close{position:absolute;top:14px;right:18px;background:rgba(0,0,0,.55);color:white;border:none;font-size:24px;cursor:pointer;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:10;}
.pdf-modal iframe{width:100%;height:100%;border:none;}
.doc-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:21000;align-items:center;justify-content:center;overflow-y:auto;padding:24px;}
.doc-modal.show{display:flex;}
.doc-modal-content{background:#FFFDF9;border-radius:6px;box-shadow:0 32px 64px rgba(0,0,0,.3);max-width:1100px;width:100%;margin:auto;padding:44px 40px;position:relative;}
.doc-modal-header{display:flex;justify-content:space-between;align-items:baseline;border-bottom:2px solid #E7DED3;padding-bottom:16px;margin-bottom:24px;flex-wrap:wrap;}
.doc-modal-header h2{font-family:'Fraunces',serif;font-size:1.9rem;font-weight:700;color:#3E2A1F;}
.doc-close{background:none;border:none;font-size:28px;cursor:pointer;color:#A28D76;}
.doc-close:hover{color:#C0392B;}
.doc-print-btn{background:#8B5A2B;border:none;padding:9px 22px;border-radius:100px;color:white;font-weight:700;cursor:pointer;font-size:.8rem;margin-bottom:22px;display:inline-flex;align-items:center;gap:8px;}
.paper-header-doc{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:24px;margin-bottom:28px;padding-bottom:20px;border-bottom:2px solid #E7DED3;}
.passport-area-doc{display:flex;flex-direction:column;align-items:center;gap:8px;}
.passport-img-doc{width:110px;height:130px;object-fit:cover;border:2px solid #C0A080;border-radius:6px;}
.shop-title-section-doc{text-align:right;flex:1;}
.shop-title-section-doc h1{font-family:'Fraunces',serif;font-size:2rem;font-weight:700;color:#3E2A1F;}
.info-grid-doc{display:grid;grid-template-columns:repeat(2,1fr);gap:16px 28px;margin-bottom:32px;}
.info-paper-item{border-bottom:1px dashed #E2D4C6;padding-bottom:8px;}
.info-paper-label{font-size:.68rem;text-transform:uppercase;font-weight:700;color:#AA7A50;letter-spacing:.5px;}
.info-paper-value{font-size:.95rem;font-weight:600;color:#2E241E;margin-top:3px;}
.doc-paper-grid{display:flex;flex-wrap:wrap;gap:24px;margin-bottom:28px;}
.a4-doc-card{background:#FEFAF2;border:1px solid #DDCFBF;border-radius:14px;width:220px;padding:14px 12px;text-align:center;}
.doc-label{font-size:.68rem;font-weight:800;background:#E9DCCE;display:inline-block;padding:4px 14px;border-radius:100px;margin-bottom:12px;color:#5C3F28;}
.a4-doc-card img{max-width:100%;max-height:140px;border-radius:8px;object-fit:contain;}
.stamp-overlay-doc{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-8deg);max-width:180px;opacity:.85;z-index:15;pointer-events:none;mix-blend-mode:multiply;}
.signature-wrapper-doc{position:absolute;bottom:30px;right:40px;text-align:center;z-index:15;pointer-events:none;display:flex;flex-direction:column;align-items:center;gap:6px;}
.signature-overlay-doc{max-width:160px;max-height:80px;object-fit:contain;}
.signature-caption-doc{font-size:.68rem;color:#5C3F28;font-weight:700;background:rgba(255,253,249,.9);padding:4px 12px;border-radius:100px;}
.seller-description-box-doc{background:#FEF7EF;border-left:4px solid #C86F2C;padding:16px 20px;margin:20px 0 24px;border-radius:14px;font-size:14px;line-height:1.6;}
.admin-remark-display-doc{background:#F0FBF5;padding:14px 20px;border-radius:16px;margin-top:20px;border-left:4px solid #2C7A47;font-size:14px;}

/* CHAT */
.floating-chat-btn{position:fixed;bottom:28px;right:28px;width:56px;height:56px;background:linear-gradient(135deg,#C0392B,#E67E22);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 6px 20px rgba(192,57,43,.45);z-index:1000;transition:transform .2s;}
.floating-chat-btn:hover{transform:scale(1.08);}
.fcb-badge{position:absolute;top:-4px;right:-4px;background:#1C1612;color:white;border-radius:50%;width:18px;height:18px;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;border:2px solid white;}
.chat-drawer{position:fixed;top:0;right:-100%;width:100%;max-width:420px;height:100vh;background:white;box-shadow:-4px 0 24px rgba(0,0,0,.12);z-index:1100;display:flex;flex-direction:column;transition:right .3s ease;}
.chat-drawer.open{right:0;}
@media(min-width:768px){.chat-drawer{width:400px;right:-400px;}.chat-drawer.open{right:0;}}
.drawer-header{padding:18px 22px;background:#FCF8F3;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;}
.drawer-header h3{font-size:1rem;margin:0;font-weight:800;}
.close-drawer{background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-muted);}
.drawer-messages{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#FEFCF9;}
.drawer-message-item{display:flex;flex-direction:column;}
.drawer-message-item.admin{align-items:flex-start;}
.drawer-message-item.seller{align-items:flex-end;}
.drawer-bubble{max-width:85%;padding:9px 15px;border-radius:18px;font-size:.84rem;line-height:1.5;word-break:break-word;}
.drawer-message-item.admin .drawer-bubble{background:#F0E9E2;color:#2D241C;}
.drawer-message-item.seller .drawer-bubble{background:#C0392B;color:white;}
.chat-img{max-width:200px;max-height:160px;border-radius:10px;margin-top:6px;display:block;cursor:pointer;}
.file-attachment{display:inline-flex;align-items:center;gap:8px;background:rgba(0,0,0,.07);padding:7px 12px;border-radius:12px;margin-top:6px;font-size:.75rem;text-decoration:none;color:inherit;}
.drawer-meta{font-size:.6rem;margin-top:4px;color:#A28D76;display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
.menu-btn{background:none;border:none;font-size:.75rem;padding:2px 4px;cursor:pointer;color:var(--text-muted);}
.dropdown-menu{display:none;position:absolute;right:0;top:20px;background:white;border:1px solid var(--border);border-radius:12px;box-shadow:0 4px 14px rgba(0,0,0,.1);z-index:10;min-width:90px;overflow:hidden;}
.dropdown-menu a{display:block;padding:8px 14px;text-decoration:none;color:var(--text);font-size:.72rem;}
.dropdown-menu a:hover{background:#F0E9E2;}
.dropdown-menu.show{display:block;}
.empty-chat{text-align:center;color:#A28D76;font-size:.8rem;margin:auto;padding:20px;}
.drawer-typing{padding:8px 16px;font-size:.7rem;color:#A28D76;font-style:italic;border-top:1px solid #EEE5DC;background:#FEFCF9;flex-shrink:0;}
.drawer-input{padding:12px 16px;border-top:1px solid var(--border);background:white;flex-shrink:0;}
.drawer-input-row{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;}
.drawer-input-row .input-group{flex:1;display:flex;gap:6px;align-items:flex-end;}
.drawer-input textarea{flex:1;border:1.5px solid #E4D6CA;border-radius:16px;padding:9px 14px;font-size:.82rem;resize:none;font-family:inherit;outline:none;max-height:100px;}
.drawer-input textarea:focus{border-color:var(--primary);}
.attachments-preview{margin-top:8px;font-size:.7rem;background:#F0F0F0;padding:4px 8px;border-radius:12px;display:inline-flex;align-items:center;gap:6px;}
.btn-send-small{background:var(--primary);border:none;color:white;padding:9px 18px;border-radius:100px;font-weight:700;font-size:.75rem;cursor:pointer;white-space:nowrap;flex-shrink:0;}
.drawer-sms-check{margin-top:8px;display:flex;align-items:center;gap:6px;font-size:.7rem;color:var(--text-muted);cursor:pointer;}
.chat-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:1050;}
.chat-overlay.show{display:block;}
.img-lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:9999;align-items:center;justify-content:center;cursor:zoom-out;}
.img-lightbox.show{display:flex;}
.img-lightbox img{max-width:90vw;max-height:90vh;border-radius:14px;}

/* ── RESPONSIVE ── */
@media(max-width:1080px){.dash-grid{grid-template-columns:1fr 270px;}}
@media(max-width:820px){
    .dash-grid{grid-template-columns:1fr;}
    .right-panel{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .night-card{grid-column:1/-1;}
    .stats-row{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:540px){
    .main{margin-left:0;padding:14px 12px;}
    .right-panel{display:flex;flex-direction:column;}
    .stats-row{grid-template-columns:repeat(2,1fr);gap:8px;}
    .info-grid-doc{grid-template-columns:1fr;}
    .unverified-left{display:none;}
    .unverified-right{padding:32px 24px;}
}
body.locked .sidebar-nav a:not(.profile-link){display:none!important;}
</style>
</head>
<body class="<?= $body_class ?>">
<main class="main">

<?php if ($is_locked): ?>
<div style="text-align:center;padding:60px 20px;">
    <div style="font-size:3rem;margin-bottom:14px;">🔒</div>
    <h2 style="font-family:'Fraunces',serif;font-size:1.8rem;margin-bottom:10px;">Dashboard Locked</h2>
    <p style="color:var(--text-muted);">Your account has been temporarily restricted.<br>Use the chat button below to contact support.</p>
</div>

<?php elseif (!$is_verified): ?>
<div class="unverified-split">
    <div class="unverified-left">
        <div class="sky-clouds"></div>
        <div class="illustration">
            <h3>🌤️ Start Your Journey</h3>
            <p>Join hundreds of verified sellers earning daily on SportGhar</p>
            <div style="margin-top:24px;font-size:1.8rem;">🏆⚽🏀🏈</div>
        </div>
    </div>
    <div class="unverified-right">
        <h2>✨ Start Selling on <span>SportGhar</span></h2>
        <p style="color:var(--text-muted);font-size:1rem;line-height:1.5;margin-bottom:28px;">Complete your seller verification to unlock the full dashboard.</p>
        <?php if ($status==='pending'): ?>
            <div class="status-badge pending">⏳ Verification Under Review</div>
            <p class="info-text">Your application is being reviewed — usually within 2–3 days.</p>
        <?php elseif ($status==='rejected'): ?>
            <div class="status-badge rejected">❌ Verification Rejected</div>
            <p class="info-text">Please contact support to resolve this issue.</p>
        <?php else: ?>
            <div class="status-badge not-started">🔑 Verification Required</div>
            <p class="info-text">Submit your documents to become a verified seller.</p>
        <?php endif; ?>
        <a href="personal.php" class="verify-button">Verify Your Account →</a>
        <p class="support-note">📞 Need help? <span onclick="openDrawer()">Chat with support</span></p>
    </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════
     VERIFIED FULL DASHBOARD
══════════════════════════════════════════ -->
<div class="dash-header">
    <h1>Welcome back, <?= htmlspecialchars($seller_name) ?> 👋</h1>
    <div class="header-btns">
        <?php if ($showOfficialDocBtn): ?><button class="btn-h" id="openOfficialDocBtn">📜 Official Document</button><?php endif; ?>
        <a href="add_jersey.php" class="btn-h solid">➕ Add New Product</a>
    </div>
</div>

<div class="dash-grid">
    <!-- ── LEFT COLUMN ── -->
    <div>
        <!-- Stats: 4 cards — Total Products has category pills below it -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-val"><?= $total_jerseys ?></div>
                <div class="stat-lbl">Total Products</div>
                <?php if (!empty($category_counts)): ?>
                <div class="cat-pills">
                    <?php
                    $icons=['Jersey'=>'👕','Bat'=>'🏏','Ball'=>'⚽','Shoes'=>'👟','Accessory'=>'🧤'];
                    foreach($category_counts as $cat=>$cnt): if($cnt>0):
                    ?><span class="cat-pill"><?= ($icons[$cat]??'📌').' '.$cnt ?></span><?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔥</div>
                <div class="stat-val"><?= $total_sell ?></div>
                <div class="stat-lbl">For Sale</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-val"><?= $total_top ?></div>
                <div class="stat-lbl">Featured</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-val">--</div>
                <div class="stat-lbl">Total Sales</div>
            </div>
        </div>

        <!-- Chart: transparent -->
        <?php if (!empty($chartLabels)): ?>
        <div class="chart-wrap">
            <h3>📊 Jersey Analytics</h3>
            <canvas id="jerseyChart" style="height:200px;"></canvas>
            <p style="text-align:center;font-size:10.5px;color:var(--text-muted);margin-top:5px;"><?= $hasCreatedAt?'Monthly additions (last 6 months)':'Distribution by sport type' ?></p>
        </div>
        <?php endif; ?>

        <!-- Guidelines -->
        <div class="guidelines-card">
            <h4>📋 Seller Guidelines &amp; Code of Conduct</h4>
            <div class="rules-container">
                <div class="rule-col">
                    <div class="rule-item"><span class="rule-number">1</span><div class="rule-text">Clearly disclose authentic vs. replica.<small>Transparency builds buyer trust</small></div></div>
                    <div class="rule-item"><span class="rule-number">2</span><div class="rule-text">Upload high-resolution product images.<small>Better photos = more sales</small></div></div>
                    <div class="rule-item"><span class="rule-number">3</span><div class="rule-text">Reply to buyer inquiries within 24 hours.<small>Faster responses = better ratings</small></div></div>
                </div>
                <div class="rule-col">
                    <div class="rule-item"><span class="rule-number">4</span><div class="rule-text">Ship within 2–3 business days after order.<small>Reliability builds repeat buyers</small></div></div>
                    <div class="rule-item"><span class="rule-number">5</span><div class="rule-text">Fair pricing — no misleading discount claims.<small>Honest pricing gains loyal customers</small></div></div>
                    <div class="rule-item"><span class="rule-number">6</span><div class="rule-text">Policy violations may lead to suspension.<small>Follow rules to grow your shop</small></div></div>
                </div>
            </div>
            <div class="guidelines-footer">
                <span>🛡️ SportGhar Seller Protection</span>
                <?php if (!empty($global_rules_pdf)&&file_exists($_SERVER['DOCUMENT_ROOT'].$global_rules_pdf)): ?>
                    <a href="javascript:void(0)" class="pdf-link" onclick="openPdfModal('<?= htmlspecialchars($global_rules_pdf.'#toolbar=0&navpanes=0') ?>')">📄 Official Rules PDF</a>
                <?php endif; ?>
                <span>⚡ Updated: <?= date('M Y') ?></span>
            </div>
        </div>
    </div><!-- end left -->

    <!-- ── RIGHT PANEL ── -->
    <div class="right-panel">

        <!-- Night sky card -->
        <div class="night-card">
            <!-- Shooting star -->
            <div class="shooting-star"></div>
            <!-- Mountains SVG -->
            <svg class="mountains" viewBox="0 0 300 65" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0,65 L0,42 L25,18 L50,36 L80,10 L110,32 L140,6 L170,28 L200,14 L230,34 L260,8 L285,26 L300,18 L300,65 Z" fill="rgba(8,13,40,0.92)"/>
                <path d="M0,65 L0,52 L35,38 L70,48 L110,40 L150,52 L190,42 L230,50 L270,43 L300,48 L300,65 Z" fill="rgba(4,7,22,0.97)"/>
            </svg>
            <div class="night-content">
                <h4>🌙 <?= htmlspecialchars($shop_name) ?></h4>
                <p>Your store is live &amp; growing</p>
                <div class="night-stats">
                    <span class="night-stat-pill">📦 <?= $total_jerseys ?> Products</span>
                    <span class="night-stat-pill">⭐ <?= $total_top ?> Featured</span>
                </div>
            </div>
        </div>

        <!-- Product Breakdown — transparent, no border, no bg -->
        <div class="breakdown-wrap">
            <div class="section-title">🏷️ Product Breakdown</div>
            <?php
            $icons=['Jersey'=>'👕','Bat'=>'🏏','Ball'=>'⚽','Shoes'=>'👟','Accessory'=>'🧤'];
            $maxCnt=max(array_values($category_counts)?:[1]);
            foreach($category_counts as $cat=>$cnt):
                $icon=$icons[$cat]??'📌';
                $pct=$maxCnt>0?round($cnt/$maxCnt*100):0;
            ?>
            <div class="cat-row">
                <span class="cat-emoji"><?= $icon ?></span>
                <span class="cat-name-txt"><?= htmlspecialchars($cat) ?></span>
                <div class="cat-bar-wrap"><div class="cat-bar" style="width:<?= $pct ?>%"></div></div>
                <span class="cat-num"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Products — transparent, no border, no bg -->
        <div class="recent-wrap">
            <div class="section-title">🕒 Recent Products</div>
            <?php if (count($recentJerseys)>0): ?>
            <ul class="recent-list">
                <?php foreach($recentJerseys as $item): ?>
                <li>
                    <a href="edit_jersey.php?id=<?= $item['id'] ?>"><?= htmlspecialchars(substr($item['title'],0,26)) ?></a>
                    <span class="rbadge <?= $item['sell']=='Yes'?'':'draft' ?>"><?= $item['sell']=='Yes'?'For Sale':'Draft' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p style="color:var(--text-muted);font-size:.8rem;padding:6px 0;">No products yet.</p>
            <?php endif; ?>
            <a href="manage_jerseys.php" class="manage-all">View All Products →</a>
        </div>

    </div><!-- end right panel -->
</div><!-- end dash-grid -->

<div class="dash-footer">
    <div class="fl">
        <a href="#">About Us</a><a href="#">Seller Policies</a>
        <a href="#">Support Center</a><a href="#">Terms of Service</a>
    </div>
    <p>&copy; <?= date('Y') ?> SportGhar Nepal. All rights reserved.</p>
</div>
<?php endif; ?>

</main>

<!-- PDF Modal -->
<div id="pdfModal" class="pdf-modal" onclick="closePdfModal()">
    <div class="pdf-modal-content" onclick="event.stopPropagation()">
        <button class="pdf-modal-close" onclick="closePdfModal()">&times;</button>
        <iframe id="pdfIframe" src="" oncontextmenu="return false"></iframe>
    </div>
</div>

<!-- Official Doc Modal -->
<div id="officialDocModal" class="doc-modal">
    <div class="doc-modal-content">
        <div class="doc-modal-header">
            <h2>📜 Official Verification Document</h2>
            <button class="doc-close" id="closeOfficialDocModal">&times;</button>
        </div>
        <div class="doc-body">
            <button class="doc-print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
            <div class="paper-header-doc">
                <div class="passport-area-doc">
                    <img src="<?= $passport_img ?>" class="passport-img-doc" alt="Passport Photo">
                    <small style="font-size:.68rem;color:#AA7A50;font-weight:700;text-transform:uppercase;">Official Passport Photo</small>
                </div>
                <div class="shop-title-section-doc">
                    <h1><?= htmlspecialchars($shop_name) ?></h1>
                    <span style="font-size:13px;color:#2C7A47;font-weight:700;">✓ Approved &amp; Verified</span><br>
                    <span style="font-size:12px;color:#AA7A50;">📅 Registered: <?= date('d M, Y',strtotime($seller['created_at']??'now')) ?></span>
                </div>
            </div>
            <div class="info-grid-doc">
                <div class="info-paper-item"><div class="info-paper-label">Full Legal Name</div><div class="info-paper-value"><?= htmlspecialchars($seller['full_name']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">Email Address</div><div class="info-paper-value"><?= htmlspecialchars($seller['email']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">Phone Number</div><div class="info-paper-value"><?= htmlspecialchars($seller['phone']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">Shop Category</div><div class="info-paper-value"><?= htmlspecialchars($seller['shop_category']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">PAN / Tax Number</div><div class="info-paper-value"><?= htmlspecialchars($seller['pan_number']) ?></div></div>
                <div class="info-paper-item"><div class="info-paper-label">Business Address</div><div class="info-paper-value"><?= nl2br(htmlspecialchars($seller['shop_address'])) ?></div></div>
            </div>
            <div style="font-family:'Fraunces',serif;font-size:1.1rem;font-weight:700;margin-bottom:14px;color:#3E2A1F;">📄 Attached Legal Documents</div>
            <div class="doc-paper-grid">
                <div class="a4-doc-card"><div class="doc-label">🇳🇵 Nagarikta — Front</div><?php if(!empty($seller['nagarikta_front'])): ?><img src="../publics/uploads/nagarikta/<?= htmlspecialchars($seller['nagarikta_front']) ?>" alt="Front"><?php else: ?><div style="padding:18px;color:#b4875f;font-size:13px;">⚠ Not Uploaded</div><?php endif; ?></div>
                <div class="a4-doc-card"><div class="doc-label">🇳🇵 Nagarikta — Back</div><?php if(!empty($seller['nagarikta_back'])): ?><img src="../publics/uploads/nagarikta/<?= htmlspecialchars($seller['nagarikta_back']) ?>" alt="Back"><?php else: ?><div style="padding:18px;color:#b4875f;font-size:13px;">⚠ Not Uploaded</div><?php endif; ?></div>
                <div class="a4-doc-card"><div class="doc-label">📸 Passport Photo</div><?php if(!empty($seller['passport_photo'])): ?><img src="../publics/uploads/passport/<?= htmlspecialchars($seller['passport_photo']) ?>" alt="Passport"><?php else: ?><div style="padding:18px;color:#b4875f;font-size:13px;">⚠ Missing</div><?php endif; ?></div>
                <div class="a4-doc-card"><div class="doc-label">🏦 Bank Cheque</div><?php if(!empty($seller['bank_cheque_image'])): ?><img src="../publics/uploads/cheque/<?= htmlspecialchars($seller['bank_cheque_image']) ?>" alt="Cheque" style="max-width:100%;"><?php else: ?><div style="padding:18px;color:#b4875f;font-size:13px;">⚠ Not Uploaded</div><?php endif; ?></div>
            </div>
            <?php if(!empty($seller['shop_description'])): ?><div class="seller-description-box-doc"><strong>📝 Shop Description:</strong><br><?= nl2br(htmlspecialchars($seller['shop_description'])) ?></div><?php endif; ?>
            <?php if(!empty($stamp_img)): ?><img src="<?= $stamp_img ?>" class="stamp-overlay-doc" alt="Stamp"><?php endif; ?>
            <?php if(!empty($signature_img)): ?><div class="signature-wrapper-doc"><img src="<?= $signature_img ?>" class="signature-overlay-doc" alt="Signature"><div class="signature-caption-doc">Admin Manager Signature</div></div><?php endif; ?>
            <?php if(!empty($admin_remarks)): ?><div class="admin-remark-display-doc"><strong>📌 Admin Note:</strong><br><?= nl2br($admin_remarks) ?></div><?php endif; ?>
        </div>
    </div>
</div>

<div class="img-lightbox" id="imgLightbox" onclick="closeLightbox()"><img id="lightboxImg" src=""></div>
<div class="chat-overlay" id="chatOverlay" onclick="closeDrawer()"></div>
<div class="floating-chat-btn" onclick="openDrawer()">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="white"><path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2z"/></svg>
    <div class="fcb-badge" id="floatBadge" style="<?= $unread_count>0?'':'display:none' ?>"><?= $unread_count ?></div>
</div>
<div class="chat-drawer" id="chatDrawer">
    <div class="drawer-header"><div><h3>💬 Support Chat</h3><small style="color:var(--text-muted);font-size:12px;">SportGhar Admin Team</small></div><button class="close-drawer" onclick="closeDrawer()">✕</button></div>
    <div class="drawer-messages" id="drawerMessages"><div class="empty-chat">Loading messages...</div></div>
    <div class="drawer-typing" id="drawerTyping" style="display:none;">Admin is typing...</div>
    <div class="drawer-input">
        <div class="drawer-input-row">
            <div class="input-group">
                <textarea id="drawerMessageText" rows="1" placeholder="Write a message..."></textarea>
                <label for="fileInput" style="background:#f0e4d8;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;">📎</label>
                <input type="file" id="fileInput" accept="image/*,application/pdf,.doc,.docx,.txt,.zip" style="display:none">
            </div>
            <button class="btn-send-small" id="sendBtn" onclick="sendMessage()">Send</button>
        </div>
        <div id="filePreview" class="attachments-preview" style="display:none;"><span id="fileName"></span><button onclick="clearAttachment()" style="background:none;border:none;cursor:pointer;">✖</button></div>
        <label class="drawer-sms-check"><input type="checkbox" id="drawerSmsCheck"> 📲 Also send as SMS</label>
    </div>
</div>

<script>
function openPdfModal(url){document.getElementById('pdfIframe').src=url;document.getElementById('pdfModal').classList.add('show');}
function closePdfModal(){document.getElementById('pdfIframe').src='';document.getElementById('pdfModal').classList.remove('show');}
const docModal=document.getElementById('officialDocModal');
document.getElementById('openOfficialDocBtn')?.addEventListener('click',()=>{docModal.classList.add('show');document.body.style.overflow='hidden';});
document.getElementById('closeOfficialDocModal')?.addEventListener('click',()=>{docModal.classList.remove('show');document.body.style.overflow='';});
window.addEventListener('click',e=>{if(e.target===docModal){docModal.classList.remove('show');document.body.style.overflow='';}});

// Chart — transparent bg, no axis borders
const ctx=document.getElementById('jerseyChart')?.getContext('2d');
if(ctx){
    new Chart(ctx,{
        type:'bar',
        data:{
            labels:<?= json_encode($chartLabels) ?>,
            datasets:[{
                label:'Products',
                data:<?= json_encode($chartData) ?>,
                backgroundColor:'rgba(192,57,43,0.72)',
                borderRadius:8,borderColor:'#C0392B',borderWidth:1.2
            }]
        },
        options:{
            responsive:true,maintainAspectRatio:false,
            plugins:{legend:{position:'top',labels:{font:{size:11}}}},
            scales:{
                x:{grid:{display:false},border:{display:false},ticks:{font:{size:10}}},
                y:{beginAtZero:true,ticks:{precision:0,font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'},border:{display:false}}
            }
        }
    });
}

// CHAT
const BASE_URL=window.location.pathname.split('?')[0];
let lastMessageId=0,pollingInterval=null,typingPollInt=null,typingTimeout=null,selectedFile=null;
const drawer=document.getElementById('chatDrawer'),overlay=document.getElementById('chatOverlay'),msgContainer=document.getElementById('drawerMessages');
const typingDiv=document.getElementById('drawerTyping'),msgInput=document.getElementById('drawerMessageText'),smsCheck=document.getElementById('drawerSmsCheck'),sendBtn=document.getElementById('sendBtn');
const floatBadge=document.getElementById('floatBadge'),fileInput=document.getElementById('fileInput'),filePreview=document.getElementById('filePreview'),fileNameSpan=document.getElementById('fileName');
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function scrollBottom(){msgContainer.scrollTop=msgContainer.scrollHeight;}
function openLightbox(src){document.getElementById('lightboxImg').src=src;document.getElementById('imgLightbox').classList.add('show');}
function closeLightbox(){document.getElementById('imgLightbox').classList.remove('show');}
function clearAttachment(){selectedFile=null;fileInput.value='';filePreview.style.display='none';}
fileInput.addEventListener('change',function(e){if(e.target.files.length){const f=e.target.files[0];if(f.size>5*1024*1024){alert('Max 5MB');fileInput.value='';return;}selectedFile=f;fileNameSpan.textContent=f.name;filePreview.style.display='flex';}else clearAttachment();});
async function openDrawer(){drawer.classList.add('open');overlay.classList.add('show');floatBadge.style.display='none';lastMessageId=0;msgContainer.innerHTML='<div class="empty-chat">Loading…</div>';await fetchMessages(true);startPolling();msgInput.focus();}
function closeDrawer(){drawer.classList.remove('open');overlay.classList.remove('show');stopPolling();updateTyping(false);clearAttachment();}
function startPolling(){stopPolling();pollingInterval=setInterval(()=>fetchMessages(false),3000);typingPollInt=setInterval(checkTyping,2500);}
function stopPolling(){clearInterval(pollingInterval);clearInterval(typingPollInt);}
async function fetchMessages(isInitial){try{const res=await fetch(`${BASE_URL}?ajax=get_messages&last_id=${lastMessageId}`);const data=await res.json();if(data.error)throw new Error(data.error);if(!data.messages?.length){if(isInitial)msgContainer.innerHTML='<div class="empty-chat">No messages yet. Say hello! 👋</div>';return;}if(isInitial)msgContainer.innerHTML='';for(let msg of data.messages){if(!msgContainer.querySelector(`[data-msg-id="${msg.id}"]`)){appendMessage(msg);if(msg.id>lastMessageId)lastMessageId=msg.id;}}scrollBottom();}catch(e){console.error(e);if(isInitial)msgContainer.innerHTML='<div class="empty-chat">❌ Connection error.</div>';}}
function isImageUrl(url,ft){if(!url)return false;if(ft?.startsWith('image/'))return true;const ext=url.split('.').pop().split('?')[0].toLowerCase();return['jpg','jpeg','png','gif','webp','bmp','svg'].includes(ext);}
function buildFileHTML(fu,fn,ft){if(!fu)return'';const name=fn||fu.split('/').pop();if(isImageUrl(fu,ft))return`<div><img src="${esc(fu)}" class="chat-img" onclick="openLightbox('${esc(fu)}')" onerror="this.parentElement.innerHTML='<a href=\\'${esc(fu)}\\' target=\\'_blank\\' class=\\'file-attachment\\'>📎 ${esc(name)}</a>'"></div>`;else return`<div><a href="${esc(fu)}" target="_blank" class="file-attachment">📎 ${esc(name)}</a></div>`;}
function appendMessage(msg){const old=msgContainer.querySelector('.empty-chat');if(old)old.remove();const wrap=document.createElement('div');wrap.className=`drawer-message-item ${msg.is_admin?'admin':'seller'}`;wrap.dataset.msgId=msg.id;const bubble=document.createElement('div');bubble.className='drawer-bubble';let content='';if(msg.message)content+=`<div>${esc(msg.message).replace(/\n/g,'<br>')}</div>`;content+=buildFileHTML(msg.file_url,msg.file_name,msg.file_type);bubble.innerHTML=content;const meta=document.createElement('div');meta.className='drawer-meta';let mh=`<span>${msg.is_admin?'🛡️ Admin':'👤 You'}</span><span>${esc(msg.created_at)}</span>`;if(msg.type==='sms')mh+=`<span>📱 SMS</span>`;if(!msg.is_admin&&msg.is_seen)mh+=`<span style="color:#5aad7a;">✓✓</span>`;if(!msg.is_admin&&msg.can_edit_delete)mh+=`<div style="position:relative;display:inline-block;"><button class="menu-btn" onclick="toggleMenu(event,this)">⋯</button><div class="dropdown-menu"><a href="#" class="edit-msg" data-id="${msg.id}" data-text="${esc(msg.message)}">✏️ Edit</a><a href="#" class="delete-msg" data-id="${msg.id}">🗑 Delete</a></div></div>`;meta.innerHTML=mh;wrap.appendChild(bubble);wrap.appendChild(meta);msgContainer.appendChild(wrap);bindEditDelete();}
async function sendMessage(){const message=msgInput.value.trim();if(!message&&!selectedFile)return;sendBtn.disabled=true;sendBtn.textContent='…';const fd=new FormData();fd.append('message',message);if(smsCheck.checked)fd.append('send_sms','1');if(selectedFile)fd.append('attachment',selectedFile);try{const res=await fetch(`${BASE_URL}?ajax=send_message`,{method:'POST',body:fd});const data=await res.json();if(data.success){const nm={id:data.message_id,message,file_url:data.file_url,file_name:data.file_name,file_type:data.file_type,is_admin:false,type:smsCheck.checked?'sms':'message',created_at:new Date().toLocaleString('en-US',{day:'numeric',month:'short',hour:'numeric',minute:'2-digit',hour12:true}),is_seen:false,can_edit_delete:true};appendMessage(nm);scrollBottom();lastMessageId=data.message_id;msgInput.value='';smsCheck.checked=false;clearAttachment();}else alert('Error: '+(data.error||'Unknown'));}catch(e){alert('Network error');}finally{sendBtn.disabled=false;sendBtn.textContent='Send';}}
msgInput.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMessage();}});
msgInput.addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,100)+'px';updateTyping(true);clearTimeout(typingTimeout);typingTimeout=setTimeout(()=>updateTyping(false),2000);});
async function checkTyping(){try{const res=await fetch(`${BASE_URL}?ajax=get_typing`);const data=await res.json();typingDiv.style.display=data.typing?'block':'none';}catch(e){}}
async function updateTyping(t){try{const fd=new FormData();fd.append('typing',t?1:0);await fetch(`${BASE_URL}?ajax=update_typing`,{method:'POST',body:fd});}catch(e){}}
async function editMessage(id,nt){const fd=new FormData();fd.append('message_id',id);fd.append('message',nt);try{const res=await fetch(`${BASE_URL}?ajax=edit_message`,{method:'POST',body:fd});const data=await res.json();if(data.success){const bubble=msgContainer.querySelector(`[data-msg-id="${id}"] .drawer-bubble`);if(bubble){const fd2=bubble.querySelector('.file-attachment,.chat-img')?.parentElement;bubble.innerHTML=esc(nt).replace(/\n/g,'<br>');if(fd2)bubble.appendChild(fd2);}}else alert('Cannot edit');}catch(e){alert('Network error');}}
async function deleteMessage(id){if(!confirm('Delete permanently?'))return;const fd=new FormData();fd.append('message_id',id);try{const res=await fetch(`${BASE_URL}?ajax=delete_message`,{method:'POST',body:fd});const data=await res.json();if(data.success){const el=msgContainer.querySelector(`[data-msg-id="${id}"]`);if(el)el.remove();if(!msgContainer.querySelector('.drawer-message-item'))msgContainer.innerHTML='<div class="empty-chat">No messages yet. Say hello! 👋</div>';}else alert('Cannot delete');}catch(e){alert('Network error');}}
function bindEditDelete(){document.querySelectorAll('.edit-msg').forEach(a=>{a.onclick=function(e){e.preventDefault();const nt=prompt('Edit message:',this.dataset.text);if(nt&&nt.trim()&&nt!==this.dataset.text)editMessage(this.dataset.id,nt.trim());};});document.querySelectorAll('.delete-msg').forEach(a=>{a.onclick=function(e){e.preventDefault();deleteMessage(this.dataset.id);};});}
window.toggleMenu=function(e,btn){e.stopPropagation();document.querySelectorAll('.dropdown-menu.show').forEach(m=>{if(m!==btn.nextElementSibling)m.classList.remove('show');});btn.nextElementSibling.classList.toggle('show');};
document.addEventListener('click',()=>document.querySelectorAll('.dropdown-menu.show').forEach(m=>m.classList.remove('show')));
</script>
</body>
</html>