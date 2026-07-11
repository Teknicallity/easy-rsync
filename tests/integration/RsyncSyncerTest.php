<?php

use unraid\plugins\EasyRsync\Exceptions\RsyncFailureException;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\RsyncSyncer;

/**
 * Exercises RsyncSyncer against the real rsync binary (temp dirs only): command
 * construction, exit-code mapping, rsync.log writing, and pid-file lifecycle.
 * Unit tests only ever fake the Syncer interface.
 */
class RsyncSyncerTest extends IntegrationTestCase {

    public function testSyncCopiesFilesAndWritesRsyncLog(): void {
        $src = $this->makeTree('src', [
            'a.txt' => 'alpha',
            'nested/b.txt' => 'beta',
        ]);
        $dst = $this->dataDir . '/dst';

        (new RsyncSyncer())->performSync($src . '/', $dst, '--recursive --verbose');

        $this->assertSame('alpha', file_get_contents("$dst/a.txt"));
        $this->assertSame('beta', file_get_contents("$dst/nested/b.txt"));
        $this->assertStringContainsString('a.txt', $this->rsyncLog(), 'rsync --log-file should record transferred files');
    }

    public function testSpecialCharactersInPathsAreHandled(): void {
        $src = $this->makeTree("sr c's", ['f.txt' => 'x']);
        $dst = $this->dataDir . "/ds t's";

        (new RsyncSyncer())->performSync($src . '/', $dst, '--recursive');

        $this->assertSame('x', file_get_contents("$dst/f.txt"));
    }

    public function testDryRunDoesNotCopy(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);
        $dst = $this->dataDir . '/dst';

        (new RsyncSyncer())->performSync($src . '/', $dst, '--recursive --dry-run');

        $this->assertFileDoesNotExist("$dst/a.txt");
    }

    public function testMissingSourceThrowsWithRsyncExitCode(): void {
        $dst = $this->dataDir . '/dst';

        try {
            (new RsyncSyncer())->performSync($this->dataDir . '/does-not-exist/', $dst, '--recursive');
            $this->fail('expected RsyncFailureException');
        } catch (RsyncFailureException $e) {
            // rsync exits 23 (partial transfer / some files could not be transferred).
            $this->assertSame(23, $e->getCode());
            $this->assertStringContainsString('exit code 23', $e->getMessage());
            $this->assertStringContainsString(RsyncFailureException::describeExitCode(23), $e->getMessage());
        }

        $this->assertStringContainsString('[ERROR]', $this->rsyncLog(), 'failures must be visible in the Rsync Log tab');
    }

    public function testBadOptionThrowsSyntaxExitCode(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);

        try {
            (new RsyncSyncer())->performSync($src . '/', $this->dataDir . '/dst', '--no-such-option');
            $this->fail('expected RsyncFailureException');
        } catch (RsyncFailureException $e) {
            $this->assertSame(1, $e->getCode(), 'rsync exits 1 on syntax/usage errors');
        }
    }

    public function testPidFileIsRemovedAfterSync(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);

        (new RsyncSyncer())->performSync($src . '/', $this->dataDir . '/dst', '--recursive');

        $this->assertFileDoesNotExist(ERSettings::getStateRsyncPidFilePath());
    }
}
