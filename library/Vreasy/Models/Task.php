<?php

namespace Vreasy\Models;

use Vreasy\Query\Builder;
use Vreasy\Models\Traits\Persistence;
use Vreasy\Models\Traits\Timestampable;

class Task extends Base
{
    use Persistence;

    use Timestampable {
        Timestampable::__construct as __constructTimestamp;
    }

    // Protected attributes should match table columns
    protected $id;
    protected $deadline;
    protected $assigned_name;
    protected $assigned_phone;

    public function __construct()
    {
        $this->__constructTimestamp();

        // Validation is done run by Valitron library
        $this->validates(
            'required',
            ['deadline', 'assigned_name', 'assigned_phone']
        );
        $this->validates(
            'integer',
            ['id']
        );
    }
}
