# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.3] - 25 November 2026
### Fixed
- Security: on built-in page cache hits, only the nonce Magento generated for the cached page is replaced, so `nonce` attributes in injected markup no longer receive a valid CSP nonce
- Nonces are only regenerated for HTML responses served from the built-in page cache
- The rewritten CSP header no longer ends up empty or with duplicate nonces, and a failure during regeneration leaves the response untouched
- Add missing dependencies on `Magento_Store` and `ext-pcre`

### Changed
- Requires PHP 8.2 up to 8.5; PHP 8.1 is no longer supported

### Added
- README and SECURITY.md document the nonce regeneration behaviour and security notes

## [0.0.2] - 4 September 2024
- Code quality, `readonly`, SOLID #1 @mcspronko
- Set minimum PHP requirement to 8.1

## [0.0.1] - 29 August 2024
- Initial release
