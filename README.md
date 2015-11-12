eZ Platform Search extension
============================

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
php composer.phar require netgen/ezplatformsearch:~1.0
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

    php ezpublish/console ezpublish:legacy:script bin/php/ezpgenerateautoloads.php

Or go to Setup -> Extensions in admin interface and click the "Regenerate autoload arrays" button

### Update parameters.yml

Ensure that the parameter `search_engine` exists by adding it to `parameters.yml`

```yml
search_engine: solr
```

Searching for content instead of locations
------------------------------------------

By default, the plugin will search for locations.

If you want to use content search, switch the `[SearchSettings]/UseLocationSearch` config in `ezplatformsearch.ini` to `false`.

License
-------

[GNU General Public License v2](LICENSE)
