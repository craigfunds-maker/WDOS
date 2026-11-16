<?php
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); $m = $_SERVER['REQUEST_METHOD'];
header('Content-Type: application/json');
$tasks = [['id'=>'t1','title'=>'Synthetic task','assigned_to'=>'u1','created_at'=>'2026-09-01T10:00:00Z'],['id'=>'t2','title'=>'Second','assigned_to'=>'u9','created_at'=>'2026-09-02T10:00:00Z']];
if ($p === '/auth/v1/admin/users') { echo json_encode(['users'=>[['id'=>'u1','email'=>'Amina.Test@wdos-mail.org']]]); exit; }
if ($p === '/rest/v1/' ) { echo json_encode(['paths'=>['/'=>[], '/tasks'=>[], '/email_secrets'=>[], '/rpc/x'=>[]]]); exit; }
if ($p === '/rest/v1/tasks') { if ($m==='HEAD') { header('Content-Range: 0-0/2'); exit; } $o=(int)($_GET['offset']??0); echo json_encode(array_slice($tasks,$o,(int)$_GET['limit'])); exit; }
if ($p === '/storage/v1/bucket') { echo json_encode([['id'=>'cvs']]); exit; }
if ($p === '/storage/v1/object/list/cvs') { $b=json_decode(file_get_contents('php://input'),true); if(($b['offset']??0)>0){echo '[]';exit;}
  echo json_encode($b['prefix']==='' ? [['name'=>'u1','id'=>null]] : [['name'=>'cv.pdf','id'=>'f1','metadata'=>['mimetype'=>'application/pdf']]]); exit; }
if ($p === '/storage/v1/object/cvs/u1/cv.pdf') { header('Content-Type: application/pdf'); echo "%PDF-1.4 synthetic"; exit; }
http_response_code(404); echo '{}';
