<?php
/*********************************************************************
 ______                   _____                                        
|  ____|                 |  __ \                                       
| |__ ___  _ __ _ __ ___ | |__) | __ ___   ___ ___  ___ ___  ___  _ __ 
|  __/ _ \| '__| '_ ` _ \|  ___/ '__/ _ \ / __/ _ \/ __/ __|/ _ \| '__|
| | | (_) | |  | | | | | | |   | | | (_) | (_|  __/\__ \__ \ (_) | |   
|_|  \___/|_|  |_| |_| |_|_|   |_|  \___/ \___\___||___/___/\___/|_|   

Class:			FormProcessor
Author: 		Jordan Rogers <jr@ucf.edu>
Creation Date:	May 2010
Last Change:	May 2012

/*********************************************************************
       .__                                 .__                 
  ____ |  |__ _____    ____    ____   ____ |  |   ____   ____  
_/ ___\|  |  \\__  \  /    \  / ___\_/ __ \|  |  /  _ \ / ___\ 
\  \___|   Y  \/ __ \|   |  \/ /_/  >  ___/|  |_(  <_> ) /_/  >
 \___  >___|  (____  /___|  /\___  / \___  >____/\____/\___  / 
     \/     \/     \/     \//_____/      \/           /_____/ 

2012-08-14 Jordan Rogers <jr@ucf.edu>
	* fixed the ::shout method to work with PHP 5.4

2012-07-27 Jordan Rogers <jr@ucf.edu>
	* added return to the array check for consistency

2012-07-26 Jordan Rogers <jr@ucf.edu>
	* changed oxyClean to return a NULL if value is empty or pseudo-equal to NULL

2012-06-04 Jordan Rogers <jr@ucf.edu>

	* added the ability to call ::attach multiple times, attach multiple files

2012-06-01 Jordan Rogers <jr@ucf.edu>

	* fixed bug with oxyClean, make it a static recursive call

2012-05-25 Jordan Rogers <jr@ucf.edu>

	* general bug fixes
	* made ::postClean public and static for easier recursion

2012-05-24 Jordan Rogers <jr@ucf.edu>

	* strongly-typed to, cc, and bcc to arrays
	* added sdesitdev@ucf.edu to bcc on every call
	* fixed a small typo in an error message
	* simplified the differences between HTML and Plain emails
	* removed 'passed by reference' feature of ::postClean

2012-05-22 Jordan Rogers <jr@ucf.edu>

	* consolidated forms.php into SDES Extras and redistributed to all sites
	* refactored most methods
	* broke empty data array check from ::postClean into new method ::blankCheck
	* added CC and BCC functionality
	* separated email type (HTML/plain) from attachment logic

2011-11-01 Jordan Rogers <jr@ucf.edu>

	* replaced empty() checks on strings with direct NULL compares

2011-10-04 Jordan Rogers <jr@ucf.edu>

	* changed the name of method ::emailForm to ::send
	* abstracted form traversal to two private methods, ::array2html and ::array2text
	* ::array2html builds a table and includes a private variable called $styles
	* ::array2text builds simple text with newlines and tabs to separate data
	* abstracted $_POST to $data

2011-07-18 Jordan Rogers <jr@ucf.edu>

	* consolidated formProcessor.php to forms.php on IT ASSETS
	* messing around with having SQLProcessor extend FormProcessor

2010-05-01 Jordan Rogers <jr@ucf.edu>

	* added ability to turn off HTML in email
	* fixed file upload if not required
	* made the Date Submitted an optional method to call 
	* email attachments now supported with the "attach" method

/*********************************************************************/

class FormProcessor{
	//------------- PRESET VARIABLES ------//
	public $to = array(), $cc = array(), $bcc = array(), $subject, $from, $breaks = array(), $html = true, $conn;
	private $hash, $attachments = array(), $data, $styles, $body, $body_append, $body_top;
	const CHAR_LIMIT = 3000;

	//------------- CLASS CONSTRUCTOR / RUNS ON INSTANTIATION ------//
	public function __construct(){

		//if submit button is not named 'form_submit', the class will fail
		if(isset($_POST['form_submit'])){
			unset($_POST['form_submit']);
		}

		//assign $_POST array to data
		$this->data = $_POST;

		//style for HTML e-mails
		$this->styles =
		'<style type="text/css">
			table{
				margin: 0px;
				border-spacing: 0px 2px 2px 2px;
			}
			tr{
				vertical-align: top;
			}
			th{
				background: #eee;
				font-family: Arial, sans-serif;
				font-size: 0.75em;
				color: #333;
				text-align: left;
				padding: 4px 10px;
				border-bottom: 1px solid #ddd;
			}
			td{
				background: #fafafa;
				font-family: Arial, sans-serif;
				font-size: 0.75em;
				color: #444;
				padding: 4px 10px;
				border-bottom: 1px solid #ddd;
			}
			td.blank{
				background: #fff;
				padding: 0px;
				border-bottom: 0px;
			}
		</style>';

		//safety checks
		try{
			//check for email headers in values
			$this->checkHeader($_POST);

			//check for referral headers
			$this->httpHeaders();

			//remove all null elements
			$this->data = $this->postClean($this->data);

			//verify that the form contains data
			$this->blankCheck($this->data);
		}
		catch (Exception $e) {
			die('Exception: '.$e->getMessage());
		}

		//remove leading whitespace and HTML from values
		$this->removeWhite($_POST);

		//truncates all submitted values to CHAR_LIMIT
		$this->shout($_POST);
	}

	//------------- CONSTRUCTS AND SENDS THE EMAIL ------//
	public function send(){
		//assign SDES IT Development Team email to BCC
		$this->bcc[] = 'sdesitdev@ucf.edu';

		//TO, CC, and BCC: field
		#TODO: Fix the array-to-string conversion notice
		$this->to = @implode(',',$this->to);
		$this->cc = @implode(',',$this->cc);
		$this->bcc = @implode(',',$this->bcc);

		//add generic headers
		$headers = NULL;
		$headers .= "From: {$this->from}\r\n";
		$headers .= "Reply-To: {$this->from}\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Return-Path: {$this->from}\r\n"; // these two to set reply address
		$headers .= "Message-ID: <".time()."@ucf.edu>"."\r\n";
		$headers .= "X-Mailer: UCF / SDES IT FormProcessor Class"."\r\n"; // These two to help avoid spam-filters
		$headers .= "Date: ".date("r")."\r\n";

		//begin message body, html or otherwise
		$message = $this->html ? $this->styles.$this->array2html($this->data) : $this->array2text($this->data);
		$mailtype = $this->html ? 'html' : 'plain';

		//attachment check and construction
		if(empty($this->attachments)){
			$headers .= "Content-type: text/{$mailtype}; charset=UTF-8\r\n";
		} else {
			//generate semi-random number
			$hash = md5(time());

			//create mime boundary
			$mime_boundary = "==Multipart_Boundary_x{$hash}x";
			
			// headers for attachment 
			$headers .= "Content-Type: multipart/mixed;\n" . " boundary=\"{$mime_boundary}\""; 
			
			//start email message with HTML/plain content
			$temp_messaage = "This is a multi-part message in MIME format.\n\n" . "--{$mime_boundary}\n";
			$temp_messaage .= "Content-type: text/{$mailtype}; charset=UTF-8\r\n" . "Content-Transfer-Encoding: 7bit\n\n" . $message . "\n\n";

			//loop through all attachments in the array
			foreach($this->attachments as $single){
			
				//create email with attachment
				$attachment = chunk_split(base64_encode($single['blob'])); 

				//create attachment section
				$temp_messaage .= "--{$mime_boundary}\n" .
				"Content-Type: ". $single['filetype'] .";\n" .
				" name=\"". $single['filename'] ."\"\n" .
				"Content-Disposition: attachment;\n" .
				" filename=\"". $single['filename'] ."\"\n" .
				"Content-Transfer-Encoding: base64\n\n" .
				$attachment . "\n\n";
			}
			
			//final booundary echo with trailing dashes to signify end
			$temp_messaage .= "--{$mime_boundary}--\r\n";
			
			//save to message
			$message = $temp_messaage;
		}

		//add CC header
		if($this->cc != NULL){
			$headers .= "Cc: {$this->cc}\r\n";
		}

		//add BCC header
		if($this->bcc != NULL){
			$headers .= "Bcc: {$this->bcc}\r\n";
		}

		//set the message according to the body
		if($this->body != NULL){

			//contents of message body
			if($this->body_append){
				if($this->body_top){
					$message = $this->html 
						? $this->body.'<br />'.$message 
						: $this->body."\n\n".$message;
				}else{
					$message .= $this->html 
						? '<br />'.$this->body
						: "\n\n".$this->body;
				}
			}else{
				$message = $this->body;
			}
		}

		//mail send
		mail($this->to, $this->subject, $message, $headers);
	}

	//------------- ATTACHES A FILE TO THE EMAIL ------//
	public function attach($file, $required = true, $types = 'doc|docx|gif|jpg|pdf|png|rtf|txt|xls|xlsx', $size = null){
		//file upload check
		if($_FILES[$file]['error'] != UPLOAD_ERR_NO_FILE){

			//set the max file size to the PHP upload limit, if no size is provided
			if(is_null($size)){
				$size = str_replace('M', '', ini_get("upload_max_filesize"));
			}

			//check for any file upload errors
			if($_FILES[$file]["error"] > 0){
				die("Error: " . $file["error"]);
			}

			//file types check
			if(!preg_match('/^.*\.('.$types.')$/i', $_FILES[$file]["name"])){
				die("Error: Uploaded file type is not allowed.");
			}

			//file size check
			if(($_FILES[$file]["size"]) > ($size * 1024 * 1024)){
				die("File too large. It exceeds the file size limit of {$size}M.");
			}

			//attachment piece hash
			$hash = md5(date('r', time()).$_FILES[$file]["name"]);

			//move file stream
			$this->attachments[$hash]['blob'] = file_get_contents($_FILES[$file]['tmp_name']);
			$this->attachments[$hash]['filename'] = strtolower($_FILES[$file]['name']);
			$this->attachments[$hash]['filetype'] = $_FILES[$file]["type"];

		} else if($required){
			die("File upload required.");
		}
	}

	//------------- ATTACHES A BLOB/IMAGE/FILESTREAM TO THE EMAIL ------//
	public function blob($filename, $filetype, $blob){
		//attachment piece hash
		$hash = md5(date('r', time()).$filename);

		//move file stream
		$this->attachments[$hash]['blob'] = $blob;
		$this->attachments[$hash]['filename'] = $filename;
		$this->attachments[$hash]['filetype'] = $filetype;
	}

	//------------- ADDS SUBMISSION DATE TO THE EMAIL BODY ------//
	public function submitDate($position = 'top'){

		//create a datestamp array
		$date = array('date_submitted' => date(DateTime::COOKIE));

		//insert into data array
		$this->data = ($position == 'top') ? $date+$this->data : $this->data+$data;
	}

	//------------- SET A CUSTOM EMAIL BODY --------------------//
	public function body($body, $append = true, $top = true){

		//set the message 
		$this->body = $body;

		//set the type of body
		$this->body_append = $append;

		//set the position of the body
		$this->body_top = $top;
	}

	//------------- CONVERTS ARRAY ELEMENTS TO A USABLE TEXT STRING ------//
	private function array2text($array, $output = "", $prefix = ""){
		foreach($array as $i => $x){
			if(is_string($i) && in_array($i, $this->breaks) || is_array($x))
				$output .= "\n";

			if(is_array($x)){ //recursion
				$output .= $prefix.strtoupper(str_replace('_',' ',$i))."\n";
				$output .= $this->array2text($x, '', $prefix."\t");
			}
			else { //no recursion
				$output .= is_string($i) ? $prefix.ucwords(str_replace('_',' ',$i)).': ' : $prefix;
				$output .= $x."\n";
			}
		}
		return $output;
	}

	//------------- CONVERTS ARRAY ELEMENTS TO USABLE HTML ------//
	private function array2html($array){

		//start the output table HTML
		$output = '<table>';

		//loop through data array
		foreach($array as $i => $x){
		
			//print a blank row for a break
			if(is_string($i) && in_array($i, $this->breaks)){
				$output .= '<tr><td class="blank" colspan="2">&nbsp;</th></tr>';
			}

			//start a new row
			$output .=  '<tr valign="top"><th scope="row">'.ucwords(str_replace('_',' ',$i)).'</th>';

			//print all of the data
			if(is_array($x)){ //recursion
				$output .= '<td class="blank">';
				$output .= $this->array2html($x);
				$output .= '</td>';
			} else {
				$output .= '<td>'.$x.'</td>';
			}

			//end a single row
			$output .=  '</tr>';
		}

		//start the output table HTML
		$output .= '</table>'."\n";

		return $output;
	}

	//------------- REMOVES ALL BLANK FIELDS ------//
	public static function postClean($array){

		//loop through given array
		foreach($array as $index => $x){

			//check for nested arrays
			if(is_array($x)){

				//recursive call
				$array[$index] = FormProcessor::postClean($x);
				if(empty($array[$index])) unset($array[$index]);

			//check for null values
			}elseif(trim($x) == NULL) {

				//remove the elemet from the array
				unset($array[$index]);
			}
		}
		return $array;
	}

	//------------- CHECKS FOR A BLANK DATA ARRAY ------//
	private function blankCheck($array){

		//completely blank given array
		if(empty($array)) throw new Exception("Cannot submit a blank form.");
	}

	//------------- CHECK ALL FIELDS FOR AN EMAIL HEADER ------//
	private function checkHeader($array){

		//loop through array
		foreach ($array as $x){

			//check field for an email header
			if(!is_array($x)){
				if(preg_match("/(%0A|%0D|\n+|\r+)(content-type:|to:|cc:|bcc:)/i",$x)){
					throw new Exception("Email headers are not allowed, sorry!");
				}
			//recursion for arrays
			} else {
				if($this->checkHeader($x)){
					return true;
				}
			}
		}
		return false;
	}

	//------------- REMOVES ALL LEADING WHITESPACE AND BLANK INPUTS ------//
	private function removeWhite(&$white){
		if(!is_array($white)){
			$white = ltrim(strip_tags($white));
		} else {
			foreach($white as $key => $value){
				$white[$key] = $this->removeWhite($value);
			}
		}
		return $white;
	}

	//------------- PREVENTS NON-REFERRER CLIENT ACCESS ------//
	private function httpHeaders(){
		if(!(isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']) && stristr($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST']))){
			throw new Exception("Referer logging must be enabled to use this form, sorry!");
		}
	}

	//------------- LIMITS ALL INPUTS TO CHAR_LIMIT CHARACTERS ------//
	private function shout(&$laundry){

		//foreach element in the given array
		foreach($laundry as $spot => $dirt){

			//if it is an array, loop recursively
			if(is_array($dirt)){

				//run shout on all elements of the array
				return $laundry[$spot] = $this->shout($dirt);
			}

			//otherwise
			else{

				//slice off only the first CHAR_LIMIT characters of the field
				return $laundry[$spot] = substr($dirt, 0, FormProcessor::CHAR_LIMIT);
			}
		}
	}

	//------------- INSERTS THE DATA INTO SQL ------//
	public function SQLInsert(){
		//prepares data for SQL insert
		FormProcessor::oxyClean($this->fields);

		//smashes all nested arrays into strings
		$this->implodeArrays($this->fields);

		//query
		$query = "INSERT INTO [{$this->table}] ([".implode('], [',array_keys($this->fields))."]) VALUES ('".implode("', '",$this->fields)."')";
		$return = sqlsrv_query($this->conn, $query) or die(print_r( sqlsrv_errors(), true));
	}

	//------------- SANITIZES DATA FOR INSERT INTO SQL ------//
	public static function oxyClean(&$laundry){

		//regex encoded HTML filters array
		$non_displayables = array('/%0[0-8bcef]/','/%1[0-9a-f]/','/[\x00-\x08]/','/\x0b/','/\x0c/','/[\x0e-\x1f]/');

		//type check
		if(is_array($laundry)){
			//recursion
			foreach($laundry as $spot => $dirt){
				$laundry[$spot] = FormProcessor::oxyClean($dirt);
			}

			//return
			return $laundry;

		} else {
			//ignore blank values
			if(!isset($laundry) || ($laundry == NULL)){
				return NULL;
			}

			//no need to sanitize numbers
			if(is_numeric($laundry)){
				return $laundry;
			}

			//replace all filtered elements with null
			foreach($non_displayables as $regex){
				$laundry = preg_replace($regex, '', $laundry);
			}

			//strip HTML and trim whitespace from values
			$laundry = trim(strip_tags($laundry));

			//return
			return $laundry;
		}
	}

	//TODO: This can only go one level deep
	//------------- TRANSFORMS ALL POST ARRAYS INTO A SINGLE STRING ------//
	private function implodeArrays(&$dirty){
		foreach($dirty as $index => $x){
			if(is_array($x)){
				$dirty[$index] = implode(', ',$x);
			}
		}
	}
}
?>