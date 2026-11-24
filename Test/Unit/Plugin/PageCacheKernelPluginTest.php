<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Test\Unit\Plugin;

use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Yireo\PageCacheCspNonceRegenerator\Model\CacheHitRegistry;
use Yireo\PageCacheCspNonceRegenerator\Model\ResponseInspector;
use Yireo\PageCacheCspNonceRegenerator\Plugin\PageCacheKernelPlugin;

class PageCacheKernelPluginTest extends TestCase
{
    private const NONCE = 'bm9uY2U=';

    private CacheHitRegistry $cacheHitRegistry;

    protected function setUp(): void
    {
        $this->cacheHitRegistry = new CacheHitRegistry();
    }

    public function testStoresNonceOfRenderedPage(): void
    {
        $response = $this->createResponse('<script nonce="' . self::NONCE . '"></script>');

        $this->createPlugin()->beforeProcess($this->createKernel(), $response);

        $this->assertSame(
            self::NONCE,
            $response->getHeader(ResponseInspector::CACHED_NONCE_HEADER)->getFieldValue()
        );
    }

    public function testInjectedNonceDoesNotGetStored(): void
    {
        $response = $this->createResponse('<script nonce="injected"></script>');

        $this->createPlugin()->beforeProcess($this->createKernel(), $response);

        $this->assertFalse($response->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
    }

    public function testExistingInternalHeaderIsRemoved(): void
    {
        $response = $this->createResponse('<p>No scripts</p>');
        $response->setHeader(ResponseInspector::CACHED_NONCE_HEADER, 'injected');

        $this->createPlugin()->beforeProcess($this->createKernel(), $response);

        $this->assertFalse($response->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
    }

    public function testNonPublicResponseIsSkipped(): void
    {
        $response = $this->createResponse('<script nonce="' . self::NONCE . '"></script>');
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);

        $this->createPlugin()->beforeProcess($this->createKernel(), $response);

        $this->assertFalse($response->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
    }

    public function testNonHtmlResponseIsSkipped(): void
    {
        $response = $this->createResponse('{"nonce="' . self::NONCE . '"}');
        $response->setHeader('Content-Type', 'application/json', true);

        $this->createPlugin()->beforeProcess($this->createKernel(), $response);

        $this->assertFalse($response->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
    }

    public function testNonceGenerationFailureIsSkipped(): void
    {
        $response = $this->createResponse('<script nonce="' . self::NONCE . '"></script>');
        $cspNonceProvider = $this->createStub(CspNonceProvider::class);
        $cspNonceProvider->method('generateNonce')->willThrowException(new LocalizedException(__('error')));

        $plugin = new PageCacheKernelPlugin($cspNonceProvider, $this->cacheHitRegistry, new ResponseInspector());
        $plugin->beforeProcess($this->createKernel(), $response);

        $this->assertFalse($response->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
    }

    public function testLoadedResponseIsMarkedAsHit(): void
    {
        $response = $this->createResponse('');

        $result = $this->createPlugin()->afterLoad($this->createKernel(), $response);

        $this->assertSame($response, $result);
        $this->assertTrue($this->cacheHitRegistry->isHit($response));
    }

    public function testCacheMissIsNotMarkedAsHit(): void
    {
        $this->assertFalse($this->createPlugin()->afterLoad($this->createKernel(), false));
        $this->assertFalse($this->cacheHitRegistry->isHit($this->createResponse('')));
    }

    private function createKernel(): Kernel
    {
        return (new ReflectionClass(Kernel::class))->newInstanceWithoutConstructor();
    }

    private function createPlugin(): PageCacheKernelPlugin
    {
        $cspNonceProvider = $this->createStub(CspNonceProvider::class);
        $cspNonceProvider->method('generateNonce')->willReturn(self::NONCE);

        return new PageCacheKernelPlugin($cspNonceProvider, $this->cacheHitRegistry, new ResponseInspector());
    }

    private function createResponse(string $body): HttpResponse
    {
        $response = (new ReflectionClass(HttpResponse::class))->newInstanceWithoutConstructor();
        $response->setBody($body);
        $response->setHeader('Cache-Control', 'public, max-age=86400, s-maxage=86400');

        return $response;
    }
}
