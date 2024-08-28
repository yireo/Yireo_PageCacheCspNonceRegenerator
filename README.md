# Yireo PageCacheCspNonceRegenerator
**Magento 2 module that replaces the CSP nonces added to various `script` tags with a fresh instance, while the built-in Magento Page Cache is enabled. In other words, even with the Page Cache enabled, nonces are generated per request**

### Requirements
- `Magento_PageCache` module enabled
- `Magento_Csp` module enabled
- Magento Page Cache configured to use the **Built-In** cache

^^Note that this module does **not** provide a solution for Varnish.

### Installation
```bash
composer require yireo/magento2-page-cache-csp-nonce-regenerator
bin/magento module:enable Yireo_PageCacheCspNonceRegenerator
```

