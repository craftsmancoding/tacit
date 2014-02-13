<?php
/*
 * This file is part of the Tacit package.
 *
 * Copyright (c) Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tacit\Controller;


use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\TransformerAbstract;
use Slim\Route;
use Tacit\Controller\Exception\MethodNotAllowedException;
use Tacit\Controller\Exception\NotAcceptableException;
use Tacit\Controller\Exception\NotImplementedException;
use Tacit\Controller\Exception\RestfulException;
use Tacit\Controller\Exception\UnauthorizedException;
use Tacit\Model\Persistent;
use Tacit\Tacit;
use Tacit\Transform\RestfulExceptionTransformer;

/**
 * Defines default behavior and properties for a Restful Controller.
 *
 * @package Tacit\Controller
 */
abstract class Restful
{
    const RESOURCE_TYPE_ERROR      = 0;
    const RESOURCE_TYPE_ITEM       = 1;
    const RESOURCE_TYPE_COLLECTION = 2;

    public static $name = 'A RESTful Controller';

    /**
     * An array of allowed HTTP methods for the controller.
     *
     * @var array[string]
     */
    protected static $allowedMethods = ['OPTIONS', 'HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * The plural name of the Resource represented by this Controller.
     *
     * @var string
     */
    protected static $collectionName = 'collection';

    /**
     * The singular name of the Resource represented by this Controller.
     *
     * @var string
     */
    protected static $itemName = 'item';

    /**
     * A Resource model class represented by this Controller.
     *
     * @var string
     */
    protected static $modelClass;

    /**
     * The Content-Type this Controller responds with.
     *
     * @var string
     */
    protected static $responseType = 'application/json';

    /**
     * An instance of Tacit.
     *
     * @var Tacit
     */
    protected $app;

    /**
     * An array of refs for this controller.
     *
     * @var array
     */
    protected $refs = [];

    /**
     * @var Route The route that instantiated this controller.
     */
    public $route;

    /**
     * Construct a new instance of the Restful controller.
     *
     * @param Tacit $app
     */
    public function __construct(Tacit &$app)
    {
        $this->app = $app;
        $this->route = $this->app->router->getCurrentRoute();
        $this->fractal = new Manager();
        $scopeParameter = $this->app->config('embedded_scopes_param') ? $this->app->config('embedded_scopes_param') : 'zoom';
        $this->fractal->setRequestedScopes(explode(',', $this->app->request->get($scopeParameter)));
    }

    /**
     * Handle a DELETE request.
     *
     * @throws Exception\RestfulException
     * @return void
     */
    public function delete()
    {
        throw new NotImplementedException($this);
    }

    /**
     * Handle a GET request.
     *
     * @throws Exception\RestfulException
     * @return void
     */
    public function get()
    {
        throw new NotImplementedException($this);
    }

    /**
     * Route to the appropriate HTTP method.
     *
     * @throws Exception\RestfulException
     * @return void
     */
    public function handle()
    {
        $method = strtoupper($this->app->request->getMethod());
        if (method_exists($this, $method)) {
            call_user_func_array([$this, $method], func_get_args());
        }
        throw new NotAcceptableException($this);
    }

    /**
     * Handle a HEAD request.
     *
     * @throws Exception\RestfulException
     * @return void
     */
    public function head()
    {
        $this->get();
    }

    /**
     * Checks if the current principal is authorized for the request.
     *
     * @throws UnauthorizedException If the principal is not authorized.
     * @return bool True if authorized.
     */
    public function isAuthorized()
    {
        return true;
    }

    /**
     * Handle an OPTIONS request.
     *
     * @throws Exception\RestfulException
     * @return void
     */
    public function options()
    {
        $this->app->response->headers->set('Content-Type', static::$responseType);
        $this->app->response->setStatus(200);
        $this->app->response->headers->set('Allow', implode(',', static::$allowedMethods));

        $this->app->stop();
    }

    /**
     * Handle a PATCH request.
     *
     * @throws Exception\RestfulException
     * @return void
     */
    public function patch()
    {
        throw new NotImplementedException($this);
    }

    /**
     * Handle a POST request.
     *
     * @throws Exception\RestfulException
     * @return void
     */
    public function post()
    {
        throw new NotImplementedException($this);
    }

    /**
     * Handle a PUT request.
     *
     * @throws Exception\RestfulException
     * @return void
     */
    public function put()
    {
        throw new NotImplementedException($this);
    }

    /**
     * Get an array of refs for this controller.
     *
     * @param array $refs An optional array of refs to merge with those already
     * defined for this controller.
     *
     * @return array An array of refs for this controller.
     */
    public function refs(array $refs = [])
    {
        return array_merge(['self' => ['href' => $this->url(), 'title' => static::$name]], $this->refs, $refs);
    }

    /**
     * Respond with a RestfulException.
     *
     * @param RestfulException $exception
     */
    public function respondWithError(RestfulException $exception)
    {
        $item = new Item($exception, new RestfulExceptionTransformer());
        $this->respond($item, self::RESOURCE_TYPE_ERROR, $exception->getStatus());
    }

    /**
     * Add an array of refs to this controller.
     *
     * @param array $refs An array of refs to add to this controller.
     */
    public function addRefs(array $refs = [])
    {
        $this->refs = array_merge($this->refs, $refs);
    }

    /**
     * Encode an array of data representing a response body.
     *
     * @param array $body An array of data representing the response body.
     * @param mixed $options Options for the encoding process.
     *
     * @return string The encoded response body as a string.
     */
    protected function encode($body, $options = 0)
    {
        return json_encode($body, $options);
    }

    /**
     * Respond with a Collection.
     *
     * @param array[Persistent]   $collection A collection to transform and  respond with.
     * @param TransformerAbstract $transformer The transformer to apply to the items in the collection.
     * @param array               $meta An array of metadata associated with the collection.
     */
    protected function respondWithCollection($collection, TransformerAbstract $transformer, array $meta = [])
    {
        if (!isset($meta['total'])) $meta['total'] = count($collection);
        if (!isset($meta['collectionName'])) $meta['collectionName'] = static::$collectionName;
        $resource = new Collection($collection, $transformer);
        $this->respond($resource, self::RESOURCE_TYPE_COLLECTION, 200, $meta);
    }

    /**
     * Respond with an Item.
     *
     * @param array|Persistent    $item A resource item to transform and respond with.
     * @param TransformerAbstract $transformer The transformer to apply to the item.
     */
    protected function respondWithItem($item, TransformerAbstract $transformer)
    {
        $resource = new Item($item, $transformer);
        $this->respond($resource, self::RESOURCE_TYPE_ITEM);
    }

    /**
     * Respond to the successful creation of an Item.
     *
     * @param Persistent          $item
     * @param string              $location
     * @param TransformerAbstract $transformer
     */
    protected function respondWithItemCreated($item, $location, TransformerAbstract $transformer)
    {
        $resource = new Item($item, $transformer);
        $this->respond(
            $resource,
            self::RESOURCE_TYPE_ITEM,
            201,
            ['headers' => ['Location' => $location]]
        );
    }

    /**
     * Respond to the request with a resource.
     *
     * @param ResourceInterface $resource A fractal resource.
     * @param int   $type The type of resource to respond with.
     * @param int   $status The HTTP status code to respond with.
     * @param array $meta An optional array of metadata for the response.
     *
     * @return void
     */
    protected function respond($resource, $type = self::RESOURCE_TYPE_ITEM, $status = 200, $meta = array())
    {
        $bodyRaw = null;
        if ($resource !== null && $status !== 204) {
            $bodyRaw = [];
            $scope = $this->fractal->createData($resource)->toArray();
            switch ($type) {
                case self::RESOURCE_TYPE_ITEM:
                    $bodyRaw['_links'] = $this->refs();
                    foreach ($scope['data'] as $key => $value) {
                        $bodyRaw[$key] = $value;
                    }
                    break;
                case self::RESOURCE_TYPE_COLLECTION:
                    $links = array();
                    $total = $meta['total'];
                    $limit = (int)$this->app->request->get('limit', 25);
                    $offset = (int)$this->app->request->get('offset', 0);
                    if ($total > $offset) {
                        $links['first'] = ['href' => $this->url(['offset' => 0])];
                        $links['previous'] = ($offset > 0) ? ['href' => $this->url(['offset' => $offset - $limit])] : null;
                        $links['next'] = (($offset + $limit) < $total) ? ['href' => $this->url(['offset' => $offset + $limit])] : null;
                        $links['last'] = ['href' => $this->url(['offset' => (floor(($total - 1) / $limit) * $limit)])];
                    }
                    $bodyRaw['_links'] = $this->refs($links);
                    $bodyRaw['_embedded'][$meta['collectionName']] = $scope['data'];
                    if ($total > $offset) {
                        $bodyRaw['total_items'] = $total;
                        $bodyRaw['returned_items'] = count($scope['data']);
                        $bodyRaw['limit'] = $limit;
                        $bodyRaw['offset'] = $offset;
                    }
                    break;
                case self::RESOURCE_TYPE_ERROR:
                    $bodyRaw = $scope['data'];
                    break;
            }
        }

        /* @var \Slim\Http\Response $response */
        $response = $this->app->response;
        $response->headers->set('Content-Type', static::$responseType);
        if (isset($meta['headers']) && is_array($meta['headers'])) {
            foreach ($meta['headers'] as $headerKey => $headerValue) {
                $response->headers->set($headerKey, $headerValue);
            }
        }
        $response->setStatus($status);
        if ($bodyRaw !== null && $status !== 204) {
            if ($this->app->config('debug') === true) {
                $bodyRaw['execution_time'] = microtime(true) - $this->app->config('startTime');
            }
            $response->setBody($this->encode($bodyRaw));
        }

        $this->app->stop();
    }

    /**
     * Generate a route for a specific Resource.
     *
     * @param string $resource A Restful controller class or named route.
     * @param string $identifier An identifier for clarifying the route.
     * @param array  $params An array of parameters for the route.
     *
     * @return string The generated route's URL.
     */
    public function route($resource, $identifier = '', array $params = array())
    {
        $name = '';
        if (is_string($resource)) {
            if (class_exists($resource)) {
                $name = $resource::$name;
            } else {
                $name = $resource;
            }
        } elseif ($resource instanceof Route) {
            $name = $resource->getName() . $identifier;
        }
        $name .= $identifier;
        $url = $this->app->request->getUrl();
        $url .= $this->app->urlFor($name, $params);
        return $url;
    }

    /**
     * Check if the current method is allowed on this controller.
     *
     * @throws Exception\MethodNotAllowedException
     */
    protected function checkMethod()
    {
        if (!in_array($this->app->request->getMethod(), static::$allowedMethods)) {
            throw new MethodNotAllowedException($this);
        }
    }

    /**
     * Generate a URL for the current requested route.
     *
     * @param array $params An array of parameters to add to the route.
     *
     * @return string The current route's URL.
     */
    protected function url(array $params = array())
    {
        $request = $this->app->request();
        $url = $request->getUrl();
        $url .= $request->getRootUri();
        $url .= $request->getResourceUri();
        $getParams = $request->get();
        $getParams = array_merge($getParams, $params);
        if (!empty($getParams)) {
            $url .= '?';
            $qs = array();
            foreach ($getParams as $qKey => $qValue) {
                $qs[] = urlencode($qKey) . "=" . urlencode($qValue);
            }
            $url .= implode('&', $qs);
        }
        return $url;
    }
}
