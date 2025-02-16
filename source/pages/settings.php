<?php
require_once dirname(__DIR__) ."/include/ERSettings.php";

use unraid\plugins\EasyRsync\ERSettings;
/*
Take list of directories to backup
take remote backup host and path
*/

try {
    $appName = ERSettings::$appName;
    echo "/plugins/" . $appName . "/include/http_handler.php\n";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

if ($_POST) {

}

?>

<div class="title">Settings</div>

<form id="erSettingsForm" method="post">
    <dl>
        <dt>Local directories</dt>
        <dd>
            <div style="display: table; width: 300px;">
                <textarea id="sourceDirectories" name="sourceDirectories"
                    onfocus="$(this).next('.ft').slideDown('fast');" style="resize: vertical; width: 400px;">
                    <?= implode("\r\n", ERSettings::getPaths()["sources"]) ?>
                </textarea>
                <div class="ft" style="display: none;">
                    <div class="fileTreeDiv"></div>
                    <button onclick="addSelectionToList(this);  return false;">Add to sources</button>
                </div>
            </div>
        </dd>
    </dl>
    <blockquote class='inline_help'>
        <p>Any local directories that should be backed up</p>
    </blockquote>
    
    <dl>
        <dt>Local directories</dt>
        <dd>
            <div style="display: table; width: 300px;">
                <textarea id="destinationHosts" name="destinationHosts"
                    onfocus="$(this).next('.ft').slideDown('fast');" style="resize: vertical; width: 400px;">
                    <?= implode("\r\n", ERSettings::getPaths()["destinations"]) ?>
                </textarea>
                <!-- <div class="ft" style="display: none;">
                    <button onclick="">Add to hosts</button>
                </div> -->
            </div>
        </dd>
    </dl>
    <blockquote class='inline_help'>
        <p>Any remote host destinations that files will be copied to</p>
    </blockquote>

</form>

<div>
    <button id="manualBackupButton">Manual BackupJQuery</button>
    <button id="manualDryBackupButton">Manual Dry Backup</button>
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

        $('#manualDryBackupButton').on('click', function() {
            $.post(urlSettings, {
                action: 'manualDryBackup',
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

    function addSelectionToList(element) {
        $el = $(element).prev().find("input:checked");
        $textarea = $(element).parent().prev();

        console.debug($el, $textarea);

        if ($el.length !== 0) {
            var checked = $el
                .map(function () {
                    return $(this).parent().find('a:first').attr('rel');
                })
                .get()
                .join('\n');

            if ($textarea.val() === "") {
                $textarea.val(checked);
            } else {
                $textarea.val($textarea.val() + "\n" + checked);
            }
        }
        $(element).parent().slideUp('fast', function () {
            $el.prop('checked', false);
        });
    }
</script>