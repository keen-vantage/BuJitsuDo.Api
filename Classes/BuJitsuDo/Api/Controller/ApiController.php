<?php
namespace BuJitsuDo\Api\Controller;

use BuJitsuDo\Api\Service\DataService;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ActionController;

class ApiController extends ActionController
{

    /**
     * @return void
     */
    protected function initializeAction()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Content-type', 'application/json');
    }

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

    /**
     * @param string $nodeType
     * @param string $identifier
     * @param string $type
     * @return string
     */
    public function showAction($nodeType, $identifier, $type) {
        return json_encode(DataService::getData([
            'type' => $type,
            'identifier' => $identifier,
            'nodeType' => $nodeType
        ]));
    }

}
