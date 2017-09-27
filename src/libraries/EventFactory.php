<?php

namespace CarlBennett\TS3Bot\Libraries;

use \CarlBennett\TS3Bot\Libraries\Event;
use \SplObjectStorage;

/**
 * Behaves as storage for registering Event objects to.
 */
class EventFactory extends SplObjectStorage {

   /**
    * Retrieves the Event by its identification string or null if not found.
    * @param $event_id (string) The identification for the event.
    * @return Event
    */
    public function getById( $event_id ) {
        $this->rewind();

        while ( $this->valid() ) {
            $object = Event( $this->current() );

            if ( $object->getId() === $event_id ) {
                return $object;
            }

            $this->next();
        }

        return null;
    }

}
