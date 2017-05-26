<?php
session_start();
require_once 'API.class.php';
include('../../../questlog_credentials.php');
include '../../php/utils.php';

define('ALLOWED_TAGS', '<i></i><b><a><s><sup><sub><li><ol><ul><br><br/><img><u>');

class QuestlogAPI extends API {

  public function __construct($request, $origin) {
    parent::__construct($request);
    if (!array_key_exists('apiKey', $this->request)) {
      throw new Exception('No API Key provided');
    } else if (!$this -> verifyKey($this->request['apiKey'], $origin)) {
      throw new Exception('Invalid API Key');
    } else if (array_key_exists('token', $this->request) &&
      !$User->get('token', $this->request['token'])) {
      throw new Exception('Invalid User Token');
    }
  }
    
  private function verifyKey($key) {
    if ($key == 1) {
      return true;
    } else {
      return false;
    }
  }

  protected function login($args) {
    try {
      $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
      $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $query = 'SELECT u.uid,u.login_name,g.group_name FROM users u, groups g
          WHERE u.login_name=:name
          AND u.login_hash=:hash
          AND u.gid=g.gid';
      $sth = $dbh -> prepare($query);
      $sth -> execute(array(':name' => $args[0], ':hash' => hashPasswd($args[0], $args[1])));
      $row = $sth -> fetch();

      if(!$row) {
        $dbh = null;
        http_response_code(401);
        exit();
      } else {
        $_SESSION['uid'] = $row['uid'];
        $_SESSION['login'] = $row['login_name'];
        $_SESSION['group'] = $row['group_name'];
        $_SESSION['last_activity'] = time();

        $query = 'SELECT ip, date FROM user_logins WHERE uid=:uid ORDER BY id DESC LIMIT 1';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':uid' => $row['uid']));
        $login_data = $sth -> fetch();
        if (!$login_data) {
          $login_data['ip'] = $_SERVER['REMOTE_ADDR'];
          $login_data['date'] = time();
        }
        $_SESSION["ip"] = $login_data['ip'];
        $_SESSION["date"] = $login_data['date'];
        $query = "INSERT INTO user_logins (uid, date, ip) VALUES (:uid, :time, :ip)";
        $sth = $dbh->prepare($query);
        $sth->execute(array(':uid' => $row['uid'],':time' => time(), ':ip' => $login_data['ip']));
        $dbh = null;
        $json_array = [];
        $json_array['user_details'] = [];
        $json_array['user_details']['name'] = $row['login_name'];
        $json_array['user_details']['id'] = $row['uid'];
        $json_array['user_details']['ip'] = $login_data['ip'];
        $json_array['user_details']['date'] = $login_data['date'];
        return $json_array;
      }
    } catch(PDOException $error) {
      return 'database_error';
    }
  }

  protected function logout() {
    killSession();
    return 'success';
  }

  protected function quests() {
    if ($this -> method == 'GET') {
      try {
        $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        // Get the user's GM quest.
        $query = 'SELECT qid,uid,quest_name,timestamp FROM quests WHERE uid=:uid AND quest_status < 4';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':uid' => $_SESSION['uid']));
        $questsArr = $sth -> fetchAll();
        $gm_quests = '0';
        foreach ($questsArr as $quest) {
          $gm_quests .= ',' . $quest['qid'];
        }
        $json_array = [];
        $json_array['quests'] = [];
        $json_array = generateQuestListings($questsArr, $json_array, 'gmQuests');

        // Get the user's player quests.
        $query = 'SELECT cid FROM characters WHERE uid=:uid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':uid' => $_SESSION['uid']));
        $results = $sth -> fetchAll();
        $questsArr = [];
        $index=0;
        $player_chars = '0';
        foreach ($results as $row) {
          $query = 'SELECT qid FROM quest_members WHERE cid=:cid';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':cid' => $row['cid']));
          $player_chars .= ',' . $row['cid'];
          $player_quests = $sth -> fetchAll();
          if (count($player_quests) > 0) {
            foreach ($player_quests as $quest) {
              $query = 'SELECT qid,uid,quest_name,timestamp FROM quests WHERE qid=:qid AND quest_status < 4';
              $sth = $dbh -> prepare($query);
              $sth -> execute(array(':qid' => $quest['qid']));
              $questsArr[$index] = $sth -> fetch();
              $index++;
            }
          }
        }
        $json_array = generateQuestListings($questsArr, $json_array, 'playerQuests');
        // Get all remaining  non-player, non-GM quests.
        $query = 'SELECT qid FROM quest_members WHERE qid NOT IN (' . $gm_quests .') AND cid NOT IN (' . $player_chars . ')';
        $sth = $dbh -> prepare($query);
        $sth -> execute();
        $non_quests = $sth -> fetchAll();
        $questsArr = [];
        $index = 0;
        foreach ($non_quests as $quest) {
          $query = 'SELECT qid,uid,quest_name,timestamp FROM quests WHERE qid=:qid AND quest_status < 4';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':qid' => $quest['qid']));
          $results = $sth -> fetch(PDO::FETCH_ASSOC);
          if ($results['quest_name'] != null) {
            $questsArr[$index] = $results;
          }
          $index++;
        }
        usort($questsArr, function($a, $b) {
          return $a['quest_name'] > $b['quest_name'];
        });
        $json_array = generateQuestListings($questsArr, $json_array, 'otherQuests');
        $dbh = null;
        return $json_array;
      } catch(PDOException $error) {
        return 'database_error';
      }
    }
  }

  protected function quest($args) {
    if ($this->method == 'GET') {
      try {
        $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        $json_array = [];
        $query = 'SELECT cid FROM characters WHERE uid = :uid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':uid' => $_SESSION['uid']));
        $cids = $sth -> fetchAll();
        $isMember = false;
        foreach($cids as $cid) {
          $query = 'SELECT COUNT(qid) FROM quest_members WHERE cid=:cid AND qid=:qid';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':cid' => $cid['cid'], ':qid' => $args[0]));
          if ($sth -> fetch()[0] > 0) {
            $isMember = true;
          }
        }
        $isGM = false;
        $query = 'SELECT uid FROM quests WHERE qid=:qid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':qid' => $args[0]));
        if ($sth->fetch()[0] == $_SESSION['uid']) {
          $isGM = true;
        }
        $query = 'SELECT qid,uid,quest_name,quest_status FROM quests WHERE qid = :questID AND quest_status = 0';
        if ($isMember) {
          $query .= ' OR qid=:questID AND quest_status = 1';
        }
        if ($isGM) {
          $query .= ' OR qid=:questID AND quest_status = 2';
        }
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':questID' => $args[0]));
        $row = $sth -> fetch();

        if (!$row) {
          $dbh = null;
          return 'null_results';
        }
        $json_array['title'] = $row['quest_name'];
        $json_array['questID'] = $row['qid'];
        $json_array['gmID'] = $row['uid'];
        $json_array['privacy'] = $row['quest_status'];
        
        $query = 'SELECT login_name FROM users WHERE uid = :gmID';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':gmID' => $row['uid']));
        $json_array['gmName'] = $sth -> fetch()[0];

        $query = 'SELECT preface_text FROM quest_prefaces WHERE qid=:qid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':qid' => $args[0]));
        $json_array['backstory'] = $sth -> fetch()[0];
        
        $query = 'SELECT cid FROM quest_members WHERE qid=:questID';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':questID' => $args[0]));
        $results = $sth -> fetchAll();
        $index=0;
        foreach($results as $row) {
          $json_array['players'][$index]['characterID'] = $row['cid'];
          $query = 'SELECT char_name,uid FROM characters WHERE cid=:charID';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':charID' => $row['cid']));
          $character = $sth->fetchAll();
          $json_array['players'][$index]['userID'] = $character[0]['uid'];
          $json_array['players'][$index]['name'] = $character[0]['char_name'];
          $query = 'SELECT login_name FROM users WHERE uid=:uid';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':uid' => $character[0]['uid']));
          $json_array['players'][$index]['userName'] = $sth -> fetch()[0];
          $index++;
        }
        $dbh = null;
        return $json_array;
      } catch(PDOException $error) {
        return 'database_error';
      }
    } else if ($this->method == 'POST') {
      try {
        $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        $query = 'SELECT COUNT(qid) FROM quests WHERE quest_name=:name';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':name' => $this->args['name']));
        $total = $sth->fetch()[0];
        if ($total > 0) {
          $dbh = null;
          return 'duplicate_entry';
        }
        $query = 'INSERT INTO quests (quest_name, quest_status, timestamp, uid) VALUES (:name, :privacy, now(), :uid)';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':name' => $this->args['NAME'], ':privacy' => $this->args['PRIVACY'], ':uid' => $_SESSION['uid']));
        $newID = $dbh->lastInsertId();
        $query = 'INSERT INTO quest_prefaces(qid, preface_text, timestamp) VALUES (:qid, :preface, now())';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':qid' => $newID, ':preface' => $this->args['backstory']));
        $players = json_decode($this->args['PLAYERS']);
        foreach ($players as $player) {
          $query = 'INSERT INTO quest_members (qid, cid) VALUES (:qid, :cid)';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':qid' => $newID, ':cid' => $player -> character));
        }
        if (strlen($args['FIRSTPOST'])) {
          $body = urldecode($body);
          $body = strip_tags($body, ALLOWED_TAGS);
          $body = sanitizeText($body, $newID);
          $query = 'INSERT INTO posts (qid,uid,post_date,post_text,post_ip) VALUES(:qid,:uid,now(),:text,:ip)';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':qid' => $newID, ':uid' => $_SESSION['uid'],':text' => $body, ':ip' => $_SERVER['REMOTE_ADDR']));
        }
        $dbh = null;
        return 'success';
      } catch(PDOException $error) {
        return 'database_error';
      }
    } else if ($this -> method == 'DELETE') {
      $nextPos = array_search('QID', $this->args)+1;
      $qid = $args[$nextPos];
      if (getQuestGMID($qid) != $_SESSION['uid']) {
        return 'unauthorized';
        exit();
      }
      try {
        $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        $query = 'UPDATE quests SET quest_status = 4 WHERE qid=:qid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':qid' => $qid));
        $dbh = null;
        return 'success';
      } catch(PDOException $error) {
        return 'database_error';
      }
    } else if ($this -> method == 'PUT') {
      $body = urldecode($args['BACKSTORY']);
      $body = strip_tags($body, ALLOWED_TAGS);
      if (getQuestGMID($this -> args['QID']) == $_SESSION['userID']) {
        return 'unauthorized';
        exit();
      }
      try {
        $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        $query = 'UPDATE quests SET quest_name=:name,quest_status=:privacy WHERE qid=:qid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':qid' => $this->args['QID'], ':name'=>$this->args['NAME'], ':privacy' => $this->args['PRIVACY']));
        $query = 'DELETE FROM quest_members WHERE qid=:qid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':qid' => $this->args['QID']));
        $query = 'UPDATE quest_prefaces SET preface_text=:preface WHERE qid=:qid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':qid' => $this->args['QID'], ':preface' => $body));
        $players = json_decode($this->args['PLAYERS']);
        foreach ($players as $player) {
          $query = 'INSERT INTO quest_members (qid, cid) VALUES (:qid, :cid)';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':qid' => $this->args['QID'], ':cid' => $player -> character));
        }
        $dbh = null;
        return 'success';
      } catch(PDOException $error) {
        return 'database_error';
      }
    }
  }
  protected function users($args) {
    if ($this->method == 'GET') {
      if (isset($args) && is_array($args) && count($args) > 0) {
        if (in_array('UID', $args)) {
          $nextPos = array_search('UID', $this->args)+1;
          $userID = $args[$nextPos];
        }
        if (in_array('QID', $this->args)) {
          $nextPos = array_search('QID', $this->args)+1;
          $questID = $args[$nextPos];
        }
        if (in_array('CID', $this->args)) {
          $nextPos = array_search('CID', $this->args)+1;
          $charID = $args[$nextPos];
        }
      }
      try {
        $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        if (isset($questID)) {
          $query = 'SELECT uid FROM quests WHERE qid= :questID AND quest_status < 4';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':questID' => $questID));
          $users = $sth -> fetchAll();
          $query = 'SELECT cid FROM quest_members WHERE qid= :questID';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':questID' => $questID));
          $characters = $sth -> fetchAll();
          $results = [];
          foreach($characters as $row) {
            $query = 'SELECT uid FROM characters WHERE cid= :characterID';
            $sth = $dbh -> prepare($query);
            $sth -> execute(array(':characterID' => $row['cid']));
            array_push($users, $sth -> fetch(PDO::FETCH_ASSOC));
            $index=0;
            foreach($users as $user) {
              $query = 'SELECT uid,login_name,user_status,timestamp FROM users WHERE uid=:userID';
              $sth = $dbh -> prepare($query);
              $sth -> execute(array(':userID' => $user['uid']));
              array_push($results, $sth -> fetch(PDO::FETCH_ASSOC));
              if ($user['uid'] == $_SESSION['uid']) {
                $query = 'SELECT user_email FROM user_profiles WHERE uid=:uid';
                $sth = $dbh -> prepare($query);
                $sth -> execute(array(':uid' => $user['uid']));
                $results[$index]['user_email'] = $sth -> fetch()[0];
              }
              $index++;
            }
          }
        } else if (isset($charID)) {
          $query = 'SELECT uid FROM characters WHERE cid= :characterID';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':characterID' => $charID));
          $users = $sth -> fetchAll();
          foreach($users as $user) {
            $query = 'SELECT uid,login_name,user_status,timestamp FROM users WHERE uid=:userID';
            $sth = $dbh -> prepare($query);
            $sth -> execute(array(':userID' => $user['uid']));
            $results = $sth -> fetchAll(PDO::FETCH_ASSOC);
            if ($user['uid'] == $_SESSION['uid']) {
              $query = 'SELECT user_email FROM user_profiles WHERE uid=:uid';
              $sth = $dbh -> prepare($query);
              $sth -> execute(array(':uid' => $user['uid']));
              $results[0]['user_email'] = $sth -> fetch()[0];
            }
          }
        } else {
          $query = 'SELECT uid,login_name,user_status,timestamp FROM users';
          if (isset($userID)) {
            $query .= ' WHERE uid = :userID';
            $sth = $dbh -> prepare($query);
            $sth -> execute(array(':userID' => $userID));
          } else {
            $sth = $dbh -> prepare($query);
            $sth -> execute();
          }
          $results = $sth -> fetchAll();
          $index = 0;
          foreach ($results as $row) {
            if ($row['uid'] == $_SESSION['uid']) {
              $query = 'SELECT user_email FROM user_profiles WHERE uid=:uid';
              $sth = $dbh -> prepare($query);
              $sth -> execute(array(':uid' => $row['uid']));
              $results[$index]['user_email'] = $sth -> fetch()[0];
              $index++;
            }
          }
        }
        if(!isset($results) || !count($results)) {
          $dbh = null;
          return 'null_results';
        }
        $index = 0;
        $json_array = [];
        $json_array['users'] = [];
        foreach($results as $row) {
          $json_array['users'][$index]['userID'] = $row['uid'];
          $json_array['users'][$index]['userName'] = $row['login_name'];
          $json_array['users'][$index]['status'] = $row['user_status'];
          $json_array['users'][$index]['created'] = strtotime($row['timestamp']);
          if (array_key_exists('user_email', $row) && $row['uid'] == $_SESSION['uid']) {
            $json_array['users'][$index]['email'] = $row['user_email'];
          }
          $index++;
        }
        $dbh = null;
        return $json_array;
      } catch(PDOException $error) {
        return 'database_error';
      }
    } else if ($this -> method == 'PUT') {
      $pos = array_search('EMAIL', $args)+1;
      $email = $args[$pos];
      try {
        $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        $query = 'UPDATE user_profiles SET user_email=:email, alert_email=:email WHERE uid=:uid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':email' => strtolower($email), ':uid' => $_SESSION['uid']));
        $dbh = null;
        return 'success';
      } catch(PDOException $error) {
        return 'database_error';
      }
    }
  }
  
  protected function search($args) {
    if (in_array('PLAYERS', $args)) {
      $type = 'players';
      $pos = array_search('PLAYERS', $args) + 1;
      $key = $args[$pos];
    }
    try {
      $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
      $query = 'SELECT char_name,cid,uid,created FROM characters WHERE char_name  LIKE concat("%", :arg, "%")';
      $sth = $dbh -> prepare($query);
      $sth -> execute(array(':arg' => $key));
      $results = $sth -> fetchAll();
      $index = 0;
      $json_array = [];
      $json_array[$type] = [];
      foreach($results as $row) {
        $json_array[$type][$index]['userID'] = $row['uid'];
        $json_array[$type][$index]['character'] = $row['char_name'];
        $json_array[$type][$index]['characterID'] = $row['cid'];
        $json_array[$type][$index]['created'] = strtotime($row['created']);
        $query = 'SELECT login_name FROM users WHERE uid=:uid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':uid' => $row['uid']));
        $json_array[$type][$index]['userName'] = $sth -> fetch()[0];
        $index++;
      }
      $dbh = null;
      return $json_array;
    } catch(PDOException $error) {
      return 'database_error';
    }
  }

  protected function posts($args) {
    if ($this -> method == 'GET') {
      $postOrder = 'DESC';
      $limit = null;
      $page = 1;
      if (in_array('ORDER', $args)) {
        $pos = array_search('ORDER', $args) + 1;
        $postOrder = strtoupper($args[$pos]);
      }
      if (in_array('LIMIT', $args)) {
        $pos = array_search('LIMIT', $args) + 1;
        $limit = $args[$pos];
      }
      if (in_array('PAGE', $args)) {
        $pos = array_search('PAGE', $args) + 1;
        $page = $args[$pos];
      }
      try {
        
        $json_array = [];
        $json_array['currentPage'] = $page;
        $json_array['order'] = $postOrder;
        $json_array['delimiter'] = 0;

        $dbh = new PDO('mysql:host=' .DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        if (in_array('PID', $args)) {
          $query = 'SELECT uid,cid,qid,pid,post_text,post_date,timestamp FROM posts WHERE pid = :postID';
          $sth = $dbh -> prepare($query);
          $pos = array_search('PID',$args)+1;
          $sth -> execute(array(':postID' => $args[$pos]));
          $results = $sth -> fetchAll();
          if(!count($results)) {
            return 'null_results';
          }
          // get this post's quest info.
          $query = 'SELECT quest_name FROM quests WHERE qid = :questID AND quest_status < 4';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':questID' => $results[0]['qid']));
          $quest = $sth -> fetch();
          $results[0]['quest_name'] = $quest['quest_name'];

          // get this post's user info.
          $query = 'SELECT login_name FROM users WHERE uid = :userID';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':userID' => $results[0]['uid']));
          $user = $sth -> fetch();
          $results[0]['login_name'] = $user['login_name'];

          // get this post's character info.
          if ($results[0]['cid'] == '0') {
            $results[0]['cid'] = 'GM';
            $results[0]['char_name'] = $user['login_name'];
          } else {
            $query = 'SELECT char_name FROM characters WHERE cid = :characterID';
            $sth = $dbh -> prepare($query);
            $sth -> execute(array(':characterID' => $results[0]['cid']));
            $character = $sth -> fetch();
            $results[0]['char_name'] = $character['char_name'];
          }
          $results[0]['post_date'] = strtotime($results[0]['post_date']);
          if (strtotime($results[0]['timestamp']) != strtotime($results[0]['post_date'])) {
            $results[0]['edited'] = strtotime($results[0]['timestamp']);
          } else {
            $results[0]['edited'] = 'never';
          }
          $query = 'SELECT pid FROM posts WHERE qid=:questID ORDER BY timestamp DESC LIMIT 1';
          $sth = $dbh -> prepare($query);
          $sth -> execute(array(':questID' => $results[0]['qid']));
          $lastPost = $sth -> fetch()[0];
          if ($results[0]['uid'] == $_SESSION['uid'] && $lastPost == $results[0]['pid']) {
            $results[0]['editable'] = 'true';
          } else {
            $results[0]['editable'] = 'false';
          }
        } else if (in_array('UID',$args) || in_array('CID',$args) || in_array('QID', $args)) {
          if (in_array('UID', $args)) {
            $pos = array_search('UID', $args) + 1;
            $whereString = 'WHERE uid = :userID';
            $paramsArray = array(':userID' => $args[$pos]);
            if (in_array('QID',$args)) {
              $pos = array_search('QID', $args)+1;
              $whereString .= ' AND qid = :questID';
              $paramsArray[':questID'] = $args[$pos];
            }
          } else if (in_array('CID', $args)) {
            $pos = array_search('CID', $args) + 1;
            $whereString = 'WHERE cid = :characterID';
            $paramsArray = array(':characterID' => $args[$pos]);
            if (in_array('QID', $args)) {
              $pos = array_search('QID', $args) + 1;
              $whereString .= ' AND qid = :questID';
              $paramsArray[':questID'] = $args[$pos];
            }
          } else if (in_array('QID', $args)) {
            $pos = array_search('QID', $args)+1;
            $whereString = 'WHERE qid = :questID';
            $paramsArray = array(':questID' => $args[$pos]);
          }
          $query = 'SELECT uid,cid,qid,pid,post_text,post_date,timestamp FROM posts ' . $whereString . ' ORDER BY timestamp ' . $postOrder;
          if (in_array('QID', $args)) {
            $pos = array_search('QID', $args)+1;
            $qid = $args[$pos];
            if (is_null($limit)) {
              $startingIndex = 0;
            } else {
              $count = 'SELECT COUNT(pid) FROM posts WHERE qid=:questID';
              $sth = $dbh -> prepare($count);
              $sth -> execute(array(':questID' => $qid));
              $total = $sth -> fetch();
              $json_array['pageCount'] = ceil($total['COUNT(pid)']/$limit);
              $json_array['delimiter'] = $limit;
              if ($postOrder == 'DESC') {
                $startingIndex = $limit * ($page-1);
              } else {
                $startingIndex = ($limit * $page) - $limit;
              }
              $query .= ' LIMIT ' . $limit . ' OFFSET ' . $startingIndex;
            }
          } else {
            if (!is_null($limit)) {
              $query .= ' LIMIT ' . $limit;
            }
          }

          $sth = $dbh -> prepare($query);
          $sth -> execute($paramsArray);
          $results = $sth -> fetchAll();
          if (!count($results)) {
            return 'null_results';
          }
          $index = 0;
          foreach ($results as $row) {
            // get this post's quest info.
            $query = 'SELECT quest_name FROM quests WHERE qid = :questID AND quest_status < 4';
            $sth = $dbh -> prepare($query);
            $sth -> execute(array(':questID' => $row['qid']));
            $quest = $sth -> fetch();
            $results[$index]['quest_name'] = $quest['quest_name'];
            
            $query = 'SELECT pid FROM posts WHERE qid=:questID ORDER BY timestamp DESC LIMIT 1';
            $sth = $dbh -> prepare($query);
            $sth -> execute(array(':questID' => $row['qid']));
            if ($sth->fetch()[0] == $row['pid'] && $row['uid'] == $_SESSION['uid']) {
              $results[$index]['editable'] = 'true';
            } else {
              $results[$index]['editable'] = 'false';
            }
            // get this post's user info.
            $query = 'SELECT login_name FROM users WHERE uid = :userID';
            $sth = $dbh -> prepare($query);
            $sth -> execute(array(':userID' => $row['uid']));
            $user = $sth -> fetch();
            $results[$index]['login_name'] = $user['login_name'];

            // get this post's character info.
            if ($row['cid'] == '0') {
              $results[$index]['cid'] = 'GM';
              $results[$index]['char_name'] = $user['login_name'];
            } else {
              $query = 'SELECT char_name FROM characters WHERE cid = :characterID';
              $sth = $dbh -> prepare($query);
              $sth -> execute(array(':characterID' => $row['cid']));
              $character = $sth -> fetch();
              $results[$index]['char_name'] = $character['char_name'];
            }
            $results[$index]['post_date'] = strtotime($row['post_date']);
            if (strtotime($row['post_date']) != strtotime($row['timestamp'])) {
              $results[$index]['edited'] = strtotime($row['timestamp']);
            } else {
              $results[$index]['edited'] = 'never';
            }
            $index++;
          }
        }
        $index = 0;
        $json_array['posts'] = [];
        foreach ($results as $row) {
          $json_array['posts'][$index] = [];
          $json_array['posts'][$index]['postID'] = $row['pid']; 
          $json_array['posts'][$index]['questID'] = $row['qid'];
          $json_array['posts'][$index]['quest'] = $row['quest_name'];
          $json_array['posts'][$index]['userID'] = $row['uid'];
          $json_array['posts'][$index]['user'] = $row['login_name'];
          $json_array['posts'][$index]['characterID'] = $row['cid'];
          $json_array['posts'][$index]['character'] = $row['char_name'];
          $json_array['posts'][$index]['date'] = $row['post_date'];
          $json_array['posts'][$index]['edited'] = $row['edited'];
          $json_array['posts'][$index]['editable'] = $row['editable'];
          
          $pattern = '#\[DICE_ROLL\]([0-9a-zA-Z]+)\[\|DICE_ROLL\]#i';
          preg_match_all($pattern, $row['post_text'], $matches);
          $json_array['posts'][$index]['rolls'] = [];
          $subIndex = 0;
          foreach ($matches[1] as $match) {
            $json_array['posts'][$index]['rolls'][$subIndex] = $match;
            $subIndex++;
          }
          $json_array['posts'][$index]['text'] = databaseToDisplayText($row['post_text'], $row['char_name'], $row['pid']);
          $index++;
        }
        $dbh = null;
        return $json_array;
      } catch(PDOException $error) {
        return 'database_error';
      }
    } else if ($this->method == 'POST') {
      print_r($args);
      $qid = $args['QID'];
      $body = urldecode($args['BODY']);
      $body = strip_tags($body, ALLOWED_TAGS);
      $pos = array_search('CID',$args)+1;
      $cid = $args['CID'];
      
      // ADD PERMISSIONS CHECKING LATER

      try {
        $dbh = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        $query = 'INSERT INTO posts (qid,uid,cid,post_date,post_ip) VALUES(:qid,:uid,:cid,now(),:ip)';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':qid' => $qid, ':uid' => $_SESSION['uid'], ':cid' => $cid, ':ip' => $_SERVER['REMOTE_ADDR']));
        $newID = $dbh->lastInsertId();
        $body = sanitizeText($body, $newID);
        $query = 'UPDATE posts SET post_text=:text WHERE pid=:pid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':text' => $body, ':pid' => $newID));
        $this->method = 'GET';
        $args[0] = 'PID';
        $args[1] = $newID;
        $dbh = null;
        return 'success';
      } catch(PDOException $error) {
        return 'database_error';
      }
    } else if ($this->method == 'DELETE') {
      $pos = array_search('QID',$args)+1;
      $pid = $args[$pos];
      try {
        $dbh = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        $query = 'DELETE FROM posts WHERE pid=:pid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':pid' => $pid));
        $dbh = null;
        return 'success';
      } catch(PDOException $error) {
        return 'database_error';
      }
    } else if ($this -> method == 'PUT') {
      $pid = $args['PID'];
      $body = $args['BODY'];
      $body = urldecode($body);
      $body = strip_tags($body, ALLOWED_TAGS);
      
      $cid = $args['CID'];
      
      // ADD PERMISSIONS CHECKING LATER

      try {
        $dbh = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE, DB_USER, DB_PASS);
        $query = 'SELECT qid FROM posts WHERE pid=:pid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':pid' => $pid));
        $qid = $sth -> fetch()[0];
        $body = sanitizeText($body, $pid, $cid, $qid);
        $query = 'UPDATE posts SET post_text=:text,cid=:cid,timestamp=now() WHERE pid=:pid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':text' => $body, ':cid' => $cid, ':pid' => $pid));
        $query = 'SELECT timestamp FROM posts WHERE pid=:pid';
        $sth = $dbh -> prepare($query);
        $sth -> execute(array(':pid' => $pid));
        $dbh = null;
        $json_array = [];
        $json_array['editedDate'] = $sth -> fetch()[0];
        return $json_array;
      } catch(PDOException $error) {
        return 'database_error';
      }
    }
  }
}
?>