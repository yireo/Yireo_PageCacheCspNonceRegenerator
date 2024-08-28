<?php declare(strict_types=1);

namespace Yireo\PageCacheCspNonceRegenerator\Config;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\PageCache\Model\Cache\Type;
use Magento\PageCache\Model\Config as PageCacheConfig;

class Config
{
    public function __construct(
        private StateInterface $cacheState,
        private ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isPageCacheEnabled(): bool
    {
        if (false === $this->cacheState->isEnabled(Type::TYPE_IDENTIFIER)) {
            return false;
        }

        if ((int)$this->scopeConfig->getValue(PageCacheConfig::XML_PAGECACHE_TYPE) !== PageCacheConfig::BUILT_IN) {
            return false;
        }

        return true;
    }
}
