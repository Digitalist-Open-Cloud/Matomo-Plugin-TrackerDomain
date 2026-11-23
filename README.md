# Matomo TrackerDomain Plugin

[![PHPCS check](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-TrackerDomain/actions/workflows/phpcs.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-TrackerDomain/actions/workflows/phpcs.yaml)
[![Semgrep OSS scan](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-TrackerDomain/actions/workflows/semgrep.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-TrackerDomain/actions/workflows/semgrep.yaml)
[![Test plugin with Matomo](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-TrackerDomain/actions/workflows/matomo.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-TrackerDomain/actions/workflows/matomo.yaml)

## Description

This  plugin could be useful if you are running your UI part of Matomo
on another domain then you are tracking from.
This plugin replaces the matomo url from your installation to a custom domain.
The plugin changes both Matomo core tracking script and the TagManager script.

## Use

To use this:
Add to config.ini.php or common.ini.php:

```php
[TrackerDomain]
url = "my.domain"
```

Or add the domain in `General settings -> TrackerDomain`.

Write the domain without `https://`. A port and a path are allowed, for example `my.domain:8443/analytics`. Anything else is refused in the settings and ignored in the config file. With an empty value the plugin changes nothing.

This would give you something like this:

```html

<!-- Matomo -->
<script>
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
  _paq.push(['trackPageView']);
  _paq.push(['enableLinkTracking']);
  (function() {
    var u="//my.domain/";
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', '1']);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();
</script>
<!-- End Matomo Code -->

```

## Tag Manager

The code to embed a container loads the container from the tracker domain, and the **Matomo URL** of a new **Matomo Configuration** variable is the tracker domain. A variable that was saved before the tracker domain was set keeps its Matomo URL: change it in Tag Manager.

## Image tracking

The image tracking code uses the tracker domain as well, always with `https://`.

## Other plugins

The plugin changes the variable `u`.

Two global JavaScript variables are added for other plugins: `piwik.trackerDomain`, the tracker domain, and `piwik.dashboardUrl`, the URL of Matomo itself.

## Tests

```sh
./console tests:run TrackerDomain
```

## Credits
This plugin is more or less a copy paste of answers from https://github.com/Findus23 (Matomo)
and https://github.com/tsteur (Matomo Tag Manager). Sharing is caring. ♥
