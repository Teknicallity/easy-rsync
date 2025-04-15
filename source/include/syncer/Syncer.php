<?php

namespace unraid\plugins\EasyRsync;

interface Syncer {
    public function performSync(string $source, string $destination, string $rsyncOptions): void;
}