<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Model;

use Magento\Framework\App\Response\Http as HttpResponse;

class ResponseInspector
{
    public const CACHED_NONCE_HEADER = 'X-Yireo-Csp-Cached-Nonce';

    public function isHtml(HttpResponse $response): bool
    {
        $contentType = $response->getHeader('Content-Type');
        if (!$contentType) {
            return true;
        }

        return str_starts_with(strtolower((string)$contentType->getFieldValue()), 'text/html');
    }

    public function isPubliclyCacheable(HttpResponse $response): bool
    {
        $cacheControl = $response->getHeader('Cache-Control');
        if (!$cacheControl) {
            return false;
        }

        return (bool)preg_match('/public.*s-maxage=\d+/', (string)$cacheControl->getFieldValue());
    }

    public function pullCachedNonce(HttpResponse $response): ?string
    {
        $header = $response->getHeader(self::CACHED_NONCE_HEADER);
        if (!$header) {
            return null;
        }

        $response->clearHeader(self::CACHED_NONCE_HEADER);
        $nonce = trim((string)$header->getFieldValue(), " \f\n\r\t\v\x00");
        if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $nonce)) {
            return null;
        }

        return $nonce;
    }
}
