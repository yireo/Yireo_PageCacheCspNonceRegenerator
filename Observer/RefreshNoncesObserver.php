<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Observer;

use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\PageCache\NotCacheableInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use RuntimeException;
use Yireo\PageCacheCspNonceRegenerator\Config\Config;
use Yireo\PageCacheCspNonceRegenerator\Model\CacheHitRegistry;
use Yireo\PageCacheCspNonceRegenerator\Model\ResponseInspector;

class RefreshNoncesObserver implements ObserverInterface
{
    public function __construct(
        private readonly CspNonceProvider $cspNonceProvider,
        private readonly Config $config,
        private readonly ModeConfigManagerInterface $modeConfigured,
        private readonly CacheHitRegistry $cacheHitRegistry,
        private readonly ResponseInspector $responseInspector
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $response = $observer->getEvent()->getData('response');
        if (!$response instanceof HttpResponse) {
            return;
        }

        $cachedNonce = $this->responseInspector->pullCachedNonce($response);
        if ($cachedNonce === null || !$this->isValidResponse($response)) {
            return;
        }

        try {
            $headerName = $this->getHeaderName();
            $regeneratedNonce = $this->cspNonceProvider->generateNonce();
        } catch (LocalizedException|RuntimeException) {
            return;
        }

        if ($regeneratedNonce === $cachedNonce) {
            return;
        }

        $header = $response->getHeader($headerName);
        if (!$header) {
            return;
        }

        $policy = $this->addNonceToPolicy((string)$header->getFieldValue(), $regeneratedNonce);
        if ($policy === null) {
            return;
        }

        $response->setBody(str_replace(
            'nonce="' . $cachedNonce . '"',
            'nonce="' . $regeneratedNonce . '"',
            (string)$response->getBody()
        ));
        $response->setHeader($headerName, $policy, true);
    }

    private function isValidResponse(HttpResponse $response): bool
    {
        if ($response instanceof NotCacheableInterface) {
            return false;
        }

        if (false === $this->cacheHitRegistry->isHit($response)) {
            return false;
        }

        if (false === $this->config->isPageCacheEnabled()) {
            return false;
        }

        return $this->responseInspector->isHtml($response);
    }

    private function getHeaderName(): string
    {
        if ($this->modeConfigured->getConfigured()->isReportOnly()) {
            return 'Content-Security-Policy-Report-Only';
        }

        return 'Content-Security-Policy';
    }

    private function addNonceToPolicy(string $policy, string $nonce): ?string
    {
        $source = "'nonce-" . $nonce . "'";
        if (str_contains($policy, $source)) {
            return $policy;
        }

        $count = 0;
        $result = preg_replace(
            '/(^|;)(\s*)script-src(?=[\s;]|$)/',
            '$1$2script-src ' . $source,
            $policy,
            1,
            $count
        );

        if ($result === null || $count === 0) {
            return null;
        }

        return $result;
    }
}
