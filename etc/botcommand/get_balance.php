<?php
/*
* Calendar return DateTo DatesBeforeDateFrom DatesFromFirstDay
*/
require_once 'etc/botcommand/get_calendar.php';

function getTotal($_DatesFromFirstDay, $_DateTo, $_DatesBeforeDateFrom, $connection, $_userdb) {
	$_BalanceBefore = 0;	$BalanceInOut = 0; $BalanceAsLi = 0;	$IndexPM = 0;

	$connection->query('USE '.$_userdb);

	# define a variable for the main page
	$TotalIn = 0; $TotalEx = 0; $TotalAs = 0; $TotalLi = 0;

	# Balans from last month
	$_BalanceBefore = get_balancebefore($_DatesBeforeDateFrom, $connection, $_userdb);

	# total summs incomes
	$Total_InTransfer = get_incomeresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb);

	$TotalIn = $Total_InTransfer[0];
	$TotalTransfer = $Total_InTransfer[1];

	# total summs incomes
	$stmt = $connection->prepare('SELECT SUM(amounts) FROM money_out WHERE dates >= ? AND dates <= ?');
	$stmt->execute(array($_DatesFromFirstDay, $_DateTo));
	$TotalEx = $stmt->fetchColumn();

	$stmt = $connection->query('SELECT SUM(amounts) FROM assets WHERE visible = 1');
	$TotalAs = $stmt->fetchColumn();

	$stmt = $connection->query('SELECT SUM(amounts) FROM liabilities WHERE visible = 1');
	$TotalLi = $stmt->fetchColumn();

	$BalanceInOut = $_BalanceBefore + $TotalIn + $TotalTransfer - $TotalEx;
	$BalanceAsLi = $TotalAs - $TotalLi;

	if ($TotalIn > 0) {
		$IndexPM = ($BalanceInOut-$TotalLi)/$TotalIn;
	} else {
		$IndexPM = 0;
	}

	$IndexPM = number_format($IndexPM, 2, '.', ',');
	$_BalanceBefore = number_format(($_BalanceBefore / 100), 2, '.', ',');
	$BalanceAsLi = number_format(($BalanceAsLi / 100), 2, '.', ',');
	$BalanceInOut = number_format(($BalanceInOut / 100), 2, '.', ',');
	$TotalIn = number_format(($TotalIn / 100), 2, '.', ',');
	$TotalTransfer = number_format(($TotalTransfer / 100), 2, '.', ',');
	$TotalAs = number_format(($TotalAs / 100), 2, '.', ',');
	$TotalEx = number_format(($TotalEx / 100), 2, '.', ',');
	$TotalLi = number_format(($TotalLi / 100), 2, '.', ',');

	return array('BalanceInOut' => $BalanceInOut, 'BalanceAsLi' => $BalanceAsLi, 'BalanceBefore' => $_BalanceBefore,
	'IndexPM' => $IndexPM, 'totalIncome' => $TotalIn,  'totalTransfer' => $TotalTransfer,	'totalExpense' => $TotalEx, 'totalAsset' => $TotalAs, 'totalLiabilities' => $TotalLi);
}

# return Balans from last month
function get_balancebefore($_DatesBeforeDateFrom, $connection, $_userdb) {
	$_BalansBefore =0;
	$connection->query('USE '.$_userdb);

	# Balans from last month
	$statement = $connection->query('SELECT accountid FROM accounts WHERE cashclearing != 2');
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$_accountid =  $row['accountid'];

		$stmt = $connection->prepare('SELECT SUM(amounts) FROM balancemoney WHERE dates = ? AND account_id = ?');
		$stmt->execute(array($_DatesBeforeDateFrom, $_accountid));
		$_BalansBefore += $stmt->fetchColumn();
	}
	$stmt->closeCursor();

	return $_BalansBefore;
}

# return total summs incomes, transfers
function get_incomeresults($_DatesFromFirstDay, $_DateTo, $connection, $_userdb) {
	$TotalIn = 0; $TotalTransfer = 0;
	$connection->query('USE '.$_userdb);

	$statement = $connection->query('SELECT accountid FROM accounts WHERE cashclearing != 2');
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$_accountid =  $row['accountid'];

		$stmt = $connection->prepare('SELECT SUM(amounts) FROM money_in WHERE dates >= ? AND dates <= ? AND account_id = ?');
		$stmt->execute(array($_DatesFromFirstDay, $_DateTo, $_accountid));
		$TotalIn += $stmt->fetchColumn();
		$stmt->closeCursor();

		$_total_transfer_recipient = 0;
		$stmt = $connection->prepare('SELECT SUM(amounts) FROM transfers WHERE dates >= ? AND dates <= ? AND recipient_acctid = ?');
		$stmt->execute(array($_DatesFromFirstDay, $_DateTo, $_accountid));
		$_total_transfer_recipient = $stmt->fetchColumn();

		$_total_transfer_sender = 0;
		$stmt = $connection->prepare('SELECT SUM(amounts) FROM transfers WHERE dates >= ? AND dates <= ? AND sender_acctid = ?');
		$stmt->execute(array($_DatesFromFirstDay, $_DateTo, $_accountid));
		$_total_transfer_sender = $stmt->fetchColumn();
		$stmt->closeCursor();

		$TotalTransfer += $_total_transfer_recipient - $_total_transfer_sender;
	}

	return  array($TotalIn, $TotalTransfer);;
}

function print_balance_result($data, $_timezone) {
  $message = null;

	if(!empty($data)) {
		date_default_timezone_set($_timezone);

    $message = "*Balance Summary*\n";
		$message .= "name " . $data['username'] . "\n";
		$message .= "index *" . $data['IndexPM'] . "*\n";
		$message .= "balance in/out *" . $data['BalanceInOut'] . "*\n";
    $message .= "incoming *" . $data['totalIncome'] . "*\n";

		if ($data['totalTransfer'] < 0) {
			$message .= "transfers to savings *" . $data['totalTransfer'] . "*\n";
		} elseif ($data['totalTransfer'] > 0) {
			$message .= "transfers from savings *" . $data['totalTransfer'] . "*\n";
		}

		$message .= "outgoing *" . $data['totalExpense'] . "*\n";
		$message .= "balance as/li *" . $data['BalanceAsLi'] . "*\n";
		$message .= "assets *" . $data['totalAsset'] . "*\n";
		$message .= "liabilities *" . $data['totalLiabilities'] . "*\n";
    $message .= date('M d Y') . "\n";
		$message .= "end of month *" . $data['BalanceBefore'] . "*";
	}
  return $message;
}

function get_balancedata($user, $connection) {
	$MsgBody = array();

  if ($user['username'] !== 'R2-D2') {

    $_UserName = $user['username']; $_userdb = $user['namedb']; $_timezone = $user['timezone'];

		# it will be month today plus time zone
	  $_date = get_calendar($_timezone);
	  $_DateTo = $_date['DateTo']; $_DatesBeforeDateFrom = $_date['DatesBeforeDateFrom']; $_DatesFromFirstDay = $_date['DatesFromFirstDay'];

		$allTotal = getTotal($_DatesFromFirstDay, $_DateTo, $_DatesBeforeDateFrom, $connection, $_userdb);

	  $_username = array('username' => $_UserName);
	  $MsgBody = array_merge($allTotal, $_username);

    if (!empty($allTotal)) {
      $msg = print_balance_result($MsgBody, $_timezone);
      if (!empty($msg)) {
        $msg = $msg;
      }
      else {
				$msg = "Sorry, some data was missing.\n";
	      $msg .= "Run `/lbw balance` again.";
      }
    }
    else {
			$msg = "Sorry, some data was missing.\n";
			$msg .= "Run `/lbw balance` again.";
    }
  }
  else {
		$msg = "example\n";
		$msg .= "*Balance Summary*\n";
		$msg .= "name R2-D2\n";
		$msg .= "index *6.70*\n";
		$msg .= "balance in/out *1,445.66*\n";
		$msg .= "incoming *215.78*\n";
		$msg .= "outgoing *270.36*\n";
		$msg .= "balance as/li *7.09*\n";
		$msg .= "assets *7.09*\n";
		$msg .= "liabilities *0.00*\n";
		$msg .= date('M d Y') . "\n";
		$msg .= "end of month *1,500.24*";
  }

	return $msg;
}
