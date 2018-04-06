<?php
/*
* Calendar return DateTo DatesBeforeDateFrom DatesFromFirstDay
*/
require_once 'etc/botcommand/get_calendar.php';
require_once 'etc/botcommand/update_accountbalance.php';
require_once 'etc/botcommand/slack_enterlog.php';

function get_accountid($_num, $connection, $_user) {
	$connection->query('USE '.$_user['namedb']);

	$stmt = $connection->prepare('SELECT accountid, anumber, nameaccount FROM accounts WHERE accountid = ?');
	$stmt->execute([$_num]);

	if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$_aname = $row['nameaccount'] . " " . substr($row['anumber'],-4);
		$_account = array('AccountId' => $row['accountid'], 'AccountName' => $_aname);
	} else {
		$_account = array('AccountId' => null, 'AccountName' => null);
	}

	return $_account;
}

function send_transfer($textstr, $connection, $_user) {
	# get string after 2 chars: tr
	$textstr = substr($textstr, 2);
	$textstr = test_input($textstr);
	$textstr = explode(" ", $textstr);

	if (is_numeric($textstr[0])) {
    $_AccountFrom = get_accountid($textstr[0], $connection, $_user);
  } else {
    $_AccountFrom = array('AccountId' => null, 'AccountName' => null);
  }

	if (is_numeric($textstr[1])) {
    $_AccountTo = get_accountid($textstr[1], $connection, $_user);
  } else {
    $_AccountTo = array('AccountId' => null, 'AccountName' => null);
  }

	if (is_numeric($textstr[2])) {
    $_summa = $textstr[2] * 100;
  } else {
    $_summa = null;
  }

	if (!empty($_summa && $_AccountFrom['AccountId'] && $_AccountFrom['AccountName'] && $_AccountTo['AccountId'] && $_AccountTo['AccountName']) and $_AccountFrom['AccountId'] != $_AccountTo['AccountId']) {
		$message = transaction_transfer($_summa, $_AccountFrom, $_AccountTo, $connection, $_user);
	} else {
		$message = "Bot says,\n";
		$message .= "sorry, the amount has not been transferred,\n";
		$message .= "some data was missing...\n";
		$message .= "Run `/lbw tr X X XXXX.XX` Transfer from ID-account to ID-account amount again.";
	}

	return $message;
}

function transaction_transfer($_summa, $_AccountFrom, $_AccountTo, $connection, $_user) {
	date_default_timezone_set($_user['timezone']);
	$_dates = date('Y-m-d');
	$connection->query('USE '.$_user['namedb']);

	try {
		$connection->beginTransaction();

		$stmt = $connection->prepare('INSERT INTO transfers (dates, amounts, sender_acctid, recipient_acctid) VALUES(:dates, :summa, :idAcctFr, :idAcctTo)');
		$stmt->execute(array(':dates'=>$_dates, ':summa'=>$_summa, ':idAcctFr'=>$_AccountFrom['AccountId'], ':idAcctTo'=>$_AccountTo['AccountId']));
		$stmt->closeCursor();

		update_balance($connection, $_user, $_AccountFrom['AccountId'], $_AccountTo['AccountId']);

		// commit the transaction
		$connection->commit();
		$_summa = number_format($_summa / 100, 2, '.', ',');
		$message = "from " . $_AccountFrom['AccountName'] . " to " . $_AccountTo['AccountName'] . " *" . $_summa . "*, Got it!";
		// $message = "The amount, *" . $_summa . "*, has been transferred.";

	} catch (PDOException $e) {
		$connection->rollBack();
		$result = 'The amount has not been transferred, uid:'.$_user['userid'].', name:'.$_user['username'].': '.$e->getMessage();
		enterlog('slackbot', $result, 'error');
		$message = "Bot says,\n";
		$message .= "sorry, the amount has not been transferred,\n";
		$message .= "some data was missing...\n";
		$message .= "Run `/lbw tr X X XXXX.XX` Transfer from ID-account to ID-account amount again.";
	}

	return $message;
}


function print_income_result($data, $_timezone) {
  $message = null;

	if(!empty($data)) {
		date_default_timezone_set($_timezone);

    $message = "*New Transfer*\n";
		$message .= "name " . $data['username'] . "\n";
		$message .= $data['addTr'] . "\n";
    $message .= date('M d Y');
	}

  return $message;
}

# call from bot command
function accounts_transfer($textstr, $user, $connection) {
	$MsgBody = array();

  if ($user['username'] !== 'R2-D2') {

    $_UserName = $user['username']; $_userdb = $user['namedb'];  $_timezone = $user['timezone'];

		$add_transfer = send_transfer($textstr, $connection, $user);
		$add_transfer = array('addTr' => $add_transfer);


	  $_username = array('username' => $_UserName);
	  $MsgBody = array_merge($add_transfer, $_username);

    if (!empty($add_transfer['addTr'])) {
      $msg = print_income_result($MsgBody, $_timezone);
      if (!empty($msg)) {
        $msg = $msg;
      } else {
				$msg = "Sorry, some data was missing.\n";
	      $msg .= "Run `/lbw tr X X XXXX.XX` Transfer from ID-account to ID-account amount again.";
      }
    } else {
			$msg = "Sorry, some data was missing.\n";
			$msg .= "Run `/lbw tr X X XXXX.XX` Transfer from ID-account to ID-account amount again.";
    }
  } else {
		$msg = "example\n";
		$msg .= "*New Transfer*\n";
		$msg .= "name R2-D2\n";
		$msg .= "The amount, *215.78*, has been transferred.\n";
		$msg .= date('M d Y');
  }

	return $msg;
}
