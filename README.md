# Matomo TrackerDomain Plugin

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

## Tag Manager Configuration Variable
Important! - When you set up the TagManager you also need to adjust the **Matomo URL** in the **Matomo Configuration** Variable so that it points to the tracker domain. 


The plugin changes the variable `u`.

A global javascript variable is added `piwik.trackerDomain`, that could be used by other plugins.

## Credits
This plugin is more or less a copy paste of answers from https://github.com/Findus23 (Matomo)
and https://github.com/tsteur (Matomo Tag Manager). Sharing is caring. ♥
