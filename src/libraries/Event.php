<?php

namespace CarlBennett\TS3Bot\Libraries;

/**
 * Behaves as logic for firing events based on timed intervals.
 */
abstract class Event {

   /**
    * Identification of this event.
    * This value cannot be changed after construction of the object.
    * @var string
    */
    private $id;

   /**
    * Expressed as [seconds.microseconds].
    * @var float
    */
    private $interval;

   /**
    * A local time cache.
    * @var float
    */
    private $invoke_time_offset;

   /**
    * Constructs an event emitter.
    * This class must not be created directly but as a parent to a child class,
    * due to the invoke() call that requires definition.
    * @param $id         (string) The identification for this event.
    * @param $interval   (float)  Sets the interval in [seconds.microseconds].
    * @param $early_emit (bool)   Elapse now or elapse after now + $interval.
    */
    public function __construct($id, $interval, $early_emit) {
        $this->id                 = $id;
        $this->interval           = $interval;
        $this->invoke_time_offset = microtime(true)
                                    - ( $early_emit ? $interval : 0 );
    }

   /**
    * Resets the internal time offset.
    * This function should only be called by child classes.
    */
    protected function _invoke() {
        $this->invoke_time_offset = microtime(true);
    }

   /**
    * Retrieves the identification for this event.
    * @return string
    */
    public function getId() {
        return $this->id;
    }

   /**
    * Retrieves the current interval.
    * @return float
    */
    public function getInterval() {
        return $this->interval;
    }

   /**
    * Fires the event.
    */
    abstract public function invoke();

   /**
    * Sets the interval.
    * @param $interval (float) Sets the interval in [seconds.microseconds].
    */
    public function setInterval($interval) {
        $this->interval = $interval;
    }

   /**
    * Returns if the interval has elapsed since the last invoke() call.
    * @return bool
    */
    public function intervalElapsed() {
        return (
            $this->invoke_time_offset + $this->interval >= microtime(true)
        );
    }

}
