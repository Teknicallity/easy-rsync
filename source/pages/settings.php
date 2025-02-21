<?php

require_once dirname(__DIR__) ."/include/ERSettings.php";
require_once dirname(__DIR__) ."/include/Logger.php";
require_once "/usr/local/emhttp/plugins/dynamix/include/Helpers.php"; //mk_option

use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\LogLevel;
use unraid\plugins\EasyRsync\Logger;
/*
Take list of directories to backup
take remote backup host and path
*/

$logger = new Logger(LogLevel::DEBUG);

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
    file_put_contents(ERSettings::$configDir ."/form_output.txt", $results);

    // Update user config
    $currentConfig = ERSettings::getUserConfig();
    foreach ($currentConfig as $key => $value){
        if(isset($_POST[$key])){
            $currentConfig[$key] = $_POST[$key];
            $logger->logDebug("Key: $key, Old: $value, New: ". $_POST[$key]);
        }
    }

    // Save user config
    try {
        $output = ERSettings::saveUserConfig($currentConfig);
    } catch (Exception $e) {
        $logger->logError("Could not save user config". $e->getMessage());
    }
    $logger->logInfo("Saved user config");


    $sources = null;
    $destinations = null;
    // save filepaths and hosts
    if(isset($_POST["sourceDirectories"])){
        $sources = explode("\n", $_POST["sourceDirectories"]);
    }
    if(isset($_POST["destinationHosts"])){
        $destinations = explode("\n", $_POST["destinationHosts"]);
    }
    $logger->logDebug("Got path results");
    $logger->logDebug(print_r($sources, true));
    $logger->logDebug(print_r($destinations, true));
    try {
        ERSettings::saveSourcesAndDestinations($sources, $destinations);
    } catch (Exception $e) {
        $logger->logError("Could not save paths". $e->getMessage());
    }
    $logger->logInfo("Saved backup paths result");
}

$userConfig = ERSettings::getUserConfig();
$paths = ERSettings::getPaths();

if ($_POST) {
    list($outString, $returnCode) = ERSettings::updateCron();
}

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
                <?= mk_option($userConfig["rsyncTimes"], "false", "No") ?>
                <?= mk_option($userConfig["rsyncTimes"], "true", "Yes") ?>
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
                <?= mk_option($userConfig["rsyncCompress"], "false", "No") ?>
                <?= mk_option($userConfig["rsyncCompress"], "true", "Yes") ?>
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
                style="resize: vertical; width: 400px;"><?= implode("\r\n", $paths["sources"]) ?></textarea>
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
        <dt>Backup Frequency</dt>
        <dd>
            <select id="backupFrequency" name="backupFrequency">
                <?= mk_option($userConfig["backupFrequency"], "disabled", "Disabled") ?>
                <?= mk_option($userConfig["backupFrequency"], "daily", "Daily") ?>
                <?= mk_option($userConfig["backupFrequency"], "weekly", "Weekly") ?>
                <?= mk_option($userConfig["backupFrequency"], "monthly", "Monthly") ?>
                <?= mk_option($userConfig["backupFrequency"], "custom", "Custom") ?>
            </select>
        </dd>

        <dt>Day of Week:</dt>
        <dd>
            <select id="frequencyWeekday" name="frequencyWeekday">
                <?= mk_option($userConfig["frequencyWeekday"], "0", "Sunday") ?>
                <?= mk_option($userConfig["frequencyWeekday"], "1", "Monday") ?>
                <?= mk_option($userConfig["frequencyWeekday"], "2", "Tuesday") ?>
                <?= mk_option($userConfig["frequencyWeekday"], "3", "Wednesday") ?>
                <?= mk_option($userConfig["frequencyWeekday"], "4", "Thursday") ?>
                <?= mk_option($userConfig["frequencyWeekday"], "5", "Friday") ?>
                <?= mk_option($userConfig["frequencyWeekday"], "6", "Saturday") ?>
            </select>
        </dd>

        <dt>Day of Month</dt>
        <dd>
            <select id="frequencyDayOfMonth" name="frequencyDayOfMonth">
                <?= mk_option($userConfig["frequencyDayOfMonth"], "1", "1st") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "2", "2nd") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "3", "3rd") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "4", "4th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "5", "5th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "6", "6th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "7", "7th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "8", "8th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "9", "9th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "10", "10th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "11", "11th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "12", "12th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "13", "13th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "14", "14th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "15", "15th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "16", "16th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "17", "17th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "18", "18th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "19", "19th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "20", "20th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "21", "21st") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "22", "22nd") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "23", "23rd") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "24", "24th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "25", "25th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "26", "26th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "27", "27th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "28", "28th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "29", "29th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "30", "30th") ?>
                <?= mk_option($userConfig["frequencyDayOfMonth"], "31", "31st") ?>

            </select>
        </dd>

        <dt>Hour</dt>
        <dd>
            <input type="number" min="0" max="23" id="frequencyHour" name="frequencyHour" value="<?= $userConfig["frequencyHour"] ?>">
        </dd>

        <dt>Minute</dt>
        <dd>
            <input type="number" min="0" max="59" id="frequencyMinute" name="frequencyMinute" value="<?= $userConfig["frequencyMinute"] ?>">
        </dd>

        <dt>Custom Entry</dt>
        <dd>
            <input type="text" id="frequencyCustom" name="frequencyCustom"
                   value="<?= $userConfig["frequencyCustom"] ?>" placeholder="Will disable other time options">
        </dd>
    </dl>

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