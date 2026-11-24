<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Plugin;

use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Exception\LocalizedException;
use Yireo\PageCacheCspNonceRegenerator\Model\CacheHitRegistry;
use Yireo\PageCacheCspNonceRegenerator\Model\ResponseInspector;

class PageCacheKernelPlugin
{
    public function __construct(
        private readonly CspNonceProvider $cspNonceProvider,
        private readonly CacheHitRegistry $cacheHitRegistry,
        private readonly ResponseInspector $responseInspector
    ) {
    }

    /**
     * @param Kernel $subject
     * @param HttpResponse $response
     * @return null
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function beforeProcess(Kernel $subject, HttpResponse $response)
    {
        $response->clearHeader(ResponseInspector::CACHED_NONCE_HEADER);

        if (!$this->responseInspector->isPubliclyCacheable($response)) {
            return null;
        }

        if (!$this->responseInspector->isHtml($response)) {
            return null;
        }

        $body = (string)$response->getBody();
        if (!str_contains($body, 'nonce="')) {
            return null;
        }

        try {
            $nonce = $this->cspNonceProvider->generateNonce();
        } catch (LocalizedException) {
            return null;
        }

        if (!str_contains($body, 'nonce="' . $nonce . '"')) {
            return null;
        }

        $response->setHeader(ResponseInspector::CACHED_NONCE_HEADER, $nonce, true);

        return null;
    }

    /**
     * @param Kernel $subject
     * @param HttpResponse|false $result
     * @return HttpResponse|false
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function afterLoad(Kernel $subject, $result)
    {
        if ($result instanceof HttpResponse) {
            $this->cacheHitRegistry->markAsHit($result);
        }

        return $result;
    }
}
