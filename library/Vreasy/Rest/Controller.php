<?php

use JacobKiers\OAuth\Server;
use JacobKiers\OAuth\OAuthException;
use Vreasy\Exceptions\AuthException;
use JacobKiers\OAuth\SignatureMethod\HmacSha1;
use JacobKiers\OAuth\Request\Request;
use Vreasy\OAuth\DataStore;
use Vreasy\Models\Traits\Forwardable;
use Vreasy\Zend\XmlParams;
use Vreasy\Models\ApiKey;
use Doctrine\Common\Inflector\Inflector;
use Vreasy\Models\Agent;
use Vreasy\Models\ListingMapping;
use Vreasy\Utils\Arrays;
use Vreasy\Models\Listing;
use Vreasy\NullObject;

class Vreasy_Rest_Controller extends \Zend_Rest_Controller
{
    use Forwardable {
      Forwardable::__call as __callForwardable;
    }

    /**
     * Whether or not the application/json content type is enabled for this controller
     *
     * Object serialization happens automatically, from the variables set in the view object. When
     * the view has a single variable set, it geenrated json will avoid including a "root object"
     * json entity in the document.
     *
     * @see \Api_Controller_Action_Helper_Serializer
     * @var boolean
     */
    protected $jsonEnabled = true;

    /**
     * Whether or not the application/xml content type is enabled for this controller
     *
     * Document renderization happens in the views. A view file matching the controller action
     * being executed will be run eg: <action>.xml.phtml (as per Zend conventions).
     * As a recommendation when implemening an xml document ourput action, use the php XmlWriter
     * instead of the DOMDocument.
     * @var boolean
     */
    protected $xmlEnabled = false;

    /**
     * Whether or not the application/xml content type is enabled for this controller
     *
     * Document renderization happens in the views. A view file matching the controller action
     * being executed will be run eg: <action>.csv.phtml (as per Zend conventions).
     * @var boolean
     */
    protected $csvEnabled = false;

    /**
     * A list of the available formats in the order of priority that will be negotiated
     *
     * Values in this list should match the variables names for the content type eg: `<type>Enabled`
     * @see getNegotiatedFormat()
     * @var string[]
     */
    protected $formatsPriority = ['json', 'xml', 'csv'];

    /**
     * A list with the options parsed from the URL, ready to be used by an `eagerLoad` method
     *
     * Used by concrete controllers and parsed out from the `expand` parameter of the URL.
     * @see getExpandedLinks(), preDispatch()
     * @var array
     */
    protected $eagerLoadOptions = [];

    /**
     * A list of the associations names to be treated as if they were immutable
     *
     * @todo : Decide if this fields belong here at the controller level,
     * or in the model  eg: hasManyImmutable
     *
     * When an association is considered immutable, three important things happen:
     *
     *  - Automatic content type rederers MAY exclude the field when found empty
     *  - It MAY be taken into account when calling `clearAssociationsWhenEmpty`
     *  - Its persistecy SHOULD be delegated to the "parent" resource.
     *
     * A "characteristic" of immutable associations, is that it SHOULD NOT have its own controller,
     * which basically means that all concept of identity without the "parent" resource is lost.
     * @see  clearAssociationsWhenEmpty()
     * @var string[]
     */
    public $immutableAssociations = [];

    public function init()
    {
        $this->view->clearVars();
        $this->delegate($this->getHelper('Sanitizer'), ['cleanUrl']);

        $contextSwitch = $this->getHelper('contextSwitch');
        if ($this->jsonEnabled) {
            $contextSwitch->setDefaultContext('json');
            $contextSwitch->setCallbacks(
                'json',
                [
                    'TRIGGER_INIT' => [$this->getHelper('Serializer'), 'initJsonContext'],
                    'TRIGGER_POST' => [$this->getHelper('Serializer'), 'postJsonContext']
                ]
            );
            $contextSwitch->setActionContext($this->getRequest()->getActionName(), 'json');
        }
        if ($this->xmlEnabled) {
            $contextSwitch->setActionContext($this->getRequest()->getActionName(), 'xml');
        }
        if ($this->csvEnabled) {
            $contextSwitch->addContext(
                'csv',
                [
                    'suffix' => 'csv',
                    'headers' => ['Content-Type' => 'application/csv']
                ]
            );
            $contextSwitch->setActionContext($this->getRequest()->getActionName(), 'csv');
        }
        ini_set('html_errors', 0);
    }

    public function preDispatch()
    {
        $request = $this->getRequest();
        $format = $this->getNegotiatedFormat($request);
        if (!$format) {
            $this->respondNotAcceptable();
        }
        $request->setParam('format', $format);
        $this->getHelper('contextSwitch')->initContext($format);

        if ($rawBody = $request->getRawBody()) {
            try {
                if ('json' == $format) {
                    $request->setParams(['body' => Zend_Json::decode($rawBody)]);
                } elseif ('xml' == $format) {
                    $request->setParams(['body' => (new XmlParams($rawBody))->toArray()]);
                }
            } catch(\Exception $e) {
                $this->respondBadRequest([$format => "Malformed $format"]);
            }
        }
        $this->sanitizeArrayParams();
        $this->maybeForwardToNewAction();
        $this->maybeForwardToCustomAction();

        // Extract and build options to be handled over to an eagerLoad method
        // An URL with params "...?expand=User,Reservation" will get an
        // array-option like this: ['including']['user', 'reservation']
        $this->eagerLoadOptions = [];
        foreach ($this->getExpandedLinks() as $linkName) {
            $this->eagerLoadOptions['including'][] = strtolower($linkName);
            $this->eagerLoadOptions['including'][] = Inflector::camelize($linkName);
            // Lets keep the old include<Field> option type just for backward compatibility
            $this->eagerLoadOptions['include'.Inflector::classify($linkName)] = true;
        }

        $this->eagerLoadOptions['fields'] = $this->getIncludedFields();
        $this->view->fieldRules()->rules = array_merge(
            $this->eagerLoadOptions['fields'] ?: [],
            $this->getIncludedFieldsFromExpand()
        );

        parent::preDispatch();
    }

    public function setPaginationLinks($nextBefore = false, $nextAfter = false)
    {
        $links = [];
        if ($nextBefore) {
            $requestUri = $this->getRequest()->getRequestUri();
            $query = http_build_query(array_diff_key(
                array_replace(
                    $this->getRequest()->getParams(),
                    [
                        'before' => $nextBefore
                    ]
                ),
                array_flip(
                    # Patch to clear the id parameter if it is included in the REQUEST_URI
                    array_merge(
                        [
                            'action', 'controller', 'module', 'setVreasyJson', 'format', 'after',
                            'orderBy', 'orderDirection'
                        ],
                        ($id = $this->getRequest()->getParam('id'))
                        && strpos($requestUri, '/'.$id.'/')
                            ? ['id', $id]
                            : []
                    )
                )
            ));

            $links[] = '<'.$this->view->scheme().HTTP_HOST.
                (substr($requestUri, 0, strpos($requestUri, '?')) ?: $requestUri).
                '?'.$query.'>; '.
                'rel="prev"';
        }
        if ($nextAfter) {
            $requestUri = $this->getRequest()->getRequestUri();
            $query = http_build_query(array_diff_key(
                array_replace(
                    $this->getRequest()->getParams(),
                    [
                        'after' => $nextAfter
                    ]
                ),
                array_flip(
                    # Patch to clear the id parameter if it is included in the REQUEST_URI
                    array_merge(
                        [
                            'action', 'controller', 'module', 'setVreasyJson', 'format', 'before',
                            'orderBy', 'orderDirection'
                        ],
                        ($id = $this->getRequest()->getParam('id'))
                        && strpos($requestUri, '/'.$id.'/')
                            ? ['id', $id]
                            : []
                    )
                )
            ));

            $links[] = '<'.$this->view->scheme().HTTP_HOST.
                (substr($requestUri, 0, strpos($requestUri, '?')) ?: $requestUri).
                '?'.$query.'>; '.
                'rel="next"';
        }
        if ($links) {
            $this->getResponse()->setHeader('Link', implode(', ', $links), true);
        }
    }

    public function isLinkEnabled($opts = [])
    {
        $linkType = 'after';
        $before = $this->getParam('before');
        $after = $this->getParam('after');
        $total = 0;
        $limit = $this->getParam('limit', 100);
        extract(array_filter($opts), EXTR_IF_EXISTS);

        if ($before) {
            // When rewinding, check if there are items left to continue and allow forwarding
            return $linkType == 'before' ? $total == $limit : true;
        } elseif ($after) {
            // When forwarding, check if there are items left to continue and allow rewinding
            return $linkType == 'after' ? $total == $limit : true;
        } else {
            // When starting from the beginning only allow forwarding if there are items left to continue
            return $linkType == 'after' ? $total == $limit : false;
        }
    }

    /**
     * Negotiates the content using the given request object
     *
     * To add new content types, the type should be added as a variable `<type>Enabled` and
     * included in the `$formatsPriority` variable.
     * @see $jsonEnabled, $xmlEnabled, $formatsPriority
     * @param \Zend_Controller_Request_Abstract $request
     * @return string The final format to be used in the response
     */
    protected function getNegotiatedFormat($request)
    {
        $format = '';
        $priority = $this->formatsPriority;

        while (count($priority) && !$format) {
            $testFormat = array_shift($priority);
            $formatEnabled = $testFormat.'Enabled';
            if (@$this->$formatEnabled
                && false !== stripos($request->getHeader('Accept'), "/$testFormat")
            ) {
               $format  = $testFormat;
            }
        }

        $priority = $this->formatsPriority;
        while (count($priority) && !$format) {
            $testFormat = array_shift($priority);
            $formatEnabled = $testFormat.'Enabled';
            $acceptsAny = false !== stripos($request->getHeader('Accept'), '*/*');
            $noAcceptHeader = !trim($request->getHeader('Accept'));
            if (@$this->$formatEnabled && ($acceptsAny || $noAcceptHeader)) {
               $format  = $testFormat;
            }
        }
        return $format;
    }

    protected function getNamesOfArrayParams()
    {
        return [];
    }

    /**
     * Fixes the parameters when the values come in joined by commas
     */
    private function sanitizeArrayParams()
    {
        $request = $this->getRequest();
        $params = array_merge(
            ['order_by', 'order_direction'],
            (array) $this->getNamesOfArrayParams()
        );
        foreach ($params as $paramKey) {
            // In order to maintain backward compatibility with some of the old camel-cased
            // query parameters, these values are retrieved and sanitized BUT will be set into the
            // new snake-cased parameters.
            $paramVal = $request->getParam($paramKey)
                ?: $request->getParam(Inflector::camelize($paramKey));

            if ($paramVal) {
                $paramVal = is_array($paramVal) ? $paramVal : [$paramVal];
                $newParam = [];
                foreach ($paramVal as $field) {
                    $newParam = array_merge($newParam, explode(',', $field));
                }
                $request->setParam($paramKey, $newParam);
            }
        }
    }

    /**
     * Internally redirects a request made to the getAction with a param id="new"
     * directly to the newAction.
     * @return boolean true if the redirect is made, false otherwise.
     */
    protected function maybeForwardToNewAction()
    {
        $request = $this->getRequest();
        $isGetAction = ('get' == $request->getActionName());
        $idParamIsValueNew = $isGetAction && ('new' == strtolower($this->getParam('id')));
        if ($isGetAction && $idParamIsValueNew) {
            $request->setParam('id', null);
            $request->setParam('action', 'new');
            $this->forward('new');
            return true;
        }
        return false;
    }

    protected function maybeForwardToCustomAction()
    {
        $request = $this->getRequest();
        // When the URI contains the id of the resource before the custom action
        // (eg: /resource/123/custom-action) Zend builds the params like:
        //     [123 => 'custom-action']
        // Otherwise (eg: /resource/custom-action), the custom action name
        // will be assigned to the param 'id'.
        $actionName = $request->getParam('id', false);
        $id = null;
        if ($actionName === false) {
            $paramsKeys = array_keys($request->getParams());
            if ($numericKeys = array_filter($paramsKeys, 'is_numeric')) {
                $id = array_shift($numericKeys);
                $actionName = $request->getParam($id);
            }
        }
        if ($actionName) {
            $currentAction = $request->getActionName();
            if ($actionName != $currentAction) {
                if ($request->isGet() && 0 !== strpos($actionName, 'get-')) {
                    // Lets prepend the name with get if it's not there
                    $actionName = 'get-'.$actionName;
                }

                if (($request->isPost() || $request->isPut())
                    && 0 === strpos($actionName, 'get-')) {
                    // Avoid sending the wrong method to a GET-only controller action.
                    return false;
                }

                if (method_exists($this, $this->dashesToCamelCase($actionName).'Action')
                    && ($request->isGet() || $request->isPost() || $request->isPut())
                ) {
                    $request->setParam('action', $actionName);
                    $request->setParam('id', $id);
                    $this->forward($actionName);
                    return true;
                }

            }
        }
        return false;
    }

    /**
     * Tracks which of the give associations changed calling dirtyChange on them.
     *
     * If in the request body the association is being set, this method assumes that a change is
     * imminent. In order to avoid to stale the association, it should not be present in the body.
     *
     * For association's fields not present in the assocKeys, wont be able to see if changed or not.
     * Only the serialized and the regular attributes from the model will be tracked by default.
     *
     * @see Dirty::dirtyChange() To signal when a property is going to change
     *
     * @param  mixed $model The resource model
     * @param  string[] $assocKeys The list of associations that should be tracked for changes
     */
    protected function trackAssociationsAreDirty($model, $assocKeys = [])
    {
        $body = $this->getParam('body');
        foreach ($assocKeys as $assoc) {
            if (array_key_exists($assoc, $body)) {
                $model->dirtyChange($assoc);
            }
        }
    }

    /**
     * Clears the given associations of the model when are found as an empty key in the body.
     *
     * @param  mixed $model The resource model
     * @param  string[] $assocKeys The list of associations that should taken into account to be cleared
     */
    protected function clearAssociationsWhenEmpty($model, $assocKeys = [])
    {
        $body = $this->getParam('body');

        $modelCanClear = method_exists($model, 'clear');
        foreach ($assocKeys as $assoc) {
            $assocParamIsEmpty = isset($body[$assoc]) && !$body[$assoc];
            if ($assocParamIsEmpty && $modelCanClear) {
                $model->clear([$assoc => true]);
            }
        }
    }

    /**
     * Convert all appearances of the alias into the name on the input parameters
     *
     * It takes into account the use case when a "column" field of a model has been aliased with a
     * different name. For this it then searches for the alias in the names of query parameters
     * (search filters), the keys of the body (object attributes), the order_by & the fields (db layer)
     * and the resultant eagerLoadOptions variables (found after checking the preDispatch).
     *
     * @param  string $alias   The alias to search.
     * @param  string $name    The value to use instead of the alias.
     */
    public function convertAliasToName($alias, $name)
    {
        $params = $this->getRequest()->getParams();

        // Replacing all the keys of the parameters (GET or POST) will take care of the
        // search filters and body of a request
        $params = Arrays::replace_key_recursive($params, $alias, $name);

        // Other parameters have the alias as a val instead, so we have to find it and replace it
        foreach (['order_by', 'fields'] as $t) {
            if (is_array(@$params[$t])) {
                $params[$t] = array_map(
                    function($v) use($alias, $name) {
                        return $alias == $v ? $name : $v;
                    },
                    $params[$t]
                );
                $params[$t] = array_map(
                    function($v) use($alias, $name) {
                        // When it starts with the alias and followed by a slash
                        return 0 === stripos($v, "$alias/")
                            ? substr_replace($v, "$name/", 0, strlen("$alias/"))
                            : $v;
                    },
                    $params[$t]
                );
                $params[$t] = array_map(
                    function($v) use($alias, $name) {
                        // When the alias is in the middle
                        return false !== stripos($v, "/$alias/")
                            ? str_ireplace("/$alias/", "/$name/", $v)
                            : $v;
                    },
                    $params[$t]
                );
                $params[$t] = array_map(
                    function($v) use($alias, $name) {
                        // When the alias is at the end
                        return (strlen($v) - strlen("/$alias")) === stripos($v, "/$alias")
                            ? substr_replace($v, "/$name", strlen($v) - strlen("/$alias"))
                            : $v;
                    },
                    $params[$t]
                );
            }
        }

        foreach ($params as $key => $value) {
            $this->getRequest()->setParam($key, $value);
        }

        if (array_key_exists('fields', $this->eagerLoadOptions)) {
            $this->eagerLoadOptions['fields'] = array_map(
                function($v) use($alias, $name) {
                    return $alias == $v ? $name : $v;
                },
                @$this->eagerLoadOptions['fields'] ?: []
            );
        }

    }

    protected function getExpandedLinks()
    {
        $expand = $this->getParam('expand');
        if (!$expand && ($body = $this->getParam('body')) && is_array($body)) {
            if (array_key_exists('expand', $body)) {
                $expand = $body['expand'];
            }
        }
        if ($expand) {
            return @explode(',', "$expand") ?: [];
        } else {
            return [];
        }
    }

    protected function getIncludedFields()
    {
        if ($fields = $this->getParam('fields')) {
            return (@explode(',', "$fields")) ?: [];
        } else {
            return ['*'];
        }
    }

    protected function getIncludedFieldsFromExpand()
    {
        if (['*'] == $this->getIncludedFields()) {
            $expandedFieldRules = ['*'];
            foreach (@$this->eagerLoadOptions['including'] ?: [] as $expandItem) {
                $loadingItems = explode('/', $expandItem);
                array_pop($loadingItems);
                foreach ($loadingItems as $i => $item) {
                    $expandedFieldRules[] = implode('/', array_slice($loadingItems, 0, $i+1)).'/*';
                }
            }
            return array_merge($expandedFieldRules, @$this->eagerLoadOptions['including'] ?: []);
        } else {
            return @$this->eagerLoadOptions['including'] ?: [];
        }
        return @$this->eagerLoadOptions['including'] ?: [];
    }

    public function getImmutableAssociations()
    {
        return (array) $this->immutableAssociations ?: [];
    }

    /**
     * The index action handles index/list requests; it should respond with a
     * list of the requested resources.
     */
    public function indexAction()
    {
        $this->respondNotFound();
    }

    /**
     * The get action handles GET requests and receives an 'id' parameter; it
     * should respond with the server resource state of the resource identified
     * by the 'id' value.
     */
    public function getAction()
    {
        $this->respondNotFound();
    }

    /**
     * The new action handles GET requests to :resource/new and could receive no parameters;
     * it should respond with a new instance of the resource
     */
    public function newAction()
    {
        $this->respondNotFound();
    }

    /**
     * The post action handles POST requests; it should accept and digest a
     * POSTed resource representation and persist the resource state.
     */
    public function postAction()
    {
        $this->respondNotFound();
    }

    /**
     * The put action handles PUT requests and receives an 'id' parameter; it
     * should update the server resource state of the resource identified by
     * the 'id' value.
     */
    public function putAction()
    {
        $this->respondNotFound();
    }

    /**
     * The delete action handles DELETE requests and receives an 'id'
     * parameter; it should update the server resource state of the resource
     * identified by the 'id' value.
     */
    public function deleteAction()
    {
        $this->respondNotFound();
    }

    public function headAction()
    {
        $this->respondNotFound();
    }

    protected function respondNotAcceptable($message = 'Not acceptable')
    {
        throw new \Zend_Controller_Action_Exception($message, 406);
    }

    protected function respondNotFound($message = 'Resource not found')
    {
        throw new \Zend_Controller_Action_Exception($message, 404);
    }

    protected function respondBadRequest($errors = null)
    {
        if ($errors) {
            $this->view->errors = $errors;
        }
        $this->getResponse()->setHttpResponseCode(400);
    }

    protected function respondUnprocessableEntity($errors = null)
    {
        if ($errors) {
            $this->view->errors = $errors;
        }
        $this->getResponse()->setHttpResponseCode(422);
    }

    protected function respondUnauthorized($realm = '')
    {
        $realm = $realm ?: 'Vreasylogin realm="https://'.HTTP_HOST.'"';
        $this->getResponse()->setHeader(
            'WWW-Authenticate',
            $realm
        );
        throw new \Zend_Controller_Action_Exception('Unauthorized: invalid credentials', 401);
    }

    protected function respondForbidden()
    {
        throw new \Zend_Controller_Action_Exception(
            'Unauthorized: resource not available for the credentials provided',
            403
        );
    }

    protected function respondCreated($resource)
    {
        $this->getResponse()->setHttpResponseCode(201);
        $this->view->resource = $resource;
    }

    protected function respondNotImplemented()
    {
        throw new \Zend_Controller_Action_Exception('Not implemented', 501);
    }

    protected function respondNoContent()
    {
        $this->getResponse()->setHttpResponseCode(204);
        $this->view->resource = '';
    }

    protected function respondServiceUnavailable()
    {
        throw new \Zend_Controller_Action_Exception('Service unavailable', 503);
    }

    public function searchParams($arrayOrObjectOrClass, $options = [])
    {
        $allAttributes = [];
        if (is_array($arrayOrObjectOrClass)) {
            $allAttributes = $arrayOrObjectOrClass;
        } elseif (is_string($arrayOrObjectOrClass) || is_object($arrayOrObjectOrClass)) {
            $allAttributes = method_exists($arrayOrObjectOrClass, 'attributeNames')
                ? call_user_func([$arrayOrObjectOrClass, 'attributeNames'])
                : [];
        }

        $searchParams = [];
        $supportedLabels = ['or', 'and'];
        $searchableAttributes = $allAttributes;

        // Remove unsearchable attributes
        if (array_key_exists('blacklist', $options) && $blacklist = $options['blacklist']) {
            $searchableAttributes = array_diff($searchableAttributes, $blacklist);
        }
        $searchableAttributes = array_flip($searchableAttributes);

        $params = \Vreasy\UriLabelExpander::expand($this->getRequest()->getParams(), '_');
        foreach ($supportedLabels as $label) {
            if (isset($params[$label])) {
                $searchParams[$label] = array_intersect_key($params[$label], $searchableAttributes);
            }
        }
        $withoutLabels = array_diff_key($params, array_flip($supportedLabels));
        $withoutLabels = array_intersect_key($withoutLabels, $searchableAttributes);
        $searchParams = array_merge(
            $searchParams,
            $withoutLabels
        );
        return $searchParams;
    }

    public function __call($name, $args)
    {
        $invokable = @$this->$name;
        if ($invokable instanceof \Closure || method_exists($invokable, '__invoke')) {
            return call_user_func_array($invokable, $args);
        }

        return $this->__callForwardable($name, $args);
    }

    private function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }
        return $str;
    }
}
