<?php
// Set document root for Unraid environment.
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
// Include Unraid's GUI helper functions.
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/webGui/include/Helpers.php";

require_once dirname(__DIR__) ."/include/ERSettings.php";

use unraid\plugins\EasyRsync\ERSettings;
?>

<style>
    .backupRunning {
        color: green;
    }

    .backupNotRunning {
        color: red;
    }
</style>

<h3>The backup is <span id="backupStatusTextStatus" class="backupStatusText"></span>.</h3>
<div style='border: 1px solid red; height:500px; overflow:auto;' id='statusLogFrame'>Loading...</div>
<input type='button' class="abortBtn" value='Abort' disabled/>

<script>
    const urlStatus = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php";

    document.addEventListener("DOMContentLoaded", function () {
        setInterval(checkBackup, 5000);

        $('.abortBtn').on('click', function () {
            $.post(urlStatus, {
                action: 'abort',
            }, function (response) {
                console.log(response);
            })
        });

        checkBackup();
    });

    function checkBackup() {
        const backupStatusTextElements = document.querySelectorAll('.backupStatusText');
        const statusLogDiv = document.getElementById('statusLogFrame');
        const abortBtnElements = document.querySelectorAll('.abortBtn');

        $.get(`${urlStatus}?action=getPluginLog`, function (data) {
            console.log(data);

            if (data.log === "") {
                statusLogDiv.innerHTML = "<p>The log does not exist or is empty</p>";
            } else {
                statusLogDiv.innerHTML = data.log;
            }

            function updateStatus(statusClass, txt) {
                backupStatusTextElements.forEach(element => {
                    element.classList.remove('backupRunning', 'backupNotRunning');
                    element.classList.add(statusClass);
                    element.textContent = txt;
                });
                
                abortBtnElements.forEach(button => button.disabled = (statusClass === 'backupNotRunning'));
            }

            if (data.running) {
                updateStatus('backupRunning', 'Running');
            } else {
                updateStatus('backupNotRunning', 'Stopped');
            }

        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Request failed:', jqXHR.status, textStatus, errorThrown);
            statusLogDiv.textContent = 'Something went wrong while talking to the server.'
        });
    };
</script>