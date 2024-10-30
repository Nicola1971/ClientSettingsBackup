<?php
header('Content-Type: text/html; charset=UTF-8');
global $_lang, $manager_language, $manager_theme,$modx_manager_charset;
$help = $_lang['help'];
$Config = $_lang["settings_module"];
$module_id = (!empty($_REQUEST["id"])) ? (int)$_REQUEST["id"] : $yourModuleId;

// Impostazioni di default
$settingsPrefix = isset($settingsPrefix) ? $settingsPrefix : 'client_';
$backupPath = isset($backupPath) ? $backupPath : 'assets/files/';
$backupDir = MODX_BASE_PATH . $backupPath;
$fileName = 'backup_settings.json';
$filePath = $backupDir . $fileName;

global $modx;

$modx_root_dir =$modx->config['base_path'];
$mods_path = $modx->config['base_path'] . "assets/modules/";

$_lang = array();



include($mods_path.'clientsettingsbackup/lang/english.php');
if (file_exists($mods_path.'clientsettingsbackup/lang/' . $modx->config['manager_language'] . '.php')) {
    include($mods_path.'clientsettingsbackup/lang/' . $modx->config['manager_language'] . '.php');
}

// Funzione per il backup
function backupSettings($settingsPrefix, $filePath) {
    global $modx;
    global $_lang;
    $tablePrefix = $modx->db->config['table_prefix'];
    $query = "SELECT * FROM `{$tablePrefix}system_settings` WHERE `setting_name` LIKE '" . $modx->db->escape($settingsPrefix) . "%'";
    $result = $modx->db->query($query);
    $settings = [];

    while ($row = $modx->db->getRow($result)) {
        $settings[] = $row;
    }

    if (!is_dir(dirname($filePath))) {
        mkdir(dirname($filePath), 0755, true);
    }

    if (file_put_contents($filePath, json_encode($settings))) {        
        return "<div class=\"alert alert-success\">"  .$_lang['bkp_Completed'] . ": $filePath</div>";
    } else {
        return "<div class=\"alert alert-danger\">" . $_lang['bkp_Error'] . "</div>";
    }
}

// Funzione per il ripristino
function restoreSettings($filePath) {
    global $modx;
    global $_lang;
    if (!file_exists($filePath)) {
        return "<div class=\"alert alert-warning\">"  .$_lang['bkp_NotFound'] . "</div>";
    }

    $jsonData = file_get_contents($filePath);
    $settings = json_decode($jsonData, true);
    if (empty($settings)) {
        return "<div class=\"alert alert-danger\">"  .$_lang['bkp_NoData'] . "</div>";
    }

    $tablePrefix = $modx->db->config['table_prefix'];
    $settingsPrefix = isset($settings[0]['setting_name']) ? explode('_', $settings[0]['setting_name'])[0] . '_' : 'client_';
    $deleteQuery = "DELETE FROM `{$tablePrefix}system_settings` WHERE `setting_name` LIKE '" . $modx->db->escape($settingsPrefix) . "%'";

    if ($modx->db->query($deleteQuery)) {
        foreach ($settings as $setting) {
            $insertQuery = "INSERT INTO `{$tablePrefix}system_settings` (`setting_name`, `setting_value`) 
                            VALUES ('" . $modx->db->escape($setting['setting_name']) . "', 
                                    '" . $modx->db->escape($setting['setting_value']) . "')
                            ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)";
            $modx->db->query($insertQuery);
        }
        $modx->invokeEvent('OnClientSettingsSave', array('prefix' => $settingsPrefix, 'settings' => $settings));
        return "<div class=\"alert alert-success\">"  .$_lang['restore_Completed'] . "</div>";
    } else {
        return "<div class=\"alert alert-danger\">"  .$_lang['restore_Error'] . "</div>";
    }
}

// Funzione per gestire l'upload del file di ripristino
function handleFileUpload($filePath) {
    global $_lang;
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['backup_file']['tmp_name'];

        if (move_uploaded_file($uploadedFile, $filePath)) {
            return "<div class=\"alert alert-success\">"  .$_lang['upload_Completed'] . "</div>";
        } else {
            return "<div class=\"alert alert-danger\">"  .$_lang['upload_Error'] . "</div>";
        }
    }
    return null;
}

// Funzione per la cancellazione delle impostazioni
function deleteSettings($settingsPrefix) {
    global $modx;
    global $_lang;
    $tablePrefix = $modx->db->config['table_prefix'];
    $deleteQuery = "DELETE FROM `{$tablePrefix}system_settings` WHERE `setting_name` LIKE '" . $modx->db->escape($settingsPrefix) . "%'";
    if ($modx->db->query($deleteQuery)) {
        $modx->clearCache('full');
        return "<div class=\"alert alert-success\">"  .$_lang['delete_Completed'] . "</div>";
    } else {
        return "<div class=\"alert alert-danger\">"  .$_lang['delete_Error'] . "</div>";
    }
}

// Gestione delle azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action == 'backup') {
        $Bmessage = backupSettings($settingsPrefix, $filePath);
    } elseif ($action == 'upload') {
        $uploadMessage = handleFileUpload($filePath);
    } elseif ($action == 'restore') {
        $Rmessage = restoreSettings($filePath);
        $modx->clearCache('full');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($action == 'delete') {
        $Dmessage = deleteSettings($settingsPrefix);
    } else {
        $Dmessage = "Azione non valida!";
    }
}

// Verifica presenza file di backup e messaggio download
$backupExists = file_exists($filePath);
$backupMessage = $backupExists ? "<div class=\"alert alert-info\">"  .$_lang['bkp_FileFound'] . " <a class=\"btn btn-success\" href='" . MODX_SITE_URL . "{$backupPath}{$fileName}' download>" . $_lang['bkp_Download'] . "</a></div>" : "<div class=\"alert alert-warning\">"  .$_lang['bkp_NotFound'] . "</div>";

// HTML output
$html = '
<!DOCTYPE html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset='.$modx_manager_charset.'" />
    <title>' . $moduleName . '</title>
    <link type="text/css" rel="stylesheet" href="media/style/' . $modx->config['manager_theme'] . '/style.css">
    <script src="media/script/tabpane.js"></script>
</head>
<body>
<div class="sectionBody">
<h1 class="pagetitle">
  <span class="pagetitle-icon">
    <i class="fa fa-download"></i>
  </span>
  <span class="pagetitle-text">
    ' . $moduleName . '
  </span>
</h1>
<div class="container"> ' . $backupMessage . '</div>
<div id="actions">
    <ul class="actionButtons">
        <li id="Button5"><a href="index.php?a=2">
            '.$_lang['close'].'
        </a></li>
    </ul>
</div>
<div class="tab-pane" id="settingsbackupPanes">
   <script type="text/javascript">
        tpResources = new WebFXTabPane(document.getElementById("settingsbackupPanes"), true);
    </script>

<!-- Tab per il Backup -->
<div class="tab-page" id="tabbackup">
    <h2 class="tab"><a href="#tabpanel-settingbackup"><span><i class="fa fa-download" aria-hidden="true"></i> ' .$_lang['Backup_Settings']. '</span></a></h2>
    <div class="container">
        <h3><i class="fa fa-download" aria-hidden="true"></i>  ' .$_lang['Backup_Settings']. '</h3>
        '. (!empty($Bmessage) ? "<p><strong>$Bmessage</strong></p>" : '') .'
        <form method="post">
            <button type="submit" class="btn btn-success" name="action" value="backup">
                <i class="fa fa-download" aria-hidden="true"></i>  ' .$_lang['Backup_Settings']. '
            </button>
        </form>
    </div>
</div>

<!-- Tab per il caricamento del file di Restore -->
<div class="tab-page" id="tabupload">
    <h2 class="tab"><a href="#tabpanel-settingupload"><span><i class="fa fa-upload" aria-hidden="true"></i> '.$_lang['Upload_Restore_File'].'</span></a></h2>
    <div class="container">
        <h3><i class="fa fa-upload" aria-hidden="true"></i> '.$_lang['Upload_Restore_File'].'</h3>
        '. (!empty($uploadMessage) ? "<p><strong>$uploadMessage</strong></p>" : '') .'
        <form id="uploadForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="backup_file" accept=".json" class="form-control mb-2">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-upload" aria-hidden="true"></i> '.$_lang['Upload_File_btn'].'
            </button>
        </form>
    </div>
</div>

<!-- Tab per il Restore -->
<div class="tab-page" id="tabrestore">
    <h2 class="tab"><a href="#tabpanel-settingrestore"><span><i class="fa fa-refresh" aria-hidden="true"></i> '.$_lang['Restore_Settings'].'</span></a></h2>
    <div class="container">
        <h3><i class="fa fa-refresh" aria-hidden="true"></i> '.$_lang['Restore_Settings'].'</h3>
        '. (!empty($Rmessage) ? "<p><strong>$Rmessage</strong></p>" : '') .'
        <form id="restoreForm" method="post" onsubmit="return confirm(\''.$_lang['Restore_Confirm'].'\');">
            <input type="hidden" name="action" value="restore">
            <button type="submit" class="btn btn-warning">
                <i class="fa fa-refresh" aria-hidden="true"></i> '.$_lang['Restore_Settings'].'
            </button>
        </form>
    </div>
</div>

<!-- Tab per la Cancellazione -->
<div class="tab-page" id="tabdelete">
    <h2 class="tab"><a href="#tabpanel-settingdelete"><span><i class="fa fa-eraser" aria-hidden="true"></i> '.$_lang['Delete_Settings'].'</span></a></h2>
    <div class="container">
        <h3><i class="fa fa-eraser" aria-hidden="true"></i> '.$_lang['Delete_Settings'].'</h3>
        '. (!empty($Dmessage) ? "<p><strong>$Dmessage</strong></p>" : '') .'
        <form id="deleteForm" method="post" onsubmit="return confirm(\''.$_lang['Delete_Confirm'].'\');">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger">
                <i class="fa fa-eraser" aria-hidden="true"></i> '.$_lang['Delete_Settings'].'
            </button>
        </form>
    </div>
</div>
</div>
</div>
</body>
</html>
';
echo $html;
?>