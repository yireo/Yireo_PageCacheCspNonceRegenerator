<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Test\Integration;

use Magento\Csp\Helper\CspNonceProvider;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Interception\PluginList\PluginList;
use Magento\Framework\Module\ModuleList;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Yireo\PageCacheCspNonceRegenerator\Model\ResponseInspector;
use Yireo\PageCacheCspNonceRegenerator\Observer\RefreshNoncesObserver;
use Yireo\PageCacheCspNonceRegenerator\Plugin\PageCacheKernelPlugin;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class PageCacheNonceRegenerationTest extends TestCase
{
    public function testModuleIsEnabled(): void
    {
        $moduleList = $this->getObjectManager()->create(ModuleList::class);
        $this->assertTrue($moduleList->has('Yireo_PageCacheCspNonceRegenerator'));
    }

    public function testKernelPluginIsRegistered(): void
    {
        $pluginInfo = $this->getObjectManager()->get(PluginList::class)->get(Kernel::class, []);
        $pluginName = 'Yireo_PageCacheCspNonceRegenerator::trackCachedNonce';
        $this->assertArrayHasKey($pluginName, $pluginInfo);
        $this->assertSame(PageCacheKernelPlugin::class, $pluginInfo[$pluginName]['instance']);
    }

    public function testObserverIsRegistered(): void
    {
        $observers = $this->getObjectManager()->get(EventConfig::class)
            ->getObservers('controller_front_send_response_before');
        $this->assertArrayHasKey('refresh_csp_nonces', $observers);
        $this->assertSame(RefreshNoncesObserver::class, $observers['refresh_csp_nonces']['instance']);
    }

    /**
     * @magentoCache full_page enabled
     * @magentoConfigFixture default/system/full_page_cache/caching_application 1
     */
    public function testCachedNonceIsRegeneratedOnCacheHit(): void
    {
        $objectManager = $this->getObjectManager();
        $originalNonce = $objectManager->get(CspNonceProvider::class)->generateNonce();

        $response = $objectManager->create(HttpResponse::class);
        $response->setBody(
            '<script nonce="' . $originalNonce . '">a()</script><script nonce="injected">evil()</script>'
        );
        $response->setHeader('Cache-Control', 'public, max-age=86400, s-maxage=86400');

        $kernel = $objectManager->get(Kernel::class);
        $kernel->process($response);

        $objectManager->removeSharedInstance(CspNonceProvider::class);
        $objectManager->removeSharedInstance(DynamicCollector::class);

        $cachedResponse = $kernel->load();
        $this->assertInstanceOf(HttpResponse::class, $cachedResponse);
        $this->assertNotFalse($cachedResponse->getHeader(ResponseInspector::CACHED_NONCE_HEADER));

        $objectManager->get(EventManager::class)->dispatch(
            'controller_front_send_response_before',
            ['response' => $cachedResponse]
        );

        $body = (string)$cachedResponse->getBody();
        $this->assertStringNotContainsString($originalNonce, $body);
        $this->assertStringContainsString('<script nonce="injected">evil()</script>', $body);
        $this->assertSame(1, preg_match('/<script nonce="([^"]+)">a\(\)<\/script>/', $body, $matches));
        $newNonce = $matches[1];

        $this->assertFalse($cachedResponse->getHeader(ResponseInspector::CACHED_NONCE_HEADER));
        $header = $cachedResponse->getHeader('Content-Security-Policy')
            ?: $cachedResponse->getHeader('Content-Security-Policy-Report-Only');
        $this->assertNotFalse($header);
        $policy = (string)$header->getFieldValue();
        $this->assertStringContainsString("'nonce-" . $newNonce . "'", $policy);
        $this->assertStringNotContainsString("'nonce-injected'", $policy);
    }

    private function getObjectManager(): ObjectManager
    {
        return Bootstrap::getObjectManager();
    }
}
