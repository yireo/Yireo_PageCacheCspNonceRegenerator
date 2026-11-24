<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Test\Unit\Observer;

use Magento\Csp\Api\Data\ModeConfiguredInterface;
use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Yireo\PageCacheCspNonceRegenerator\Config\Config;
use Yireo\PageCacheCspNonceRegenerator\Model\CacheHitRegistry;
use Yireo\PageCacheCspNonceRegenerator\Model\ResponseInspector;
use Yireo\PageCacheCspNonceRegenerator\Observer\RefreshNoncesObserver;

class RefreshNoncesObserverTest extends TestCase
{
    private const OLD_NONCE = 'b2xkbm9uY2U=';
    private const NEW_NONCE = 'bmV3bm9uY2U=';
    private const HTML = '<script nonce="b2xkbm9uY2U=">a()</script><script nonce="x">evil()</script>'
        . '<div data-nonce="b2xkbm9uY2U="></div>';

    private CacheHitRegistry $cacheHitRegistry;

    protected function setUp(): void
    {
        $this->cacheHitRegistry = new CacheHitRegistry();
    }

    public function testReplacesOnlyTheCachedNonceOnCacheHit(): void
    {
        $response = $this->createCachedResponse();

        $this->createObserver()->execute($this->createEventObserver($response));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('<script nonce="' . self::NEW_NONCE . '">a()</script>', $body);
        $this->assertStringContainsString('<script nonce="x">evil()</script>', $body);
        $this->assertStringContainsString('data-nonce="' . self::NEW_NONCE . '"', $body);
        $this->assertStringNotContainsString(self::OLD_NONCE, $body);
        $this->assertSame(
            "default-src 'self'; script-src 'nonce-" . self::NEW_NONCE . "' 'self';",
            $response->getHeader('Content-Security-Policy')->getFieldValue()
        );
        $this->assertFalse($response->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
    }

    public function testInjectedNonceIsNotReplacedWithoutCachedNonceHeader(): void
    {
        $response = $this->createCachedResponse();
        $response->clearHeader(ResponseInspector::CACHED_NONCE_HEADER);

        $this->createObserver()->execute($this->createEventObserver($response));

        $this->assertSame(self::HTML, $response->getBody());
        $this->assertSame(
            "default-src 'self'; script-src 'self';",
            $response->getHeader('Content-Security-Policy')->getFieldValue()
        );
    }

    public function testCacheMissIsLeftUntouchedButInternalHeaderIsRemoved(): void
    {
        $response = $this->createCachedResponse(false);

        $this->createObserver()->execute($this->createEventObserver($response));

        $this->assertSame(self::HTML, $response->getBody());
        $this->assertFalse($response->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
    }

    public function testDisabledPageCacheIsLeftUntouched(): void
    {
        $response = $this->createCachedResponse();

        $this->createObserver(false)->execute($this->createEventObserver($response));

        $this->assertSame(self::HTML, $response->getBody());
        $this->assertFalse($response->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
    }

    public function testNonHtmlResponseIsLeftUntouched(): void
    {
        $response = $this->createCachedResponse();
        $response->setHeader('Content-Type', 'application/json', true);

        $this->createObserver()->execute($this->createEventObserver($response));

        $this->assertSame(self::HTML, $response->getBody());
    }

    public function testInvalidCachedNonceIsIgnored(): void
    {
        $response = $this->createCachedResponse();
        $response->setHeader(ResponseInspector::CACHED_NONCE_HEADER, 'x" onload="evil()', true);

        $this->createObserver()->execute($this->createEventObserver($response));

        $this->assertSame(self::HTML, $response->getBody());
        $this->assertFalse($response->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
    }

    public function testMissingCspHeaderLeavesResponseUntouched(): void
    {
        $response = $this->createCachedResponse();
        $response->clearHeader('Content-Security-Policy');

        $this->createObserver()->execute($this->createEventObserver($response));

        $this->assertSame(self::HTML, $response->getBody());
        $this->assertFalse($response->getHeader('Content-Security-Policy'));
    }

    public function testPolicyWithoutScriptSrcLeavesResponseUntouched(): void
    {
        $response = $this->createCachedResponse();
        $response->setHeader('Content-Security-Policy', "default-src 'self'; style-src 'self'", true);

        $this->createObserver()->execute($this->createEventObserver($response));

        $this->assertSame(self::HTML, $response->getBody());
        $this->assertSame(
            "default-src 'self'; style-src 'self';",
            $response->getHeader('Content-Security-Policy')->getFieldValue()
        );
    }

    public function testReportOnlyHeaderIsUsedInReportOnlyMode(): void
    {
        $response = $this->createCachedResponse();
        $response->clearHeader('Content-Security-Policy');
        $response->setHeader('Content-Security-Policy-Report-Only', "script-src 'self'", true);

        $this->createObserver(true, true)->execute($this->createEventObserver($response));

        $this->assertStringNotContainsString(self::OLD_NONCE, (string)$response->getBody());
        $this->assertSame(
            "script-src 'nonce-" . self::NEW_NONCE . "' 'self'",
            $response->getHeader('Content-Security-Policy-Report-Only')->getFieldValue()
        );
    }

    public function testNonceIsNotAddedTwiceToPolicy(): void
    {
        $response = $this->createCachedResponse();
        $policy = "script-src 'nonce-" . self::NEW_NONCE . "' 'self';";
        $response->setHeader('Content-Security-Policy', $policy, true);

        $this->createObserver()->execute($this->createEventObserver($response));

        $this->assertSame($policy, $response->getHeader('Content-Security-Policy')->getFieldValue());
        $this->assertStringNotContainsString(self::OLD_NONCE, (string)$response->getBody());
    }

    public function testNonceGenerationFailureLeavesResponseUntouched(): void
    {
        $response = $this->createCachedResponse();
        $cspNonceProvider = $this->createStub(CspNonceProvider::class);
        $cspNonceProvider->method('generateNonce')->willThrowException(new LocalizedException(__('error')));

        $this->createObserver(true, false, $cspNonceProvider)->execute($this->createEventObserver($response));

        $this->assertSame(self::HTML, $response->getBody());
    }

    public function testModeConfigurationFailureLeavesResponseUntouched(): void
    {
        $response = $this->createCachedResponse();
        $modeConfigManager = $this->createStub(ModeConfigManagerInterface::class);
        $modeConfigManager->method('getConfigured')->willThrowException(new RuntimeException('error'));

        $observer = new RefreshNoncesObserver(
            $this->createNonceProvider(),
            $this->createConfig(true),
            $modeConfigManager,
            $this->cacheHitRegistry,
            new ResponseInspector()
        );
        $observer->execute($this->createEventObserver($response));

        $this->assertSame(self::HTML, $response->getBody());
    }

    private function createObserver(
        bool $pageCacheEnabled = true,
        bool $reportOnly = false,
        ?CspNonceProvider $cspNonceProvider = null
    ): RefreshNoncesObserver {
        $modeConfigured = $this->createStub(ModeConfiguredInterface::class);
        $modeConfigured->method('isReportOnly')->willReturn($reportOnly);
        $modeConfigManager = $this->createStub(ModeConfigManagerInterface::class);
        $modeConfigManager->method('getConfigured')->willReturn($modeConfigured);

        return new RefreshNoncesObserver(
            $cspNonceProvider ?? $this->createNonceProvider(),
            $this->createConfig($pageCacheEnabled),
            $modeConfigManager,
            $this->cacheHitRegistry,
            new ResponseInspector()
        );
    }

    private function createNonceProvider(): CspNonceProvider
    {
        $cspNonceProvider = $this->createStub(CspNonceProvider::class);
        $cspNonceProvider->method('generateNonce')->willReturn(self::NEW_NONCE);

        return $cspNonceProvider;
    }

    private function createConfig(bool $pageCacheEnabled): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isPageCacheEnabled')->willReturn($pageCacheEnabled);

        return $config;
    }

    private function createCachedResponse(bool $hit = true): HttpResponse
    {
        $response = (new ReflectionClass(HttpResponse::class))->newInstanceWithoutConstructor();
        $response->setBody(self::HTML);
        $response->setHeader(ResponseInspector::CACHED_NONCE_HEADER, self::OLD_NONCE);
        $response->setHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'");

        if ($hit) {
            $this->cacheHitRegistry->markAsHit($response);
        }

        return $response;
    }

    private function createEventObserver(HttpResponse $response): Observer
    {
        return new Observer(['event' => new Event(['response' => $response])]);
    }
}
