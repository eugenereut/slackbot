<?php

function update_balance($connection, $_user, $_accountidfirst, $_accountidsecond) {
	date_default_timezone_set($_user['timezone']);
	# From
	$_month_from = date('m');
	$_year_from = date('Y');
	$_dates_from = strtotime($_year_from.$_month_from.'01');
	$_dates_from = date('Y-m-d', $_dates_from);

	# To
	$_month_to = date('m');
	$_year_to = date('Y');
	$dayofmonth_to = cal_days_in_month(CAL_GREGORIAN, $_month_to, $_year_to);
	$_dates_to = strtotime($_year_to.$_month_to.$dayofmonth_to);
	$_dates_to = date('Y-m-d', $_dates_to);

	# month before DateFrom
	$_beforemonth_from = date('m', strtotime($_dates_from.' -1 month'));
	$dayofmonth = cal_days_in_month(CAL_GREGORIAN, $_beforemonth_from, $_year_from);
	$_beforedates_from = strtotime($_year_from.$_beforemonth_from.$dayofmonth);
	$_beforedates_from = date('Y-m-d', $_beforedates_from);

	if ($_accountidsecond == 0) {
		accountbalance ($_beforedates_from, $_dates_from, $_dates_to, $_accountidfirst, $connection, $_user);
	} elseif ($_accountidfirst == $_accountidsecond) {
		accountbalance ($_beforedates_from, $_dates_from, $_dates_to, $_accountidfirst, $connection, $_user);
	} else {
		accountbalance ($_beforedates_from, $_dates_from, $_dates_to, $_accountidfirst, $connection, $_user);
		accountbalance ($_beforedates_from, $_dates_from, $_dates_to, $_accountidsecond, $connection, $_user);
	}
}

function accountbalance ($_beforedates_from, $_dates_from, $_dates_to, $_accountid, $connection, $_user) {
	# date_default_timezone_set($_user['timezone']);
	$connection->query('USE '.$_user['namedb']);

	# Balance update for single account
	$stmt = $connection->prepare('SELECT SUM(amounts) FROM money_in WHERE dates >= ? AND dates <= ? AND account_id = ?');
	$stmt->execute(array($_dates_from, $_dates_to, $_accountid));
	$_total_income_account = $stmt->fetchColumn();
	$stmt->closeCursor();

	$stmt = $connection->prepare('SELECT SUM(amounts) FROM transfers WHERE dates >= ? AND dates <= ? AND recipient_acctid = ?');
	$stmt->execute(array($_dates_from, $_dates_to, $_accountid));
	$_total_transfer_recipient = $stmt->fetchColumn();
	$stmt->closeCursor();

	$stmt = $connection->prepare('SELECT SUM(amounts) FROM transfers WHERE dates >= ? AND dates <= ? AND sender_acctid = ?');
	$stmt->execute(array($_dates_from, $_dates_to, $_accountid));
	$_total_transfer_sender = $stmt->fetchColumn();
	$stmt->closeCursor();

	$_total_income_account = $_total_income_account + $_total_transfer_recipient - $_total_transfer_sender;

	$stmt = $connection->prepare('SELECT SUM(amounts) FROM money_out WHERE dates >= ? AND dates <= ? AND account_id = ?');
	$stmt->execute(array($_dates_from, $_dates_to, $_accountid));
	$_total_expenses_account = $stmt->fetchColumn();
	$stmt->closeCursor();

	$stmt = $connection->prepare('SELECT amounts FROM balancemoney WHERE account_id = ? AND dates = ?');
	$stmt->execute(array($_accountid, $_beforedates_from));
	$rs = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt->closeCursor();

	if ($rs) {
		$BalanceAccountBeforeMonth = $rs["amounts"];
	} else {
		$BalanceAccountBeforeMonth = 0;
	}

	$BalanceInOutAccount = $BalanceAccountBeforeMonth + ($_total_income_account - $_total_expenses_account);

	$stmt = $connection->prepare('SELECT amounts FROM balancemoney WHERE account_id = ? AND dates = ?');
	$stmt->execute(array($_accountid, $_dates_to));
	$rs = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($rs) {
		$stmt = $connection->prepare('UPDATE balancemoney SET amounts = ? WHERE account_id = ? AND dates = ?');
		$stmt->execute(array($BalanceInOutAccount, $_accountid, $_dates_to));
	}
	else {
		$stmt = $connection->prepare('INSERT INTO balancemoney (dates, amounts, account_id) VALUES(:endmonth, :binouta, :aid)');
		$stmt->execute(array(':endmonth'=>$_dates_to, ':binouta'=>$BalanceInOutAccount, ':aid'=>$_accountid));
	}
}
