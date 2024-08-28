<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Observer;

use Magento\Csp\Api\ModeConfigManagerInterface;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\PageCache\NotCacheableInterface;
use Magento\Framework\App\Response\HttpInterface as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Yireo\PageCacheCspNonceRegenerator\Config\Config;


class RefreshNoncesObserver implements ObserverInterface
{
    public function __construct(
        private CspNonceProvider $cspNonceProvider,
        private Config $config,
        private ModeConfigManagerInterface $modeConfigured
    ) {
    }

    public function execute(Observer $observer)
    {
        /** @var HttpResponse $response */
        $response = $observer->getEvent()->getData('response');

        if (false === $response instanceof HttpResponse) {
            return $response;
        }

        if ($response instanceof NotCacheableInterface) {
            return $response;
        }

        if (false === $this->config->isPageCacheEnabled()) {
            return $response;
        }

        $body = $response->getBody();
        if (!preg_match_all('/nonce="([^"]+)"/', $body, $matches)) {
            return $response;
        }

        $regeneratedNonce = $this->regenerateNonce();
        foreach ($matches[0] as $match) {
            $newNonce = $regeneratedNonce;
            $body = str_replace($match, 'nonce="'.$newNonce.'"', $body);
        }

        $response->setBody($body);

        if ($this->modeConfigured->getConfigured()->isReportOnly()) {
            $header = 'Content-Security-Policy-Report-Only';
        } else {
            $header = 'Content-Security-Policy';
        }

        $value = '';
        if ($existing = $response->getHeader($header)) {
            $value = $value .' ' .$existing->getFieldValue();
        }

        $value = str_replace('script-src ', "script-src 'nonce-".$regeneratedNonce."' ", $value);
        $response->setHeader($header, $value, true);

        return $response;
    }

    private function regenerateNonce(): string
    {
        return $this->cspNonceProvider->generateNonce();
    }
}
