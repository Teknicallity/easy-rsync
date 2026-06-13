<?php

namespace unraid\plugins\EasyRsync;

// Set document root for Unraid environment.
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
// Include Unraid's GUI helper functions.
require_once "$docroot/webGui/include/Translations.php";
require_once "$docroot/webGui/include/Helpers.php"; //mk_option

require_once dirname(__DIR__) ."/include/ERSettings.php";
require_once dirname(__DIR__) ."/include/Logger.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncList.php";
require_once dirname(__DIR__) ."/include/sync_list/SyncEntry.php";

use Exception;

//try {
//    $appName = ERSettings::$appName;
//    echo "/plugins/" . $appName . "/include/http_handler.php\n";
//} catch (Exception $e) {
//    die("Error: " . $e->getMessage());
//}

if ($_POST) {
    $userConfig = ERSettings::getUserConfig();

    $logger = Logger::getLogger();

//    ob_start();
//    var_dump($_POST);
//    $output = ob_get_clean();
//
//    $logger->logInfo($output);
//
//    die();

    if (!file_exists(ERSettings::getConfigDir())) {
        mkdir(ERSettings::getConfigDir());
    }

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

    .remove-confirm {
        padding: 10px 15px;
        margin-top: 10px;
        border: 1px solid #c44;
        background: rgba(204, 68, 68, 0.08);
        border-radius: 3px;
        max-width: 400px;
        box-sizing: border-box;
    }

    .remove-confirm p {
        margin: 0 0 10px 0;
    }

    .confirmRemoveJobButton {
        background-color: #c44 !important;
        color: white !important;
    }

    .tabs > .tab > .content {
        margin-top: 5rem;
    }
</style>


<form id="erSettingsForm" method="post">
    <div class="title">
        <div style="display: flex; justify-content: space-between; width: 100%">
            <span>General Settings</span>
            <div>
                <span>Status: </span>
                <span id="backupStatusTextSettings" class="backupStatusText"></span>
            </div>
        </div>
    </div>

    <dl>
        <dt>Capture File Datetimes In Place</dt>
        <dd>
            <select id="rsyncTimes" name="rsyncTimes" class="rsyncOption globalOption">
                <?= mk_option($userConfig["rsyncTimes"], "false", "No") ?>
                <?= mk_option($userConfig["rsyncTimes"], "true", "Yes") ?>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">
        <p>Preserves source modification times on the destination. Rsync flag: <code>--times</code>.</p>
        <p>If disabled, copied files get the current time as their modified date.</p>
    </blockquote>

    <dl>
        <dt>Preserve Symbolic Links</dt>
        <dd>
            <select id="rsyncLinks" name="rsyncLinks" class="rsyncOption globalOption">
                <?= mk_option($userConfig["rsyncLinks"], "false", "No") ?>
                <?= mk_option($userConfig["rsyncLinks"], "true", "Yes") ?>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">
        <p>Copies symlinks as symlinks. Rsync flag: <code>--links</code>.</p>
        <p>If disabled, symlinks are skipped entirely and won't be in the backup.</p>
    </blockquote>

    <dl>
        <dt>When to Delete Old Transfer Files</dt>
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
        <p>Removes files from the destination that no longer exist in the source, so the destination mirrors the source. Maps to rsync's <code>--delete-after</code>, <code>--delete-before</code>, <code>--delete-during</code>, or <code>--delete-delay</code>.</p>
        <p><strong>After</strong> is safest: deletions only happen if transfers succeeded.</p>
    </blockquote>
    
    <dl>
        <dt>Compress Data During Transit</dt>
        <dd>
            <select id="rsyncCompress" name="rsyncCompress" class="rsyncOption globalOption">
                <?= mk_option($userConfig["rsyncCompress"], "false", "No") ?>
                <?= mk_option($userConfig["rsyncCompress"], "true", "Yes") ?>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">
        <p>Compresses files during transfer. Rsync flag: <code>--compress</code>.</p>
        <p>Saves bandwidth on slow or remote links. Rarely worth the CPU cost for local-to-local syncs or already-compressed files (mp4, zip, etc.).</p>
    </blockquote>

    <dl>
        <dt>Custom Rsync Parameters</dt>
        <dd>
            <input type="text" id="rsyncCustom" name="rsyncCustom" oninput="updateGlobalRsyncOptions();"
                   value="<?= $userConfig["rsyncCustom"] ?>" class="rsyncCustomParam globalOption"
                   placeholder="Will override other Rsync options">
        </dd>
    </dl>
    <blockquote class="inline_help">
        <p>Free-form rsync flags. When set, replaces the structured options above.</p>
        <p>Example: <code>-avh --exclude=node_modules</code></p>
    </blockquote>

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
        <p>Controls how verbose the plugin's own log is (the Status Log tab).</p>
        <p><strong>Debug</strong>: everything including internal trace messages. <strong>Info</strong>: normal operation (recommended). <strong>Warning</strong>: only warnings and errors. <strong>Error</strong>: errors only.</p>
    </blockquote>

    <div class="title">
        Notifications and Scheduling
    </div>
    <dl>
        <dt>Summary Notification</dt>
        <dd>
            <select id="notificationMode" name="notificationMode">
                <?= mk_option($userConfig["notificationMode"], "none", "None")?>
                <?= mk_option($userConfig["notificationMode"], "foreach", "After Each Sync")?>
                <?= mk_option($userConfig["notificationMode"], "summary", "Summary")?>
                <?= mk_option($userConfig["notificationMode"], "both", "Both")?>
            </select>
        </dd>
    </dl>
    <blockquote class="inline_help">
        <p>Where notifications appear in Unraid's notification panel (top-right bell icon).</p>
        <p><strong>None</strong>: no notifications. <strong>After Each Sync</strong>: one per sync entry as it finishes. <strong>Summary</strong>: a single notification after all sync entries complete. <strong>Both</strong>: per-entry and a final summary.</p>
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

        <dt>Day of Week</dt>
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
    </dl>

    <dl>
        <dt>Custom Entry</dt>
        <dd>
            <input type="text" id="frequencyCustom" name="frequencyCustom"
                   value="<?= $userConfig["frequencyCustom"] ?>" placeholder="Will disable other time options">
        </dd>
    </dl>
    <blockquote class="inline_help">
        <p>Cron expression with five fields: <code>minute hour day-of-month month day-of-week</code>.</p>
        <p>Examples: <code>0 3 * * *</code> (daily at 3:00am), <code>*/15 * * * *</code> (every 15 minutes), <code>0 2 * * 0</code> (Sundays at 2:00am).</p>
    </blockquote>

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
                <p>Local paths to back up. One per line. Files or directories under <code>/mnt/</code>.</p>
                <p><strong>Trailing slash matters in rsync.</strong></p>
                <p><code>/mnt/user/Docs</code> copies the <code>Docs</code> folder itself. The destination gets a <code>Docs/</code> subdirectory.</p>
                <p><code>/mnt/user/Docs/</code> copies only its contents. Files land directly in the destination, no <code>Docs/</code> wrapper.</p>
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
                <p>Where to copy the sources. One per line. Every source is copied to every destination.</p>
                <p>Remote: <code>[user@]host:/path</code>. For example, <code>backup@nas:/srv/backups</code>. SSH key auth must be set up from this Unraid box to the remote host (no password prompts).</p>
                <p>Local: a plain path like <code>/mnt/disk2/backups</code> works too.</p>
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
                    <p>Preserves source modification times on the destination. Rsync flag: <code>--times</code>.</p>
                    <p>If disabled, copied files get the current time as their modified date.</p>
                </blockquote>

                <dl>
                    <dt>Preserve symbolic links</dt>
                    <dd>
                        <select name="syncEntries[<?= (int)$index; ?>][rsyncOptions][rsyncLinks]"
                                class="rsyncOption entryOption">
                            <?php
                            $selectedValue = bool_to_str($syncEntry->rsyncOptions?->rsyncLinks ?? $userConfig["rsyncLinks"]);
                            echo mk_option($selectedValue, "false", "No");
                            echo mk_option($selectedValue, "true", "Yes");
                            ?>
                        </select>
                    </dd>
                </dl>
                <blockquote class="inline_help">
                    <p>Copies symlinks as symlinks. Rsync flag: <code>--links</code>.</p>
                    <p>If disabled, symlinks are skipped entirely and won't be in the backup.</p>
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
                    <p>Removes files from the destination that no longer exist in the source, so the destination mirrors the source. Maps to rsync's <code>--delete-after</code>, <code>--delete-before</code>, <code>--delete-during</code>, or <code>--delete-delay</code>.</p>
                    <p><strong>After</strong> is safest: deletions only happen if transfers succeeded.</p>
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
                    <p>Compresses files during transfer. Rsync flag: <code>--compress</code>.</p>
                    <p>Saves bandwidth on slow or remote links. Rarely worth the CPU cost for local-to-local syncs or already-compressed files (mp4, zip, etc.).</p>
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
                <blockquote class="inline_help">
                    <p>Free-form rsync flags. When set, replaces the structured options above.</p>
                    <p>Example: <code>-avh --exclude=node_modules</code></p>
                </blockquote>
            </blockquote>

            <dl>
                <dt>&nbsp;</dt>
                <dd>
                    <input type="button" class="deleteSyncJobButton" value="Remove" onclick="askRemoveSyncEntry(this, event)"/>
                    <div class="remove-confirm" style="display:none;">
                        <p>Remove this sync job? Files will not be deleted.</p>
                        <input type="button" value="Cancel" onclick="cancelRemoveSyncEntry(this)"/>
                        <input type="button" class="confirmRemoveJobButton" value="Remove Job" onclick="confirmRemoveSyncEntry(this)"/>
                    </div>
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
    <button class="manualBackupButton">Manual Backup</button>
    <button class="manualDryBackupButton">Manual Dry Backup</button>
    <input type='button' class="abortBtn" value='Graceful Stop' disabled/>
    <input type='button' class="forceStopBtn" value='Force Stop' disabled/>
<!--    <button id="getBackupStatus">Get StatusJQuery</button>-->
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

        $(document).on('keydown', function(event) {
            if (event.key === 'Escape') {
                $('.remove-confirm:visible').each(function() {
                    cancelRemoveSyncEntry(this);
                });
            }
        });

        $(document).on('keydown keyup', function(event) {
            $('.deleteSyncJobButton').val(event.shiftKey ? 'Remove (no confirm)' : 'Remove');
        });
        $(window).on('blur', function() {
            $('.deleteSyncJobButton').val('Remove');
        });

        // Start manual backup
        $('.manualBackupButton').on('click', function() {
            $.post(urlSettings, {
                action: 'manualBackup',
            }, function(response) {
                console.log('Success:', response);
            });
        });

        // Start dry test backup
        $('.manualDryBackupButton').on('click', function() {
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

        let syncEntryIndex = <?= count($syncList->entries) ?>;

        $('#addSyncJobButton').on('click', function() {
            console.log('clicked addSyncJobButton');

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
                    <p>Local paths to back up. One per line. Files or directories under <code>/mnt/</code>.</p>
                    <p><strong>Trailing slash matters in rsync.</strong></p>
                    <p><code>/mnt/user/Docs</code> copies the <code>Docs</code> folder itself. The destination gets a <code>Docs/</code> subdirectory.</p>
                    <p><code>/mnt/user/Docs/</code> copies only its contents. Files land directly in the destination, no <code>Docs/</code> wrapper.</p>
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
                    <p>Where to copy the sources. One per line. Every source is copied to every destination.</p>
                    <p>Remote: <code>[user@]host:/path</code>. For example, <code>backup@nas:/srv/backups</code>. SSH key auth must be set up from this Unraid box to the remote host (no password prompts).</p>
                    <p>Local: a plain path like <code>/mnt/disk2/backups</code> works too.</p>
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
                        <p>Preserves source modification times on the destination. Rsync flag: <code>--times</code>.</p>
                        <p>If disabled, copied files get the current time as their modified date.</p>
                    </blockquote>

                    <dl>
                        <dt>Preserve symbolic links</dt>
                        <dd>
                            <select name="syncEntries[${syncEntryIndex}][rsyncOptions][rsyncLinks]"
                                    class="rsyncOption entryOption">
                                <?php
                echo mk_option($userConfig["rsyncLinks"], "false", "No");
                echo mk_option($userConfig["rsyncLinks"], "true", "Yes");
                ?>
                            </select>
                        </dd>
                    </dl>
                    <blockquote class="inline_help">
                        <p>Copies symlinks as symlinks. Rsync flag: <code>--links</code>.</p>
                        <p>If disabled, symlinks are skipped entirely and won't be in the backup.</p>
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
                        <p>Removes files from the destination that no longer exist in the source, so the destination mirrors the source. Maps to rsync's <code>--delete-after</code>, <code>--delete-before</code>, <code>--delete-during</code>, or <code>--delete-delay</code>.</p>
                        <p><strong>After</strong> is safest: deletions only happen if transfers succeeded.</p>
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
                        <p>Compresses files during transfer. Rsync flag: <code>--compress</code>.</p>
                        <p>Saves bandwidth on slow or remote links. Rarely worth the CPU cost for local-to-local syncs or already-compressed files (mp4, zip, etc.).</p>
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
                    <blockquote class="inline_help">
                        <p>Free-form rsync flags. When set, replaces the structured options above.</p>
                        <p>Example: <code>-avh --exclude=node_modules</code></p>
                    </blockquote>
                </blockquote>

                <dl>
                    <dt>&nbsp;</dt>
                    <dd>
                        <input type="button" class="deleteSyncJobButton" value="Remove" onclick="askRemoveSyncEntry(this, event)"/>
                        <div class="remove-confirm" style="display:none;">
                            <p>Remove this sync job? Files will not be deleted.</p>
                            <input type="button" value="Cancel" onclick="cancelRemoveSyncEntry(this)"/>
                            <input type="button" class="confirmRemoveJobButton" value="Remove Job" onclick="confirmRemoveSyncEntry(this)"/>
                        </div>
                    </dd>
                </dl>
            </div>
            `;

            $('#syncEntriesContainer').append(newSyncEntry);

            const $newEntry = $('#syncEntriesContainer .sync-entry').last();
            updateSyncEntryRsyncOptions($newEntry);
            $newEntry.find('.fileTreeDiv').fileTree({
                root: '/mnt/',
                top: '/mnt/',
                filter: '',
                multiSelect: true,
                allowBrowsing: true
            });
            $newEntry.find('dl').each(function () {
                const $dl = $(this);
                const $help = $dl.next('blockquote.inline_help');
                if (!$help.length) return;
                $dl.children('dt').css('cursor', 'help').on('click', function () {
                    $help.slideToggle();
                });
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
                    const rel = $(this).parent().find('a:first').attr('rel');
                    // Strip trailing slashes so rsync copies the directory itself, not its contents.
                    return rel.length > 1 ? rel.replace(/\/+$/, '') : rel;
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

    function askRemoveSyncEntry(element, event) {
        if (event && event.shiftKey) {
            confirmRemoveSyncEntry(element);
            return;
        }
        const $entry = $(element).closest('.sync-entry');
        $entry.find('.deleteSyncJobButton').hide();
        $entry.find('.remove-confirm').slideDown('fast');
    }

    function cancelRemoveSyncEntry(element) {
        const $entry = $(element).closest('.sync-entry');
        $entry.find('.remove-confirm').slideUp('fast', function() {
            $entry.find('.deleteSyncJobButton').show();
        });
    }

    function confirmRemoveSyncEntry(element) {
        $(element).closest('.sync-entry').slideUp('fast', function() {
            $(this).remove();
        });
    }

    // $('#erSettingsForm').on('submit', function () {
        
    // })
</script>