<?php

function get_calendar($_timezone)
{
	date_default_timezone_set($_timezone);

	$_year = date('Y');
	$_month = date('m');
	# put month on first day
	$_DatesFromFirstDay = strtotime($_year.$_month.'01');
	$_DatesFromFirstDay = date('Y-m-d', $_DatesFromFirstDay);

	$daymonth =  cal_days_in_month(CAL_GREGORIAN, $_month, $_year);
	$_DateTo = strtotime($_year.$_month.$daymonth);
	$_DateTo = date('Y-m-d', $_DateTo);

	# find date before $_DateFrom
	if($_month == '01')
	{
		$_year = $_year - 1;
	}
	$_month = date('m', strtotime($_year.$_month.'01 -1 month'));

	$daymonth =  cal_days_in_month(CAL_GREGORIAN, $_month, $_year);
	$_DatesBeforeDateFrom = strtotime($_year.$_month.$daymonth);
	$_DatesBeforeDateFrom = date('Y-m-d', $_DatesBeforeDateFrom);

	return array('DateTo' => $_DateTo, 'DatesBeforeDateFrom' => $_DatesBeforeDateFrom, 'DatesFromFirstDay' => $_DatesFromFirstDay);
}
