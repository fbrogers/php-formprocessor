<?php

class FormProcessor{

	//user-definable properties
	public $from;
	public $to = array();
	public $cc = array();
	public $bcc = array();
	public $subject;
	public $breaks = array();
	public $html;
	public $conn;

	//internal, private properties
	private $data;
	private $hash;
	private $attachments = array();
	private $styles;
	private $body;
	private $body_append;
	private $body_top;

	//constant settings
	const CHAR_LIMIT = 3000;
	const DEV_EMAIL = 'sdesitdev@ucf.edu';

	//constructor
	public function __construct(){

		//if the submit button is set, remove it
		if(isset($_POST['form_submit'])){
			unset($_POST['form_submit']);
		}

		//assign default values
		$this->data = $_POST;
		$this->from = 'sdesitdev@ucf.edu';
		$this->subject = 'Form Submission';
		$this->html = true;

		//check for blank array
		if(empty($this->data)){
			throw new Exception("Cannot submit a blank form.", 1);			
		}

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

		//check for email headers in values
		$this->checkHeader($this->data);

		//check for referral headers
		$this->httpHeaders();

		//remove all null elements
		$this->data = $this->postClean($this->data);
		
		//strip html, trim values, and truncate to CHAR_LIMIT
		$this->oxyClean($this->data);
	}

	//constructs and sends an email
	public function send($redirect = false){

		//assign development team email to bcc
		$this->bcc[] = $this::DEV_EMAIL;

		//check for required fields
		if(empty($this->to)){
			throw new Exception('"To:" email recipient not set.', 1);
		}
		if($this->from == NULL){
			throw new Exception('"From:" email recipient not set.', 1);			
		}

		//converting all email headers to strings
		$to = implode(',', $this->to);
		$cc = implode(',', $this->cc);
		$bcc = implode(',', $this->bcc);

		//add generic headers
		$headers = NULL;
		$headers .= "From: {$this->from}\r\n";
		$headers .= "Reply-To: {$this->from}\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Return-Path: {$this->from}\r\n"; // these two to set reply address
		$headers .= "Message-ID: <".time()."@ucf.edu>"."\r\n";
		$headers .= "X-Mailer: UCF / SDES IT FormProcessor Class"."\r\n"; // These two to help avoid spam-filters
		$headers .= "Date: ".date("r")."\r\n";

		//begin message body
		$message = $this->html
			? $this->styles.$this->array2html($this->data)
			: $this->array2text($this->data);

		//set the mail type to html or plain
		$mailtype = $this->html ? 'html' : 'plain';

		//attachment check and construction
		if(empty($this->attachments)){

			//set the mail type
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

		//add cc header
		if($cc != NULL){
			$headers .= "Cc: {$cc}\r\n";
		}

		//add bcc header
		if($bcc != NULL){
			$headers .= "Bcc: {$bcc}\r\n";
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
		mail($to, $this->subject, $message, $headers);

		//redirect to the indicated page if set
		if($redirect){
			header("Location: {$redirect}");
		}
	}

	//TODO: Disable/Enable DevMode

	//adds an attachment
	public function attach($file, $required = true, $types = 'doc|docx|gif|jpg|pdf|png|rtf|txt|xls|xlsx', $size = null){

		//file upload check
		if($_FILES[$file]['error'] > 0){
			if($required){
				throw new Exception("File upload required.", 1);
			}else{
				return true;
			}
		}

		//set the max file size to the PHP upload limit, if no size is provided
		if(is_null($size)){
			$size = str_replace('M', '', ini_get("upload_max_filesize"));
		}

		//check for any file upload errors
		if($_FILES[$file]['error'] > 0){
			throw new Exception("Error: " . $file["error"]);
		}

		//file types check
		if(!preg_match('/^.*\.('.$types.')$/i', $_FILES[$file]["name"])){
			throw new Exception("Error: Uploaded file type is not allowed.");
		}

		//file size check
		if(($_FILES[$file]["size"]) > ($size * 1024 * 1024)){
			throw new Exception("File too large. It exceeds the file size limit of {$size}M.");
		}

		//attachment piece hash
		$hash = md5(date('r', time()).$_FILES[$file]["name"]);

		//move file stream
		$this->attachments[$hash]['blob'] = file_get_contents($_FILES[$file]['tmp_name']);
		$this->attachments[$hash]['filename'] = strtolower($_FILES[$file]['name']);
		$this->attachments[$hash]['filetype'] = $_FILES[$file]["type"];
	}

	//attach a file stream
	public function blob($filename, $filetype, $blob){

		//attachment piece hash
		$hash = md5(date('r', time()).$filename);

		//move file stream
		$this->attachments[$hash]['blob'] = $blob;
		$this->attachments[$hash]['filename'] = $filename;
		$this->attachments[$hash]['filetype'] = $filetype;
	}

	//add submission date to the body of the email
	public function submitDate($position = 'top'){

		//create a datestamp array
		$date = array('date_submitted' => date(DateTime::COOKIE));

		//insert into data array
		$this->data = ($position == 'top') ? $date+$this->data : $this->data+$data;
	}

	//set a custom email body
	public function body($body, $append = true, $top = true){

		//set the message 
		$this->body = $body;

		//set the type of body
		$this->body_append = $append;

		//set the position of the body
		$this->body_top = $top;
	}

	//converts array elements into a usable text string
	private function array2text($array, $output = NULL, $prefix = NULL){
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

	//converts array elements into usable HTML
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

		//return the html string
		return $output;
	}

	//checks all elements of a given array for an email header
	private function checkHeader($array){

		//loop through array
		foreach($array as $x){

			//check field for an email header
			if(!is_array($x)){
				if(preg_match("/(%0A|%0D|\n+|\r+)(content-type:|to:|cc:|bcc:)/i",$x)){
					throw new Exception("Email headers are not allowed, sorry!");
				}

			//recursion for arrays
			} else {
				return $this->checkHeader($x);
			}
		}
		return false;
	}

	//checks for valid http referer from the same tld
	private function httpHeaders(){

		//checks to verify that the referer exists
		if(!(isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER']))){
			throw new Exception("Referer logging must be enabled to use this form.");
		}

		//checks to verify that the referer set matches the current tld
		if(!stristr($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST'])){
			throw new Exception("Referer does not match the current site.", 1);			
		}
	}

	//removes all blank fields
	public static function postClean($array){

		//loop through given array
		foreach($array as $index => $x){

			//check for nested arrays
			if(is_array($x)){

				//recursive call
				$array[$index] = FormProcessor::postClean($x);

				//if returned empty, remove it
				if(empty($array[$index])){
					unset($array[$index]);
				}

			//check for null values
			}elseif(trim($x) == NULL) {

				//remove the elemet from the array
				unset($array[$index]);
			}
		}

		//return the array
		return $array;
	}
	
	//cleans an array of html, non-displayables, and white space
	public static function oxyClean(&$laundry){

		//type check
		if(is_array($laundry)){

			//recursion
			foreach($laundry as $spot => $dirt){
				$laundry[$spot] = FormProcessor::oxyClean($dirt);
			}

		} else {

			//ignore blank values
			if(!isset($laundry) or ($laundry == NULL)){
				return NULL;
			}

			//no need to sanitize numbers
			if(is_numeric($laundry)){
				return $laundry;
			}

			//strip non-allowed html, trim whitespace from result
			$laundry = trim(strip_tags($laundry, '<em><strong><ul><ol><li>'));

			//limit output to CHAR_LIMIT
			$laundry = substr($laundry, 0, FormProcessor::CHAR_LIMIT);
		}

		//return
		return $laundry;
	}
}
?>