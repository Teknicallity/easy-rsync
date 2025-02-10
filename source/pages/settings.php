<?php
require_once dirname(__DIR__) ."/include/ERSettings.php";

use unraid\plugins\EasyRsync\ERSettings;

echo "\nsettings\n";

if (class_exists('unraid\plugins\EasyRsync\ERSettings')) {
    echo "ERSettings class is loaded successfully.\n";
} else {
    die("ERSettings class not found. Please check your autoloading configuration.");
}

try {
    $unbalanced_cfg = parse_plugin_cfg("unbalanced");
    print_r($unbalanced_cfg);
} catch (Exception $e) {
    print_r($e->getMessage());
}

/*
Take list of directories to backup
take remote backup host and path

call rsync helper

*/

try {
    $appName = ERSettings::$appName;
    echo "/plugins/" . $appName . "/include/http_handler.php\n";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<div>
    <button id="manualBackupButton">Manual BackupJQuery</button>
    <button id="getBackupStatus">Get StatusJQuery</button>
    <div id="backupStatusDisplay"></div>
</div>

<script>
    const urlSettings = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php";
    $(function() {

        $('#manualBackupButton').on('click', function() {
            $.post(urlSettings, {
                action: 'manualBackup',
            }, function(response) {
                console.log('Success:', response);
            });
        });
        
        // Construct the full URL with query parameters
        const getUrl = `${urlSettings}?action=getBackupStatus`;

        $('#getBackupStatus').on('click', function(){
            console.log('clicked getBackupStatusJQuery');
            
            $.get(getUrl, function(data) {
                console.log('Response:', data);
                // You can handle the response data here
                console.log('Backup Status:', data.status);

            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('Request failed:', jqXHR.status, textStatus, errorThrown);
            });
        });
    });
</script>