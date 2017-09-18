<?php

  if (php_sapi_name() != "cli") {
    http_response_code(503);
    header("Cache-Control: max-age=0, must-revalidate, no-cache, no-store");
    header("Content-Type: text/plain;charset=utf-8");
    echo "This file is intended to be executed via php's command line.";
    exit(1);
  }

  if ($argc > 1) {
    echo "Usage: php -f " . $argv[0] . "\n";
    exit(1);
  }

  global $_CONFIG;
  $_CONFIG = json_decode(
    file_get_contents("./teamspeak-automation.json"),
    true
  );

  final class Logger {

    const FILENAME    = "/var/log/teamspeak-automation.log";
    const DATE_FORMAT = "Y-m-d H:i:s O";
    
    const STYLE_NORMAL      = "\e[0;0m";
    const STYLE_BLACK       = "\e[0;30m";
    const STYLE_DARKGRAY    = "\e[1;30m";
    const STYLE_LIGHTGRAY   = "\e[0;29m";
    const STYLE_WHITE       = "\e[1;29m";
    const STYLE_DARKRED     = "\e[0;31m";
    const STYLE_LIGHTRED    = "\e[1;31m";
    const STYLE_DARKGREEN   = "\e[0;32m";
    const STYLE_LIGHTGREEN  = "\e[1;32m";
    const STYLE_DARKYELLOW  = "\e[0;33m";
    const STYLE_LIGHTYELLOW = "\e[1;33m";
    const STYLE_DARKBLUE    = "\e[0;34m";
    const STYLE_LIGHTBLUE   = "\e[1;34m";
    const STYLE_DARKPURPLE  = "\e[0;35m";
    const STYLE_LIGHTPURPLE = "\e[1;35m";
    const STYLE_DARKTEAL    = "\e[0;36m";
    const STYLE_LIGHTTEAL   = "\e[1;36m";

    private static $fileHandle;

    public static function openLog() {
      self::$fileHandle = fopen(self::FILENAME, "w");
    }

    public static function closeLog() {
      fclose(self::$fileHandle);
    }

    public static function logOpened() {
      return is_resource(self::$fileHandle);
    }

    public static function write($use_timestamp, $message) {
      if ($use_timestamp) {
        $timestamp = self::STYLE_WHITE . "[" . date(self::DATE_FORMAT) . "] " . self::STYLE_NORMAL;
        self::write(false, $timestamp);
      }
      if (!self::logOpened()) {
        self::openLog();
      }
      if (self::logOpened()) {
        fwrite(self::$fileHandle, $message);
      }
      echo $message;
    }

    public static function writeLine($use_timestamp, $message) {
      self::write($use_timestamp, $message . "\n" . self::STYLE_NORMAL);
    }

  }

  final class Timer {

    private $start;

    public function __construct() {
      $this->setStart();
    }

    public function setStart() {
      $args = func_get_args();
      if (isset($args[0]) && is_float($args[0])) {
        $this->start = $args[0];
      } else {
        $this->start = microtime(true);
      }
    }

    public function getElapsedTime() {
      return (microtime(true) - $this->start);
    }

    public function formatElapsedTime() {
      return sprintf("%1.2fs", $this->getElapsedTime());
    }
  }

  final class TeamSpeakCron {
    protected $complaintList;
    protected $clientList;
    protected $clientListDb;
    protected $ts3;

    public function clientHasPermission(TeamSpeak3_Node_Client $client, $permission) {
      $permOverview = $client->permOverview(0);
      foreach ($permOverview as $perm) {
        if (is_numeric($permission) &&                                   $perm["p"]  == $permission && $perm["v"] == 1) return true;
        if (is_string($permission)  && $this->ts3->permissionGetNameById($perm["p"]) == $permission && $perm["v"] == 1) return true;
      }
      return false;
    }

    public function connect() {
      if (isset($this->ts3)) return $this->ts3;
      Logger::writeLine(true, Logger::STYLE_LIGHTYELLOW . "Connecting to TeamSpeak 3 ServerQuery...");
      try {
        global $_CONFIG;
        $ts3 = TeamSpeak3::factory($_CONFIG["connection"]);
        Logger::writeLine(true, Logger::STYLE_LIGHTGREEN . "Connected to TeamSpeak 3 ServerQuery.");
      } catch (TeamSpeak3_Exception $e) {
        $ts3 = null;
        Logger::writeLine(true, Logger::STYLE_LIGHTRED . "Failed to connect to TeamSpeak 3 ServerQuery.");
      }
      $this->ts3 = $ts3;
      return $this->ts3;
    }

    public function demoteInactiveUsers() {
      global $_CONFIG;
      $timeUntilInactive = $_CONFIG["time_until_inactive"];
      $clientListDb = $this->getClientListDatabase();
      $group = $this->ts3->serverGroupGetByName("Active User");
      $currentTime = time();
      foreach ($clientListDb as $dbid => $client) {
        $lastConnected = $client["client_lastconnected"];
        $interval = ($currentTime - $lastConnected);
        if ($interval < $timeUntilInactive) continue;
        $interval_str = TimeString::intervalToString(TimeString::secondsToInterval($interval)[1]);
        Logger::writeLine(true, Logger::STYLE_DARKGRAY . $client["client_nickname"] . " last connected " . $interval_str . " ago.");
        // TODO: Demote this client from active user group.
      }
    }

    public function disconnect() {
      $this->ts3 = null;
    }

    public function getComplaintList() {
      if (isset($this->comaplaintList)) return $this->complaintList;
      Logger::writeLine(true, Logger::STYLE_LIGHTYELLOW . "Getting list of complaints...");
      try {
        $complaintList = $this->ts3->complaintList();
        Logger::writeLine(true, Logger::STYLE_LIGHTGREEN . "Complaints found: " . count($complaintList) . ".");
      } catch (TeamSpeak3_Exception $e) {
        $complaintList = array();
        Logger::writeLine(true, Logger::STYLE_LIGHTGREEN . "Complaints found: 0.");
      }
      $this->complaintList = $complaintList;
      return $this->complaintList;
    }

    public function getClientListConnected() {
      if (isset($this->clientList)) return $this->clientList;
      Logger::writeLine(true, Logger::STYLE_LIGHTYELLOW . "Getting connected client list...");
      try {
        $clientList = $this->ts3->clientList();
        Logger::writeLine(true, Logger::STYLE_LIGHTGREEN . "Other connected clients found: " . (count($clientList) - 1) . ".");
      } catch (TeamSpeak3_Exception $e) {
        $clientList = array();
        Logger::writeLine(true, Logger::STYLE_LIGHTGREEN . "Connected clients found: 0.");
      }
      $this->clientList = $clientList;
      return $this->clientList;
    }

    public function getClientListConnectedWithPermission($permission) {
      $clientList = $this->getClientListConnected();
      $targets = array();
      foreach ($clientList as $client) {
        if ($this->clientHasPermission($client, $permission)) $targets[] = $client;
      }
      return $targets;
    }

    public function getClientListDatabase() {
      if (isset($this->clientListDb)) return $this->clientListDb;
      Logger::writeLine(true, Logger::STYLE_LIGHTYELLOW . "Getting database client list...");
      try {
        $clientList = $this->ts3->clientListDb(0, 25);
        Logger::writeLine(true, Logger::STYLE_LIGHTGREEN . "Database clients found: " . (count($clientList) - 1) . ".");
      } catch (TeamSpeak3_Exception $e) {
        $clientList = array();
        Logger::writeLine(true, Logger::STYLE_LIGHTGREEN . "Database clients found: 0.");
      }
      $this->clientListDb = $clientList;
      return $this->clientListDb;
    }

    public function moveAFKs() {
      $movedCount = 0;
      $channelId = $this->ts3->customSearch("afk_channel_cid", "%")[0]["value"];
      $channel = $this->ts3->channelGetById($channelId);
      $channelName = $channel["channel_name"];
      $clientList = $this->getClientListConnected();
      global $_CONFIG; $maxIdleTime = $_CONFIG["time_until_afk"];
      $maxIdleTimeStr = TimeString::intervalToString(TimeString::secondsToInterval($maxIdleTime)[1]);
      Logger::writeLine(true, Logger::STYLE_LIGHTYELLOW . "Finding AFK clients...");
      foreach ($clientList as $client) {
        if ($client["client_type"] != TeamSpeak3::CLIENT_TYPE_REGULAR) continue;
        if ($client["client_away"] != 0) continue;
        $idleTime = $client["client_idle_time"] / 1000;
        if ($idleTime < $maxIdleTime) continue;
        try {
          ++$movedCount;
          $client->move($channelId, null);
          Logger::writeLine(true, Logger::STYLE_DARKGRAY . "Moved " . $client["client_nickname"] . " due to being AFK (" . $idleTime . " >= " . $maxIdleTime . ").");
          $client->message("You were moved to [B]" . $channelName . "[/B] because you have been AFK for longer than [B]" . $maxIdleTimeStr . "[/B] and do not have the away status set.");
          $this->ts3->logAdd("TeamSpeak Cronjob: Moved and notified '" . $client . "'(id:" . $client["client_database_id"] . ") for being AFK", TeamSpeak3::LOGLEVEL_INFO);
        } catch (TeamSpeak3_Exception $e) {
          if ($e->getMessage() != "already member of channel") throw $e;
          continue;
        }
      }
      Logger::writeLine(true, Logger::STYLE_LIGHTGREEN . "Found AFK clients: " . $movedCount . ".");
    }

    public function notifyOfComplaints() {
      $notificationsSent = 0;
      $complaintList = $this->getComplaintList();
      $complaintCount = count($complaintList);
      if ($complaintCount == 0) return $notificationsSent;
      $clientList = $this->getClientListConnectedWithPermission("b_client_complain_list");
      foreach ($clientList as $client) {
        if ($client["client_type"] != TeamSpeak3::CLIENT_TYPE_REGULAR) continue;
        Logger::writeLine(true, Logger::STYLE_DARKGRAY . "Notifying " . $client["client_nickname"] . " of the open complaints.");
        $msg = "There " . ($complaintCount != 1 ? "are" : "is") . " " . $complaintCount . " open complaint" . ($complaintCount != 1 ? "s" : "") . ".\n";
        sleep(0.5);
        for ($i = 0; $i < $complaintCount; ++$i) {
          $complaint = $complaintList[$i];
          // sample complaint: tcldbid=51 tname=Almirith fcldbid=2 fname=Jailout2000 message=test timestamp=1399825855
          $tclient   = $this->ts3->clientGetByDbid($complaint["tcldbid"]);
          $fclient   = $this->ts3->clientGetByDbid($complaint["fcldbid"]);
          $timestamp = new DateTime("@" . $complaint["timestamp"]);
          $timestamp->setTimezone(new DateTimeZone("America/Chicago"));
          $msg .= "[B][I]Complaint " . ($i + 1) . " of " . $complaintCount . "...[/I][/B]\n";
          $msg .= "  [B]From:[/B] [COLOR=#c00000]" . $complaint["fname"] . "[/COLOR] ([COLOR=#006000]" . ($fclient ? $fclient["client_unique_identifier"] : "[I]unknown[/I]") . "[/COLOR])\n";
          $msg .= "  [B]To:[/B] [COLOR=#c00000]" . $complaint["tname"] . "[/COLOR] ([COLOR=#006000]" . ($tclient ? $tclient["client_unique_identifier"] : "[I]unknown[/I]") . "[/COLOR])\n";
          $msg .= "  [B]Date:[/B] " . $timestamp->format("l, F jS, Y, g:i:s A T") . "\n";
          $msg .= "  [B]Message:[/B] " . $complaint["message"] . "\n";
          sleep(0.5);
        }
        $client->message($msg);
        ++$notificationsSent;
        $this->ts3->logAdd("TeamSpeak Cronjob: Notified '" . $client . "'(id:" . $client["client_database_id"] . ") of the currently open complaints", TeamSpeak3::LOGLEVEL_INFO);
      }
      return $notificationsSent;
    }

    public function promoteActiveUsers() {
      /*global $_CONFIG;
      $timesUntilActive = $_CONFIG["times_until_active"];
      $clientList = $this->getClientListConnected();
      $group = $this->ts3->serverGroupGetByName("Active User");
      foreach ($clientList as $client) {
        if ($client["client_type"] != TeamSpeak3::CLIENT_TYPE_REGULAR) continue;
        $total_connections = $client["client_totalconnections"];
        if ($total_connections < $timesUntilActive) continue;
        Logger::writeLine(true, Logger::STYLE_DARKGRAY . $client["client_nickname"] . " is eligible for active user promotion.");
        // TODO: Promote this client to active user group.
      }*/
    }

    public function setOurNickname($new_name) {
      $obj = new TeamSpeak3_Helper_String($new_name);
      $this->ts3->execute("clientupdate client_nickname=" . $obj->escape());
    }
  }

  class TimeString {

    private function __construct(){}

    public static function intervalToString($di, $zero_interval = "") {
      if (!$di instanceof DateInterval) return null;
      $buf = "";
      if ($di->y) { if ($buf) $buf .= ", "; $buf .= $di->y . " year";   if ($di->y != 1) $buf .= "s"; }
      if ($di->m) { if ($buf) $buf .= ", "; $buf .= $di->m . " month";  if ($di->m != 1) $buf .= "s"; }
      if ($di->d) { if ($buf) $buf .= ", "; $buf .= $di->d . " day";    if ($di->d != 1) $buf .= "s"; }
      if ($di->h) { if ($buf) $buf .= ", "; $buf .= $di->h . " hour";   if ($di->h != 1) $buf .= "s"; }
      if ($di->i) { if ($buf) $buf .= ", "; $buf .= $di->i . " minute"; if ($di->i != 1) $buf .= "s"; }
      if ($di->s) { if ($buf) $buf .= ", "; $buf .= $di->s . " second"; if ($di->s != 1) $buf .= "s"; }
      // Splice the "and" keyword and take care of commas if necessary. We support the Oxford comma!
      if (strpos($buf, ", ") !== false) {
        $buf = explode(", ", $buf); $i = count($buf) - 1;
        $buf[$i] = "and " . $buf[$i];
        if ($i == 1) $buf = implode(" ", $buf); else $buf = implode(", ", $buf);
      }
      if (!$buf) $buf = $zero_interval;
      return $buf;
    }

    public static function secondsToInterval($seconds) {
      $s = round($seconds); // Don't overwrite parameters, duh!
      $o =              30; // Constant days in a month
      
      if ($s < 0 || $s > 311039999999)
        throw new OutOfBoundsException("Rule not satisfied: 0 <= value <= 311039999999");

      $m =  (int)($s / 60); // Expand minutes
      $s =       ($s % 60); // Reduce seconds

      $h =  (int)($m / 60); // Expand hours
      $m =       ($m % 60); // Reduce minutes

      $d =  (int)($h / 24); // Expand days
      $h =       ($h % 24); // Reduce hours

      $M =  (int)($d / $o); // Expand months
      $d =       ($d % $o); // Reduce days

      $y =  (int)($M / 12); // Expand years
      $M =       ($M % 12); // Reduce months

           if ($s > -10   && $s < 10  ) $s = "0"   . $s;
           if ($m > -10   && $m < 10  ) $m = "0"   . $m;
           if ($h > -10   && $h < 10  ) $h = "0"   . $h;
           if ($d > -10   && $d < 10  ) $d = "0"   . $d;
           if ($M > -10   && $M < 10  ) $M = "0"   . $M;
           if ($y > -10   && $y < 10  ) $y = "000" . $y;
      else if ($y > -100  && $y < 100 ) $y = "00"  . $y;
      else if ($y > -1000 && $y < 1000) $y = "0"   . $y;

      $v = "P".$y."-".$M."-".$d."T".$h.":".$m.":".$s;
      try {
        $o = new DateInterval($v);
      } catch (Exception $e) {
        echo "fuck";
        $o = null;
      }

      return [$v, $o];
    }

  }

  require_once($_CONFIG["library_path"]);

  $script_runtime = new Timer();
  Logger::writeLine(true, Logger::STYLE_LIGHTTEAL . "Script started.");

  $exit_code = 1;

  register_shutdown_function(function($script_runtime){
    Logger::writeLine(true, Logger::STYLE_LIGHTTEAL . "Script finished in " . 
      $script_runtime->formatElapsedTime() . ".");
    // Return console style to normal upon shutdown:
    Logger::write(false, Logger::STYLE_NORMAL);
  }, $script_runtime);

  $teamspeakcron = new TeamSpeakCron();
  if (is_null($teamspeakcron->connect())) exit(1);
  $teamspeakcron->setOurNickname("Server Messenger");

  //$teamspeakcron->demoteInactiveUsers();
  //$teamspeakcron->moveAFKs();
  $teamspeakcron->notifyOfComplaints();
  //$teamspeakcron->promoteActiveUsers();

  $teamspeakcron->disconnect();
  exit(0);
