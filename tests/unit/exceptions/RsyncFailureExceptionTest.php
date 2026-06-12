<?php

use unraid\plugins\EasyRsync\Exceptions\RsyncFailureException;
use PHPUnit\Framework\TestCase;

class RsyncFailureExceptionTest extends TestCase {
    public function testKnownCodesAreDescribed() {
        foreach ([1, 2, 3, 5, 10, 11, 12, 13, 23, 24, 30, 35] as $code) {
            $this->assertNotSame(
                'Unknown rsync error',
                RsyncFailureException::describeExitCode($code),
                "Exit code $code should have a specific description"
            );
        }
    }

    public function testMissingParentCodesMentionParent() {
        $this->assertStringContainsString('parent', strtolower(RsyncFailureException::describeExitCode(11)));
        $this->assertStringContainsString('parent', strtolower(RsyncFailureException::describeExitCode(12)));
    }

    public function testUnknownCode() {
        $this->assertSame('Unknown rsync error', RsyncFailureException::describeExitCode(999));
    }

    public function testMessageAndCodePreserved() {
        $e = new RsyncFailureException('boom', 11);
        $this->assertSame('boom', $e->getMessage());
        $this->assertSame(11, $e->getCode());
    }
}
