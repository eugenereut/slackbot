<?php
# Type log
function enterlog($user, $result, $typelog) {
  $rip = $_SERVER['REMOTE_ADDR'];
  $lip = $_SERVER['HTTP_USER_AGENT'];
  date_default_timezone_set('UTC');
  $times = date("m d H:i:s");

  $str = 't:'.$times.', u:'.$user.', ip:'.$rip.', agnt:'.$lip.', rslt:"'.$result.'"';

  $_datelog = date('M-Y');

  if ($typelog == 'error') {
    $file_name = '/var/log/lightburroway/slack/'.$_datelog.'_error.log';
  } else {
    $file_name = '/var/log/lightburroway/slack/'.$_datelog.'_enter.log';
  }

  if (file_exists($file_name) && is_writeable ($file_name)) {
    # Processing
    $fh = fopen($file_name, 'a-');
    fputs($fh,$str."\r\n");
    fclose($fh);
  } else  {
    $fh = fopen($file_name, 'w+');
    fputs($fh, $str."\r\n");
    fclose($fh);
  }
}
