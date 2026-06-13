<?php

namespace unraid\plugins\EasyRsync;

// Set document root for Unraid environment.
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
// Include Unraid's GUI helper functions.
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/webGui/include/Helpers.php";

require_once dirname(__DIR__) ."/include/ERSettings.php";

?>

<style>
    .backupRunning {
        color: green;
    }

    .backupNotRunning {
        color: red;
    }

    .abort-confirm {
        padding: 10px 15px;
        margin-top: 10px;
        border: 1px solid #c44;
        background: rgba(204, 68, 68, 0.08);
        border-radius: 3px;
        max-width: 400px;
        box-sizing: border-box;
    }

    .abort-confirm p {
        margin: 0 0 10px 0;
    }

    .confirmForceStopButton, .confirmGracefulButton {
        background-color: #c44 !important;
        color: white !important;
    }
</style>

<h3>The backup is <span id="backupStatusTextStatus" class="backupStatusText"></span>.</h3>
<div style='border: 1px solid red; height:500px; overflow:auto;' id='statusLogFrame'>Loading...</div>
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
    const urlStatus = "/plugins/<?= ERSettings::$appName ?>/include/http_handler.php";

    document.addEventListener("DOMContentLoaded", function () {
        setInterval(checkBackupStatus, 1000);

        $('.abortBtn').on('click', function (e) {
            if (e.shiftKey) { postAbort('abort'); return; }
            showAbortConfirm(this, 'graceful-confirm');
        });

        $('.forceStopBtn').on('click', function (e) {
            if (e.shiftKey) { postAbort('abortNow'); return; }
            showAbortConfirm(this, 'force-confirm');
        });

        // Escape cancels any open confirmation (mirrors the Remove pattern).
        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                $('.abort-confirm:visible').each(function () { cancelAbortConfirm(this); });
            }
        });

        // Shift hints that the confirmation will be skipped (mirrors the Remove pattern).
        $(document).on('keydown keyup', function (event) {
            $('.abortBtn').val(event.shiftKey ? 'Graceful Stop (no confirm)' : 'Graceful Stop');
            $('.forceStopBtn').val(event.shiftKey ? 'Force Stop (no confirm)' : 'Force Stop');
        });
        $(window).on('blur', function () {
            $('.abortBtn').val('Graceful Stop');
            $('.forceStopBtn').val('Force Stop');
        });

        checkBackupStatus();
    });

    function postAbort(action) {
        $.post(urlStatus, { action: action }, function (response) {
            console.log(response);
        });
    }

    function showAbortConfirm(btn, confirmClass) {
        const $c = $(btn).closest('.abortControls');
        $c.find('.abortBtn, .forceStopBtn').hide();
        $c.find('.abort-confirm').hide();
        $c.find('.' + confirmClass).slideDown('fast');
    }

    function cancelAbortConfirm(el) {
        const $c = $(el).closest('.abortControls');
        $c.find('.abort-confirm:visible').slideUp('fast', function () {
            $c.find('.abortBtn, .forceStopBtn').show();
        });
    }

    function confirmGracefulStop(el) { postAbort('abort');    cancelAbortConfirm(el); }
    function confirmForceStop(el)    { postAbort('abortNow'); cancelAbortConfirm(el); }

    function checkBackupStatus() {
        const backupStatusTextElements = document.querySelectorAll('.backupStatusText');
        const statusLogDiv = document.getElementById('statusLogFrame');
        const abortBtnElements = document.querySelectorAll('.abortBtn');
        const forceStopBtnElements = document.querySelectorAll('.forceStopBtn');

        $.get(`${urlStatus}?action=getPluginLog`, function (data) {
            // console.log(data);

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
                forceStopBtnElements.forEach(button => button.disabled = (statusClass === 'backupNotRunning'));
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
    }
</script>