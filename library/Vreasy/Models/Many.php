<?php

namespace Vreasy\Models;

class Many extends Collection implements \IteratorAggregate
{
    public function offsetUnset($i)
    {
        parent::offsetUnset($i);
        $this->reindexStorage();
    }

    private function reindexStorage()
    {
        $tmpArray = [];
        foreach ($this as $item) {
            $tmpArray[] = $item;
        }
        $this->exchangeArray($tmpArray);
    }

    public function getCollection()
    {
        return $this->getArrayCopy();
    }
}
