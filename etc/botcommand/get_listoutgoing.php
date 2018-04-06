<?php

function get_nameexpense($connection, $_userdb) {
	$i = 0; $j = 0; $arr_listout = array();

	$connection->query('USE '.$_userdb);

	$statement = $connection->query('SELECT listexpensesid, namexpenses FROM listexpenses WHERE visible = 1');

	while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$_idname = $row['listexpensesid'];
		$_sublist = null; $array_count = null;

		$stmt = $connection->prepare('SELECT sub_listexpensesid, sub_name FROM sub_listexpenses WHERE listexpenses_id = ?');
		$stmt->execute([$_idname]);

		$array_count = $stmt->rowCount();

		while ($subrow = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$j++;
			if ($j != $array_count) {
				$_sublist  .= '`'.$subrow['sub_listexpensesid'].'` '.$subrow['sub_name'].', ';
			} else {
				$_sublist  .= '`'.$subrow['sub_listexpensesid'].'` '.$subrow['sub_name'];
			}
		}
		$j = 0;

		$arr_listout[$i] = array('NameExpense'=>$row['namexpenses'], 'SubName'=>$_sublist);
		$i++;
	}

	return array('listout' => $arr_listout);
}

function print_expense_result($data) {
  $message = null;

	if(!empty($data)) {

    $message = "*List Outgoing*\n";
    $message .= "name " . $data['username'] . "\n";

    foreach($data['listout'] as $value) {
      $message .= "*".$value['NameExpense'].":* ".$value['SubName'] . "\n";
    }
	}
  return $message;
}

function get_listexpensedata($user, $connection) {
	$MsgBody = array();

  if ($user['username'] !== 'R2-D2') {

    $_UserName = $user['username']; $_userdb = $user['namedb'];

		$all_expense = get_nameexpense($connection, $_userdb);

	  $_username = array('username' => $_UserName);
	  $MsgBody = array_merge($all_expense, $_username);

    if (!empty($all_expense)) {
      $msg = print_expense_result($MsgBody);
      if (!empty($msg)) {
        $msg = $msg;
      } else {
				$msg = "Sorry, some data was missing.\n";
	      $msg .= "Run `/lbw list out` again.";
      }
    } else {
			$msg = "Sorry, some data was missing.\n";
      $msg .= "Run `/lbw list out` again.";
    }
  } else {
		$msg = "example\n";
		$msg .= "*List Outgoing*\n";
		$msg .= "name R2-D2\n";
		$msg .= "*housing:* `1` rent, `2` phone, `3` electricity, `4` gas, `5` water, `6` cable, `7` waste removal, `8` maintenance or repairs, `9` supplies\n";
		$msg .= "*transportation:* `10` vehicle payment, `11` bus/train/ferry, `12` insurance, `13` licensing, `14` fuel, `15` maintenance, `45` parking\n";
		$msg .= "*insurance:* `16` home, `17` health, `18` life\n";
		$msg .= "*food/cafe:* `19` groceries, `20` dining out\n";
		$msg .= "...";
  }

	return $msg;
}
