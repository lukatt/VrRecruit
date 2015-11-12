<?php

namespace Vreasy\Zend\Plugins;

use Vreasy\ConsumerApiKey;

final class Cors extends \Zend_Controller_Plugin_Abstract
{
    protected $exposeHeaders = false;
    public $whitelist = [];

    public function preDispatch(\Zend_Controller_Request_Abstract $request)
    {
        try {
            if ($origin = $request->getHeader('Origin')) {
                if (!$this->isOriginAllowed($origin)) {
                    $this->getResponse()->clearBody();
                    if ($request->isOptions()) {
                        $this->getResponse()->setHeader(
                            'Access-Control-Allow-Origin',
                            'null',
                            true
                        );
                        $this->respondInvalidOrigin();
                    } else {
                        $this->getResponse()->setHeader(
                            'Access-Control-Allow-Origin',
                            'null',
                            true
                        );
                        $this->respondForbidden();
                    }
                } else {
                    if ($request->isOptions()) {
                        $this->getResponse()->setHeader(
                            'Access-Control-Max-Age',
                            2592000,
                            true
                        );
                        $this->getResponse()->setHeader(
                            'Access-Control-Allow-Methods',
                            'OPTIONS, GET, HEAD, POST, PUT, DELETE, CONNECT, TRACE',
                            true
                        );
                        if ($reqHeaders = $request->getHeader('Access-Control-Request-Headers')) {
                            $this->getResponse()->setHeader(
                                'Access-Control-Allow-Headers',
                                $request->getHeader('Access-Control-Request-Headers'),
                                true
                            );
                        }
                    } else {
                        $this->exposeHeaders = true;
                    }

                    $this->getResponse()->setHeader(
                        'Access-Control-Allow-Credentials',
                        'true',
                        true
                    );
                    $this->getResponse()->setHeader(
                        'Access-Control-Allow-Origin',
                        $this->normalizeUri($origin),
                        true
                    );
                }
            }
        } catch(\Exception $e) {
            $error = new \ArrayObject([], \ArrayObject::ARRAY_AS_PROPS);
            $error->exception = $e;
            $error->type = \Zend_Controller_Plugin_ErrorHandler::EXCEPTION_OTHER;
            $error->request = clone($request);
            $request->setParam('error_handler', $error);
            $this->getRequest()
            ->setControllerName('error')
            ->setActionName('error')
            ->setDispatched(true);
        }
    }

    public function postDispatch(\Zend_Controller_Request_Abstract $request)
    {
        if ($this->exposeHeaders) {
            $this->exposeHeaders = false;
            $blacklist = [
                'set-cookie',
                'access-control-allow-credentials',
                'access-control-allow-origin',
                'access-control-expose-headers'
            ];
            $headerNames = [];
            foreach ($this->getResponse()->getHeaders() as $zendHeader) {
                if ($header = @$zendHeader['name']) {
                    $headerNames[] = $header;
                }
            }
            $headerNames = array_merge(
                $headerNames,
                (explode(',', $request->getHeader('Access-Control-Request-Headers')) ?: [])
            );
            $headerNames = array_map('trim', $headerNames);
            $headerNames = array_map('strtolower', $headerNames);
            if ($headerNames = array_diff(array_unique(array_filter($headerNames)), $blacklist)) {
                $this->getResponse()->setHeader(
                    'Access-Control-Expose-Headers',
                    implode(', ', $headerNames),
                    true
                );
            }
        }
    }

    protected function normalizeUri($origin)
    {
        $uri = parse_url($origin);
        if (isset($uri['scheme']) && isset($uri['host'])) {
            $url = $uri['scheme'] . '://' . $uri['host'];
            $url .= (@$uri['port'] ? ':' . $uri['port'] : '');
        }
        return isset($url) ? $url : 'null';
    }

    protected function isOriginAllowed($origin)
    {
        $uri = parse_url($origin);
        $origins = [];
        $origins[] = @$uri['scheme'];
        $origins[] = @$uri['host'];

        $url = '';
        if (isset($uri['scheme']) && isset($uri['host'])) {
            $url = $uri['scheme'] . '://' . $uri['host'];
            $url .= (@$uri['port'] ? ':' . $uri['port'] : '');
            $origins[] = $url;
        }
        $isWhitelisted = array_intersect($this->whitelist, $origins);
        if (!$isWhitelisted && isset($uri['scheme']) && isset($uri['host'])) {
            $consumers = ConsumerApiKey::where(
                ['active' => 1, 'url' => '#{LIKE \''.$url.'%\'}'],
                ['limit' => 1]
            );
            return (bool) $consumers;
        }
        return $isWhitelisted;
    }

    protected function respondInvalidOrigin()
    {
        throw new \Zend_Controller_Action_Exception(
            'Invalid Origin: the origin sent is not allowed',
            204
        );
    }

    protected function respondForbidden()
    {
        throw new \Zend_Controller_Action_Exception(
            'Unauthorized: resource not available for the credentials provided',
            403
        );
    }
}
