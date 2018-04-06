<?php
/*
* Calendar return DateTo DatesBeforeDateFrom DatesFromFirstDay
*/
require_once 'etc/botcommand/get_calendar.php';

function get_income($_DatesFromFirstDay, $_DateTo, $connection, $_userdb) {
	$i = 0; $totalIncome = 0; $arr_income = array();

	$connection->query('USE '.$_userdb);

	$statement =  $connection->query('SELECT listincomeid, nameincome FROM listincome');

	while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$_idname = $row['listincomeid']; $_NameIncome = $row['nameincome'];
		$totalIncome_id = 0;

		$stmt = $connection->prepare('SELECT SUM(amounts) FROM money_in WHERE listincome_id = ? AND dates >= ? AND dates <= ?');
		$stmt->execute(array($_idname, $_DatesFromFirstDay, $_DateTo));
		$totalIncome_id = $stmt->fetchColumn();

		if ($totalIncome_id > 0) {
			$totalIncome += $totalIncome_id;

			$totalIncome_id = number_format(($totalIncome_id / 100), 2, '.', ',');

			$arr_income[$i] = array('NameIncome' => $_NameIncome, 'totalInId' => $totalIncome_id);
			$i++;
		}
	}

	$totalIncome = $totalIncome / 100;
	$totalIncome = number_format($totalIncome, 2, '.', ',');

	return array('income' => $arr_income, 'totalIn' => $totalIncome);
}

function print_income_result($data, $_timezone) {
  $message = null;

	if(!empty($data)) {
		date_default_timezone_set($_timezone);

    $message = "*Incoming*\n";
		$message .= "name " . $data['username'] . "\n";
		$message .= "total *" . $data['totalIn'] . "*\n";

    foreach($data['income'] as $value) {
      $message .= $value['NameIncome']." *".$value['totalInId'] . "*\n";
    }
    $message .= date('M d Y');
	}
  return $message;
}

# call from bot command
function get_incomingdata($user, $connection) {
	$MsgBody = array();

  if ($user['username'] !== 'R2-D2') {

    $_UserName = $user['username']; $_userdb = $user['namedb']; $_timezone = $user['timezone'];

	  $_date = get_calendar($_timezone);
	  $_DateTo = $_date['DateTo']; $_DatesBeforeDateFrom = $_date['DatesBeforeDateFrom']; $_DatesFromFirstDay = $_date['DatesFromFirstDay'];

		$all_income = get_income($_DatesFromFirstDay, $_DateTo, $connection, $_userdb);

	  $_username = array('username' => $_UserName);
	  $MsgBody = array_merge($all_income, $_username);

    if (!empty($all_income)) {
      $msg = print_income_result($MsgBody, $_timezone);
      if (!empty($msg)) {
        $msg = $msg;
      } else {
				$msg = "Sorry, some data was missing.\n";
	      $msg .= "Run `/lbw in` again.";
      }
    } else {
			$msg = "Sorry, some data was missing.\n";
			$msg .= "Run `/lbw in` again.";
    }
  } else {
		$msg = "example\n";
		$msg .= "*Incoming*\n";
		$msg .= "name R2-D2\n";
		$msg .= "total *215.78*\n";
		$msg .= "money *215.78*\n";
		$msg .= date('M d Y');
  }

	return $msg;
}
