<?php
namespace BuJitsuDo\Api\Service;

use TYPO3\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Service\ContextFactoryInterface;

class EventService extends AbstractDataService
{

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

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
        $result['event'] = $this->buildSingleItemJson($event);
        return $result;
    }

    /**
     * @return string
     */
    final public function getList()
    {
        $context = $this->contextFactory->create(['workspaceName' => 'live']);
        $flowQuery = new FlowQuery([$context->getRootNode()]);
        $events = $flowQuery->find('[instanceof Nieuwenhuizen.BuJitsuDo:Event][!instanceof Nieuwenhuizen.BuJitsuDo:Exam]')->get();
        usort($events, function(NodeInterface $a, NodeInterface $b) {
            return $this->sortNodesSelection($a, $b, 'start', false);
        }) ;
        $result['events'] = [];
        foreach ($events as $event) {
            $result['events'][] = $this->buildSingleItemJson($event);
        }
        return $result;
    }

        /**
     * @param NodeInterface $event
     * @return array
     */
    protected function buildSingleItemJson($event)
    {
        $properties = [
            '@context' => [
                'ical' => 'http://www.w3.org/2002/12/cal/ical#',
                'xsd' => 'http://www.w3.org/2001/XMLSchema#',
                'ical:dtstart' => [
                    '@type' => 'xsd:dateTime'
                ]
            ],
            '@id' => $event->getIdentifier(),
            'id' => $event->getIdentifier(),
            'shortIdentifier' => explode('-', $event->getIdentifier())[0],
            'title' => $event->getProperty('title'),
            'startDate' => $event->getProperty('start')->format('D M d Y H:i:s O'),
            'endDate' => $event->getProperty('end')->format('D M d Y H:i:s O'),
        ];

        $this->processProperties($properties);

        return $properties;
    }
}