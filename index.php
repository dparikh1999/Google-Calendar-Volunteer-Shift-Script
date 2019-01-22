<?php
require_once './google-api-php-client-2.2.2/vendor/autoload.php';

//getting clientid
function getClient(){

    $client = new Google_Client();
    $client->setApplicationName('Google Calendar Schedule-Maker');
    $client->addScope(Google_Service_Calendar::CALENDAR);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');
    $tokenPath = 'token.json';

    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }
    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

class Shift {
	public $title;
	public $start;
	public $end;
  public $key;
}

date_default_timezone_set("America/New_York");

$client = getClient();
$service = new Google_Service_Calendar($client);
$calendarId = 'primary';

//choose the start and end date for week_end
$day = 24*60*60;
//echo date('l', strtotime('today +10 days'));
switch (date('l')) {
	case 'Sunday':		$filter_start = 'this Monday';		break;
	case 'Monday':		$filter_start = 'today';			break;
	case 'Tuesday':		$filter_start = 'yesterday';		break;
	case 'Wednesday':	$filter_start = 'this Saturday';	break;
	case 'Thursday':	$filter_start = 'this Saturday';	break;
	case 'Friday':		$filter_start = 'this Saturday';	break;
	case 'Saturday':	$filter_start = 'today';			break;
}
$filter_start = strtotime($filter_start);
$filter_end	= strtotime('next Sunday 23:59:59', $filter_start + (2 * $day));
$filter_start_new = date('l Y-m-d H:i', $filter_start); //military time
$filter_end_new = date('l Y-m-d H:i', $filter_end);

$filter_params = array(
		'orderBy' => 'startTime',
    'timeMin' => date("c", $filter_start),
    'timeMax' => date("c", $filter_end),
		//'sortorder' => 'ascending',
		'singleEvents' => true,
		//'showdeleted' => 'false',
		'maxResults' => 500,
		//'fields' => 'title,id,updated,entry(title,link,gd:eventStatus,gd:when)',
);

echo "<div id='schedule' onDblclick='fnSelect(\"schedule\");' style='margin-bottom:.3em;'>\n";
echo "<h3 style='font-size:120%;'>Volunteer Schedule</h3>\n";

//get events within time frame
$results = $service->events->listEvents($calendarId, $filter_params);
$events = $results->getItems();

//create array of shift events
$shift_events = array();
foreach ($events as $event){
  $shift = new Shift();
  $shift->title = $event->getSummary();
  $shift->start = strtotime($event->start->dateTime);
  $shift->end = strtotime($event->end->dateTime);
  $shift_key = $shift->start . '-' . $shift->end . ':' . $shift->title;
  $shift_events[$shift_key] = $shift;
}

ksort($shift_events);

//foreach of the shift events
if (empty($shift_events)){
  print "No upcoming shifts found.\n";
} else {
  print "<tr><td colspan=3><b style='font-size:120%'>Shifts from ";
  echo date('l m-d', $filter_start);
  print "<tr><td colspan=3><b style='font-size:110%'> to ";
  echo date('l m-d', $filter_end);
  print "<tr><td colspan=3><b style='font-size:110%'>:\n<br></br>";

  $last_date = '';
  echo "<table style='border-collapse:collapse;'>\n\t<tbody>\n";
  foreach ($shift_events as $shift){
    $shift_date = date("l, F jS", $shift->start);
  	if ($shift_date != $last_date) {
      print "<tr><td colspan=3><b style='font-size:110%'>";
      echo $shift_date;
      print ": <br></br>";
  		//echo "<tr><td colspan=3><b style='font-size:110%'>{$shift_date}</b>&nbsp;</td></tr>\n";
  		$last_date = $shift_date;
  	}
    print "<tr><td colspan=3><style='font-size:110%'>     - ";
    echo $shift->title;
    print ": ";
    echo date('h:i A',$shift->start); print " - "; echo date('h:i A',$shift->end);
    print "<br></br>";
  }
}
