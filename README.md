# Yireo PageCacheCspNonceRegenerator

<!-- badges.specs.start -->
<div class="github-specs-badges">

![Magento version](https://img.shields.io/badge/Magento-2.4.6%20%7C%202.4.9-orange)
![PHP version](https://img.shields.io/badge/PHP-8.2%E2%80%938.5-777BB4)
![License](https://img.shields.io/badge/License-OSL--3.0-blue)
![Latest Version](https://img.shields.io/packagist/v/yireo/magento2-page-cache-csp-nonce-regenerator)

</div>
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

After installing or upgrading, flush the Full Page Cache (`bin/magento cache:flush full_page`). Pages that were cached before that don't contain the information this module needs, so their nonces are not regenerated.

### How it works
When the built-in Page Cache stores a page, this module remembers the nonce that Magento generated for that page in an internal response header (`X-Yireo-Csp-Cached-Nonce`), which is saved with the cached page. When that cached page is served (a cache hit), only that exact nonce is replaced with a fresh one, both in the HTML and in the `script-src` directive of the CSP header. The internal header is always removed before the response is sent.

Any other `nonce="..."` attribute in the HTML (for instance markup injected through CMS content) is left alone, so that it doesn't get a valid nonce. Cache misses, uncacheable pages and non-HTML responses are not touched.

### Security notes
- With Varnish (or any other external full page cache), cached pages keep the same nonce for as long as they're cached, so an attacker who has seen one of those pages knows the nonce. This module doesn't fix that.
- On a cache hit, Magento can't use page-specific CSP mode settings, so the default storefront CSP mode (report-only or restrict) is used.


## Current status

<!-- badges.test.start -->
<div class="github-pipeline-badges">

![Static Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/static-tests.yml?label=static-tests)
![Unit Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/unit-tests.yml?label=unit-tests)
![Integration Tests](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/integration-tests.yml?label=integration-tests)
![Playwright](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/playwright.yml?label=playwright)
![DI Compilation](https://img.shields.io/github/actions/workflow/status/yireo/Yireo_PageCacheCspNonceRegenerator/compile.yml?label=compile)

</div>
<!-- badges.test.end -->
