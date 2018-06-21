eZ Platform Search extension
============================

[![Downloads](https://img.shields.io/packagist/dt/netgen/ezplatformsearch.svg?style=flat-square)](https://packagist.org/packages/netgen/ezplatformsearch/stats)
[![Latest stable](https://img.shields.io/packagist/v/netgen/ezplatformsearch.svg?style=flat-square)](https://packagist.org/packages/netgen/ezplatformsearch)
[![License](https://img.shields.io/github/license/netgen/ezplatformsearch.svg?style=flat-square)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg?style=flat-square)](https://secure.php.net/)

eZ Platform Search is an eZ Publish legacy extension that integrates eZ Platform search
capabilities into eZ Publish legacy.

This extension is useful when you wish to run eZ Platform with legacy administration installed
and you don't want to maintain two search indexes, one for eZ Platform and one for legacy.

This extension is aiming to support only legacy search with `SearchViewHandling` configuration
value set to `default`, thus it will not work if you directly used eZ Find (either in PHP or
templates).

After activating the extension, default legacy search features should continue to work as before
including:

* Search in legacy administration interface
* Search in `ezobjectrelationlist` attribute
* Search in `ezxmltext` embed object dialog
* Reindexing when content is added/updated/deleted in legacy administration

Installation instructions
-------------------------

### Install through Composer

Use Composer to install the extension:

```
composer require netgen/ezplatformsearch:^1.0
```

### Activate extension

Activate the extension by using the admin interface ( Setup -> Extensions ) or by
prepending `ezplatformsearch` to `ActiveExtensions[]` in `ezpublish_legacy/settings/override/site.ini.append.php`:

```ini
[ExtensionSettings]
ActiveExtensions[]=ezplatformsearch
```

### Regenerate the legacy autoload array

Run the following from your installation root folder

    php app/console ezpublish:legacy:script bin/php/ezpgenerateautoloads.php

Or go to Setup -> Extensions in admin interface and click the "Regenerate autoload arrays" button

Setup cronjobs
--------------

This extension ships with a cronjob to index subtrees of content that have had their visibility updated. The cron needs to be executed using the `ezpublish:legacy:script` runner.

    php app/console ezpublish:legacy:script runcronjobs.php ezplatformindexsubtree
    
In addition to that you should make sure eZ Publish legacy's `cronjobs/indexcontent.php` is executed as well. This is part of the "main set" of cronjobs executed as:

    php app/console ezpublish:legacy:script runcronjobs.php

For further information on setting up cronjobs, see [eZ Publish legacy documentation](https://doc.ez.no/eZ-Publish/Technical-manual/4.x/Features/Cronjobs/Running-cronjobs).

Searching for content instead of locations
------------------------------------------

By default, the plugin will search for locations.

If you want to use content search, switch the `[SearchSettings]/UseLocationSearch` config in `ezplatformsearch.ini` to `false`.

License
-------

[GNU General Public License v2](LICENSE)
