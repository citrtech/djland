<?php
foreach ( File::allFiles(__DIR__.'/Routes') as $partial )
{
    require $partial->getPathname();
}
use App\Option as Option;
use App\User as User;
use App\Member as Member;
use App\MembershipYear as MembershipYear;
use App\Permission as Permission;
use App\Show as Show;
use App\Showtime as Showtime;
use App\Host as Host;
use App\Social as Social;
use App\Playsheet as Playsheet;
use App\Playitem as Playitem;
use App\Podcast as Podcast;
use App\Ad as Ad;
use App\Socan as Socan;
use App\SpecialBroadcasts as SpecialBroadcasts;

use App\Donor as Donor;
//SAM CLASSES
use App\Songlist as Songlist;
use App\Categorylist as Categorylist;
use App\Historylist as Historylist;

use App\Friends as Friends;

Route::get('/', function () {
    //return view('welcome');
    return "Welcome to DJLand API 2.0";
});

//Anything inside the auth middleware requires an active session (user to be logged in)
Route::group(['middleware' => 'auth'], function(){
	//Member Resource Routes
	Route::group(array('prefix'=>'resource'),function(){
		Route::get('/',function(){
			return Option::where('djland_option','=','member_resources')->get();
		});
		Route::post('/',function(){
			$resource = Option::where('djland_option','=','member_resources')->first();
			$resource -> value = Input::get()['resources'];
			return Response::json($resource->save());
		});
	});
	Route::group(array('prefix'=>'membership_year'),function(){
		Route::get('/',function(){
			return MembershipYear::select('membership_year')->groupBy('membership_year')->orderBy('membership_year','DESC')->get();
		});
		Route::group(array('prefix'=>'rollover'),function(){
			Route::get('/',function(){
				
			});
			ROute::post('/',function(){

			});
		});
	});
});

Route::get('/social',function(){
	return Social::all();
});

Route::group(array('prefix'=>'tools'),function(){
	//re-writes all the show xmls.
	Route::get('/write_show_xmls',function(){
		$shows = Show::orderBy('id')->get();
		echo "<pre>";
		$index = 0;
		foreach($shows as $show){
			$index++;
			if($show->podcast_slug){
				$result = $show->make_show_xml();
				$result['index'] = $index;
				print_r($result);
				$results[] = $result;
			}
		}
	});
});

Route::post('/adschedule',function(){
	$post = array();
	parse_str(Input::get('ads'),$post);

	foreach($post['show'] as $ad){
		if($ad['id']){
			$a = Ad::find($ad['id']);
			unset($ad['id']);
			$a->update($ad);
		}else{
			$a = Ad::create($ad);
		}
		$ads[]=$a;
	}
	return Response::json($ads);
});

Route::get('/adschedule',function(){

	date_default_timezone_set('America/Los_Angeles');
	$date = implode('-',explode('/',$_GET['date']));
	$formatted_date = date('Y-m-d',strtotime($date));
	$unix = strtotime($formatted_date);
	$parsed_date = date_parse($formatted_date);
	if($parsed_date["error_count"] == 0 && checkdate($parsed_date["month"], $parsed_date["day"], $parsed_date["year"])){
		//Constants (second conversions)
		$one_day = 24*60*60;
		$one_hour = 60*60;
		$one_minute = 60;


		//Get Day of Week (0-6)
		$day_of_week = date('w',strtotime($date));
        	//Get mod 2 of (current unix - time since start of last sunday divided by one week). Then add 1 to get 2||1 instead of 1||0
        	$week = (floor( (strtotime($date) - intval($day_of_week*$one_day)) /($one_day*7) ) % 2) + 1;


		if($formatted_date == date('Y-M-d',strtotime('now'))){
			//Set cutoff time to right now if we are loading today
			$time = date('H:i:s',strtotime('now'));
		}else{
			//Set cutoff time to 00:00:00
			$time = '00:00:00';
		}

		//Select active shows that run during the date specified.
		$shows =
		Show::selectRaw('shows.id,shows.name,show_times.start_day,show_times.start_time,show_times.end_day,show_times.end_time')
		->join('show_times','show_times.show_id','=','shows.id')
		->where('show_times.start_day','=',$day_of_week)
		->where('show_times.start_time','>=',$time)
		->whereRaw('(show_times.alternating = '.$week.' OR show_times.alternating = 0)')
		->where('shows.active','=','1')
		->orderBy('show_times.start_time','ASC')
		->get();

		//for each show time get the ads, or create them.
		foreach($shows as $show_time){
			$start_hour_offset = date_parse($show_time['start_time'])['hour'] * $one_hour;
			$start_minute_offset = date_parse($show_time['start_time'])['minute'] * $one_minute;
			$start_unix_offset = $start_hour_offset + $start_minute_offset;
			$end_hour_offset = date_parse($show_time['end_time'])['hour'] * $one_hour;
			$end_minute_offset = date_parse($show_time['end_time'])['minute'] * $one_minute;
			$end_unix_offset = $end_hour_offset + $end_minute_offset;
			if( $show_time['end_day'] != $show_time['start_day'] ){
				$end_unix_offset += $one_day;
			}

			$show_time->start_unix = $unix + $start_unix_offset;
			$show_time->end_unix = $unix + $end_unix_offset;
			$show_time->duration = $show_time->end_unix - $show_time->start_unix;

			/*if( date('I',strtotime($show_time->start_unix))=='0' ){
                $show_time->start_unix += 3600;
                $show_time->end_unix += 3600;
            }*/

			$ads = Ad::where('time_block','=',$show_time->start_unix)->get();
			$show_time->generated = false;
			if(count($ads) == 0){
				$show_time->generated = true;
				$show_time->ads = Ad::generateAds($show_time->start_unix,$show_time->duration,$show_time->id);
			}else{
				$show_time->ads = $ads;
			}
			$show_time->date = date('l F jS g:i a',$show_time->start_unix);
			$show_time->start = date('g:i a',$show_time->start_unix);
		}
		return Response::json($shows);
	}else{
		http_response_code('400');
		return "Not a Valid Date: {$formatted_date}";
	}
});



Route::get('/promotions/{unixtime}-{duration}/{show_id}',function($unixtime = unixtime,$duration = duration,$show_id = show_id){
	$ads = Ad::where('time_block','=',$unixtime)->orderBy('num','asc')->get();
	if(sizeof($ads) > 0) return Response::json($ads);
	else return Ad::generateAds($unixtime,$duration,$show_id);
});


Route::get('/nowplaying',function(){
	require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');
	date_default_timezone_set('America/Los_Angeles');
	$result = array();
	if($using_sam){
		$last_track = DB::connection('samdb')->table('historylist')->selectRaw('artist,title,album,date_played,songtype,duration')
			->where('songtype','=','S')->orderBy('date_played','DESC')->limit('1')->get();
		$now = strtotime('now');
		if(count($last_track) > 0){
			$last_track = $last_track[0];
			if( (strtotime($last_track->date_played) + floor(($last_track->duration)/1000) ) >= $now ){
				$result['music'] = $last_track;
			}else{
				$result['music'] = null;
			}
		}else{
			$result['music'] = null;
		}
	}else{
		$result['music'] = null;
	}
	$day_of_week = date('w');
	//Get mod 2 of (current unix - time since start of last sunday divided by one week). Then add 1 to get 2||1 instead of 1||0
	$current_week = floor( (date('now') - intval($day_of_week*60*60*24)) /(60*60*24*7) ) % 2 + 1;
    if ((int) $current_week % 2 == 0){
        $current_week_val = 1;
    } else {
        $current_week_val = 2;
    };

	//We use 0 = Sunday instead of 7
	$yesterday = ($day_of_week - 1);
	$tomorrow = ($day_of_week + 1);

	$specialbroadcast = SpecialBroadcasts::whereRaw('start <= '.$now.' and end >= '.$now)->get();
	if($specialbroadcast->first()){
		//special broadcast exists
		$specialbroadcast = $specialbroadcast->first();
		$result['showId'] = $specialbroadcast->show_id;
		$result['showName'] = $specialbroadcast->name;
		$start_time = date('H:i:s',$specialbroadcast->start);
		$end_time = date('H:i:s',$specialbroadcast->end);
		$result['showTime'] = "{$start_time} - {$end_time}";
		$result['lastUpdated'] = date('D, d M Y g:i:s a',strtotime('now'));
	}else{
		$current_show = DB::select(DB::raw(
		"SELECT s.*,sh.name as name,NOW() as time from show_times AS s INNER JOIN shows as sh ON s.show_id = sh.id
			WHERE
				CASE
					WHEN s.start_day = s.end_day THEN s.start_day={$day_of_week} AND s.end_day={$day_of_week} AND s.start_time <= CURTIME() AND s.end_time > CURTIME()
					WHEN s.start_day != s.end_day AND CURTIME() <= '23:59:59' AND CURTIME() > '12:00:00 'THEN s.start_day={$day_of_week} AND s.end_day = {$tomorrow} AND s.start_time <= CURTIME() AND s.end_time >= '00:00:00'
					WHEN s.start_day != s.end_day AND CURTIME() < '12:00:00' AND CURTIME() >= '00:00:00' THEN s.start_day= {$yesterday} AND s.end_day = {$day_of_week} AND s.end_time > CURTIME()
				END
				AND sh.active = 1
				AND (s.alternating = 0 OR s.alternating = {$current_week_val});"));
		if( count($current_show) > 0 ){
			$current_show = $current_show[0];
			$result['showId'] = $current_show->show_id;
			$result['showName'] = $current_show->name;
			$result['showTime'] = "{$current_show->start_time} - {$current_show->end_time}";
			$result['lastUpdated'] = date('D, d M Y g:i:s a',strtotime($current_show->time));
		}else{
			$result['showName'] = "CiTR Ghost Mix";
			$result['showId'] = null;
			$result['showTime'] = "";
			$result['lastUpdated'] = date('D, d M Y g:i:s a',strtotime('now'));
		}
	}
	return Response::json($result);
});

Route::get('/socan',function(){
	$now = strtotime('now');
	$socan = Socan::all();
	foreach($socan as $period){
		if( strtotime($period['socanStart']) <= $now && strtotime($period['socanEnd']) >= $now){
			return Response::json(true);
		}
	}
	return Response::json(false);
});
Route::get('/socan/{time}',function($unixtime = time){
	$now = $unixtime;
	$socan = Socan::all();
	foreach($socan as $period){
		if( strtotime($period['socanStart']) <= $now && strtotime($period['socanEnd']) >= $now){
			return Response::json(true);
		}
	}
	return Response::json(false);
});

// Table Helper Routes
Route::get('/table',function(){
	return  DB::select('SHOW TABLES');
});

Route::get('/table/{table}',function($table_name =table){
	echo "<table>";
	echo "<tr><th>Field<th>Type<th>Null<th>Key<th>Extra</tr>";
	$table = DB::select('DESCRIBE '.$table_name);
	foreach($table as $column){
		echo "<tr>";
		foreach($column as $item){
			echo "<td>".$item."</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
	foreach($table as $column){
		echo "'".$column->Field."', ";
	}
});
Route::post('/error',function(){
	date_default_timezone_set('America/Los_Angeles');
	$from = $_SERVER['HTTP_REFERER'];
	$error = Input::get()['error'];
	$date = date('l F jS g:i a',strtotime('now'));
	$out = '<hr>';
	$out .= '<h3>'.$date.'</h3>';
	$out .= '<h4>'.$from.'</h4>';
	$out .= '<p>'.$error.'</p>';
	$result = file_put_contents($_SERVER['DOCUMENT_ROOT'].'/log.html',$out.PHP_EOL,FILE_APPEND);
	return $result;
});