<?php

namespace Vreasy\Models\Traits;

use Vreasy\DateTime;

/**
 * Track creation and update times
 *
 * ## Timestamps
 *
 * Use it to hold the last time when an object changes. It adds two `created_at` and `updated_at`
 * properties that are managed by the `Base#hasDate` compositional method.
 *
 * When used in conjunction with the `Persistence` trait, it also hooks into the `beforeInsert` and
 * `beforeUpdate` callbacks. So if your class needs to make use of these, be sure to rename it when
 * using the trait and call these methods inside the ones you've overwritten.
 *
 */
trait Timestampable
{
    /**
     * @var DateTime
     */
    protected $created_at;

    /**
     * @var DateTime
     */
    protected $updated_at;

    protected function __construct()
    {
        $this->hasDate('created_at');
        $this->hasDate('updated_at');
    }

    public function beforeInsert()
    {
        $now = gmdate(DATE_FORMAT);
        $this->created_at = $now;
        $this->updated_at = $now;
    }

    public function beforeUpdate()
    {
        $this->updated_at = gmdate(DATE_FORMAT);
    }
}
