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
    <button id="manualBackupButtonJS">Manual BackupJS</button>
    <button id="manualBackupButton">Manual BackupJQuery</button>
    <button id="getBackupStatusJS">Get StatusJS</button>
    <button id="getBackupStatus">Get StatusJQuery</button>
    <div id="backupStatusDisplay"></div>
</div>

<script>
    $(document).ready(function() {
        const postUrl = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php";

        $('#manualBackupButton').on('click', function() {
            const data = JSON.stringify({ action: 'manualBackup' });

            console.log(data);
            
            $.post({
                url: postUrl,
                contentType: 'application/json',
                csrf: '<?=$var['csrf_token']?>',
                data: data,
                success: function(response) {
                    console.log('Success:', response);
                },
                error: function(xhr, status, error) {
                    console.error('Error:', xhr.responseText);
                }
            });
        });
    });

    $(document).ready(function() {
        const url = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php";
        
        // Construct the full URL with query parameters
        const fullUrl = `${url}?action=getBackupStatus`;

        $('#getBackupStatus').on('click', function(){
            console.log('clicked getBackupStatusJQuery');
            
            $.get(fullUrl, function(data) {
                console.log('Response:', data);
                // You can handle the response data here
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    // Assuming the response is JSON with a status field
                    console.log('Backup Status:', data.status);
                    // Further processing of the backup status
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('Request failed:', textStatus, errorThrown);
            });
        })
    });

    const manualBackupButtonJS = document.getElementById('manualBackupButtonJS');
    let u = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php";
    manualBackupButtonJS.addEventListener('click', () => {
        console.log('javascript manualBackupButton');
        
        fetch(u, {
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
        });
    });

    const getBackupStatusJS = document.getElementById('getBackupStatusJS');
    let ur = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php?action=getBackupStatus";
    getBackupStatusJS.addEventListener('click', () => {
        console.log('getstatus javascript');
        
        fetch(ur)
            .then(response => {
                if (!response.ok) { // Check for response errors
                    throw new Error('Network response was not ok');
                }
                return response.json();  // Correctly parse JSON from the response
            })
            .then(data => {
                console.log('Data:', data);  // Log parsed data here
            })
            .catch((error) => {
                console.error('Error: ', error);
            });
    });
</script>