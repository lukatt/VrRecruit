<?php

use Vreasy\Models\Base;
use Vreasy\Models\Task;
use Vreasy\Utils\Arrays;
use Vreasy\Presenters\Json\Task as TaskJson;

class Api_TasksController extends Vreasy_Rest_Controller
{
    public function preDispatch()
    {
        parent::preDispatch();

        if ($id = $this->getParam('id')) {
           $this->task = current(Task::where(['id' => $id]));
            if (!$this->task) {
                $this->respondNotFound();
            }
        }
    }

    public function indexAction()
    {
        $this->tasks = Task::where(
            $this->getFixedSearchParams() ?: ['id' => '!NULL'],
            [
                'scopes' => [
                    // The After & Before scopes allows to transverse a collection of model items
                    // in batches of `limit`. E.g. for pagination of an API
                    'after' => $this->getParam('after'),
                    'before' => $this->getParam('before'),
                ],
                'limit' => $this->getParam('limit', 100),
            ]
        );

        $this->view->tasks = array_map(
            function($p){ return new TaskJson($p); },
            $this->tasks
        );
    }

    public function getAction()
    {
        $this->view->task = new TaskJson($this->task);
        Task::eagerLoad([$this->task], $this->eagerLoadOptions);
    }

    public function postAction()
    {
        $this->task = new Task();
        Task::hydrate($this->task, $this->getTaskAttributes());
        if ($this->task->save()) {
            $this->view->task = new TaskJson($this->task);
        } else {
            $this->respondUnprocessableEntity($this->task->errors());
        }
    }

    public function putAction()
    {
        Task::hydrate($this->task, $this->getTaskAttributes());
        if ($this->task->save()) {
            $this->view->task = new TaskJson($this->task);
        } else {
            $this->respondUnprocessableEntity($this->task->errors());
        }
    }

    /**
     * Returns the request body parameters curated
     *
     * @return []
     */
    private function getTaskAttributes()
    {
        $allowed = [
            'deadline',
            'assigned_name',
            'assigned_phone',
            'status',
            'responded_at',
            'finished_at',
        ];

        return Arrays::intersect_key_recursive(
            $this->getParam('body') ?: [],
            array_flip($allowed)
        );
    }

    /**
     * Builds a `where` compatible array to search for db records
     *
     * Using the "search-able" fields declared inside and the query parameters of the request,
     * it gets a query to be used in the a model's `where` method.
     *
     * @return [] A where-compatible array @see Vreasy\Query\Builder
     */
    private function getFixedSearchParams()
    {
        return $this->searchParams(['assigned_name', 'assigned_phone']);
    }
}
