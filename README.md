# Yireo PageCacheCspNonceRegenerator

<!-- badges.specs.start -->
![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-page-cache-csp-nonce-regenerator)
<!-- badges.specs.end -->

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


## Current status

<!-- badges.test.start -->
![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/compile.yml?label=compile)
<!-- badges.test.end -->
