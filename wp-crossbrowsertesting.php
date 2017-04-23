<?php
/*
Plugin Name: CrossBrowserTesting
Plugin URI: http://www.atomicsmash.co.uk
Description: Sync development media files to Amazon S3
Version: 0.0.1
Author: David Darke
Author URI: http://www.atomicsmash.co.uk
*/

// if (!defined('ABSPATH'))exit; //Exit if accessed directly





/* Taken from CrossBrowserTesting.com API v3 Screenshots sample script */

//set authentication info

define ('EOL', "<br>");
define ('TAB', " -- ");


class ScreenshotTestApi{

	public $baseUrl = "https://crossbrowsertesting.com/api/v3/screenshots";

	public $currentTest = NULL;
	public $allTests = array();
	public $recordCout = 0;

	function __construct($username, $password, $screenshot_test_id=NULL) {
		$this->user = $username;
		$this->pass = $password;

		//if test id provided initialize this test to that test data
        if ($screenshot_test_id){
            $url = $this->baseUrl . "/" . $screenshot_test_id;
            $this->currentTest = $this->callApi($url);
        }
	}

	function startNewTest($params){
		$this->currentTest = $this->callApi($this->baseUrl, 'POST', $params);
	}

	function updateTestInfo(){
		$url = $this->baseUrl . "/" . $this->getTestId();
		return $this->callApi($url, 'GET');
	}

	function getTestId(){
		return $this->currentTest->screenshot_test_id;
	}

	function printTestBrowsers(){
		if ($this->currentTest){
			foreach ($this->currentTest->versions[0]->results as $result) {
				print $result->os->name  . TAB . $result->browser->name . TAB . $result->resolution->name . EOL;
			}
		}
	}

	function isTestComplete(){
		$this->currentTest = $this->updateTestInfo();
		return !$this->currentTest->versions[0]->active;
	}


	function getAllTests($params = false){
		$url = $this->baseUrl;
		$result = $this->callApi($url, 'GET',$params);
		$this->recordCount = $result->meta->record_count;
        $this->allTests = $result->screenshots;
        return $this->allTests;
	}

	function callApi($api_url, $method = 'GET', $params = false){
		$apiResult = new stdClass();

	    $process = curl_init();

	    switch ($method){
	        case "POST":
	            curl_setopt($process, CURLOPT_POST, 1);

	            if ($params){
	                curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($params));
	                curl_setopt($process, CURLOPT_HTTPHEADER, array('User-Agent: php')); //important
	            }
	            break;
	        case "PUT":
	            curl_setopt($process, CURLOPT_CUSTOMREQUEST, "PUT");
	            if ($params){
	                curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($params));
	                curl_setopt($process, CURLOPT_HTTPHEADER, array('User-Agent: php')); //important
	            }
	            break;
	         case 'DELETE':
	         	curl_setopt($process, CURLOPT_CUSTOMREQUEST, "DELETE");
	         	break;
	        default:
	            if ($params){
	                $api_url = sprintf("%s?%s", $api_url, http_build_query($params));
	            }
	    }

	    // Optional Authentication:
	    curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	    curl_setopt($process, CURLOPT_USERPWD, $this->user . ":" . $this->pass);

	    curl_setopt($process, CURLOPT_URL, $api_url);
	    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($process, CURLOPT_TIMEOUT, 30);




	    $apiResult->content = curl_exec($process);
		$apiResult->httpResponse = curl_getinfo($process);
		$apiResult->errorMessage =  curl_error($process);
		$apiResult->params = $params;

		curl_close($process);

		//print_r($apiResult);

		$paramsString = $params ? http_build_query($params) : '';
		$response = json_decode($apiResult->content);

		if ($apiResult->httpResponse['http_code'] != 200){
			$message = 'Error calling "' . $apiResult->httpResponse['url'] . '" ';
			$message .= (isset($paramsString) ? 'with params "'.$paramsString.'" ' : ' ');
			$message .= '. Returned HTTP status ' . $apiResult->httpResponse['http_code'] . ' ';
			$message .= (isset($apiResult->errorMessage) ? $apiResult->errorMessage : ' ');
			$message .= (isset($response->message) ? $response->message : ' ');
			die($message);
		}
		else {
			$response = json_decode($apiResult->content);
			if (isset($response->status)){
				die('Error calling "' . $apiResult->httpResponse['url'] . '"' .(isset($paramsString) ? 'with params "'.$paramsString.'"' : '') . '". ' . $response->message);
			}
		}

	    return $response;
	}
}



function runNewTest(){
	global $username, $password;

	print EOL.'** Starting CrossBrowserTesting.com API v3 Run Screenshot Test example **'.EOL;

	//set parameters we want for the screenshot test
    $params["url"] = "http://wordpress.dev/?page_id=2";

    //set browsers
    $params["browsers"] = array();
    $params["browsers"][] = "Win7x64-C2|IE10|1400x1050";
    $params["browsers"][] = "Win7x64-C1|Chrome23|1400x1050";
    $params["browsers"][] = "Win7x64-C1|Chrome28|1400x1050";
    // $params["browsers"][] = "Win7x64-C2|IE8|1400x1050";
    // $params["browsers"][] = "Mac10.9|Chrome36";
    // $params["browsers"][] = "GalaxyNote3-And44|MblChrome36";

    //other options

    // $params["check_url"] = "false";
    //$params["browser_list_name"] = "Popular Browsers";
    //$params["login"] = "mydomain.com login"; //valid only if you've created a login profile with this name
    //$params["basic_username"] = "username"; //for basic auth urls only
    //$params["basic_password"] = "password"; //for basic auth urls only
    //$params["delay"] = 0; //delay for number of seconds to wait after page is loaded to start capturing screenshots

	//create api object and set auth info
	$screenshot = new ScreenshotTestApi(USERNAME,PASSWORD);

	print "starting new screenshot test for " . $params["url"].EOL;
    $screenshot->startNewTest($params);

    print "screenshot_test_id is " . $screenshot->getTestId().EOL;
    print "view Screenshot Test on web here: https://app.crossbrowsertesting.com/screenshots/" . $screenshot->getTestId().EOL;

    print EOL."browsers to be tested are: ".EOL;
    $screenshot->printTestBrowsers();

    print "waiting on test to complete".EOL;
    $tries = 0;
    $maxTries = 100;
    while ($tries < $maxTries){

        if ($screenshot->isTestComplete()){
            print "screenshot test complete".EOL;
            break;
        }
        else{
            sleep(2);
            $tries += 1;
        }
	}
    if ($tries >= $maxTries){
        die("screenshot did not complete after " . str($tries*2) . " seconds!".EOL);
    }
}

function viewTestHistory(){
	global $username, $password;

	print EOL."** Starting CrossBrowserTesting.com API v3 View Screenshot History example **".EOL;

	//set paging options
	$params["start"] = 0; //start with the last test run
    $params["num"] = 20; //how many to retrieve

    //filter results
    $params["url"] = "google"; //filter for only tests run that have 'google' somewhere in the URL
    // $params["start_date"] = "2014-06-01"; //fitler to only tests run within a date range
    // $params["end_date"] = "2014-10-01";
    $params["archived"] = false; //only include screenshot tests that are not archived

    //create api object and set auth info
    print "retrieving test history".EOL;
    $screenshots = new ScreenshotTestApi(USERNAME,PASSWORD);
    $allTests = $screenshots->getAllTests($params);

    //show total number of tests
    print "There are " . $screenshots->recordCount . " tests for  the filters provided, showing " . count($allTests) . EOL;

    //print out results
    for ($i=0; $i<count($allTests); $i++){
        echo '<pre>';
        print_r($allTests[$i]);
        echo '</pre>';
        die();

        $test = $allTests[$i];
        $version = $test->versions[0];
        $start_date = $version->start_date;
        print ($i+1) . TAB . $start_date . TAB . $test->screenshot_test_id  . TAB . $test->url.EOL;
    }




}

function getScreenshotBrowsers(){

    //create api object and set auth info
    $screenshotApi = new ScreenshotTestApi(USERNAME,PASSWORD);

	$url = $screenshotApi->baseUrl . "/browsers";
	$oss = $screenshotApi->callApi($url, 'GET');


	$browsers = array();

	$count = 0;

    foreach ($oss as $os){

		$browsers[$os->api_name]['os-name'] = $os->name;
		$browsers[$os->api_name]['os-api-name'] = $os->api_name;


        foreach($os->browsers as $key => $browser){

			// $browsers[$count]['browser-name'] = $browser->name;
			// $browsers[$count]['os-api-name'] = $os->api_name;
			// $browsers[$count]['browser-api-name'] = $browser->api_name;
			// $browsers[$count]['full-api-name'] = $os->api_name . "|" . $browser->api_name;
			$browsers[$os->api_name]['browsers'][$key]['browser-name'] = $browser->name;
			$browsers[$os->api_name]['browsers'][$key]['browser-api-name'] = $browser->api_name;
			$browsers[$os->api_name]['browsers'][$key]['full-api-name'] = $os->api_name . "|" . $browser->api_name;

			// $count++;

        }
    }

	return $browsers;

}

if(isset($_GET['test'])){

    // uncomment a function to run
    // listScreenshotBrowsers();
    // die();
    // runNewTest();
    // viewTestHistory();

}







/*
* add a group of links under a parent link
*/

// Add a parent shortcut link

function custom_toolbar_link($wp_admin_bar) {

	$args = array(
		'id' => 'browser-test',
		'title' => 'Browser Test',
		'href' => 'https://www',
		'meta' => array(
			'class' => 'browser-test',
			'title' => 'Test this page'
        )
	);
	$wp_admin_bar->add_node($args);

    // Add the first child link
	$args = array(
		'id' => 'browser-test-options',
		'title' => 'Update test browsers',
		'href' => 'http://www',
		'parent' => 'browser-test',
		'meta' => array(
			'class' => 'browser-test',
			'title' => 'Update the default browsers used in test'
			)
	);
	$wp_admin_bar->add_node($args);

}

add_action('admin_bar_menu', 'custom_toolbar_link', 999);





add_action( 'admin_menu', 'wpcrossbrowsertesting_add_admin_menu' );
add_action( 'admin_init', 'wpcrossbrowsertesting_settings_init' );


function wpcrossbrowsertesting_add_admin_menu(  ) {

	add_submenu_page( 'options-general.php', 'wpcrossbrowsertesting', 'wpcrossbrowsertesting', 'manage_options', 'wpcrossbrowsertesting', 'wpcrossbrowsertesting_options_page' );

}


function wpcrossbrowsertesting_settings_init(  ) {

	register_setting( 'pluginPage', 'wpcrossbrowsertesting_settings' );

	add_settings_section(
		'wpcrossbrowsertesting_pluginPage_section',
		__( 'Your section description', 'wpcrossbrowsertesting' ),
		'wpcrossbrowsertesting_settings_section_callback',
		'pluginPage'
	);

	add_settings_field(
		'wpcrossbrowsertesting_text_field_0',
		__( 'Settings field description', 'wpcrossbrowsertesting' ),
		'wpcrossbrowsertesting_text_field_0_render',
		'pluginPage',
		'wpcrossbrowsertesting_pluginPage_section'
	);

	add_settings_field(
		'wpcrossbrowsertesting_text_field_1',
		__( 'Settings field description', 'wpcrossbrowsertesting' ),
		'wpcrossbrowsertesting_text_field_1_render',
		'pluginPage',
		'wpcrossbrowsertesting_pluginPage_section'
	);

	add_settings_field(
		'wpcrossbrowsertesting_checkbox_field_2',
		__( 'Settings field description', 'wpcrossbrowsertesting' ),
		'wpcrossbrowsertesting_checkbox_field_2_render',
		'pluginPage',
		'wpcrossbrowsertesting_pluginPage_section'
	);


}


function wpcrossbrowsertesting_text_field_0_render(  ) {

	$options = get_option( 'wpcrossbrowsertesting_settings' );
	?>
	<input type='text' name='wpcrossbrowsertesting_settings[wpcrossbrowsertesting_text_field_0]' value='<?php echo $options['wpcrossbrowsertesting_text_field_0']; ?>'>
	<?php

}


function wpcrossbrowsertesting_text_field_1_render(  ) {

	$options = get_option( 'wpcrossbrowsertesting_settings' );
	?>
	<input type='text' name='wpcrossbrowsertesting_settings[wpcrossbrowsertesting_text_field_1]' value='<?php echo $options['wpcrossbrowsertesting_text_field_1']; ?>'>
	<?php

}


function wpcrossbrowsertesting_checkbox_field_2_render(  ) {

	$options = get_option( 'wpcrossbrowsertesting_settings' );

	echo '<pre>';
	print_r($options);
	echo '</pre>';

	$ossAndBrowsers = getScreenshotBrowsers();

    // [16] => Array
    //     (
    //         [browser-name] => Mobile Safari 5.1
    //         [os-api-name] => iPad2-iOS5
    //         [browser-api-name] => MblSafari5.1
    //         [full-api-name] => iPad2-iOS5|MblSafari5.1
    //     )


	// echo '<pre>';
	// print_r($browsers);
	// echo '</pre>';

	foreach($ossAndBrowsers as $os){

		// echo '<pre>';
		// print_r($os);
		// echo '</pre>';

		echo "<h2>".$os['os-name']."</h2>";


// Array
// (
//     [os-name] => Android Nexus 6P / 6.0
//     [os-api-name] => Nexus6P-And60
//     [browsers] => Array
//         (
//             [0] => Array
//                 (
//                     [browser-name] => Chrome Mobile 52
//                     [browser-api-name] => MblChrome52
//                     [full-api-name] => Nexus6P-And60|MblChrome52
//                 )

//         )

// )
// Array

		foreach($os['browsers'] as $browser){

			echo "<p>";
			$checked = "";

			if(isset($options['browsers'][$browser['full-api-name']])){
				$checked = checked( $options['browsers'][$browser['full-api-name']], 1, 0 );
			};

			echo "<input type='checkbox' $checked name='wpcrossbrowsertesting_settings[browsers][".$browser['full-api-name']."]' id='wpcrossbrowsertesting_settings[browsers][".$browser['full-api-name']."]' value='1'>";

			echo "<label for='wpcrossbrowsertesting_settings[browsers][".$browser['full-api-name']."]'>".$browser['browser-name']."</label>";

			echo "</p>";

		}
	};

	// echo '<pre>';
	// print_r($browsers);
	// echo '</pre>';

	// checked( $options['wpcrossbrowsertesting_checkbox_field_2'], 1 );


}


function wpcrossbrowsertesting_settings_section_callback(  ) {

	echo __( 'This section description', 'wpcrossbrowsertesting' );

}


function wpcrossbrowsertesting_options_page(  ) {

	?>
	<form action='options.php' method='post'>

		<h2>wpcrossbrowsertesting</h2>

		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );
		submit_button();
		?>

	</form>
	<?php

}



