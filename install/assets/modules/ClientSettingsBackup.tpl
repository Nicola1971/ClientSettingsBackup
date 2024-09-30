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

// Funzione per il backup
function backupSettings($settingsPrefix, $filePath) {
    global $modx;
    $tablePrefix = $modx->db->config['table_prefix'];
    $query = "SELECT * FROM `{$tablePrefix}system_settings` WHERE `setting_name` LIKE '" . $modx->db->escape($settingsPrefix) . "%'";
    $result = $modx->db->query($query);
    $settings = [];

    while ($row = $modx->db->getRow($result)) {
        $settings[] = $row;
    }

    // Crea la cartella se non esiste
    if (!is_dir(dirname($filePath))) {
        mkdir(dirname($filePath), 0755, true);
    }

    // Salva i dati in formato JSON
    if (file_put_contents($filePath, json_encode($settings))) {
        return "Backup completato con successo! File salvato in: {$filePath}";
    } else {
        return "Errore durante il backup!";
    }
}

// Funzione per il restore
function restoreSettings($filePath) {
    global $modx;
    if (!file_exists($filePath)) {
        return "File di backup non trovato!";
    }

    $jsonData = file_get_contents($filePath);
    $settings = json_decode($jsonData, true);
    if (empty($settings)) {
        return "Nessun dato trovato nel file o errore nel file JSON!";
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

        return "Ripristino completato con successo!";
    } else {
        return "Errore durante l'eliminazione delle vecchie impostazioni.";
    }
}

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action == 'backup') {
        $message = backupSettings($settingsPrefix, $filePath);
    } elseif ($action == 'restore') {
        $message = restoreSettings($filePath);
    } else {
        $message = "Azione non valida!";
    }
} else {
    $message = '';
}

// Verifica se esiste un file di backup e crea un messaggio e link di download
$backupExists = file_exists($filePath);
$backupMessage = $backupExists ? "<p>File di backup trovato: <a href='" . MODX_SITE_URL . "{$backupPath}{$fileName}' download>Scarica il file di backup</a></p>" : "<p>Nessun file di backup trovato.</p>";

// Inizializza l'output HTML
$html = '
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Backup e Restore delle Impostazioni</title>
</head>
<body>
    <h1>Gestione Backup e Restore</h1>

    <!-- Mostra il messaggio di risultato, se presente -->
    '. (!empty($message) ? "<p><strong>$message</strong></p>" : '') .'

    <!-- Mostra la presenza di un file di backup -->
    ' . $backupMessage . '

    <!-- Form con i pulsanti per Backup e Restore -->
    <form method="post">
        <button type="submit" name="action" value="backup">Esegui Backup</button>
        <button type="submit" name="action" value="restore">Esegui Restore</button>
    </form>
</body>
</html>
';

// Visualizza l'output HTML
echo $html;