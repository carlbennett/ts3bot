<?php

  if (php_sapi_name() != "cli") {
    http_response_code(503);
    header("Cache-Control: max-age=0, must-revalidate, no-cache, no-store");
    header("Content-Type: text/plain;charset=utf-8");
    echo "This file is intended to be executed via php's command line.";
    exit(1);
  }

  if (!file_exists(__DIR__ . '/../lib/autoload.php')) {
    exit(
      'Application misconfigured. Please run `composer install`.' . PHP_EOL
    );
  }
  require(__DIR__ . '/../lib/autoload.php');

  if ($argc > 1) {
    echo "Usage: php -f " . $argv[0] . "\n";
    exit(1);
  }

  global $_CONFIG; $_CONFIG = array();
  $_CONFIG['connection'] = json_decode(
    file_get_contents('./etc/connection.json'), true
  );
  $_CONFIG['foul_words'] = json_decode(
    file_get_contents('./etc/foul_words.json'), true
  );
  $_CONFIG['options'] = json_decode(
    file_get_contents("./etc/options.json"), true
  );

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
      echo 'Connecting to TeamSpeak 3 ServerQuery...' . PHP_EOL;
      try {
        global $_CONFIG;
        $ts3 = TeamSpeak3::factory($_CONFIG["connection"]["uri"]);
        echo 'Connected to TeamSpeak 3 ServerQuery.' . PHP_EOL;
      } catch (TeamSpeak3_Exception $e) {
        $ts3 = null;
        echo 'Failed to connect to TeamSpeak 3 ServerQuery.' . PHP_EOL;
      }
      $this->ts3 = $ts3;
      return $this->ts3;
    }

    public function demoteInactiveUsers() {
      global $_CONFIG;
      $timeUntilInactive = $_CONFIG["options"]["legacy"]["time_until_inactive"];
      $clientListDb = $this->getClientListDatabase();
      $group = $this->ts3->serverGroupGetByName("Active User");
      $currentTime = time();
      foreach ($clientListDb as $dbid => $client) {
        $lastConnected = $client["client_lastconnected"];
        $interval = ($currentTime - $lastConnected);
        if ($interval < $timeUntilInactive) continue;
        $interval_str = TimeString::intervalToString(TimeString::secondsToInterval($interval)[1]);
        echo $client['client_nickname'] . ' last connected ' . $interval_str . ' ago.' . PHP_EOL;
        // TODO: Demote this client from active user group.
      }
    }

    public function disconnect() {
      $this->ts3 = null;
    }

    public function getComplaintList() {
      if (isset($this->complaintList)) return $this->complaintList;
      echo 'Getting list of complaints...' . PHP_EOL;
      try {
        $complaintList = $this->ts3->complaintList();
        echo 'Complaints found: ' . count($complaintList) . '.' . PHP_EOL;
      } catch (TeamSpeak3_Exception $e) {
        $complaintList = array();
        echo 'Complaints found: 0.' . PHP_EOL;
      }
      $this->complaintList = $complaintList;
      return $this->complaintList;
    }

    public function getClientListConnected() {
      if (isset($this->clientList)) return $this->clientList;
      echo 'Getting connected client list...' . PHP_EOL;
      try {
        $clientList = $this->ts3->clientList();
        echo 'Other connected clients found: ' . (count($clientList) - 1) . '.' . PHP_EOL;
      } catch (TeamSpeak3_Exception $e) {
        $clientList = array();
        echo 'Connected clients found: 0.' . PHP_EOL;
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
      echo 'Getting database client list...' . PHP_EOL;
      try {
        $clientList = $this->ts3->clientListDb(0, 25);
        echo 'Database clients found: ' . (count($clientList) - 1) . '.' . PHP_EOL;
      } catch (TeamSpeak3_Exception $e) {
        $clientList = array();
        echo 'Database clients found: 0.' . PHP_EOL;
      }
      $this->clientListDb = $clientList;
      return $this->clientListDb;
    }

    public function kickBadNames() {
      $kickedCount = 0;
      $clientName = "";
      $clientList = $this->getClientListConnected();
      global $_CONFIG; $badNames = $_CONFIG["foul_words"];
      echo 'Finding bad nicknames...' . PHP_EOL;
      foreach ($clientList as $client) {
        $clientName = $client["client_nickname"];
        foreach ($badNames as $name => $reason) {
          if (stripos($clientName, $name) !== false) {
            ++$kickedCount;
            $client->kick(TeamSpeak3::KICK_SERVER, $reason);
            echo 'Kicked ' . $clientName . ' due to bad nickname' . ($reason ? ': ' . $reason : '') . '.' . PHP_EOL;
            break;
          }
        }
      }
      echo 'Kicked bad nicknames: ' . $kickedCount . '.' . PHP_EOL;
    }

    public function moveAFKs() {
      $movedCount = 0;
      $channelId = $this->ts3->customSearch("afk_channel_cid", "%")[0]["value"];
      $channel = $this->ts3->channelGetById($channelId);
      $channelName = $channel["channel_name"];
      $clientList = $this->getClientListConnected();
      global $_CONFIG; $maxIdleTime = $_CONFIG["options"]["legacy"]["time_until_afk"];
      $maxIdleTimeStr = TimeString::intervalToString(TimeString::secondsToInterval($maxIdleTime)[1]);
      echo 'Finding AFK clients...' . PHP_EOL;
      foreach ($clientList as $client) {
        if ($client["client_type"] != TeamSpeak3::CLIENT_TYPE_REGULAR) continue;
        if ($client["client_away"] != 0) continue;
        $idleTime = $client["client_idle_time"] / 1000;
        if ($idleTime < $maxIdleTime) continue;
        try {
          ++$movedCount;
          $client->move($channelId, null);
          echo 'Moved ' . $client['client_nickname'] . ' due to being AFK (' . $idleTime . ' >= ' . $maxIdleTime . ').' . PHP_EOL;
          $client->message("You were moved to [B]" . $channelName . "[/B] because you have been AFK for longer than [B]" . $maxIdleTimeStr . "[/B] and do not have the away status set.");
          $this->ts3->logAdd("TeamSpeak Cronjob: Moved and notified '" . $client . "'(id:" . $client["client_database_id"] . ") for being AFK", TeamSpeak3::LOGLEVEL_INFO);
        } catch (TeamSpeak3_Exception $e) {
          if ($e->getMessage() != "already member of channel") throw $e;
          continue;
        }
      }
      echo 'Found AFK clients: ' . $movedCount . '.' . PHP_EOL;
    }

    public function notifyOfComplaints() {
      $notificationsSent = 0;
      $complaintList = $this->getComplaintList();
      $complaintCount = count($complaintList);
      if ($complaintCount == 0) return $notificationsSent;
      $clientList = $this->getClientListConnectedWithPermission("b_client_complain_list");
      foreach ($clientList as $client) {
        if ($client["client_type"] != TeamSpeak3::CLIENT_TYPE_REGULAR) continue;
        echo 'Notifying ' . $client['client_nickname'] . ' of the open complaints.' . PHP_EOL;
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
      $timesUntilActive = $_CONFIG["options"]["legacy"]["times_until_active"];
      $clientList = $this->getClientListConnected();
      $group = $this->ts3->serverGroupGetByName("Active User");
      foreach ($clientList as $client) {
        if ($client["client_type"] != TeamSpeak3::CLIENT_TYPE_REGULAR) continue;
        $total_connections = $client["client_totalconnections"];
        if ($total_connections < $timesUntilActive) continue;
        echo $client["client_nickname"] . ' is eligible for active user promotion.' . PHP_EOL;
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

  $script_runtime = new Timer();
  echo 'Script started.' . PHP_EOL;

  $exit_code = 1;

  register_shutdown_function(function($script_runtime){
    echo 'Script finished in ' . $script_runtime->formatElapsedTime() . '.'
      . PHP_EOL;
  }, $script_runtime);

  $teamspeakcron = new TeamSpeakCron();
  if (is_null($teamspeakcron->connect())) exit(1);
  $teamspeakcron->setOurNickname($_CONFIG["options"]["bot_nickname"]);

  //$teamspeakcron->demoteInactiveUsers();
  $teamspeakcron->kickBadNames();
  //$teamspeakcron->moveAFKs();
  $teamspeakcron->notifyOfComplaints();
  //$teamspeakcron->promoteActiveUsers();

  $teamspeakcron->disconnect();
  exit(0);
