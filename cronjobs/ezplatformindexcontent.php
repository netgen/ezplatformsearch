<?php

if ( !$isQuiet )
{
    $cli->output( "Processing pending content index actions" );
}

// check that solr is enabled and used
$eZSolr = eZSearch::getEngine();
if ( !$eZSolr instanceof eZPlatformSearch )
{
    $script->shutdown( 1, 'The current search engine plugin is not eZPlatformSearch' );
}

$limit = 50;
$entries = eZPendingActions::fetchByAction( 'index_content_object' );

if ( !empty( $entries ) )
{
    $contentObjectIdList = array();
    foreach ( $entries as $entry )
    {
        $contentObjectId = $entry->attribute( 'param' );
        $contentObjectIdList[] = (int)$contentObjectId;

        $contentObject = eZContentObject::fetch( $contentObjectId );
        if ( !is_null( $contentObject ) )
        {
            $eZSolr->addObject( $contentObject, false );
        }
    }

    // force commit additions
    $eZSolr->commit();

    // clear object cache
    eZContentObject::clearCache();

    eZPendingActions::removeByAction(
        'index_content_object',
        array(
            'param' => array( $contentObjectIdList )
        )
    );
}

if ( !$isQuiet )
{
    $cli->output( "Done" );
}
