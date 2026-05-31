<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\PathHelper;

class PathHelperTest extends TestCase {
    public function testAbsolutePathStripsLeadingEmpty(): void {
        $this->assertSame(['a', 'b', 'c'], PathHelper::extractPathComponents('/a/b/c'));
    }

    public function testTrailingSeparatorTrimmed(): void {
        $this->assertSame(['a', 'b'], PathHelper::extractPathComponents('/a/b/'));
    }

    public function testRelativePathKept(): void {
        $this->assertSame(['a', 'b'], PathHelper::extractPathComponents('a/b'));
    }

    public function testEmptyStringYieldsEmptyArray(): void {
        $this->assertSame([], PathHelper::extractPathComponents(''));
    }

    public function testSingleSlashYieldsEmptyArray(): void {
        $this->assertSame([], PathHelper::extractPathComponents('/'));
    }
}
