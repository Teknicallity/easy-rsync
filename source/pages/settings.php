<?php

namespace unraid\plugins\EasyRsync;

use Exception;

require_once dirname(__DIR__) ."/include/ERSettings.php";
require_once dirname(__DIR__) ."/include/Logger.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncList.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncEntry.php";
require_once "/usr/local/emhttp/plugins/dynamix/include/Helpers.php"; //mk_option

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
    $userConfig = ERSettings::getUserConfig();

    $logger = Logger::getLogger(loglevelString: $userConfig["logLevel"]);

//    ob_start();
//    var_dump($_POST);
//    $output = ob_get_clean();
//
//    $logger->logInfo($output);
//
//    die();

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
            $logger->debug("Key: $key, Old: $value, New: ". $_POST[$key]);
        }
    }

    // Save user config
    try {
        $output = ERSettings::saveUserConfig($currentConfig);
    } catch (Exception $e) {
        $logger->error("Could not save user config". $e->getMessage());
    }
    $logger->info("Saved user config");

    // Save sync job paths
    if (isset($_POST["syncEntries"])) {
        $logger->debug(print_r($_POST["syncEntries"], true));

        $syncList = SyncList::fromArray($_POST);

        $logger->debug("Got path results");
        try {
            $syncList->saveToFile();
            $logger->info("Saved backup paths result");
        } catch (Exception $e) {
            $logger->error("Could not save paths". $e->getMessage());
        }
    }
}

if ($_POST) {
    list($outString, $returnCode) = ERSettings::updateCron();
}

$userConfig = ERSettings::getUserConfig();
$syncList = SyncList::fromFile();

function bool_to_str($val): string {
    if ($val === true) return "true";
    if ($val === false) return "false";
    return $val;
}

?>
<link type="text/css" rel="stylesheet" href="<?php autov('/webGui/styles/jquery.filetree.css') ?>">
<script src="<?php autov('/webGui/javascript/jquery.filetree.js') ?>" charset="utf-8"></script>
<!--<script src="<php autov('/plugins/easy.rsync/javascript/unraid.js') ?>" charset="utf-8"></script>-->

<style>
    .sync-entry {
        padding: 15px;
        border: 1px solid #ddd;
    }

    .sync-entry(:last-child) {
        margin-bottom: 20px;
    }

    .fileTree, .sync-entry textarea {
        background: rgba(0,0,0,0.05);
    }

    .deleteSyncJobButton {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }
</style>


<form id="erSettingsForm" method="post">
    <div class="title">
        <div style="display: flex; justify-content: space-between">
            <span>Settings</span>
            <div>
                <span>Status: </span>
                <span id="backupStatusTextSettings" class="backupStatusText"></span>
            </div>
        </div>
    </div>

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
            <select id="rsyncTimes" name="rsyncTimes" class="rsyncOption globalOption">
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
            <select id="rsyncDelete" name="rsyncDelete" class="rsyncOption globalOption">
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
            <select id="rsyncCompress" name="rsyncCompress" class="rsyncOption globalOption">
                <?= mk_option($userConfig["rsyncCompress"], "false", "No") ?>
                <?= mk_option($userConfig["rsyncCompress"], "true", "Yes") ?>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">

    </blockquote>

    <dl>
        <dt>Custom Rsync Parameters</dt>
        <dd>
            <input type="text" id="rsyncCustom" name="rsyncCustom" oninput="updateGlobalRsyncOptions();"
                   value="<?= $userConfig["rsyncCustom"] ?>" class="rsyncCustomParam globalOption"
                   placeholder="Will override other Rsync options">
        </dd>
    </dl>

    <dl>
        <dt>Easy Rsync Log Level</dt>
        <dd>
            <select id="logLevel" name="logLevel">
                <?= mk_option($userConfig["logLevel"], "DEBUG", "Debug") ?>
                <?= mk_option($userConfig["logLevel"], "INFO", "Info") ?>
                <?= mk_option($userConfig["logLevel"], "WARNING", "Warning") ?>
                <?= mk_option($userConfig["logLevel"], "ERROR", "Error") ?>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">
        Set the log level for the plugin log. Recommended to keep on Info.
    </blockquote>

    <dl>
        <dt>Backup Frequency</dt>
        <dd>
            <select id="backupFrequency" name="backupFrequency" onchange="updateCronEntries();">
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

    <div class="title">
        Sync Jobs
    </div>
    <div id="syncEntriesContainer">
        <?php foreach($syncList->entries as $index => $syncEntry) { ?>
        <div class="sync-entry" data-entry-id="<?= (int)$index ?>">
            <dl>
                <dt>Local Directories</dt>
                <dd>
                    <div style="display: table; width: 300px;">
                        <textarea name="syncEntries[<?= (int)$index; ?>][sources]"
                                  onfocus="$(this).next('.ft').slideDown('fast');"
                                  style="resize: vertical; width: 400px;"
                                  rows="3"><?= implode("\r\n", $syncEntry->sources) ?></textarea>
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
                <dt>Destination Hosts</dt>
                <dd>
                    <div style="display: table; width: 300px;">
                        <textarea name="syncEntries[<?= (int)$index; ?>][destinations]"
                                  onfocus="$(this).next('.ft').slideDown('fast');"
                                  style="resize: vertical; width: 400px;"
                                  rows="3"><?= implode("\r\n", $syncEntry->destinations) ."\r\n" ?></textarea>
                        <!-- <div class="ft" style="display: none;">
                            <button onclick="">Add to hosts</button>
                        </div> -->
                    </div>
                </dd>
            </dl>
            <blockquote class='inline_help'>
                <p>Any remote host destinations that files will be copied to</p>
                <p>In the format: <code>[User@]Host:/Folder</code></p>
            </blockquote>

            <dl>
                <dt>Use Global Rsync Settings</dt>
                <dd>
                    <select name="syncEntries[<?= (int)$index; ?>][useDefaultRsync]"
                            class="entryOption use-default-rsync"
                            onchange="updateSyncEntryRsyncOptions(this.closest('.sync-entry')); expandOptionsBlock(this);">
                        <?= mk_option(is_null($syncEntry->rsyncOptions) ? 'true' : 'false', 'false', "No") ?>
                        <?= mk_option(is_null($syncEntry->rsyncOptions) ? 'true' : 'false', 'true', "Yes") ?>
                    </select>
                </dd>
            </dl>
            <blockquote class='inline_help entry-options-block'>
                <dl>
                    <dt>Capture file datetimes</dt>
                    <dd>
                        <select name="syncEntries[<?= (int)$index; ?>][rsyncOptions][rsyncTimes]"
                                class="rsyncOption entryOption">
                            <?php
                            $selectedValue = bool_to_str($syncEntry->rsyncOptions?->rsyncTimes ?? $userConfig["rsyncTimes"]);
                            echo mk_option($selectedValue, "false", "No");
                            echo mk_option($selectedValue, "true", "Yes");
                            ?>
                        </select>
                    </dd>
                </dl>
                <blockquote class="inline_help">

                </blockquote>

                <dl>
                    <dt>When to delete old files</dt>
                    <dd>
                        <select name="syncEntries[<?= (int)$index; ?>][rsyncOptions][rsyncDelete]"
                                class="rsyncOption entryOption">
                            <?php
                            $selectedValue = $syncEntry->rsyncOptions?->rsyncDelete ?? $userConfig["rsyncDelete"];
                            echo mk_option($selectedValue, "after", "After");
                            echo mk_option($selectedValue, "before", "Before");
                            echo mk_option($selectedValue, "during", "During");
                            echo mk_option($selectedValue, "delay", "Delay");
                            ?>
                        </select>
                    </dd>
                </dl>
                <blockquote class="inline_help">
                    Look up rsync delete-after
                </blockquote>

                <dl>
                    <dt>Compress backup</dt>
                    <dd>
                        <select name="syncEntries[<?= (int)$index; ?>][rsyncOptions][rsyncCompress]"
                                class="rsyncOption entryOption">
                            <?php
                            $selectedValue = bool_to_str($syncEntry->rsyncOptions?->rsyncCompress ?? $userConfig["rsyncCompress"]);
                            echo mk_option($selectedValue, "false", "No");
                            echo mk_option($selectedValue, "true", "Yes");
                            ?>
                        </select>
                    </dd>
                </dl>
                <blockquote class="inline_help">

                </blockquote>

                <dl>
                    <dt>Custom Rsync Parameters</dt>
                    <dd>
                        <input type="text" name="syncEntries[<?= (int)$index; ?>][rsyncOptions][rsyncCustom]"
                               class="rsyncCustomParam entryOption"
                               oninput="updateSyncEntryRsyncOptions(this.closest('.sync-entry'));"
                               value="<?= $syncEntry->rsyncOptions?->rsyncCustom ?? $userConfig["rsyncCustom"] ?>"
                               placeholder="Will override other Rsync options">
                    </dd>
                </dl>
            </blockquote>

            <dl>
                <dt>&nbsp;</dt>
                <dd>
                    <button type="button" class="deleteSyncJobButton" onclick="removeSyncEntry(this)">Remove</button>
                </dd>
            </dl>
        </div>
        <?php } ?>
    </div>

    <dl>
        <dt>&nbsp;</dt>
        <dd>
            <a id="addSyncJobButton" style="cursor: pointer;">
                <i class="fa fa-fw fa-plus"></i>Add Another Sync Job
            </a>
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
            root: '/mnt/',
            top: '/mnt/',
            filter: '',
            multiSelect: true,
            allowBrowsing: true
        });

        $(document).on('mousedown', function(event) {
            $('textarea + .ft').each(function() {
                var $ftDiv = $(this);
                var $container = $ftDiv.parent();

                if (!$container.is(event.target) && $container.has(event.target).length === 0) {
                    if ($ftDiv.is(':visible')) {
                        $ftDiv.slideUp('fast');
                    }
                }
            });
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

        $('#addSyncJobButton').on('click', function() {
            console.log('clicked addSyncJobButton');

            let syncEntryIndex = <?= count($syncList->entries) ?>;

            const xnewSyncEntry = `
            <div class="sync-entry" data-index="${syncEntryIndex}">
                <dl>
                    <dt>Local Directories</dt>
                    <dd>
                        <div style="display: table; width: 300px;">
                            <textarea id="sourceDirectories_${syncEntryIndex}"
                                    name="sourceDirectories[]"
                                    onfocus="$(this).next('.ft').slideDown('fast');"
                                    style="resize: vertical; width: 400px;"
                                    rows="3"></textarea>
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
                    <dt>Destination Hosts</dt>
                    <dd>
                        <textarea id="destinationHosts_${syncEntryIndex}"
                                name="destinationHosts[]"
                                style="resize: vertical; width: 400px;"
                                rows="3"></textarea>
                    </dd>
                </dl>
                <blockquote class='inline_help'>
                    <p>Any remote host destinations that files will be copied to</p>
                    <p>In the format: <code>[User@]Host:/Folder</code></p>
                </blockquote>
                <dl>
                    <dt>&nbsp;</dt>
                    <dd><button type="button" onclick="removeSyncEntry(this)">Remove</button></dd>
                </dl>
            </div>
            `;

            const newSyncEntry =`
            <div class="sync-entry" data-entry-id="${syncEntryIndex}">
                <dl>
                    <dt>Local Directories</dt>
                    <dd>
                        <div style="display: table; width: 300px;">
                            <textarea name="syncEntries[${syncEntryIndex}][sources]"
                                      onfocus="$(this).next('.ft').slideDown('fast');"
                                      style="resize: vertical; width: 400px;"
                                      rows="3"></textarea>
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
                    <dt>Destination Hosts</dt>
                    <dd>
                        <div style="display: table; width: 300px;">
                            <textarea name="syncEntries[${syncEntryIndex}][destinations]"
                                      onfocus="$(this).next('.ft').slideDown('fast');"
                                      style="resize: vertical; width: 400px;"
                                      rows="3"></textarea>
                            <!-- <div class="ft" style="display: none;">
                                <button onclick="">Add to hosts</button>
                            </div> -->
                        </div>
                    </dd>
                </dl>
                <blockquote class='inline_help'>
                    <p>Any remote host destinations that files will be copied to</p>
                    <p>In the format: <code>[User@]Host:/Folder</code></p>
                </blockquote>

                <dl>
                    <dt>Use Global Rsync Settings</dt>
                    <dd>
                        <select name="syncEntries[${syncEntryIndex}][useDefaultRsync]"
                                class="entryOption use-default-rsync"
                                onchange="updateSyncEntryRsyncOptions(this.closest('.sync-entry')); expandOptionsBlock(this);">
                            <option value="false">No</option>
                            <option value="true" selected="">Yes</option>
                        </select>
                    </dd>
                </dl>
                <blockquote class='inline_help entry-options-block'>
                    <dl>
                        <dt>Capture file datetimes</dt>
                        <dd>
                            <select name="syncEntries[${syncEntryIndex}][rsyncOptions][rsyncTimes]"
                                    class="rsyncOption entryOption">
                                <?php
                echo mk_option($userConfig["rsyncTimes"], "false", "No");
                echo mk_option($userConfig["rsyncTimes"], "true", "Yes");
                ?>
                            </select>
                        </dd>
                    </dl>
                    <blockquote class="inline_help">

                    </blockquote>

                    <dl>
                        <dt>When to delete old files</dt>
                        <dd>
                            <select name="syncEntries[${syncEntryIndex}][rsyncOptions][rsyncDelete]"
                                    class="rsyncOption entryOption">
                                <?php
                echo mk_option($userConfig["rsyncDelete"], "after", "After");
                echo mk_option($userConfig["rsyncDelete"], "before", "Before");
                echo mk_option($userConfig["rsyncDelete"], "during", "During");
                echo mk_option($userConfig["rsyncDelete"], "delay", "Delay");
                ?>
                            </select>
                        </dd>
                    </dl>
                    <blockquote class="inline_help">
                        Look up rsync delete-after
                    </blockquote>

                    <dl>
                        <dt>Compress backup</dt>
                        <dd>
                            <select name="syncEntries[${syncEntryIndex}][rsyncOptions][rsyncCompress]"
                                    class="rsyncOption entryOption">
                                <?php
                echo mk_option($userConfig["rsyncCompress"], "false", "No");
                echo mk_option($userConfig["rsyncCompress"], "true", "Yes");
                ?>
                            </select>
                        </dd>
                    </dl>
                    <blockquote class="inline_help">

                    </blockquote>

                    <dl>
                        <dt>Custom Rsync Parameters</dt>
                        <dd>
                            <input type="text" name="syncEntries[${syncEntryIndex}][rsyncOptions][rsyncCustom]"
                                   class="rsyncCustomParam entryOption"
                                   oninput="updateSyncEntryRsyncOptions(this.closest('.sync-entry'));"
                                   value="<?= $userConfig["rsyncCustom"] ?>"
                                   placeholder="Will override other Rsync options">
                        </dd>
                    </dl>
                </blockquote>

                <dl>
                    <dt>&nbsp;</dt>
                    <dd>
                        <button type="button" class="deleteSyncJobButton" onclick="removeSyncEntry(this)">Remove</button>
                    </dd>
                </dl>
            </div>
            `;

                $('#syncEntriesContainer').append(newSyncEntry);

            // Reinitialize the file tree functionality for the new textarea
            $('#sourceDirectories_' + syncEntryIndex).next('.ft').find('.fileTreeDiv').fileTree({
                root: '/mnt/',
                top: '/mnt/',
                filter: '',
                multiSelect: true,
                allowBrowsing: true
            }, function(file) {
                $('#sourceDirectories_' + syncEntryIndex).val(file).trigger('change');
            });

            syncEntryIndex++;
        });

        updateCronEntries();
        updateGlobalRsyncOptions();
        $('.sync-entry').each(function () {
            updateSyncEntryRsyncOptions(this); // Process each entry individually
        });
    });

    function addSelectionToList(element) {
        $el = $(element).prev().find("input:checked");
        $textarea = $(element).parent().prev();

        console.debug($el, $textarea);

        if ($el.length !== 0) {
            let checked = $el
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

    function updateSyncEntryRsyncOptions(entry) {
        const useDefault = $(entry).find('.use-default-rsync').val() === 'true';
        const useCustom = $(entry).find('.rsyncCustomParam').val().trim() !== '';

        $(entry).find('.entry-options-block :input').prop('disabled', useDefault);

        if (!useDefault) {
            $(entry).find('.rsyncOption.entryOption').prop('disabled', useCustom);
        }
    }

    function expandOptionsBlock(useDefaultElement) {
        const entryOptionsBlockquote = $(useDefaultElement).closest('.sync-entry').find('blockquote.entry-options-block');

        if ($(useDefaultElement).val() !== 'true' && entryOptionsBlockquote.css('display') === 'none') {
            $(useDefaultElement).parent().prev().trigger('click');
        }
    }

    function updateCronEntries() {
        $('#frequencyWeekday, #frequencyDayOfMonth, #frequencyHour, #frequencyMinute, #frequencyCustom').prop('disabled', true);
        switch ($('#backupFrequency').val()) {
            case 'disabled':
                break;
            case 'daily':
                $('#frequencyHour, #frequencyMinute').prop('disabled', false);
                break;
            case 'weekly':
                $('#frequencyHour, #frequencyMinute, #frequencyWeekday').prop('disabled', false);
                break;
            case 'monthly':
                $('#frequencyHour, #frequencyMinute, #frequencyDayOfMonth').prop('disabled', false);
                break;
            default:
                $('#frequencyCustom').prop('disabled', false);
                break;
        }
    }

    function updateGlobalRsyncOptions() {
        let hasText = $('#rsyncCustom.globalOption').val().trim().length > 0;

        if (hasText) {
            $('.rsyncOption.globalOption').prop('disabled', true);
        } else {
            $('.rsyncOption.globalOption').prop('disabled', false);
        }
    }

    function removeSyncEntry(element) {
        $(element).closest('.sync-entry').slideUp('fast', function() {
            $(this).remove();
        });
    }

    // $('#erSettingsForm').on('submit', function () {
        
    // })
</script>