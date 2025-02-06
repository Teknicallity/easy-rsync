<?php
require_once "/usr/local/emhttp/plugins/easy.rsync/include/ERSettings.php";

use unraid\plugins\EasyRsync\ERSettings;

echo "\nsettings\n";

if (class_exists('unraid\plugins\EasyRsync\ERSettings')) {
    echo "ERSettings class is loaded successfully.\n";
} else {
    die("ERSettings class not found. Please check your autoloading configuration.");
}

try {
    $appName = ERSettings::$appName;
    echo "/plugins/" . $appName . "/include/http_handler.php\n";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<div>
    <button id="manualBackupButton">Manual Backup</button>
</div>

<script>
    const manualBackupButton = document.getElementById('manualBackupButton');

    let url = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php";

    manualBackupButton.addEventListener('click', () => {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action: 'manualBackup' })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Success: ', data);
        })
        .catch((error) => {
            console.error('Error: ', error);
        })
    })
</script>