/**
 * ClientSettingsBackup
 *
 * ClientSettings Backup and restore
 *
 * @category	module
 * @version     1.0
 * @author      Author: Nicola Lambathakis http://www.tattoocms.it/
 * @internal	@modx_category Manager
 * @internal    @properties &settingsPrefix= Settings Prefix:;string;client_ &backupPath= Backup Path:;string;assets/files/
 */
header('Content-Type: text/html; charset=UTF-8');

$settingsPrefix = isset($settingsPrefix) ? $settingsPrefix : 'client_';
$backupPath = isset($backupPath) ? $backupPath : 'assets/files/';
$backupDir = MODX_BASE_PATH . $backupPath;
$fileName = 'backup_settings.json';
$filePath = $backupDir . $fileName;

// Backup function
function backupSettings($settingsPrefix, $filePath) {
    global $modx;
    $tablePrefix = $modx->db->config['table_prefix'];
    $query = "SELECT * FROM `{$tablePrefix}system_settings` WHERE `setting_name` LIKE '" . $modx->db->escape($settingsPrefix) . "%'";
    $result = $modx->db->query($query);
    $settings = [];

    while ($row = $modx->db->getRow($result)) {
        $settings[] = $row;
    }

    // Create the folder if it doesn't exist
    if (!is_dir(dirname($filePath))) {
        mkdir(dirname($filePath), 0755, true);
    }

    // Save data in JSON format
    if (file_put_contents($filePath, json_encode($settings))) {
        return "<div class=\"alert alert-success\">Backup completed successfully! File saved in: {$filePath}</div>";
    } else {
        return "<div class=\"alert alert-danger\">Error during backup!</div>";
    }
}

// Restore function
function restoreSettings($filePath) {
    global $modx;
    if (!file_exists($filePath)) {
        return "Backup file not found!";
    }

    $jsonData = file_get_contents($filePath);
    $settings = json_decode($jsonData, true);
    if (empty($settings)) {
        return "<div class=\"alert alert-warning\">No data found in the file or error in the JSON file!</div>";
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

        return "<div class=\"alert alert-success\">Restore completed successfully!/div>";
		$modx->invokeEvent('OnClientSettingsSave', array('prefix' => $settingsPrefix, 'settings' => $settings));
    } else {
        return "<div class=\"alert alert-danger\">Error while deleting old settings.</div>";
    }
}

// Action handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action == 'backup') {
        $message = backupSettings($settingsPrefix, $filePath);
    } elseif ($action == 'restore') {
        $message = restoreSettings($filePath);
		$modx->clearCache('full');
    } else {
        $message = "Invalid action!";
    }
} else {
    $message = '';
}

// Check if a backup file exists and create a message and download link
$backupExists = file_exists($filePath);
$backupMessage = $backupExists ? "<div class=\"alert alert-info\">Backup file found: <a href='" . MODX_SITE_URL . "{$backupPath}{$fileName}' download>Download backup file</a></div>" : "<div class=\"alert alert-warning\">No backup file found.</div>";

// Initialize HTML output
$html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings Backup and Restore</title>
	<link type="text/css" rel="stylesheet" href="media/style/' . $modx->config['manager_theme'] . '/style.css">
	<script src="media/script/tabpane.js"></script>
</head>
<body>
<div class="tab-pane" id="settingsbackupPanes">
    <h1>Settings Backup & Restore</h1>
<div class="tab-page" id="tabsettingsbackup">

<h2 class="tab"><a href="#tabpanel-settingsbackup"><span><i class="fa fa-cog" aria-hidden="true"></i> ClientSettings Backup & Restore</span></a></h2>

<div class="container">
    '. (!empty($message) ? "<p><strong>$message</strong></p>" : '') .'

    <!-- Show the presence of a backup file -->
    ' . $backupMessage . '

    <!-- Form with buttons for Backup and Restore -->
    <form method="post">
        <button type="submit" class="btn btn-success" name="action" value="backup"><i class="fa fa-download" aria-hidden="true"></i> Backup Settings</button>
        <button type="submit" class="btn btn-warning" name="action" value="restore"><i class="fa fa-refresh" aria-hidden="true"></i> Restore Settings</button>
    </form>
	</div>
</div>
</div>
</body>
</html>
';

// Display HTML output
echo $html;