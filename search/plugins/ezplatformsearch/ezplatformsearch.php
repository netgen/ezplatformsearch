<?php

use eZ\Publish\Core\Search\Legacy\Content\Handler as LegacyHandler;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;

class eZPlatformSearch implements ezpSearchEngine
{
    /**
     * @var \eZ\Publish\SPI\Search\Handler
     */
    protected $searchHandler;

    /**
     * @var \eZ\Publish\SPI\Persistence\Handler
     */
    protected $persistenceHandler;

    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    /**
     * @var \eZINI
     */
    protected $iniConfig;

    /**
     * Constructor
     */
    public function __construct()
    {
        $serviceContainer = ezpKernel::instance()->getServiceContainer();

        $this->searchHandler = $serviceContainer->get( 'ezpublish.spi.search' );
        $this->persistenceHandler = $serviceContainer->get( 'ezpublish.api.persistence_handler' );
        $this->repository = $serviceContainer->get( 'ezpublish.api.repository' );

        $this->iniConfig = eZINI::instance( 'ezplatformsearch.ini' );
    }

    /**
     * Whether a commit operation is required after adding/removing objects.
     *
     * @return bool
     */
    public function needCommit()
    {
        return method_exists( $this->searchHandler, 'commit' );
    }

    /**
     * Whether calling removeObject() is required when updating an object.
     *
     * @return bool
     */
    public function needRemoveWithUpdate()
    {
        return true;
    }

    /**
     * Adds object $contentObject to the search database.
     *
     * @param \eZContentObject $contentObject Object to add to search engine
     * @param bool $commit Whether to commit after adding the object
     *
     * @return bool True if the operation succeeded.
     */
    public function addObject( $contentObject, $commit = true )
    {
        // Indexing is not implemented in eZ Publish 5 legacy search engine
        if ( $this->searchHandler instanceof LegacyHandler )
        {
            $searchEngine = new eZSearchEngine();
            $searchEngine->addObject( $contentObject, $commit );

            return true;
        }

        try
        {
            // If the method is called for restoring from trash we'll be inside a transaction,
            // meaning created Location(s) will not be visible outside of it.
            // We check that Content's Locations are visible from the new stack, if not Content
            // will be registered for indexing.
            foreach ( $contentObject->assignedNodes() as $node )
            {
                $this->persistenceHandler->locationHandler()->load(
                    $node->attribute( 'node_id' )
                );
            }

            $content = $this->persistenceHandler->contentHandler()->load(
                (int)$contentObject->attribute( 'id' ),
                (int)$contentObject->attribute( 'current_version' )
            );
        }
        catch ( NotFoundException $e )
        {
            $pendingAction = new eZPendingActions(
                array(
                    'action' => 'index_object',
                    'created' => time(),
                    'param' => (int)$contentObject->attribute( 'id' )
                )
            );

            $pendingAction->store();

            return true;
        }

        $this->searchHandler->indexContent( $content );

        if ( $commit )
        {
            $this->commit();
        }

        return true;
    }

    /**
     * Removes object $contentObject from the search database.
     *
     * @deprecated Since 5.0, use removeObjectById()
     *
     * @param \eZContentObject $contentObject the content object to remove
     * @param bool $commit Whether to commit after removing the object
     *
     * @return bool True if the operation succeeded
     */
    public function removeObject( $contentObject, $commit = null )
    {
        return $this->removeObjectById( $contentObject->attribute( 'id' ), $commit );
    }

    /**
     * Removes a content object by ID from the search database.
     *
     * @since 5.0
     *
     * @param int $contentObjectId The content object to remove by ID
     * @param bool $commit Whether to commit after removing the object
     *
     * @return bool True if the operation succeeded
     */
    public function removeObjectById( $contentObjectId, $commit = null )
    {
        if ( !isset( $commit ) && ( $this->iniConfig->variable( 'IndexOptions', 'DisableDeleteCommits' ) === 'true' ) )
        {
            $commit = false;
        }
        elseif ( !isset( $commit ) )
        {
            $commit = true;
        }

        // Indexing is not implemented in eZ Publish 5 legacy search engine
        if ( $this->searchHandler instanceof LegacyHandler )
        {
            $searchEngine = new eZSearchEngine();
            $searchEngine->removeObjectById( $contentObjectId, $commit );

            return true;
        }

        $this->searchHandler->deleteContent( (int)$contentObjectId );

        if ( $commit )
        {
            $this->commit();
        }

        return true;
    }

    /**
     * Searches $searchText in the search database.
     *
     * @param string $searchText Search term
     * @param array $params Search parameters
     * @param array $searchTypes Search types
     *
     * @return array
     */
    public function search( $searchText, $params = array(), $searchTypes = array() )
    {
        $searchText = trim( $searchText );

        if ( empty( $searchText ) )
        {
            return array(
                'SearchResult' => array(),
                'SearchCount' => 0,
                'StopWordArray' => array()
            );
        }

        $doFullText = true;
        $query = new LocationQuery();

        $criteria = array();

        if ( isset( $params['SearchDate'] ) && (int)$params['SearchDate'] > 0 )
        {
            $currentTimestamp = time();
            $dateSearchType = (int)$params['SearchDate'];

            $fromTimestamp = 0;
            if ( $dateSearchType === 1 )
            {
                // Last day
                $fromTimestamp = $currentTimestamp - 86400;
            }
            else if ( $dateSearchType === 2 )
            {
                // Last week
                $fromTimestamp = $currentTimestamp - ( 7 * 86400 );
            }
            else if ( $dateSearchType === 3 )
            {
                // Last month
                $fromTimestamp = $currentTimestamp - ( 30 * 86400 );
            }
            else if ( $dateSearchType === 4 )
            {
                // Last three months
                $fromTimestamp = $currentTimestamp - ( 3 * 30 * 86400 );
            }
            else if ( $dateSearchType === 5 )
            {
                // Last year
                $fromTimestamp = $currentTimestamp - ( 365 * 86400 );
            }

            $criteria[] = new Criterion\DateMetadata(
                Criterion\DateMetadata::CREATED,
                Criterion\Operator::GTE,
                $fromTimestamp
            );
        }

        if ( isset( $params['SearchSectionID'] ) && (int)$params['SearchSectionID'] > 0 )
        {
            $criteria[] = new Criterion\SectionId( (int)$params['SearchSectionID'] );
        }

        if ( isset( $params['SearchContentClassID'] ) && (int)$params['SearchContentClassID'] > 0 )
        {
            $criteria[] = new Criterion\ContentTypeId( (int)$params['SearchContentClassID'] );

            if ( isset( $params['SearchContentClassAttributeID'] ) && (int)$params['SearchContentClassAttributeID'] > 0 )
            {
                $classAttribute = eZContentClassAttribute::fetch( $params['SearchContentClassAttributeID'] );
                if ( $classAttribute instanceof eZContentClassAttribute )
                {
                    $criteria[] = new Criterion\Field(
                        $classAttribute->attribute( 'identifier' ),
                        Criterion\Operator::LIKE,
                        $searchText
                    );

                    $doFullText = false;
                }
            }
        }

        if ( isset( $params['SearchSubTreeArray'] ) && !empty( $params['SearchSubTreeArray'] ) )
        {
            $subTreeArrayCriteria = array();
            foreach ( $params['SearchSubTreeArray'] as $nodeId )
            {
                $node = eZContentObjectTreeNode::fetch( $nodeId );
                $subTreeArrayCriteria[] = $node->attribute( 'path_string' );
            }

            $criteria[] = new Criterion\Subtree( $subTreeArrayCriteria );
        }

        if ( $doFullText )
        {
            $query->query = new Criterion\FullText( $searchText );
        }

        if(!empty($criteria)) {
            $query->filter = new Criterion\LogicalAnd( $criteria );
        }

        $query->limit = isset( $params['SearchLimit'] ) ? (int)$params['SearchLimit'] : 10;
        $query->offset = isset( $params['SearchOffset'] ) ? (int)$params['SearchOffset'] : 0;

        $useLocationSearch = $this->iniConfig->variable( 'SearchSettings', 'UseLocationSearch' ) === 'true';

        if ( $useLocationSearch )
        {
            $searchResult = $this->repository->getSearchService()->findLocations( $query );
        }
        else
        {
            $searchResult = $this->repository->getSearchService()->findContentInfo( $query );
        }

        $nodeIds = array();
        foreach ( $searchResult->searchHits as $searchHit )
        {
            $nodeIds[] = $useLocationSearch ?
                $searchHit->valueObject->id :
                $searchHit->valueObject->mainLocationId;
        }

        $resultNodes = array();
        if ( !empty( $nodeIds ) )
        {
            $resultNodes = array_fill_keys($nodeIds, '');

            $nodes = eZContentObjectTreeNode::fetch( $nodeIds );
            if ( $nodes instanceof eZContentObjectTreeNode )
            {
                $resultNodes[$nodes->attribute('node_id')] = $nodes;
            }
            else if ( is_array( $nodes ) )
            {
                foreach($nodes as $node){
                    $resultNodes[$node->attribute('node_id')] = $node;
                }
            }
        }

        return array(
            'SearchResult' => $resultNodes,
            'SearchCount' => $searchResult->totalCount,
            'StopWordArray' => array()
        );
    }

    /**
     * Returns an array describing the supported search types by the search engine.
     *
     * @see search()
     * @return array
     */
    public function supportedSearchTypes()
    {
        return array(
            'types' => array(
                array(
                    'type' => 'fulltext',
                    'subtype' => 'text',
                    'params' => array( 'value' )
                )
            ),
            'general_filter' => array()
        );
    }

    /**
     * Commit the changes made to the search engine.
     *
     * @see needCommit()
     */
    public function commit()
    {
        if ( method_exists( $this->searchHandler, 'commit' ) )
        {
            $this->searchHandler->commit();
        }
    }

    /**
     * Purges the index.
     */
    public function cleanup()
    {
        // Indexing is not implemented in eZ Publish 5 legacy search engine
        if ( $this->searchHandler instanceof LegacyHandler )
        {
            $db = eZDB::instance();
            $db->begin();
            $db->query( "DELETE FROM ezsearch_word" );
            $db->query( "DELETE FROM ezsearch_object_word_link" );
            $db->commit();
        }
        else if ( method_exists( $this->searchHandler, 'purgeIndex' ) )
        {
            $this->searchHandler->purgeIndex();
        }
    }

    /**
     * Update index when a new section is assigned to an object, through a node.
     *
     * @param int $nodeID
     * @param int $sectionID
     */
    public function updateNodeSection( $nodeID, $sectionID )
    {
        $contentObject = eZContentObject::fetchByNodeID( $nodeID );
        eZContentOperationCollection::registerSearchObject( $contentObject->attribute( 'id' ) );
    }

    /**
     * Update the section in the search engine
     *
     * @param array $objectIDs
     * @param int $sectionID
     */
    public function updateObjectsSection( array $objectIDs, $sectionID )
    {
        foreach( $objectIDs as $id )
        {
            $object = eZContentObject::fetch( $id );
            // we may be inside a DB transaction running update queries for the
            // section id or the content object may come from the memory cache
            // make sure the section_id is the right one
            $object->setAttribute( 'section_id', $sectionID );
            eZContentOperationCollection::registerSearchObject( $id );
        }
    }

    /**
     * Update index when node's visibility is modified.
     *
     * If the node has children, they will be also re-indexed, but this action is deferred to ezfindexsubtree cronjob.
     *
     * @param int $nodeID
     * @param string $action
     */
    public function updateNodeVisibility( $nodeID, $action )
    {
        $node = eZContentObjectTreeNode::fetch( $nodeID );
        eZContentOperationCollection::registerSearchObject( $node->attribute( 'contentobject_id' ) );

        $params = array(
            'Depth' => 1,
            'DepthOperator' => 'eq',
            'Limitation' => array(),
            'IgnoreVisibility' => true
        );

        if ( $node->subTreeCount( $params ) > 0 )
        {
            $pendingAction = new eZPendingActions(
                array(
                    'action' => 'index_subtree',
                    'created' => time(),
                    'param' => $nodeID
                )
            );

            $pendingAction->store();
        }
    }

    /**
     * Update search index when node assignment is added to an object
     *
     * @param int $mainNodeID
     * @param int $objectID
     * @param array $nodeAssignmentIDList
     * @param bool $isMoved
     */
    public function addNodeAssignment( $mainNodeID, $objectID, $nodeAssignmentIDList, $isMoved )
    {
        eZContentOperationCollection::registerSearchObject( $objectID, null, $isMoved );
    }

    /**
     * Update search index when node assignment is removed from an object
     *
     * @param int $mainNodeID
     * @param int $newMainNodeID
     * @param int $objectID
     * @param array $nodeAssignmentIDList
     */
    public function removeNodeAssignment( $mainNodeID, $newMainNodeID, $objectID, $nodeAssignmentIDList )
    {
        eZContentOperationCollection::registerSearchObject( $objectID );
    }

    /**
     * Update search index when two nodes are swapped
     *
     * @param int $nodeID
     * @param int $selectedNodeID
     * @param array $nodeIdList
     */
    public function swapNode( $nodeID, $selectedNodeID, $nodeIdList = array() )
    {
        $contentObject1 = eZContentObject::fetchByNodeID( $nodeID );
        $contentObject2 = eZContentObject::fetchByNodeID( $selectedNodeID );

        eZContentOperationCollection::registerSearchObject( $contentObject1->attribute( 'id' ) );
        eZContentOperationCollection::registerSearchObject( $contentObject2->attribute( 'id' ) );
    }

    /**
     * Update search index when object state changes
     *
     * @param int $objectID
     * @param array $objectStateList
     */
    public function updateObjectState( $objectID, $objectStateList )
    {
        eZContentOperationCollection::registerSearchObject( $objectID );
    }

    /**
     * Returns true if the search part is incomplete.
     *
     * Used only by legacy search engine (eZSearchEngine class).
     *
     * @param string $part
     *
     * @return bool
     */
    function isSearchPartIncomplete( $part )
    {
        if ( $this->searchHandler instanceof LegacyHandler )
        {
            $searchEngine = new eZSearchEngine();
            return $searchEngine->isSearchPartIncomplete( $part );
        }

        return false;
    }

    /**
     * Normalizes the text so that it is easily parsable
     *
     * Used only by legacy search engine (eZSearchEngine class).
     *
     * @param string $text
     *
     * @return string
     */
    public function normalizeText( $text )
    {
        if ( $this->searchHandler instanceof LegacyHandler )
        {
            $searchEngine = new eZSearchEngine();
            return $searchEngine->normalizeText( $text );
        }

        return $text;
    }
}
