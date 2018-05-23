<?php
	session_start();
	//set defaul timezone
	date_default_timezone_set("America/New_York");
	
	//get startTime from request
	$startTimeStr = $_REQUEST['startTime'];

	//get endTime from request
	$endTimeStr = $_REQUEST['endTime'];
	
	//get JSON data from request
	$seasonJson	= $_REQUEST['seasonjson'];
	
	//convert JSON to PHP array format
	$seasonPeriods = json_decode($seasonJson,true);
	
	//create DateTime object from startTime&endTime(Format=YYYY-MM-DD HH:MM:SS.000000)
	$startTimePHP = date_create_from_format("Y-m-d?H:i:s.u*",$startTimeStr);
	$endTimePHP = date_create_from_format("Y-m-d?H:i:s.u*",$endTimeStr);

	//input check
	if(($startTimePHP == false) || ($endTimePHP == false)) 
	{
		displayHtmlPage("Please enter the right startTime and endTime(YYYY-MM-DDTHH:MM:SS.nnnZ)!", true);
		exit();
	}

	//input check
	if($startTimePHP > $endTimePHP) 
	{
		displayHtmlPage("endTime must be greater than startTime!", true);
		exit();
	}
	
	//call customerTouPeriodsAPI
	$responseList = customerTouPeriodsAPI($startTimePHP,$endTimePHP,$seasonPeriods);
	
	session_destroy();
	
/********************************************************************************
 * Name: customerTouPeriodsAPI
 * Function: create a list of response JSON objects
 *******************************************************************************/
function customerTouPeriodsAPI($startTime,$endTime,$seasonPeriods)
{
	//get season index of startTime
	$seasonNo = getstartTimeSeason($startTime,$endTime,$seasonPeriods);

	//get periods of startTime 
	$startTimePeriodsNo = getstartTimePeriods($startTime,$endTime,$seasonPeriods[$seasonNo]);

	//construct output JSON
    $jsonObject = makeResponseJSONList($seasonNo,$startTimePeriodsNo,$startTime,$endTime,$seasonPeriods);

    //display response JSON
    displayJSONdatas($jsonObject);    
}

/********************************************************************************
 * Name: makeResponseJSONList
 * Function: convert the response to JSON format
 *******************************************************************************/
function makeResponseJSONList($seasonNo,$startTimePeriodsNo,$startTime,$endTime,$seasonPeriods) 
{
	//initialize array index of response JOSN list
	$index = 0;
	
	//set startTime String
	$startTimeStr = $_REQUEST['startTime'];
	
	//set endTime String
	$endTimeStr = $_REQUEST['endTime'];
	
	//set periods index
	$periodsNo = $startTimePeriodsNo;

	//set loop finish flag
	$finishFlag = false;
	
	//get current season date
	$seasonStartMonth = $seasonPeriods[$seasonNo]["startMonth"];
	$seasonStartDay	= $seasonPeriods[$seasonNo]["startDay"];
	$seasonEndMonth	= $seasonPeriods[$seasonNo]["endMonth"];
	$seasonEndDay = $seasonPeriods[$seasonNo]["endDay"];
	
	//handle each periods from startTime to endTime
	do {
		
		//set JSON List: startTime(for example: "2017-09-01T09:00:00.000Z")
		$responseJOSN[$index]["startTime"] = $startTimeStr;
		
		//get time from JSON toTime
		if (($seasonPeriods[$seasonNo]["periods"][$periodsNo]["fromHour"] >= $seasonPeriods[$seasonNo]["periods"][$periodsNo]["toHour"]) &&
			($startTime->format("G") >= $seasonPeriods[$seasonNo]["periods"][$periodsNo]["fromHour"])) 
		{
			//the period cross two days(for example:17:00 to 9:00)
			$toTime = $startTime->modify('+1 day');
			//$startTimeStr = $toTime->format("Y-m-d")."T".$toTime->format("H:i:s").".000Z";
		} 
		else 
		{
			$toTime = $startTime;
		}
		
		$periodsToTime = date_create_from_format("Y-m-d?H:i:s.u*",$toTime->format("Y-m-d")." ".
				                                str_pad($seasonPeriods[$seasonNo]["periods"][$periodsNo]["toHour"],2,0,STR_PAD_LEFT).":".
												str_pad($seasonPeriods[$seasonNo]["periods"][$periodsNo]["toMinute"],2,0,STR_PAD_LEFT).":00.000Z");
		$periodsToTimeStr = $periodsToTime->format("Y-m-d")."T".$periodsToTime->format("H:i:s").".000Z";
		
		//set JSON List: endTime((for example: "2017-09-01T17:00:00.000Z"))
		if($endTime < $periodsToTime) 
		{
			$responseJOSN[$index]["endTime"] = $endTimeStr;

			//set finish flag
			$finishFlag = true;
		} 
		else 
		{
			$responseJOSN[$index]["endTime"] = $periodsToTimeStr;
		}
	
		//set JSON List: season&period((for example: "Summer","Work"))
		$responseJOSN[$index]["season"] = $seasonPeriods[$seasonNo]["seasonName"];
		$responseJOSN[$index]["period"] = $seasonPeriods[$seasonNo]["periods"][$periodsNo]["periodName"];
		
		//set JSON List index+1
		$index++;
		
		//set startTime for next loop
		$startTime = $periodsToTime;
		$startTimeStr = $periodsToTimeStr;
		
		//get season index from new startTime
		$seasonNo = getstartTimeSeason($startTime,$endTime,$seasonPeriods);
		
		//get periods from new startTime 
		$periodsNo = getstartTimePeriods($startTime,$endTime,$seasonPeriods[$seasonNo]);
		
	} while ($finishFlag == false);
	
	//convert PHP JSON List to JSON object
	$JSONList = json_encode($responseJOSN);
	
	return $JSONList;
}

/********************************************************************************
 * Name: getstartTimeSeason
 * Function: determine the season of starttime
 *******************************************************************************/
function getstartTimeSeason($startTime,$endTime,$seasonPeriods) 
{
	//get datetime object of startTime
	$startTimeMD = date_create_from_format("m-d",$startTime->format("m-d"));

	//find out season index of startTime
	for($seasonNo=0; $seasonNo<sizeof($seasonPeriods); $seasonNo++) 
	{
		//get season start date from request Json data
		$seasonStartMD = date_create_from_format("m-d",str_pad($seasonPeriods[$seasonNo]["startMonth"],2,0,STR_PAD_LEFT)."-".
				str_pad($seasonPeriods[$seasonNo]["startDay"],2,0,STR_PAD_LEFT));
		//get season end date from request Json data
		$seasonEndMD = date_create_from_format("m-d",str_pad($seasonPeriods[$seasonNo]["endMonth"],2,0,STR_PAD_LEFT)."-".
				str_pad($seasonPeriods[$seasonNo]["endDay"],2,0,STR_PAD_LEFT));

		//Compare startTime and season start date/end date
		//Start date <= end date(for example:04-01 to 08-31)
		if(($seasonStartMD <= $seasonEndMD) && ($startTimeMD >= $seasonStartMD) && ($startTimeMD <= $seasonEndMD)) 
		{
			//season found
			break;
		}
		
		//Start date >= end date(for example:10-01 to 01-31)
		if(($seasonStartMD > $seasonEndMD) && ($startTimeMD >= date_create_from_format("m-d","01-01")) && ($startTimeMD <= $seasonEndMD)) 
		{
			//season found
			break;
		}

		//Start date >= end date(for example:10-01 to 01-31)
		if(($seasonStartMD > $seasonEndMD) && ($startTimeMD >= $seasonStartMD) && ($startTimeMD <= date_create_from_format("m-d","12-31"))) 
		{
			//season found
			break;
		}
	}
	//if season not found, display error message
	if($seasonNo >= sizeof($seasonPeriods)) 
	{
		displayHtmlPage("season JSON data error!", true);
		exit();
	}

	//return season index
	return $seasonNo;
}

/********************************************************************************
 * Name: getstartTimePeriods
 * Function: find period
 *******************************************************************************/
function getstartTimePeriods($startTime,$endTime,$seasonPeriods) 
{
	//convert JSON weekday to PHP datetime format 
	$startTimeW = $startTime->format("w") - 1;
	if($startTimeW < 0) 
	{
		$startTimeW = 6;
	}

	//get periods index of startTime
	for($PeriodsNo=0; $PeriodsNo<sizeof($seasonPeriods["periods"]); $PeriodsNo++) 
	{
		//fromDayOfWeek <= toDayOfWeek(for example:0 to 4)
		if(($seasonPeriods["periods"][$PeriodsNo]["fromDayOfWeek"] <= $seasonPeriods["periods"][$PeriodsNo]["toDayOfWeek"]) &&
		   ($startTimeW >= $seasonPeriods["periods"][$PeriodsNo]["fromDayOfWeek"]) && ($startTimeW  <= $seasonPeriods["periods"][$PeriodsNo]["toDayOfWeek"]))
		{
		   	if(determinePeriods($startTime,$endTime,$seasonPeriods["periods"][$PeriodsNo]) == true) 
			{
				//period found
				break;
			}
		}

		//fromDayOfWeek > toDayOfWeek(for example:5 to 0)
		if(($seasonPeriods["periods"][$PeriodsNo]["fromDayOfWeek"] > $seasonPeriods["periods"][$PeriodsNo]["toDayOfWeek"]) &&
		   ($startTimeW >= $seasonPeriods["periods"][$PeriodsNo]["fromDayOfWeek"]) && ($startTimeW <= ($seasonPeriods["periods"][$PeriodsNo]["toDayOfWeek"]+7))) 
		{
			if(determinePeriods($startTime,$endTime,$seasonPeriods["periods"][$PeriodsNo]) == true) 
			{
				//period found
				break;
			}
		}
	}

	//if periods not found, display error message
	if($PeriodsNo >= sizeof($seasonPeriods["periods"])) 
	{
		displayHtmlPage("season JSON periods data error!", true);
		exit();
	}
	
	//return period
	return $PeriodsNo;
}

/********************************************************************************
 * Name:find determinePeriods
 * Function:determine period
 *******************************************************************************/
function determinePeriods($startTime,$endTime,$Period) 
{
	$startTimeHm = date_create_from_format("H:i",$startTime->format("H:i"));

	$fromHm = date_create_from_format("H:i", str_pad($Period["fromHour"],2,0,STR_PAD_LEFT).":".str_pad($Period["fromMinute"],2,0,STR_PAD_LEFT));
	if(($Period["toHour"] <=0) && ($Period["toMinute"] <= 0)) 
	{
		//fromHour:fromMinute=00:00 and toHour:toMinute=00:00 
		$toHm = date_create_from_format("H:i", "24".":"."00");
	} 
	else 
	{
		$toHm = date_create_from_format("H:i", str_pad($Period["toHour"],2,0,STR_PAD_LEFT).":".str_pad($Period["toMinute"],2,0,STR_PAD_LEFT));
	}
	
	//fromHour:fromMinute <= toHour:toMinute(for example:09:00 to 17:00)
	if(($fromHm <= $toHm) && ($startTimeHm >= $fromHm) && ($startTimeHm < $toHm)) 
	{
		return true;
	}

	//fromHour:fromMinute > toHour:toMinute(for example:17:00 to 09:00)
	//(for example:00:00 to 09:00)
	if(($fromHm > $toHm) && ($startTimeHm >= date_create_from_format("H:i", "00:00")) && ($startTimeHm < $toHm)) 
	{
		return true;
	}
	
	//(for example:17:00 to 23:59)
	if(($fromHm > $toHm) && ($startTimeHm >= $fromHm) && ($startTimeHm < date_create_from_format("H:i", "23:59"))) 
	{
		return true;
	}

	return false;
}

/********************************************************************************
 * Name: displayHtmlPage
 * Function: diaplay error message on web page
 *******************************************************************************/
function displayHtmlPage($msg, $error_flag) 
{
	echo "<html>";
	
	echo "<head>";
	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
	echo "</head>";
	
	echo "<body>";
	if($error_flag == true) 
	{echo '<font color="#FF0000">';};
	echo "<p>".$msg."</p><br>";
	if($error_flag == true) {echo "</font>";};
	//Dispaly a return button for re-enter
	echo '<br><input type="button" name="Submit" onclick="javascript:history.back(-1);" value="RETURN">';
	echo "</body>";
	
	echo "</html>";
};

/********************************************************************************
 * Name: displayJSONdatas
 * Function: diaplay response JSON file on web page
 *******************************************************************************/
function displayJSONdatas($jsonObject) 
{
	echo "<html>";

	echo "<head>";
	echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
	echo "</head>";

	echo "<body>";
	//dispaly a return button for test
	echo '<br><br><input type="button" name="Submit" onclick="javascript:history.back(-1);" value="RETURN">';
	//display a button to show formatted JSON file
	echo '&nbsp;&nbsp;&nbsp;&nbsp;<button onclick="display_json_format_list()">Display response JSON file<tton>';
	echo '<br><br><textarea name="jsonlist" id="jsonlist" cols="72" rows="20"></textarea>';
    echo "</body>";
	//convert jsonObject to formatted JSON file
	echo '<script type="text/javascript">                                                                             
	function display_json_format_list()       
	{
		document.getElementById("jsonlist").value = JSON.stringify(jsonObject,null,4);
	}';
    echo "var jsonObject =".$jsonObject;
    echo "</script>";

	echo "</html>";
};

?>
