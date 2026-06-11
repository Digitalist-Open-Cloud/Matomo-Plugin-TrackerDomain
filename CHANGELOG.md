# Changelog

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
