<?php

namespace Vreasy\Tests;

use Guzzle\Common\Event;
use Guzzle\Common\Exception\InvalidArgumentException;
use Guzzle\Common\AbstractHasDispatcher;
use Guzzle\Http\Exception\CurlException;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use Guzzle\Http\Message\Response;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Guzzle\Plugin\Mock\MockPlugin;

/**
 * Mocks all responses or exceptions with a single response.
 */
class MockAllRequestsPlugin extends MockPlugin
{
    /**
     * Clear the queue
     *
     * @return MockPlugin
     */
    public function clearQueue()
    {
        return $this;
    }

    /**
     * Get a response from the front of the list and add it to a request
     *
     * @param RequestInterface $request Request to mock
     *
     * @return self
     * @throws CurlException When request.send is called and an exception is queued
     */
    public function dequeue(RequestInterface $request)
    {
        $this->dispatch('mock.request', array('plugin' => $this, 'request' => $request));

        $item = end($this->queue);
        if ($item instanceof Response) {
            if ($this->readBodies && $request instanceof EntityEnclosingRequestInterface) {
                $request->getEventDispatcher()->addListener('request.sent', $f = function (Event $event) use (&$f) {
                    while ($data = $event['request']->getBody()->read(8096));
                    // Remove the listener after one-time use
                    $event['request']->getEventDispatcher()->removeListener('request.sent', $f);
                });
            }
            $request->setResponse($item);
        } elseif ($item instanceof CurlException) {
            // Emulates exceptions encountered while transferring requests
            $item->setRequest($request);
            $state = $request->setState(RequestInterface::STATE_ERROR, array('exception' => $item));
            // Only throw if the exception wasn't handled
            if ($state == RequestInterface::STATE_ERROR) {
                throw $item;
            }
        }

        return $this;
    }
}
