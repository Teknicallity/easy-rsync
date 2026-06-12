<?php

use unraid\plugins\EasyRsync\RsyncOptions;
use PHPUnit\Framework\TestCase;

class RsyncOptionsTest extends TestCase {
    public function testBuildRsyncArgumentsStringIncludesEnabledFlags() {
        $options = RsyncOptions::fromArray([
            'rsyncRecursive' => true,
            'rsyncTimes' => false,
            'rsyncVerbose' => true,
            'rsyncHumanReadable' => false,
            'rsyncDelete' => "after",
            'rsyncCompress' => true,
            'rsyncCustom' => ""
        ]);

        $args = $options->buildRsyncArgumentsString();
        $this->assertStringContainsString('--recursive', $args);
        $this->assertStringContainsString('--verbose', $args);
        $this->assertStringContainsString('--delete-after', $args);
        $this->assertStringContainsString('--compress', $args);
        $this->assertStringNotContainsString('--times', $args);
        $this->assertStringNotContainsString('--human-readable', $args);
        $this->assertStringNotContainsString('--dry-run', $args);
    }

    public function testDryRunAppendsDryRunFlag() {
        $options = RsyncOptions::fromArray(['rsyncRecursive' => true]);
        $this->assertStringContainsString('--dry-run', $options->buildRsyncArgumentsString(true));
    }

    public function testCustomOptionsOverrideStandardFlags() {
        $options = RsyncOptions::fromArray([
            'rsyncRecursive' => true,
            'rsyncCustom' => '--test-option',
        ]);
        $args = $options->buildRsyncArgumentsString();
        $this->assertStringContainsString('--test-option', $args);
        $this->assertStringNotContainsString('--recursive', $args);
    }

    public function testCustomOptionsStillGetDryRunWhenRequested() {
        $options = RsyncOptions::fromArray(['rsyncCustom' => '--test-option']);
        $this->assertStringContainsString('--dry-run', $options->buildRsyncArgumentsString(true));
    }

    public function testDefaultOptionsIncludeMkpath() {
        $options = RsyncOptions::fromArray(['rsyncRecursive' => true]);
        $this->assertStringContainsString('--mkpath', $options->buildRsyncArgumentsString());
        $this->assertStringContainsString('--mkpath', $options->buildRsyncArgumentsString(true));
    }

    public function testCustomOptionsDoNotInjectMkpath() {
        $options = RsyncOptions::fromArray(['rsyncCustom' => '--test-option']);
        $this->assertStringNotContainsString('--mkpath', $options->buildRsyncArgumentsString());
    }

    public function testDefaultOptionsIncludeLinks() {
        // rsyncLinks defaults to true when the key is absent.
        $options = RsyncOptions::fromArray(['rsyncRecursive' => true]);
        $this->assertStringContainsString('--links', $options->buildRsyncArgumentsString());
        $this->assertStringContainsString('--links', $options->buildRsyncArgumentsString(true));
    }

    public function testLinksDisabledOmitsLinks() {
        $options = RsyncOptions::fromArray(['rsyncLinks' => false]);
        $this->assertStringNotContainsString('--links', $options->buildRsyncArgumentsString());
    }

    public function testCustomOptionsDoNotInjectLinks() {
        $options = RsyncOptions::fromArray(['rsyncCustom' => '--test-option']);
        $this->assertStringNotContainsString('--links', $options->buildRsyncArgumentsString());
    }

    public function testCompressDefaultsOff() {
        // Default matches default.cfg (rsyncCompress="false").
        $options = RsyncOptions::fromArray(['rsyncRecursive' => true]);
        $this->assertStringNotContainsString('--compress', $options->buildRsyncArgumentsString());
    }

    public function testCompressEnabledStillAddsFlag() {
        $options = RsyncOptions::fromArray(['rsyncCompress' => true]);
        $this->assertStringContainsString('--compress', $options->buildRsyncArgumentsString());
    }
}