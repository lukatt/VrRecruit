<?php

namespace Vreasy\Models\Traits;

/**
 * Provides a way to track changes in your object.
 *
 * ## Dirty Changes
 *
 * Use it to track changes in your object and know if the object has been modified, and what changed.
 * It also provides methods to integrate with a persistence layer and track changes after storing the
 * object.
 *
 * ### Requirements
 *
 * - ```use Dirty``` in your class.
 * - Call ```setPropertiesToTrack``` passing each property name you want to track.
 * - Call ```dirtyChange($name)``` **before** each change to the tracked property.
 * - Call ```changesApplied``` **after** the changes are persisted.
 * - Call ```resetChanges``` when you want to remove trace of the changes.
 *
 * ### Examples
 *
 * A minimal implementation could be:
 *
 * ```php
 * class Reservation
 * {
 *     use Dirty;
 *
 *     protected $checkin;
 *
 *     public function __construct()
 *     {
 *         $this->setPropertiesToTrack(['checkin']);
 *     }
 *
 *     public function setCheckin($date)
 *     {
 *         $this->dirtyChange('checkin');
 *         $this->checkin = $date;
 *     }
 *
 *     public function save()
 *     {
 *         // Do something to persist your object, and after...
 *         $this->changesApplied();
 *     }
 *
 * }
 * ```
 *
 * In the wild you could use it like so:
 *
 * ```php
 * // This is an example
 * $reservation = Reservation::find(['checkin' => '2014-01-01 12:12:00']);
 * $reservation->dirtyChange('checkin');
 * $reservation->checkin = '2015-02-02 13:13:00';
 * $reservation->propertyDidChange('checkin'); # => true
 * $reservation->getLastChangeFor('checkin'); # => '2014-01-01 12:12:00'
 * ```
 */
trait Dirty
{
    // TODO: Move tests from BaseTest into a DirtyTest file
    // TODO: Move Base tests to dirty tests
    // TODO: Write more docs about how to use dirty outside of Base

    /**
     * Change this flag in your object to stop/start tracking changes. By default is true.
     * @var boolean
     */
    public $dirtyTrackChanges = true;

    /**
     * Change this flag in your class to stop/start tracking changes for all its instances. By default is true.
     * @var boolean
     */
    public static $dirtyTrackChangesClass = true;

    /**
     * Collection of properties names to track changes on.
     *
     * By default it is set to `self::TRACK_ALL` so the changes of all the properties will be tracked.
     * @var boolean|string[]
     */
    protected $dirtyPropertiesToTrack = true;

    /**
     * Collection that holds the current changes of the tracked properties.
     * @var mixed[]
     */
    protected $dirtyChanges = [];

    /**
     * Collection that holds the previous changes of the tracked properties.
     * @var mixed[]
     */
    protected $dirtyPreviousChanges = [];

    /**
     * Allows to define a list of properties that will be tracked for changes.
     * By default it tracks the changes of all the object's properties.
     *
     * @param string[] $props A list of names of the properties to track
     */
    public function setPropertiesToTrack($props = [])
    {
        $this->dirtyPropertiesToTrack = [];
        foreach ($props as $p) {
            $this->dirtyPropertiesToTrack[] = $p;
        }
    }

    /**
     * Acknoledges about a possible change to be made in a property.
     *
     * @param string $name The name of the property to be changed
     */
    public function dirtyChange($name)
    {
        if (static::$dirtyTrackChangesClass
            && $this->dirtyTrackChanges
            && (true === $this->dirtyPropertiesToTrack
                || in_array($name, $this->dirtyPropertiesToTrack))
        ) {

            // Make sure that the value did changed from the last time
            if (array_key_exists($name, $this->dirtyChanges)) {
                $lastValue = end($this->dirtyChanges[$name]);
                reset($this->dirtyChanges[$name]);
            }

            if (!isset($lastValue)
                // || (is_object($lastValue) ? $lastValue != $this->$name : $lastValue !== $this->$name)
                || $this->propertyDidChange($name)
            ) {
                // Track the value before it changes
                $this->dirtyChanges[$name][] = is_object($this->$name)
                    ? (clone $this->$name)
                    : $this->$name;
            }
        }
    }

    /**
     * Gets the last known value for a property.
     *
     * @param string $name The name of the property
     * @param [] $changes (optional) The collection of the changes where to look. When `null` uses the current changes (not the previous ones).
     * @return mixed The value of the last change.
     */
    public function getLastChangeFor($name, &$changes = null)
    {
        if (is_null($changes)) {
            $changes = &$this->dirtyChanges;
        }

        if (array_key_exists($name, $changes) && !empty($changes[$name])) {
            reset($changes[$name]);
            $lastValue = end($changes[$name]);
            while ((is_object($lastValue) ? $lastValue == $this->$name : $lastValue === $this->$name)
                && 0 !== key($changes[$name])
            ) {
                $lastValue = prev($changes[$name]);
            }
            reset($changes[$name]);

            if (is_object($lastValue) ? $lastValue == $this->$name : $lastValue === $this->$name) {
                return null;
            } else {
                return $lastValue;
            }
        }
    }

    /**
     * Gets the last known value for a property after its changes were applied.
     *
     * @param string $name The name of the property
     * @return mixed The value of the last previous change.
     */
    public function getPreviousChangeFor($name)
    {
        return $this->getLastChangeFor($name, $this->dirtyPreviousChanges);
    }

    /**
     * Asks to see if at least one property value changed.
     *
     * @param [] $changes (optional) The collection of the changes where to look. When `null` uses the current changes (not the previous ones).
     * @return boolean true if the object had a tracked change.
     */
    public function didChange(&$changes = null)
    {
        if (is_null($changes)) {
            $changes = &$this->dirtyChanges;
        }

        foreach (array_keys($changes) as $name) {
            if ($this->propertyDidChange($name, $changes)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Asks to see if at least one property value changed after its changes were applied.
     * @return boolean true if the object had a previous tracked change.
     */
    public function previouslyDidChange()
    {
        return $this->didChange($this->dirtyPreviousChanges);
    }

    /**
     * Asks to see if the property value differs from the last one tracked.
     *
     * @param string $name The name of the property
     * @param [] $changes (optional) The collection of the changes where to look. When `null` uses the current changes (not the previous ones).
     * @return boolean true if the property changed.
     */
    public function propertyDidChange($name, &$changes = null)
    {
        if (is_null($changes)) {
            $changes = &$this->dirtyChanges;
        }

        if (array_key_exists($name, $changes)) {
            // Compare the last tracked value against the current one.
            reset($changes[$name]);
            $lastValue = end($changes[$name]);
            reset($changes[$name]);
            return is_object($lastValue)
                ? $lastValue != $this->$name
                : $lastValue !== $this->$name;
        } else {
            return false;
        }
    }

    /**
     * Asks to see if the property value differs from the last one tracked after its changes were applied.
     *
     * @param string $name The name of the property
     * @return boolean true if the property previously changed.
     */
    public function propertyPreviouslyDidChange($name)
    {
        return $this->propertyDidChange($name, $this->dirtyPreviousChanges);
    }

    /**
     * Moves the current changes to the previous changes.
     */
    public function changesApplied()
    {
        $this->dirtyPreviousChanges = $this->dirtyChanges;
        $this->dirtyChanges = [];
    }

    /**
     * Removes the current changes and the previous ones.
     */
    public function resetChanges()
    {
        $this->dirtyPreviousChanges = [];
        $this->dirtyChanges = [];
    }

    public function restoreChanges(&$changes = null)
    {
        if (is_null($changes)) {
            $changes = &$this->dirtyChanges;
        }

        foreach (array_keys($changes) as $name) {
            reset($changes[$name]);
            $this->$name = current($changes[$name]);
        }

        $this->resetChanges();
    }

    public function restorePreviousChanges()
    {
        $this->restoreDirtyChanges($this->dirtyPreviousChanges);
    }
}
