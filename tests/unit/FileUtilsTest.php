<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\FileUtils;

class FileUtilsTest extends TestCase {
    private string $tmp;

    protected function setUp(): void {
        $this->tmp = sys_get_temp_dir() . '/easy-rsync-tests/fileutils-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void {
        if (is_file($this->tmp)) {
            @unlink($this->tmp);
        }
    }

    public function testReadReturnsEmptyArrayWhenMissing(): void {
        $this->assertSame([], FileUtils::readJsonFile('/nope/does/not/exist.json'));
    }

    public function testWriteThenReadRoundTrip(): void {
        $data = ['a' => 1, 'b' => ['c', 'd']];
        FileUtils::writeJsonFile($this->tmp, $data);
        $this->assertSame($data, FileUtils::readJsonFile($this->tmp));
    }

    public function testReadThrowsOnInvalidJson(): void {
        file_put_contents($this->tmp, '{ not valid json');
        $this->expectException(RuntimeException::class);
        FileUtils::readJsonFile($this->tmp);
    }
}
