<?php
namespace BuJitsuDo\Api\Controller;

use BuJitsuDo\Api\Service\DataService;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ActionController;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

class ApiController extends ActionController
{

    /**
     * @param string $nodeType
     * @return string
     */
    public function indexAction($nodeType)
    {
        return json_encode(DataService::getData([
            'type' => null,
            'nodeType' => $nodeType
        ]));
    }

    protected function initializeAction()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Content-type', 'application/json');
    }

    /**
     * @param NodeInterface $item
     * @return string
     */
    protected function createSingleItem(NodeInterface $item)
    {
        return json_encode([
            '@context' => 'http://schema.org/"',
            '@type' => 'Person',
            'name' => $item->getProperty('firstName')
        ]);
    }

}
