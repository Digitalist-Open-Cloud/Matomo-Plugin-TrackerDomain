# Changelog

## [5.1.0] - 2026-11-21

### Security

- The tracker domain has to be a domain name, optionally with a port and a path. Other values are refused in the settings and ignored when they are in the config file. Before, the value was written unchanged into the tracking code and into the pages of Matomo.
- The values given to JavaScript are encoded.

### Fixed

- An empty tracker domain changes nothing. Before, emptying the setting left the tracking code and the Tag Manager code without a domain.
- A domain entered with `https://` or a slash at the end is used without them.
- The image tracking code is built with the tracker domain, and no longer by replacing the host of Matomo in the finished code.

### Changed

- The plugin needs PHP 8.2 or newer.
- The texts of the setting can be translated.
- Tests, and workflows that run them, PHPCS and Semgrep.

## [5.0.6] - 2026-06-11

### Fixed

The Tag Manager "Matomo Configuration" variable now defaults its Matomo URL to the configured tracker domain (overriding the core variable via the `TagManager.filterVariables` event), so newly created sites/containers use the tracker domain instead of the dashboard URL.

### Added

Expose `piwik.dashboardUrl` (the real Matomo URL) for other plugins (e.g. UserFeedback) to reach the Matomo API when a tracker domain is in use.

## [5.0.5] - 2026-02-02

### Added

Also change image tracker domain.

## [5.0.2] - 2024-09-13

### Added

License info, author.

## [5.0.1] - 2024-08-30

### Added

Cover for Marketplace.
