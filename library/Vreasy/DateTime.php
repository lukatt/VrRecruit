<?php

namespace Vreasy;

class DateTime extends \DateTime implements \JsonSerializable
{
    public $toStringFormat = DATE_FORMAT;

    public function __construct($time = "now", \DateTimeZone $timezone = null)
    {
        if ($time instanceof \DateTime) {
            $timezone = $timezone ?: $time->getTimeZone();
            $time = $time->format(DATE_FORMAT);
        }
        parent::__construct($time, $timezone);
    }

    public function getHours()
    {
        return (int) $this->format('H');
    }

    public function getMinutes()
    {
        return (int) $this->format('i');
    }

    public function getSeconds()
    {
        return (int) $this->format('s');
    }

    public function __toString()
    {
        return $this->format($this->toStringFormat ?: DATE_FORMAT);
    }

    public function jsonSerialize()
    {
        return $this->__toString();
    }
}
