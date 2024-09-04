<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Observer;

use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\PageCache\NotCacheableInterface;
use Magento\Framework\App\Response\HttpInterface as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Yireo\PageCacheCspNonceRegenerator\Config\Config;

class RefreshNoncesObserver implements ObserverInterface
{
    public function __construct(
        private readonly CspNonceProvider $cspNonceProvider,
        private readonly Config $config,
        private readonly ModeConfigManagerInterface $modeConfigured
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        /** @var HttpResponse $response */
        $response = $observer->getEvent()->getData('response');

        if (!$this->isValidResponse($response)) {
            return;
        }

        $body = $response->getBody();
        if (!preg_match_all('/nonce="([^"]+)"/', $body, $matches)) {
            return;
        }

        $regeneratedNonce = $this->regenerateNonce();
        foreach ($matches[0] as $match) {
            $body = str_replace($match, 'nonce="' . $regeneratedNonce . '"', $body);
        }

        $response->setBody($body);

        if ($this->modeConfigured->getConfigured()->isReportOnly()) {
            $header = 'Content-Security-Policy-Report-Only';
        } else {
            $header = 'Content-Security-Policy';
        }

        $value = '';
        $existing = $response->getHeader($header);
        if ($existing) {
            $value .= ' ' . $existing->getFieldValue();
        }

        $value = str_replace('script-src ', "script-src 'nonce-" . $regeneratedNonce . "' ", $value);
        $response->setHeader($header, $value, true);
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    private function regenerateNonce(): string
    {
        return $this->cspNonceProvider->generateNonce();
    }

    private function isValidResponse($response): bool
    {
        if (false === $response instanceof HttpResponse) {
            return false;
        }

        if ($response instanceof NotCacheableInterface) {
            return false;
        }

        if (false === $this->config->isPageCacheEnabled()) {
            return false;
        }

        return true;
    }
}
