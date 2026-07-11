<?php

use unraid\plugins\EasyRsync\ConnectionTester;

/**
 * Exercises ConnectionTester's real `ssh` execution path (unit tests cover only
 * classification and local paths). Uses an unresolvable host so no network access
 * or credentials are needed; the success path is covered by the e2e suite.
 */
class ConnectionTesterSshTest extends IntegrationTestCase {

    public function testUnreachableSshDestinationFails(): void {
        $result = ConnectionTester::test('user@host-that-does-not-exist.invalid:/backup');

        $this->assertSame('ssh', $result['type']);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('SSH connection failed', $result['message']);
    }
}
