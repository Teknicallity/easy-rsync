<?php

namespace unraid\plugins\EasyRsync;

enum NotificationLevel: string {
    case NORMAL = 'normal';
    case ALERT = 'alert';
    case WARNING = 'warning';
}