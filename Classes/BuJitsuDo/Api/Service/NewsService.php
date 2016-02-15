<?php
namespace BuJitsuDo\Api\Service;

use Gedmo\Tree\Node;
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Media\Domain\Model\Image;
use TYPO3\Media\Domain\Model\ThumbnailConfiguration;
use TYPO3\Media\Domain\Service\AssetService;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Service\ContextFactoryInterface;

class NewsService extends AbstractDataService
{

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var AssetService
     */
    protected $assetService;

    /**
     * @param string $identifier
     * @return string
     */
    final public function getSingle($identifier)
    {
        if (strlen($identifier) === 8) {
            $identifier = $this->getNodeIdentifierByShortIdentifier($identifier);
        }
        $context = $this->contextFactory->create(['workspace' => 'live']);
        $event = $context->getNodeByIdentifier($identifier);
        $result['article'] = $this->buildSingleItemJson($event);
        return $result;
    }

    /**
     * @return string
     */
    final public function getList()
    {
        $context = $this->contextFactory->create(['workspaceName' => 'live']);
        $flowQuery = new FlowQuery([$context->getRootNode()]);
        $events = $flowQuery->find('[instanceof Nieuwenhuizen.BuJitsuDo:Article]')->get();
        $result['articles'] = [];
        foreach ($events as $event) {
            $result['articles'][] = $this->buildSingleItemJson($event);
        }
        return $result;
    }

    /**
     * @param NodeInterface $article
     * @return array
     */
    protected function buildSingleItemJson(NodeInterface $article)
    {
        $contentCollection = $article->getChildNodes('TYPO3.Neos:ContentCollection')[0];
        $articleBody = '';
        if ($contentCollection instanceof NodeInterface) {
            $content = $contentCollection->getChildNodes();
            if (is_array($content) && array_key_exists(0, $content)) {
                foreach ($content as $node) {
                    /** @var NodeInterface $node */
                    if ($node->getNodeType()->getName() === 'TYPO3.Neos.NodeTypes:Text' ||
                        $node->getNodeType()->getName() === 'TYPO3.Neos.NodeTypes:TextWithImage'
                    ) {
                        $articleBody .= $node->getProperty('text');
                    }
                }
            }
        }
        $thumbnailConfiguration = new ThumbnailConfiguration(150, 150, 150, 150, true, true, false);
        $detailConfiguration = new ThumbnailConfiguration(400, 400, 300, 300, true, true, false);
        /** @var Image $image */
        $image = $article->getProperty('headerImage');
        $properties = [
            '@context' => 'http://schema.org',
            '@type' => 'Article',
            '@id' => $article->getIdentifier(),
            'id' => $article->getIdentifier(),
            'shortIdentifier' => explode('-', $article->getIdentifier())[0],
            'title' => $article->getProperty('title'),
            'articleBody' => $articleBody,
            'publicationDate' => $article->getProperty('publicationDate')->format('D M d Y H:i:s O'),
            'teaser' => $article->getProperty('article'),
            'listImage' => $this->assetService->getThumbnailUriAndSizeForAsset($image, $thumbnailConfiguration)['src'],
            'image' => $this->assetService->getThumbnailUriAndSizeForAsset($image, $detailConfiguration)['src']
        ];

        $this->processProperties($properties);

        return $properties;
    }
}