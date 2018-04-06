<?php
require_once 'etc/botcommand/slack_enterlog.php';

function get_lbwusername($text, $user, $connection) {

  $text = test_input($text);

  if ($text == 'user') {
    # show active user for sender
    return cat_user($user);
  } elseif ($text == 'user -a') {
   # show all user for sender
   return cat_allusers($user, $connection);
  } else {
    # change actie user
    if (substr($text, 0, 7) == 'user -u') {
      # after first 8 chr is name
      $_username = substr($text, 7);
      $_username = test_input($_username);
  		return change_actie_user($user, $_username, $connection);
    } else {
      # show message for sender, and make log for this issue
      $msg = "Sorry, some data was missing.\n";
      $msg .= "Run `/lbw user` again.";
      $result = 'error in command user and function cat_user';
      enterlog('slackbot', $result, 'error');

  		return $msg;
    }
	}
}

# change actie user
function change_actie_user($user, $_username, $connection) {
  $_UserName = null;

  if (isset($user['userid'])) {
    $statement = $connection->prepare('SELECT u_id FROM slackusers WHERE id_chn = :idchn');
    $statement->execute(array(':idchn' => $user['idchn']));

    try {
      $connection->beginTransaction();

      while ($rs = $statement->fetch(PDO::FETCH_ASSOC)) {
        $uid_botuser = $rs['u_id'];

        $stmt = $connection->prepare('SELECT username FROM users WHERE uid = :uid');
        $stmt->execute(array(':uid'=>$uid_botuser));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($_username == strtolower($result['username'])) {
          $_stmt = $connection->prepare('UPDATE slackusers SET active = 1 WHERE u_id = :usrid AND id_chn = :idchn');
          $_stmt->execute(array(':usrid'=>$uid_botuser, ':idchn'=>$user['idchn']));
          $_UserName = $result['username'];
        } else {
          $_stmt = $connection->prepare('UPDATE slackusers SET active = 0 WHERE u_id = :usrid AND id_chn = :idchn');
          $_stmt->execute(array(':usrid'=>$uid_botuser, ':idchn'=>$user['idchn']));
        }
      }

      # commit the transaction
      $connection->commit();
    } catch (PDOException $e) {
      $connection->rollBack();
      $msg = 'Username '.$user['username'].' has not been chahged: '.$e->getMessage();
      enterlog('slackbot', $msg, 'error');
    }
  }

  if (!empty($_UserName)) {
   $msg = "username: *" . $_UserName."*";
  }
  else {
    $msg = "example\n";
    $msg .= "username: *R2-D2*";
    $result = 'was command user -u in function change_actie_user';
    enterlog('slackbot', $result, 'error');
  }

  return $msg;
}

# show all user for sender
function cat_allusers($user, $connection) {
  $i = 0; $msg = null;

  if (isset($user['userid'])) {
    $statement = $connection->prepare('SELECT u_id FROM slackusers WHERE id_chn = :idchn');
    $statement->execute(array(':idchn' => $user['idchn']));

    while ($rs = $statement->fetch(PDO::FETCH_ASSOC)) {
      $uid_botuser = $rs['u_id'];

      $stmt = $connection->prepare('SELECT username FROM users WHERE uid = :uid');
      $stmt->execute(array(':uid'=>$uid_botuser));
      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      $msg .= $result['username'] . "\n";
      $i++;

    }

    if ($i == 1) {
      $msg = "lightburroway name:\n" . $msg;
    } else {
      $msg = "lightburroway names:\n" . $msg;
    }
  } else {
    $msg = "example\n";
    $msg .= "lightburroway name: *R2-D2*";
  }

  return $msg;
}

# show active user for sender
function cat_user($user) {

  if ($user['username'] !== 'R2-D2') {
    $msg = "username: *" . $user['username']."*";

  } else {
    $msg = "example\n";
    $msg .= "username: *R2-D2*";
  }

  return $msg;
}
