<?php
require_once dirname(__DIR__) ."/include/ERSettings.php";
require_once "/usr/local/emhttp/plugins/dynamix/include/Helpers.php"; //mk_option

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
    if (!file_exists(ERSettings::$configDir)) {
        mkdir(ERSettings::$configDir);
    }
    $results = print_r($_POST, true);
    file_put_contents(ERSettings::$configDir ."form_output.txt", $results);

    // save user config
    $currentConfig = self::getUserConfig();
    foreach ($currentConfig as $key => $value){
        if(isset($_POST[$key])){
            $currentConfig = $_POST[$key];
        }
    }

    $sources = null;
    $destinations = null;
    // save filepaths and hosts
    if(isset($_POST["sourceDirectories"])){
        $sources = $_POST["sourceDirectories"];
    }
    if(isset($_POST["destinationHosts"])){
        $destinations = $_POST["destinationHosts"];
    }
    ERSettings::saveSourcesAndDestinations($sources, $destinations);
}

$userConfig = ERSettings::getUserConfig();
$paths = ERSettings::getPaths();

?>
<link type="text/css" rel="stylesheet" href="<?php autov('/webGui/styles/jquery.filetree.css') ?>">
<script src="<?php autov('/webGui/javascript/jquery.filetree.js') ?>" charset="utf-8"></script>


<form id="erSettingsForm" method="post">
    <div class="title">Settings</div>

    <dl>
        <dt>Text Checkbox</dt>
        <dd><input type="checkbox" id="testBox" name="testBox" value="Testing"/></dd>
    </dl>
    <blockquote class="inline_help"></blockquote>

    <!-- </blockquote>
    <dl>
        <dt></dt>
        <dd>
            <select>
                <option></option>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">

    </blockquote> -->
    
    <dl>
        <dt>Capture file datetimes</dt>
        <dd>
            <select id="rsyncTimes" name="rsyncTimes">
                <?= mk_option($userConfig["rsyncTimes"], 0, "No") ?>
                <?= mk_option($userConfig["rsyncTimes"], 1, "Yes") ?>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">

    </blockquote>
    
    <dl>
        <dt>When to delete old files</dt>
        <dd>
            <select id="rsyncDelete" name="rsyncDelete">
                <!-- <option value="after">After</option>
                <option value="before">Before</option>
                <option value="during">During</option>
                <option value="delay">Delay</option> -->
                <?= mk_option($userConfig["rsyncDelete"], "after", "After") ?>
                <?= mk_option($userConfig["rsyncDelete"], "before", "Before") ?>
                <?= mk_option($userConfig["rsyncDelete"], "during", "During") ?>
                <?= mk_option($userConfig["rsyncDelete"], "delay", "Delay") ?>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">
        Look up rsync delete-after
    </blockquote>
    
    <dl>
        <dt>Compress backup</dt>
        <dd>
            <select id="rsyncCompress" name="rsyncCompress">
                <?= mk_option($userConfig["rsyncCompress"], 0, "No") ?>
                <?= mk_option($userConfig["rsyncCompress"], 1, "Yes") ?>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">

    </blockquote>
    
    <dl>
        <dt>Local directories</dt>
        <dd>
            <div style="display: table; width: 300px;">
                <textarea id="sourceDirectories" name="sourceDirectories" onfocus="$(this).next('.ft').slideDown('fast');" 
                style="resize: vertical; width: 400px;"><?= implode("\r\n", $paths["sources"]) ."\r\n" ?></textarea>
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
                <textarea id="destinationHosts" name="destinationHosts" onfocus="$(this).next('.ft').slideDown('fast');" 
                    style="resize: vertical; width: 400px;"><?= implode("\r\n", $paths["destinations"]) ."\r\n" ?></textarea>
                <!-- <div class="ft" style="display: none;">
                    <button onclick="">Add to hosts</button>
                </div> -->
            </div>
        </dd>
    </dl>
    <blockquote class='inline_help'>
        <p>Any remote host destinations that files will be copied to</p>
    </blockquote>

    <dl>
        <dt>Done?</dt>
        <dd>
            <input type="submit" value="Save" id="submitBtn"/>
            <input type="reset" value="Discard"/>
        </dd>
    </dl>
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
        $('.fileTreeDiv').fileTree({
            multiSelect: true,
        });

        // Start manual backup
        $('#manualBackupButton').on('click', function() {
            $.post(urlSettings, {
                action: 'manualBackup',
            }, function(response) {
                console.log('Success:', response);
            });
        });

        // Start dry test backup
        $('#manualDryBackupButton').on('click', function() {
            $.post(urlSettings, {
                action: 'manualDryBackup',
            }, function(response) {
                console.log('Success:', response);
            });
        });
        
        // Get current backup status
        // Construct the full URL with query parameters
        const getUrl = `${urlSettings}?action=getBackupStatus`;
        $('#getBackupStatus').on('click', function(){
            console.log('clicked getBackupStatusJQuery');
            
            $.get(getUrl, function(data) {
                console.log('Response:', data);
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

    // $('#erSettingsForm').on('submit', function () {
        
    // })
</script>