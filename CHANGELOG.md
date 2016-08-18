eZ Platform Search extension changelog
======================================

1.1.3 (18.08.2016)
------------------

* Fix a potential issue with empty array passed to `eZContentObjectTreeNode::fetch` (thanks @fdege)

1.1.2 (01.03.2016)
------------------

* Do not use `search_engine` parameter as it is unreliable

1.1.1 (25.01.2016)
------------------

* Fix failures to index content restored from trash and sent to trash (thanks @pspanja)

1.1.0 (16.12.2015)
------------------

* Add eZ Platform index subtree cron to index `index_subtree` pending actions (thanks @harmstyler)
* Fix failure to index copied content (thanks @harmstyler)

1.0.5 (12.10.2015)
------------------

* Locations are searched by default
* Use `findContentInfo` instead of `findContent` method when content is being searched

1.0.4 (08.09.2015)
------------------

* Bug fix: Indexing handler expects SPI content, not API one

1.0.3 (07.09.2015)
------------------

* Add config to use content search instead of location search

1.0.2 (07.09.2015)
------------------

* Bug fix: Do not ignore permissions when searching locations

1.0.1 (07.09.2015)
------------------

* Do not use `needCommit` flag directly, should be used by clients

1.0.0 (07.09.2015)
------------------

* Initial release
