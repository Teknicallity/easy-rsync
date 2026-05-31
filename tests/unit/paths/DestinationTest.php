<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\Destination;

class DestinationTest extends TestCase {
    public function testUserHostPath(): void {
        $d = new Destination('user@host:/srv/backups');
        $this->assertSame('user', $d->username);
        $this->assertSame('host', $d->host);
        $this->assertSame('/srv/backups', $d->path);
        $this->assertSame(['srv', 'backups'], $d->pathParts);
        $this->assertSame('host:/srv/backups', $d->hostAndPath());
    }

    public function testHostPathWithoutUser(): void {
        $d = new Destination('host:/srv');
        $this->assertSame('', $d->username);
        $this->assertSame('host', $d->host);
        $this->assertSame('/srv', $d->path);
        $this->assertSame(['srv'], $d->pathParts);
    }

    public function testTrailingSlashStrippedFromPathParts(): void {
        $d = new Destination('user@host:/srv/backups/');
        $this->assertSame('/srv/backups/', $d->path);
        $this->assertSame(['srv', 'backups'], $d->pathParts);
    }

    public function testHostOnlyHasEmptyPath(): void {
        $d = new Destination('user@host');
        $this->assertSame('user', $d->username);
        $this->assertSame('host', $d->host);
        $this->assertSame('', $d->path);
        $this->assertSame('host:', $d->hostAndPath());
    }
}
