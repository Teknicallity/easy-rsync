<?php

$plugin_path = __DIR__;

# Others
require_once $plugin_path . '/ERHelper.php';
require_once $plugin_path . '/ERSettings.php';
require_once $plugin_path . '/FileUtils.php';
require_once $plugin_path . '/Logger.php';
require_once $plugin_path . '/LogHandler.php';

# exceptions
require_once $plugin_path . '/exceptions/RsyncFailureException.php';

# notifications
require_once $plugin_path . '/notifications/Notification.php';
require_once $plugin_path . '/notifications/NotificationLevel.php';

# paths
require_once $plugin_path . '/paths/Destination.php';
require_once $plugin_path . '/paths/PathHelper.php';

# sync_list
require_once $plugin_path . '/sync_list/RsyncOptions.php';
require_once $plugin_path . '/sync_list/SyncEntry.php';
require_once $plugin_path . '/sync_list/SyncList.php';
require_once $plugin_path . '/sync_list/SyncResult.php';
require_once $plugin_path . '/sync_list/SyncStatus.php';

# syncer
require_once $plugin_path . '/syncer/RsyncSyncer.php';
require_once $plugin_path . '/syncer/Syncer.php';
