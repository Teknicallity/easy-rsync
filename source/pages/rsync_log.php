<?php
// Set document root for Unraid environment.
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
// Include Unraid's GUI helper functions.
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/webGui/include/Helpers.php";

require_once dirname(__DIR__) . "/include/ERSettings.php";

use unraid\plugins\EasyRsync\ERSettings;

?>

<h3>The backup is <span id="backupStatusTextRsync" class="backupStatusText"></span>.</h3>
<div style='border: 1px solid red; height:500px; overflow:auto;' id='rsyngLogFrame'>Loading...</div>
<input type='button' class="abortBtn" value='Abort' disabled/>

<script>
    const urlRsync = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php";

    document.addEventListener("DOMContentLoaded", function () {
        setInterval(checkBackupRsync, 1000);

        // Still loaded from status_log
        // const abortBtn = document.getElementById('abortBtn');
        // $('#abortBtn').on('click', function () {
        //     $.post(urlRsync, {
        //         action: 'abort',
        //     }, function (response) {
        //         console.log(response);
        //     })
        // });

        checkBackupRsync();
    });

    function checkBackupRsync() {
        const rsyngLogDiv = document.getElementById('rsyngLogFrame');

        $.get(`${urlRsync}?action=getRsyncLog`, function (data) {
            // console.log(data);

            if (data.log === "") {
                rsyngLogDiv.innerHTML = "<p>The log does not exist or is empty</p>";
            } else {
                rsyngLogDiv.innerHTML = data.log;
            }

            // Still loaded from status_log
            // if (data.status === 'running') {
            //     backupStatusText.className = 'backupRunning';
            //     backupStatusText.textContent = 'Running';
            //     abortBtn.disabled = false;
            // } else if (data.status === 'stopped') {
            //     backupStatusText.className = 'backupNotRunning';
            //     backupStatusText.textContent = 'Not Running';
            //     abortBtn.disabled = true;
            // }

        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Request failed:', jqXHR.status, textStatus, errorThrown);
            rsyngLogDiv.textContent = 'Something went wrong while talking to the server.'
        })
    }

</script>