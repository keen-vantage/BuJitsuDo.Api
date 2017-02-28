<?php
namespace BuJitsuDo\Api\Service;

use Neos\Flow\Http\Request;
use Neos\Flow\Http\Response;

class DataService
{

    /**
     * @var array
     */
    private static $serviceMapping = [
        'BuJitsuDo.Api:Event' => '\BuJitsuDo\Api\Service\EventService',
        'BuJitsuDo.Api:Exam' => '\BuJitsuDo\Api\Service\ExamService',
        'BuJitsuDo.Api:Article' => '\BuJitsuDo\Api\Service\NewsService'
    ];

    /**
     * @param array $routeData
     * @return integer
     */
    final public static function countData(array $routeData)
    {
        $service = new self::$serviceMapping[$routeData['nodeType']];
        if ($routeData['type'] === 'count') {
            return $service->countItems();
        }
    }

    /**
     * @param array $routeData
     * @return string
     */
    final public static function getData(array $routeData)
    {
        $service = new self::$serviceMapping[$routeData['nodeType']];
        if ($routeData['type'] === 'single') {
            return $service->getSingle($routeData['identifier']);
        }
        return $service->getList();
    }

    /**
     * @param array $routeData
     * @param Request $request
     * @return integer
     */
    final public static function putData(array $routeData, Request $request)
    {
        /** @var DataInterface $service */
        $service = new self::$serviceMapping[$routeData['nodeType']];
        return $service->update($routeData['identifier'], $request);
    }

    /**
     * @param array $routeData
     * @param Request $request
     * @return string
     */
    final public static function createData(array $routeData, Request $request)
    {
        /** @var DataInterface $service */
        $service = new self::$serviceMapping[$routeData['nodeType']];
        return $service->create($request);
    }

    /**
     * @param array $routeData
     * @param Request $request
     * @return string
     */
    final public static function deleteData(array $routeData, Request $request)
    {
        /** @var DataInterface $service */
        $service = new self::$serviceMapping[$routeData['nodeType']];
        return $service->delete($routeData['identifier'], $request);
    }
}
