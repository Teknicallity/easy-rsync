<?php

/**
 * The real emhttp auth/CSRF surface the plugin lives behind: session cookie
 * required everywhere, CSRF token required on POST (enforced by webGui's
 * local_prepend.php, which silently terminates the request).
 */
class AuthTest extends E2ETestCase {

    public function testRequestsWithoutSessionRedirectToLogin(): void {
        $r = self::$web->getUnauthenticated(self::$handlerPath . '?action=getBackupStatus');
        $this->assertSame(302, $r['status']);
    }

    public function testSettingsPageRendersForAuthenticatedSession(): void {
        $r = self::$web->get(self::$pageUrl);
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('erSettingsForm', $r['body'],
            'the settings form should be present in the rendered page');
        $this->assertStringContainsString(self::$handlerPath, $r['body'],
            'the page JS should target this plugin instance\'s AJAX endpoint');
    }

    public function testPostWithoutCsrfTokenIsRejected(): void {
        $r = self::$web->post(self::$handlerPath, ['action' => 'abort'], withCsrf: false);
        $this->assertSame('', trim($r['body']),
            'webGui terminates CSRF-less POSTs without output');
    }

    public function testPostWithCsrfTokenIsAccepted(): void {
        $r = self::$web->post(self::$handlerPath, ['action' => 'abort']);
        $this->assertSame(200, $r['status']);
        $this->assertSame('No backup is running.', $r['json']['msg']);
    }
}
