<?php

if ( !$isQuiet )
{
    $cli->output( "Processing pending content index actions" );
}

// check that eZPlatformSearch is enabled and used
$searchEngine = eZSearch::getEngine();
if ( !$searchEngine instanceof eZPlatformSearch )
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
            $searchEngine->addObject( $contentObject, false );
        }
    }

    // force commit additions
    $searchEngine->commit();

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
