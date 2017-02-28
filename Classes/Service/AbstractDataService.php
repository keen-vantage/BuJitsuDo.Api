<?php
namespace BuJitsuDo\Api\Service;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Statement;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\SystemLoggerInterface;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\Exception\AccessDeniedException;
use Neos\Flow\Security\Policy\Role;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Service\Context;
use TYPO3\TYPO3CR\Domain\Service\ContextFactoryInterface;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;
use TYPO3\TYPO3CR\Domain\Service\PublishingService;
use TYPO3\TYPO3CR\Utility as NodeUtility;

class AbstractDataService {

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var PublishingService
     */
    protected $publishingService;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $logger;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="dateProperties", package="SimplyAdmire.Cap.Api.configuration" )
     */
    protected $dateProperties;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="editorProperties", package="SimplyAdmire.Cap.Api.configuration" )
     */
    protected $editorProperties;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="editorTagWhitelist", package="SimplyAdmire.Cap.Api.configuration" )
     */
    protected $editorTagWhitelist;

    /**
     * @Flow\Inject
     * @var ObjectManager
     */
    protected $entityManager;

    /**
     * @Flow\Inject
     * @var SecurityContext
     */
    protected $securityContext;

    /**
     * @param array $contextProperties
     * @return Context
     */
    protected function createContentContext($contextProperties = array())
    {
        return $this->contextFactory->create($contextProperties);
    }

    /**
     * @param array $tags
     * @return array
     */
    protected function getTagIdentifiers(array $tags)
    {
        $tagIdentifiers = [];
        foreach ($tags as $tag) {
            /** @var $tag NodeInterface */
            if ($tag instanceof NodeInterface) {
                $tagIdentifiers[] = $tag->getIdentifier();
            }
        }
        return $tagIdentifiers;
    }

    /**
     * @param array $properties
     * @todo: REMOVE!
     */
    protected function processProperties(array &$properties)
    {
        foreach ($properties as $propertyName => $propertyValue) {
            if (strpos($propertyName, '_') !== false) {
                $upperCamelCasePropertyName = preg_replace_callback('/(_[a-z])/', function ($matches) {
                    return strtoupper(substr($matches[0], -1));
                }, $propertyName);
                $properties[$upperCamelCasePropertyName] = $propertyValue;
            }
        }
    }

    /**
     * @param string $idealNodeName
     * @param NodeInterface $referenceNode
     * @return string
     */
    protected function getFreeNodeName($idealNodeName, NodeInterface $referenceNode)
    {
        $idealNodeName = NodeUtility::renderValidNodeName($idealNodeName);
        $possibleNodeName = $idealNodeName;
        $counter = 1;
        while ($referenceNode->getNode($possibleNodeName) !== null) {
            $possibleNodeName = $idealNodeName . '-' . $counter;
            $counter++;
        }
        return $possibleNodeName;
    }

    /**
     * @param NodeInterface $referenceNode
     * @param string $idealNodeName
     * @param string $nodeTypeName
     * @param array $properties
     * @throws \Exception
     * @return NodeInterface
     */
    public function createChildNode(NodeInterface $referenceNode, $idealNodeName, $nodeTypeName, array $properties = []) {
        $this->processProperties($properties);
        $nodeType = $this->nodeTypeManager->getNodeType($nodeTypeName);
        $node = $referenceNode->createNode(
            $this->getFreeNodeName($idealNodeName, $referenceNode),
            $nodeType
        );

        $nodeTypeProperties = $nodeType->getProperties();

        foreach ($properties as $propertyName => $propertyValue) {
            if (in_array($propertyName, $this->dateProperties)) {
                try {
                    $propertyValue = trim($propertyValue);
                    if (!empty($propertyValue)) {
                        $propertyValue = new \DateTime($propertyValue);
                    }
                    if ($propertyValue === 'Invalid date') {
                        $propertyValue = null;
                    }

                    if (!$propertyValue instanceof \DateTime && $propertyName === 'publicationDate') {
                        $propertyValue = new \DateTime();
                    }
                } catch (\Exception $exception) {
                    $propertyValue = null;
                }
            }
            if (!isset($nodeTypeProperties[$propertyName])) {
                $this->logger->log(sprintf('Could not add non-existent property "%s"', $propertyName), LOG_WARNING);
                continue;
            }

            if (is_string($propertyValue)) {
                $allowedTags = in_array($propertyName, $this->editorProperties) ? $this->editorTagWhitelist: '';
                $propertyValue = strip_tags($propertyValue, $allowedTags);
            }

            $node->setProperty($propertyName, $propertyValue);
        }

        if ($node->getNodeType()->isOfType('Neos.Neos:Document')) {
            $node->setProperty('uriPathSegment', $node->getName());
        }

        $this->publishingService->emitNodePublished($node);

        return $node;
    }

    /**
     * @param NodeInterface $referenceNode
     * @return void
     */
    public function removeNode(NodeInterface $referenceNode)
    {
        $this->checkNodeEditAccess($referenceNode, 'remove');
        $referenceNode->remove();
    }

    /**
     * @param NodeInterface $referenceNode
     * @param array $newPropertyValues
     * @return void
     */
    public function updateNode(NodeInterface $referenceNode, array $newPropertyValues)
    {
        $this->checkNodeEditAccess($referenceNode, 'update');
        $this->processProperties($newPropertyValues);

        $nodeTypeProperties = $referenceNode->getNodeType()->getProperties();

        foreach ($newPropertyValues as $property => $value) {
            if (in_array($property, $this->dateProperties)) {
                try {
                    $value = trim($value);
                    if (!empty($value)) {
                        $value = new \DateTime($value);
                    }
                    if ($value === 'Invalid date') {
                        $value = null;
                    }
                    if (!$value instanceof \DateTime) {
                        throw new \Exception('Not a valid DateTime object');
                    }
                } catch (\Exception $exception) {
                    $value = null;
                }

                if ($property === 'publicationDate' && !$value instanceof \DateTime) {
                    $value = $referenceNode->getProperty('publicationDate');
                }
            }

            if (!isset($nodeTypeProperties[$property])) {
                $this->logger->log(sprintf('Could not add non-existent property "%s"', $property), LOG_WARNING);
                continue;
            }

            if (is_string($value)) {
                $allowedTags = in_array($property, $this->editorProperties) ? $this->editorTagWhitelist: '';
                $value = strip_tags($value, $allowedTags);
            }

            $referenceNode->setProperty($property, $value);
        }
    }

    /**
     *  return NodeInterface
     */
    public function getActiveProfile()
    {
        /* @var \SimplyAdmire\Cap\AuthenticationBundle\Domain\Model\User $user */
        $user = $this->userRepository->findOneByAccount($this->securityContext->getAccount());

        return $user->getProfile();
    }

    /**
     * @return Account
     */
    public function getCurrentAccount()
    {
        return $this->securityContext->getAccount();
    }

    /**
     * @return array
     */
    public function getAccountRoleIdentifiers()
    {
        $roleIdentifiers = [];
        foreach ($this->securityContext->getRoles() as $role) {
            /** @var Role $role */
            $roleIdentifiers[] = $role->getIdentifier();
        }
        return $roleIdentifiers;
    }

    /**
     * @param NodeInterface $referenceNode
     * @param string $action
     * @throws AccessDeniedException
     */
    protected function checkNodeEditAccess(NodeInterface $referenceNode, $action = 'remove')
    {
        $nodeType = $referenceNode->getNodeType()->getName();

        if ($this->securityContext->hasRole('SimplyAdmire.Cap.Api:Editor')) {
            return;
        }

        if ($nodeType === 'SimplyAdmire.Cap.PersonBundle:Person') {
            $identifier = $referenceNode->getIdentifier();
            if ($identifier === $this->getActiveProfile()->getIdentifier()) {
                return;
            }
        }
        $author = $referenceNode->getProperty('author');
        if ($author instanceof NodeInterface) {
            $identifier = $referenceNode->getProperty('author')->getIdentifier();
            if ($identifier === $this->getActiveProfile()->getIdentifier()) {
                return;
            }
        }

        throw new AccessDeniedException('You do not have access to ' . $action . ' this node');
    }

    /**
     * @param $shortIdentifier
     * @return string
     * @throws \Exception
     */
    protected function getNodeIdentifierByShortIdentifier($shortIdentifier)
    {
        /** @var Connection $connection */
        $connection = $this->entityManager->getConnection();
        /** @var Statement $query */
        $query = $connection->prepare('SELECT identifier FROM typo3_typo3cr_domain_model_nodedata WHERE identifier LIKE :identifier');

        $query->execute([':identifier' => $shortIdentifier . '-%']);
        $rows = $query->fetchAll(\PDO::FETCH_COLUMN);
        // Assume we will never have duplicates
        if (!empty($rows)) {
            return current($rows);
        }

        throw new \Exception('Could not find full node identifier for short identifier "' . $shortIdentifier . '"');
    }

    /**
     * Gets a storage folder by type, and creates it if needed
     *
     * @param string $nodeTypeName
     * @param NodeInterface $rootNode
     * @return NodeInterface
     */
    protected function getStorageFolder($nodeTypeName, NodeInterface $rootNode)
    {
        $query = new FlowQuery([$rootNode]);
        $storageFolder = $query->find('[instanceof ' . $nodeTypeName . ']')->get(0);

        if (!$storageFolder instanceof NodeInterface) {
            $storageFolder = $rootNode->createNode(
                uniqid('node-'),
                $this->nodeTypeManager->getNodeType($nodeTypeName)
            );
        }

        return $storageFolder;
    }

    /**
     * @param NodeInterface $node
     * @param $tagType
     * @return array
     */
    public function buildTagResults(NodeInterface $node, $tagType)
    {
        $tagArray = [];

        //TODO: Category is currently stored as a string, when it's a reference array it can be included here
        if(in_array($tagType, ['skills', 'responsibilities', 'tags'])) {
            if (count($node->getProperty($tagType)) > 0) {
                foreach ($node->getProperty($tagType) as $tag) {
                    $tagArray[] = [
                        '@id' => $tag->getIdentifier(),
                        'id' => $tag->getIdentifier(),
                        'label' => $tag->getProperty('title')
                    ];
                }
            }
        }

        return $tagArray;
    }

    /**
     * Method that can be used in usort() to sort by a property in a node
     *
     * Example
     * <code>
     * usort($events, function(NodeInterface $a, NodeInterface $b) {
     *     return $this->sortNodesByDate($a, $b, 'date', false);
     * });
     * </code>
     *
     * @param NodeInterface $a
     * @param NodeInterface $b
     * @param string $property
     * @param boolean $ascending
     * @return integer
     */
    protected function sortNodesSelection(NodeInterface $a, NodeInterface $b, $property, $ascending = true)
    {
        if ($a->getProperty($property) === $b->getProperty($property)) {
            return 0;
        }
        if ($a->getProperty($property) < $b->getProperty($property)) {
            return $ascending === true ? -1 : 1;
        }
        return $ascending === true ? 1 : -1;
    }

}
