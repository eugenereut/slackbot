<?php
/*
* Calendar return DateTo DatesBeforeDateFrom DatesFromFirstDay
*/
require_once 'etc/botcommand/get_calendar.php';
require_once 'etc/botcommand/update_accountbalance.php';
require_once 'etc/botcommand/slack_enterlog.php';

function get_description($_num, $connection, $_user) {
	$_comment = array('Description' => null, 'SourceMoney' => null, 'SourceMoneyName' => null);
	$connection->query('USE '.$_user['namedb']);

	$stmt = $connection->prepare('SELECT sub_listincome.listincome_id, sub_listincome.sub_name, listincome.listincomeid, listincome.nameincome FROM sub_listincome, listincome
																WHERE sub_listincome.sub_listincomeid = ? AND sub_listincome.listincome_id = listincome.listincomeid AND listincome.visible = 1');
	$stmt->execute([$_num]);

	if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$_comment = array('Description' => $row['sub_name'], 'SourceMoney' => $row['listincome_id'], 'SourceMoneyName' => $row['nameincome']);
	} else {
		$_comment = array('Description' => null, 'SourceMoney' => null, 'SourceMoneyName' => null);
	}

	return $_comment;
}

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

function send_income($textstr, $connection, $_user) {
	# get string after 6 chars: add in
	$textstr = substr($textstr, 6);
	$textstr = test_input($textstr);
	$textstr = explode(" ", $textstr);

	if (is_numeric($textstr[0])) {
    $_comment = get_description($textstr[0], $connection, $_user);
  } else {
    $_comment = array('Description' => null, 'SourceMoney' => null, 'SourceMoneyName' => null);
  }

	if (is_numeric($textstr[1])) {
    $_account = get_accountid($textstr[1], $connection, $_user);
  } else {
    $_account = array('AccountId' => null, 'AccountName' => null);
  }

	if (is_numeric($textstr[2])) {
    $_summa = $textstr[2] * 100;
		if ($_summa <= 0) {
			$_summa = null;
		}
  } else {
    $_summa = null;
  }

	if (!empty($_comment['Description'] && $_summa && $_comment['SourceMoney'] && $_comment['SourceMoneyName'] && $_account['AccountId'] && $_account['AccountName'])) {
		$message = transaction_income($_comment, $_summa, $_account, $connection, $_user);
	} else {
		$message = "Bot says,\n";
		$message .= "sorry, the amount has not been added,\n";
		$message .= "some data was missing...\n";
		$message .= "Run `/lbw add in ID-name ID-account XXXX.XX` again.";
	}

	return $message;
}

function transaction_income($_comment, $_summa, $_account, $connection, $_user) {
	date_default_timezone_set($_user['timezone']);
	$_dates = date('Y-m-d');
	$connection->query('USE '.$_user['namedb']);

	try {
		$connection->beginTransaction();

		$stmt = $connection->prepare('INSERT INTO money_in (dates, description, amounts, listincome_id, account_id) VALUES (:dates, :comment, :summa, :srsm, :idAcct)');
		$stmt->execute(array(':dates'=>$_dates, ':comment'=>$_comment['Description'], ':summa'=>$_summa, ':srsm'=>$_comment['SourceMoney'], ':idAcct'=>$_account['AccountId']));
		$stmt->closeCursor();

		# if asset_id in list income > 0 then income from asset
		update_amounts_inasset($connection, $_summa, $_comment['SourceMoney']);
		update_balance($connection, $_user, $_account['AccountId'], 0);

		# commit the transaction
		$connection->commit();
		$_summa = number_format($_summa / 100, 2, '.', ',');

		$message = "*" . $_comment['SourceMoneyName'] . "* " . $_comment['Description'] . " " . $_account['AccountName'] . " *" . $_summa . "*, Got it!";

	} catch (PDOException $e) {
		$connection->rollBack();
		$result = 'The amount has not been added, uid:'.$_user['userid'].', name:'.$_user['username'].': '.$e->getMessage();
		enterlog('slackbot', $result, 'error');
		$message = "Bot says,\n";
		$message .= "sorry, the amount has not been added,\n";
		$message .= "some data was missing...\n";
		$message .= "Run `/lbw add in ID-name ID-account XXXX.XX` again.";
	}

	return $message;
}

function update_amounts_inasset($connection, $_summa, $_source_money) {
	# if asset_id in list income > 0 then income from asset
	$stmt = $connection->prepare('SELECT asset_id FROM listincome WHERE listincomeid = :idname');
	$stmt->execute([':idname' => $_source_money]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$_idAsset = $row['asset_id'];
	$stmt->closeCursor();

	if ($_idAsset > 0) {
		$stmt = $connection->prepare('SELECT amounts FROM assets WHERE assetid = :idAsset');
		$stmt->execute([':idAsset' => $_idAsset]);
		$rs = $stmt->fetch(PDO::FETCH_ASSOC);
		$_amouts = $rs['amounts'] - $_summa;

		$stmt = $connection->prepare('UPDATE assets SET amounts = :amouts WHERE assetid = :idAsset');
		$stmt->execute(array(':amouts' => $_amouts, ':idAsset' => $_idAsset));
		$stmt->closeCursor();
	}
}

function print_income_result($data, $_timezone) {
  $message = null;

	if(!empty($data)) {
		date_default_timezone_set($_timezone);

    $message = "*New Incoming*\n";
		$message .= "name " . $data['username'] . "\n";
		$message .= $data['addIn'] . "\n";
    $message .= date('M d Y');
	}

  return $message;
}

# call from bot command
function getin_incomingdata($textstr, $user, $connection) {
	$MsgBody = array();

  if ($user['username'] !== 'R2-D2') {

    $_UserName = $user['username']; $_userdb = $user['namedb'];  $_timezone = $user['timezone'];

		$add_income = send_income($textstr, $connection, $user);
		$add_income = array('addIn' => $add_income);


	  $_username = array('username' => $_UserName);
	  $MsgBody = array_merge($add_income, $_username);

    if (!empty($add_income['addIn'])) {
      $msg = print_income_result($MsgBody, $_timezone);
      if (!empty($msg)) {
        $msg = $msg;
      } else {
				$msg = "Sorry, some data was missing.\n";
	      $msg .= "Run `/lbw add in ID-name ID-account XXXX.XX` again.";
      }
    } else {
			$msg = "Sorry, some data was missing.\n";
			$msg .= "Run `/lbw add in ID-name ID-account XXXX.XX` again.";
    }
  } else {
		$msg = "example\n";
		$msg .= "*New Incoming*\n";
		$msg .= "name R2-D2\n";
		$msg .= "The amount, *215.78*, has been added.\n";
		$msg .= date('M d Y');
  }

	return $msg;
}
