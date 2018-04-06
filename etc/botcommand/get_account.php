<?php
/*
* Calendar return DateTo DatesBeforeDateFrom DatesFromFirstDay
*/
require_once 'etc/botcommand/get_calendar.php';

function get_wallet($_DatesBeforeDateFrom, $_DatesFromFirstDay, $_DateTo, $connection, $_userdb) {
	$i = 0; $arr_wallet = array();
	$connection->query('USE '.$_userdb);
	$statement = $connection->query('SELECT accountid, anumber, nameaccount FROM accounts WHERE cashclearing = 0');

	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$_accountid =  $row['accountid'];
		$_aname = "`". $_accountid . "` " . $row['nameaccount'] . " " . substr($row['anumber'],-4);

		# balans wallet from last month
		$BalansWallet = get_balancebefore($_DatesBeforeDateFrom, $connection, $_userdb, $_accountid);
		if (empty($BalansWallet)) { $BalansWallet = 0; }

		# total income month
		$totalIncomeWallet = get_incomeresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb, $_accountid);
		# total expense month
		$totalExpensesWallet = get_expenseresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb, $_accountid);

		$BalanseInOutWallet = $BalansWallet + $totalIncomeWallet - $totalExpensesWallet;
		$BalanseInOutWallet = number_format(($BalanseInOutWallet / 100), 2, '.', ',');
    $arr_wallet[$i] = array('NameAccount' => $_aname, 'Summa' => $BalanseInOutWallet);
    $i++;
	}

	return array('wallet' => $arr_wallet);
}

# Accounts defined at database as clearing
function get_account($_DatesBeforeDateFrom, $_DatesFromFirstDay, $_DateTo, $connection, $_userdb) {
	$i = 0; $arr_account = array();
	$connection->query('USE '.$_userdb);
	$statement = $connection->query('SELECT accountid, anumber, nameaccount FROM accounts WHERE cashclearing = 1');

	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$_accountid =  $row['accountid'];
		$_aname = "`". $_accountid . "` " . $row['nameaccount'] . " " . substr($row['anumber'],-4);

		# balans account past month
		$BalansAccount = get_balancebefore($_DatesBeforeDateFrom, $connection, $_userdb, $_accountid);
		if (empty($BalansAccount)) { $BalansAccount = 0; }

		# total income month
		$totalIncome = get_incomeresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb, $_accountid);
		# total expense month
		$totalExpenses = get_expenseresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb, $_accountid);

		$BalanseInOut = $BalansAccount + $totalIncome - $totalExpenses;
		$BalanseInOut = number_format(($BalanseInOut / 100), 2, '.', ',');
    $arr_account[$i] = array('NameAccount' => $_aname, 'Summa' => $BalanseInOut);
    $i++;
	}

	return array('account' => $arr_account);
}

# Accounts defined at database as savings
function get_savings($_DatesBeforeDateFrom, $_DatesFromFirstDay, $_DateTo, $connection, $_userdb) {
	$i = 0; $arr_account = array();
	$connection->query('USE '.$_userdb);
	$statement = $connection->query('SELECT accountid, anumber, nameaccount FROM accounts WHERE cashclearing = 2');

	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$_accountid =  $row['accountid'];
		$_aname = "`". $_accountid . "` " . $row['nameaccount'] . " " . substr($row['anumber'],-4);

		# balans account past month
		$BalansAccount = get_balancebefore($_DatesBeforeDateFrom, $connection, $_userdb, $_accountid);
		if (empty($BalansAccount)) { $BalansAccount = 0; }

		# total income month
		$totalIncome = get_incomeresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb, $_accountid);
		# total expense month
		$totalExpenses = get_expenseresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb, $_accountid);

		$BalanseInOut = $BalansAccount + $totalIncome - $totalExpenses;
		$BalanseInOut = number_format(($BalanseInOut / 100), 2, '.', ',');
    $arr_account[$i] = array('NameAccount' => $_aname, 'Summa' => $BalanseInOut);
    $i++;
	}

	return array('savings' => $arr_account);
}

# common function for wallet, accounts and savings
function get_balancebefore($_DatesBeforeDateFrom, $connection, $_userdb, $_accountid) {
	$Balance = 0;
	$connection->query('USE '.$_userdb);

	$stmt = $connection->prepare('SELECT amounts FROM balancemoney WHERE dates = ? AND account_id = ?');
	$stmt->execute(array($_DatesBeforeDateFrom, $_accountid));
	$Balance = $stmt->fetchColumn();

	return $Balance;
}

# common function for wallet, accounts and savings
function get_incomeresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb, $_accountid) {
	$totalIncome = 0;
	$connection->query('USE '.$_userdb);

	$stmt = $connection->prepare('SELECT SUM(amounts) FROM money_in WHERE dates >= ? AND dates <= ? AND account_id = ?');
	$stmt->execute(array($_DatesFromFirstDay, $_DateTo, $_accountid));
	$totalIncome = $stmt->fetchColumn();

	$totalTransferRecipient = 0;
	$stmt = $connection->prepare('SELECT SUM(amounts) FROM transfers WHERE dates >= ? AND dates <= ? AND recipient_acctid = ?');
	$stmt->execute(array($_DatesFromFirstDay, $_DateTo, $_accountid));
	$totalTransferRecipient = $stmt->fetchColumn();

	$totalTransferSender = 0;
	$stmt = $connection->prepare('SELECT SUM(amounts) FROM transfers WHERE dates >= ? AND dates <= ? AND sender_acctid = ?');
	$stmt->execute(array($_DatesFromFirstDay, $_DateTo, $_accountid));
	$totalTransferSender = $stmt->fetchColumn();

	$totalIncome = $totalIncome + $totalTransferRecipient - $totalTransferSender;

	return $totalIncome;
}
# common function for wallet, accounts and savings
function get_expenseresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb, $_accountid) {
	$totalExpenses = 0;
	$connection->query('USE '.$_userdb);

	$stmt = $connection->prepare('SELECT SUM(amounts) FROM money_out WHERE dates >= ? AND dates <= ? AND account_id = ?');
	$stmt->execute(array($_DatesFromFirstDay, $_DateTo, $_accountid));
	$totalExpenses = $stmt->fetchColumn();

	return $totalExpenses;
}

function print_accounts_result($data, $_timezone) {
  $message = null;

	if(!empty($data)) {
		date_default_timezone_set($_timezone);

    $message = "*Accounts Summary*\n";
		$message .= "name " . $data['username'] . "\n";
    $message .= "*Cash*\n";

		foreach($data['wallet'] as $value) {
      $message .= $value['NameAccount'] . " *" . $value['Summa'] . "*\n";
    }

    $message .= "*Accounts*\n";

    foreach($data['account'] as $value) {
      $message .= $value['NameAccount'] . " *" . $value['Summa'] . "*\n";
    }

		foreach($data['savings'] as $value) {
      $message .= $value['NameAccount'] . " *" . $value['Summa'] . "*\n";
    }

		$message .= date('M d Y');
	}
  return $message;
}

function get_accountdata($user, $connection) {
  $MsgBody = array();

  if ($user['username'] !== 'R2-D2') {

    $_UserName = $user['username']; $_userdb = $user['namedb']; $_timezone = $user['timezone'];

		# it will be month today plus time zone
		$_date = get_calendar($_timezone);
	  $_DateTo = $_date['DateTo']; $_DatesBeforeDateFrom = $_date['DatesBeforeDateFrom']; $_DatesFromFirstDay = $_date['DatesFromFirstDay'];

	  $_wallet = get_wallet($_DatesBeforeDateFrom, $_DatesFromFirstDay, $_DateTo, $connection, $_userdb);
	  $_account = get_account($_DatesBeforeDateFrom, $_DatesFromFirstDay, $_DateTo, $connection, $_userdb);
		$content = array_merge($_wallet, $_account);

		$_savings = get_savings($_DatesBeforeDateFrom, $_DatesFromFirstDay, $_DateTo, $connection, $_userdb);
		$content = array_merge($content, $_savings);

	  $_username = array('username' => $_UserName);
	  $MsgBody = array_merge($content, $_username);

    if (!empty($content)) {
      $msg = print_accounts_result($MsgBody, $_timezone);
      if (!empty($msg)) {
        $msg = $msg;
      } else {
				$msg = "Sorry, some data was missing.\n";
	      $msg .= "Run `/lbw account` again.";
      }
    } else {
			$msg = "Sorry, some data was missing.\n";
			$msg .= "Run `/lbw account` again.";
    }
  } else {
		$msg = "example\n";
		$msg .= "*Accounts Summary*\n";
		$msg .= "name R2-D2\n";
		$msg .= "*Cash*\n";
		$msg .= "wallet R2 *0.00*\n";
		$msg .= "wallet D2 *3.00*\n";
		$msg .= "*Accounts*\n";
		$msg .= "Cheking 7979 *1,130.00*\n";
		$msg .= "Liquid 9292 *12.62*\n";
		$msg .= "Credit_R2 5995 *0.00*\n";
		$msg .= "Credit_D2 9696 *0.00*\n";
		$msg .= date('M d Y');
  }

	return $msg;
}
