<?php

function get_nameincome($connection, $_userdb) {
	$i = 0; $j = 0; $arr_listin = array();

	$connection->query('USE '.$_userdb);

	$statement =  $connection->query('SELECT listincomeid, nameincome FROM listincome WHERE visible = 1');

	while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$_idname = $row['listincomeid'];
		$_sublist = null; $array_count = null;

		$stmt = $connection->prepare('SELECT sub_listincomeid, sub_name FROM sub_listincome WHERE listincome_id = ?');
		$stmt->execute([$_idname]);

		$array_count = $stmt->rowCount();

		while ($subrow = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$j++;
			if ($j != $array_count) {
				$_sublist  .= '`'.$subrow['sub_listincomeid'].'` '.$subrow['sub_name'].', ';
			} else {
				$_sublist  .= '`'.$subrow['sub_listincomeid'].'` '.$subrow['sub_name'];
			}
		}
		$j = 0;

		$arr_listin[$i] = array('NameIncome'=>$row['nameincome'], 'SubName'=>$_sublist);
		$i++;
	}

	return array('listin' => $arr_listin);
}

function print_income_result($data) {
  $message = null;

	if(!empty($data)) {

    $message = "*List Incoming*\n";
		$message .= "name " . $data['username'] . "\n";

    foreach($data['listin'] as $value) {
      $message .= "*".$value['NameIncome'].":* ".$value['SubName'] . "\n";
    }
	}
  return $message;
}

# call from bot command
function get_listincomingdata($user, $connection) {
	$MsgBody = array();

  if ($user['username'] !== 'R2-D2') {

    $_UserName = $user['username']; $_userdb = $user['namedb'];

		$all_nameincome = get_nameincome($connection, $_userdb);

	  $_username = array('username' => $_UserName);
	  $MsgBody = array_merge($all_nameincome, $_username);

    if (!empty($all_nameincome)) {
      $msg = print_income_result($MsgBody);
      if (!empty($msg)) {
        $msg = $msg;
      } else {
				$msg = "Sorry, some data was missing.\n";
	      $msg .= "Run `/lbw list in` again.";
      }
    } else {
			$msg = "Sorry, some data was missing.\n";
			$msg .= "Run `/lbw list in` again.";
    }
  } else {
		$msg = "example\n";
		$msg .= "*List Incoming*\n";
		$msg .= "name R2-D2\n";
		$msg .= "*money:* `1` salary R2, `2` salary D2\n";
		$msg .= "*bank deposit:* `3` interest payment\n";
  }

	return $msg;
}
