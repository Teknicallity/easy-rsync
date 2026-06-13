<?php

namespace unraid\plugins\EasyRsync;

// Set document root for Unraid environment.
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
// Include Unraid's GUI helper functions.
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/webGui/include/Helpers.php";

require_once dirname(__DIR__) . "/include/ERSettings.php";

?>

<h3>The backup is <span id="backupStatusTextRsync" class="backupStatusText"></span>.</h3>
<div style='border: 1px solid red; height:500px; overflow:auto;' id='rsyngLogFrame'>Loading...</div>
<button class="manualBackupButton">Manual Backup</button>
<button class="manualDryBackupButton">Manual Dry Backup</button>
<div class="abortControls" style="display:inline-block;">
    <input type='button' class="abortBtn" value='Graceful Stop' disabled/>
    <input type='button' class="forceStopBtn" value='Force Stop' disabled/>

    <div class="abort-confirm graceful-confirm" style="display:none;">
        <p>Stop after the current sync finishes? The job in progress completes, then the remaining jobs are skipped.</p>
        <input type="button" value="Cancel" onclick="cancelAbortConfirm(this)"/>
        <input type="button" class="confirmGracefulButton" value="Graceful Stop" onclick="confirmGracefulStop(this)"/>
    </div>

    <div class="abort-confirm force-confirm" style="display:none;">
        <p>Force stop now? The transfer in progress is killed immediately. Files already copied are kept; nothing is deleted.</p>
        <input type="button" value="Cancel" onclick="cancelAbortConfirm(this)"/>
        <input type="button" class="confirmForceStopButton" value="Force Stop" onclick="confirmForceStop(this)"/>
    </div>
</div>

<script>
    const urlRsync = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php";

    document.addEventListener("DOMContentLoaded", function () {
        setInterval(checkBackupRsync, 1000);
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
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Request failed:', jqXHR.status, textStatus, errorThrown);
            rsyngLogDiv.textContent = 'Something went wrong while talking to the server.'
        })
    }

</script>