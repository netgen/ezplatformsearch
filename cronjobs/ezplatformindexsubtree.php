<?php

if ( !$isQuiet )
{
    $cli->output( "Processing pending subtree re-index actions" );
}

// check that solr is enabled and used
$eZSolr = eZSearch::getEngine();
if ( !$eZSolr instanceof eZPlatformSearch )
{
    $script->shutdown( 1, 'The current search engine plugin is not eZPlatformSearch' );
}

$limit = 50;
$entries = eZPendingActions::fetchByAction( 'index_subtree' );

if ( !empty( $entries ) )
{
    $parentNodeIDList = array();
    foreach ( $entries as $entry )
    {
        $parentNodeID = $entry->attribute( 'param' );
        $parentNodeIDList[] = (int)$parentNodeID;

        $offset = 0;
        while ( true )
        {
            $nodes = eZContentObjectTreeNode::subTreeByNodeID(
                array(
                    'IgnoreVisibility' => true,
                    'Offset' => $offset,
                    'Limit' => $limit,
                    'Limitation' => array(),
                ),
                $parentNodeID
            );

            if ( !empty( $nodes ) && is_array( $nodes ) )
            {
                foreach ( $nodes as $node )
                {
                    ++$offset;
                    $cli->output( "\tIndexing object ID #{$node->attribute( 'contentobject_id' )}" );
                    // delay commits with passing false for $commit parameter
                    $eZSolr->addObject( $node->attribute( 'object' ), false );
                }

                // finish up with commit
                $eZSolr->commit();
                // clear object cache to conserver memory
                eZContentObject::clearCache();
            }
            else
            {
                break; // No valid nodes
            }
        }
    }

    eZPendingActions::removeByAction(
        'index_subtree',
        array(
            'param' => array( $parentNodeIDList )
        )
    );
}

if ( !$isQuiet )
{
    $cli->output( "Done" );
}
