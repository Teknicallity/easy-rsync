<?php

require_once dirname(__DIR__, 3) .'/source/include/sync_list/RsyncOptions.php';

use unraid\plugins\EasyRsync\RsyncOptions;
use PHPUnit\Framework\TestCase;

class RsyncOptionsTest extends TestCase {
    public function testFromArray() {
        $data = [
            'rsyncRecursive' => false,
            'rsyncTimes' => true,
            'rsyncVerbose' => false,
            'rsyncHumanReadable' => true,
            'rsyncDelete' => "before",
            'rsyncRemoteShell' => "rsh",
            'rsyncCompress' => false,
            'rsyncCustom' => "--test-option"
        ];

        $options = RsyncOptions::fromArray($data);

        $this->assertFalse($options->rsyncRecursive);
        $this->assertTrue($options->rsyncTimes);
        $this->assertFalse($options->rsyncVerbose);
        $this->assertTrue($options->rsyncHumanReadable);
        $this->assertEquals("before", $options->rsyncDelete);
        $this->assertEquals("rsh", $options->rsyncRemoteShell);
        $this->assertFalse($options->rsyncCompress);
        $this->assertEquals("--test-option", $options->rsyncCustom);
    }

    public function testBuildRsyncArgumentsString() {
        $data = [
            'rsyncRecursive' => true,
            'rsyncTimes' => false,
            'rsyncVerbose' => true,
            'rsyncHumanReadable' => false,
            'rsyncDelete' => "after",
            'rsyncRemoteShell' => "ssh",
            'rsyncCompress' => true,
            'rsyncCustom' => ""
        ];

        $options = RsyncOptions::fromArray($data);

        $this->assertEquals('--recursive --verbose --delete-after --compress', $options->buildRsyncArgumentsString());
        $this->assertEquals('--recursive --verbose --delete-after --compress --dry-run', $options->buildRsyncArgumentsString(true));

        // With custom options
        $customData = array_merge($data, ['rsyncCustom' => '--test-option']);
        $options = RsyncOptions::fromArray($customData);
        $this->assertEquals('--test-option --dry-run', $options->buildRsyncArgumentsString(true));
    }
}