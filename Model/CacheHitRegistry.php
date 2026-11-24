<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Model;

use WeakMap;

class CacheHitRegistry
{
    private WeakMap $responses;

    public function __construct()
    {
        $this->responses = new WeakMap();
    }

    public function markAsHit(object $response): void
    {
        $this->responses[$response] = true;
    }

    public function isHit(object $response): bool
    {
        return isset($this->responses[$response]);
    }
}
