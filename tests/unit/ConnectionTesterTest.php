<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\ConnectionTester;

class ConnectionTesterTest extends TestCase {
    public function testClassifyLocal() {
        $this->assertSame('local', ConnectionTester::classify('/mnt/user/backups'));
        $this->assertSame('local', ConnectionTester::classify('backups'));
        // A colon after a slash is part of a local path, not a host separator.
        $this->assertSame('local', ConnectionTester::classify('/mnt/user/weird:name'));
    }

    public function testClassifySsh() {
        $this->assertSame('ssh', ConnectionTester::classify('user@nas:/srv/backups'));
        $this->assertSame('ssh', ConnectionTester::classify('nas:/srv/backups'));
        $this->assertSame('ssh', ConnectionTester::classify('192.168.1.5:/data'));
    }

    public function testClassifyDaemon() {
        $this->assertSame('daemon', ConnectionTester::classify('host::module'));
        $this->assertSame('daemon', ConnectionTester::classify('rsync://host/module'));
    }

    public function testLocalWritableDirectoryPasses() {
        $r = ConnectionTester::test(sys_get_temp_dir());
        $this->assertSame('local', $r['type']);
        $this->assertTrue($r['ok']);
    }

    public function testLocalFailsWhenParentMissing() {
        $r = ConnectionTester::test('/nonexistent_easyrsync_xyz/deep/child');
        $this->assertSame('local', $r['type']);
        $this->assertFalse($r['ok']);
    }

    public function testDaemonIsNotTested() {
        $r = ConnectionTester::test('rsync://host/module');
        $this->assertSame('daemon', $r['type']);
        $this->assertNull($r['ok']);
    }
}
