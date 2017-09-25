<?php

namespace CarlBennett\TS3Bot\Libraries;

use \TeamSpeak3_Node_Client as TS3Client;

abstract class Command {

    abstract public function invoke(TS3Client $client, $arguments);
    abstract public function match($command);

}
