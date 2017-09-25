<?php

namespace CarlBennett\TS3Bot\Libraries;

use \CarlBennett\MVC\Libraries\Term;
use \CarlBennett\TS3Bot\Libraries\Command;
use \CarlBennett\TS3Bot\Libraries\Commands\Ping as PingCommand;
use \CarlBennett\TS3Bot\Libraries\Common;
use \TeamSpeak3 as TS3;
use \TeamSpeak3_Adapter_ServerQuery_Event as TS3Event;
use \TeamSpeak3_Exception as TS3Exception;
use \TeamSpeak3_Helper_Signal as TS3Signal;
use \TeamSpeak3_Helper_String as TS3String;
use \TeamSpeak3_Node_Host as TS3Host;

class Bot {

    const COMMAND_TRIGGER = '!';

    public static $commands = array();
    public static $ts3      = null;

    public static function connect() {
        $uri = Common::$config->connection->uri;

        try {
            Term::stdout('Connecting to TeamSpeak 3 ServerQuery...' . PHP_EOL);
            self::$ts3 = TS3::factory( $uri );
            Term::stdout('Connected to TeamSpeak 3 ServerQuery' . PHP_EOL);
        } catch (TS3Exception $err) {
            self::$ts3 = null;
            Term::stderr(
                'Failed to connect to TeamSpeak 3 ServerQuery' . PHP_EOL
            );
            Term::stderr((string) $err . PHP_EOL);
            return false;
        }

        return true;
    }

    public static function disconnect() {
        if (isset(self::$ts3)) {
            self::$ts3 = null;
        }
    }

    public static function handleCommand($audience, $id, $uid, $name, $msg) {
        $triggers = array(self::COMMAND_TRIGGER);

        $trigger_found = false;
        $text          = null;
        $segment       = null;
        $command       = null;
        $arguments     = null;

        foreach ($triggers as $trigger) {
            if (substr($msg, 0, strlen($trigger)) == $trigger) {
                $trigger_found = true;
                break;
            }
        }

        if (!$trigger_found) {
            return;
        }

        $text     = substr($msg, strlen($trigger));
        $segments = explode(';', $text);

        foreach ($segments as $segment) {
            $delimiter = strpos($segment, ' ');

            if ($delimiter === false) {
                $command   = $segment;
                $arguments = '';
            } else {
                $command   = substr($segment, 0, $delimiter);
                $arguments = substr($segment, 1 + $delimiter);
            }
        }

        $command_object = self::matchCommand($command);

        if (!$command_object instanceof Command) {
            Term::stderr(sprintf(
                'Command not found [%s]' . PHP_EOL, $command
            ));
            return;
        }

        $client = self::$ts3->clientGetById($id);
        $command_object->invoke($client, $arguments);
    }

    protected static function matchCommand($command) {
        foreach (self::$commands as $object) {
            if ($object->match($command)) {
                return $object;
            }
        }
        return null;
    }

    public static function notifyEvent(TS3Event $event, TS3Host $host) {
        $type = $event->getType();
        $data = $event->getData();

        switch ($type) {
            case 'textmessage': {
                Term::stdout(sprintf(
                    '[Chat] <id:%d %s> %s' . PHP_EOL,
                    (int)    $data['invokerid'],
                    (string) $data['invokername'],
                    (string) $data['msg']
                ));
                Bot::handleCommand(
                    (int)    $data['targetmode'],
                    (int)    $data['invokerid'],
                    (string) $data['invokeruid'],
                    (string) $data['invokername'],
                    (string) $data['msg']
                );
                break;
            }
            default: {
                Term::stderr(sprintf(
                    'Received unknown event [%s]' . PHP_EOL, $type
                ));
            }
        }
    }

    public static function registerCommands() {
        self::$commands[] = new PingCommand();
    }

    public static function registerEvents() {
        self::$ts3->notifyRegister('server');
        self::$ts3->notifyRegister('textserver');
        self::$ts3->notifyRegister('textprivate');

        $callback = __CLASS__ . '::notifyEvent';

        TS3Signal::getInstance()->subscribe( 'notifyTextmessage', $callback );
    }

    public static function setNickname($nickname) {
        Term::stdout(sprintf(
            'Setting bot nickname to [%s]' . PHP_EOL, $nickname
        ));
        self::$ts3->setPredefinedQueryName($nickname);
        /*$str = new TS3String($nickname);
        $cmd = 'clientupdate client_nickname=' . $str->escape();
        if (self::$ts3->execute($cmd)) {
            return true;
        } else {
            return false;
        }*/
    }

    public static function waitForEvents() {
        self::$ts3->getAdapter()->wait();
    }

}
