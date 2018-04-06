<?php
/*
* Calendar return DateTo DatesBeforeDateFrom DatesFromFirstDay
*/
require_once 'etc/botcommand/get_calendar.php';

function get_expense($_DatesFromFirstDay, $_DateTo, $connection, $_userdb) {
	$i = 0; $totalExpenses = 0; $arr_expense = array();

	$connection->query('USE '.$_userdb);

	$statement = $connection->query('SELECT listexpensesid, namexpenses FROM listexpenses');

	while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$_idname = $row['listexpensesid']; $_NameExpenses = $row['namexpenses'];
		$totalExpenses_id = 0;

		$stmt = $connection->prepare('SELECT SUM(amounts) FROM money_out WHERE listexpenses_id = ? AND dates >= ? AND dates <= ?');
		$stmt->execute(array($_idname, $_DatesFromFirstDay, $_DateTo));
		$totalExpenses_id = $stmt->fetchColumn();

		if($totalExpenses_id > 0) {
			$totalExpenses += $totalExpenses_id;

			$totalExpenses_id = $totalExpenses_id / 100;
			$totalExpenses_id = number_format($totalExpenses_id, 2, '.', ',');

			$arr_expense[$i] = array('NameExpense' => $_NameExpenses, 'totalExId' => $totalExpenses_id);
			$i++;
		}
	}

	$totalExpenses = number_format(($totalExpenses / 100), 2, '.', ',');

	return array('expense' => $arr_expense, 'totalEx' => $totalExpenses);
}

function print_expense_result($data, $_timezone) {
  $message = null;

	if(!empty($data))
	{
		date_default_timezone_set($_timezone);

    $message = "*Outgoing*\n";
    $message .= "name " . $data['username'] . "\n";
		$message .= "total *" . $data['totalEx'] . "*\n";
    foreach($data['expense'] as $value)
    {
      $message .= $value['NameExpense']." *".$value['totalExId'] . "*\n";
    }
    $message .= date('M d Y');
	}
  return $message;
}

function get_expensedata($user, $connection) {
	$MsgBody = array();

  if ($user['username'] !== 'R2-D2') {

    $_UserName = $user['username']; $_userdb = $user['namedb']; $_timezone = $user['timezone'];

	  $_date = get_calendar($_timezone);
	  $_DateTo = $_date['DateTo']; $_DatesBeforeDateFrom = $_date['DatesBeforeDateFrom']; $_DatesFromFirstDay = $_date['DatesFromFirstDay'];

		$all_expense = get_expense($_DatesFromFirstDay, $_DateTo, $connection, $_userdb);

	  $_username = array('username' => $_UserName);
	  $MsgBody = array_merge($all_expense, $_username);

    if (!empty($all_expense)) {
      $msg = print_expense_result($MsgBody, $_timezone);
      if (!empty($msg)) {
        $msg = $msg;
      } else {
				$msg = "Sorry, some data was missing.\n";
	      $msg .= "Run `/lbw out` again.";
      }
    } else {
			$msg = "Sorry, some data was missing.\n";
      $msg .= "Run `/lbw out` again.";
    }
  } else {
		$msg = "example\n";
		$msg .= "*Outgoing*\n";
		$msg .= "name R2-D2\n";
		$msg .= "total *270.36*\n";
		$msg .= "food/cafe *90.48*\n";
		$msg .= "personal care *134.00*\n";
		$msg .= "gifts and donations *45.88*\n";
		$msg .= date('M d Y');
  }

	return $msg;
}
