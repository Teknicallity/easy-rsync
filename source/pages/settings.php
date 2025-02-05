<?php

use unraid\plugins\EasyRsync\ERSettings;

echo "settings";

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