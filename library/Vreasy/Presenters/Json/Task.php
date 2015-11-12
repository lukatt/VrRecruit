<?php

namespace Vreasy\Presenters\Json;

use Vreasy\PresenterWithTranslation;
use Vreasy\Presenters\Traits\AutoloadAssociationPresenters;

class Task extends PresenterWithTranslation
{
    use AutoloadAssociationPresenters;

    protected $hiddenAttributes = [];

    public function presentCreatedAt()
    {
        return (string) $this->getObject()->created_at;
    }

    public function presentUpdatedAt()
    {
        return (string) $this->getObject()->updated_at;
    }
}
