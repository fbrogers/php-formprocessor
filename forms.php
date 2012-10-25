<?php
class FormProcessor{

	//email properties
	private $from;
	private $to;
	private $cc;
	private $bcc;
	private $subject;

	//formatting properties
	private $breaks = array();
	private $html;
	
	//data properties
	private $data;
	private $attachments = array();

	//internal class properties
	private $hash;
	private $body;
	private $body_append;
	private $body_top;

	//config properties
	private $limit;
	private $dev_mode;
	private $dev_email;
	private $default_file_types;
	private $install_dir;
	private $styles;

	//constructor
	public function __construct(){

		//open config file
		$config = parse_ini_file('config.ini');

		//set config file options to properties
		$this->limit = $config['CHAR_LIMIT'];
		$this->dev_mode = (bool)$config['DEV_MODE'];
		$this->dev_email = $config['DEV_EMAIL'];
		$this->default_file_types = $config['ALLOWED_FILE_TYPES'];
		$this->install_dir = $config['INSTALL_DIR'];

		//import styles
		$this->styles = file_get_contents($this->install_dir.$config['EMAIL_STYLING']);

		//assign default values
		$this->data = $_POST;
		$this->from = $this->dev_email;
		$this->subject = 'Form Submission';
		$this->html = true;

		//check for blank array
		if(empty($this->data)){
			throw new Exception("Cannot submit a blank form.", 1);			
		}

		//check for email headers in values
		$this->checkHeader($this->data);

		//check for referral headers
		$this->httpHeaders();

		//remove all null elements
		$this->data = $this->postClean($this->data);
		
		//strip html, trim values, and truncate to CHAR_LIMIT
		$this->oxyClean($this->data, $this->limit);
	}

	/*-------------------------------------------------------------------------------------------------------------------*/
	/*--- EMAIL SENDER METHOD -------------------------------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------*/

	//constructs and sends an email
	public function send($redirect = false){

		//assign development team email to bcc
		if($this->dev_mode){
			$this->bcc .= $this->bcc == NULL ? $this->dev_email : ','.$this->dev_email;
		}

		//check for required fields
		if(empty($this->to)){
			throw new Exception('"To:" email recipient not set.', 1);
		}
		if($this->from == NULL){
			throw new Exception('"From:" email recipient not set.', 1);			
		}

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

		//set the message according to the body
		if($this->body != NULL){

			//contents of message body
			if($this->body_append){

				//position boolean
				if($this->body_top){
					$message = $this->html 
						? $this->body.'<br />'.$message 
						: $this->body."\n\n".$message;
				} else {
					$message .= $this->html 
						? '<br />'.$this->body
						: "\n\n".$this->body;
				}

			//replace the body
			} else {
				$message = $this->body;
			}
		}

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

		//add cc header if set
		if($this->cc != NULL){
			$headers .= "Cc: {$this->cc}\r\n";
		}

		//add bcc header if set
		if($this->bcc != NULL){
			$headers .= "Bcc: {$this->bcc}\r\n";
		}

		//mail send
		mail($this->to, $this->subject, $message, $headers);

		//redirect to the indicated page if set
		if($redirect){
			header("Location: {$redirect}");
		}
	}	

	/*-------------------------------------------------------------------------------------------------------------------*/
	/*--- SITE DATA INPUT METHODS (MUTATORS / SETTERS) ------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------*/

	//setter for FROM email field
	public function from($email){

		//check type
		if(!is_string($email)){
			throw new Exception("FROM field must be passed as a string.", 1);
		}

		//regex for email address
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
			throw new Exception("FROM field must be a valid email address.", 1);
		}

		//set property
		$this->from = $email;
	}

	//setter for TO email field
	public function to($email){

		//check type
		if(!is_array($email) and !is_string($email)){
			throw new Exception("TO field must be passed as a string or an array.", 1);
		}

		//type fracture
		if(is_array($email)){

			//check for empty array
			if(empty($email)){
				throw new Exception("TO field must not be blank.", 1);
			}

			//check each address
			foreach($email as $address){

				//regex for email address
				if(!filter_var($address, FILTER_VALIDATE_EMAIL)){
					throw new Exception("All TO fields must be valid email addresses.", 1);
				}
			}

			//convert array to string
			$input = implode(',', $email);

		//string
		} else {

			//check for null string
			if($email == NULL){
				throw new Exception("TO field must not be blank.", 1);
			}

			//regex for email address
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
				throw new Exception("TO field must be a valid email address.", 1);
			}

			//set to internal variable
			$input = $email;
		}

		//set property
		$this->to = $input;
	}

	//setter for CC email field
	public function cc($email){

		//check type
		if(!is_array($email) and !is_string($email)){
			throw new Exception("CC field must be passed as a string or an array.", 1);
		}

		//type fracture
		if(is_array($email)){

			//check for empty array
			if(empty($email)){
				throw new Exception("CC field must not be empty.", 1);
			}

			//check each address
			foreach($email as $address){

				//regex for email address
				if(!filter_var($address, FILTER_VALIDATE_EMAIL)){
					throw new Exception("All CC fields must be valid email addresses.", 1);
				}
			}

			//convert array to string
			$input = implode(',', $email);

		//string
		} else {

			//check for null string
			if($email == NULL){
				throw new Exception("CC field must not be blank.", 1);
			}

			//regex for email address
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
				throw new Exception("CC field must be a valid email address.", 1);
			}

			//set to internal variable
			$input = $email;
		}

		//set property
		$this->cc = $input;
	}

	//setter for BCC email field
	public function bcc($email){

		//check type
		if(!is_array($email) and !is_string($email)){
			throw new Exception("BCC field must be passed as a string or an array.", 1);
		}

		//type fracture
		if(is_array($email)){

			//check for empty array
			if(empty($email)){
				throw new Exception("BCC field must not be empty.", 1);
			}

			//check each address
			foreach($email as $address){

				//regex for email address
				if(!filter_var($address, FILTER_VALIDATE_EMAIL)){
					throw new Exception("All BCC fields must be valid email addresses.", 1);
				}
			}

			//convert array to string
			$input = implode(',', $email);

		//string
		} else {

			//check for null string
			if($email == NULL){
				throw new Exception("BCC field must not be blank.", 1);
			}

			//regex for email address
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
				throw new Exception("BCC field must be a valid email address.", 1);
			}

			//set to internal variable
			$input = $email;
		}

		//set property
		$this->bcc = $input;
	}

	//setter for SUBJECT email field
	public function subject($input){

		//type check
		if(!is_string($input) or $input == NULL){
			throw new Exception("SUBJECT field must be a non-empty string.", 1);
		}

		//set property
		$this->subject = trim(strip_tags($input));
	}

	//setter for the visual breaks in the email code
	public function breaks($input){

		//type check
		if(!is_array($input) or empty($input)){
			throw new Exception("BREAKS field must be passed as an array.", 1);
		}

		//check each field
		foreach($input as $field){
			if(!isset($this->data[$field])){
				throw new Exception("{$field} does not exist in the data object, and cannot be a break.", 1);
			}
		}

		//set property
		$this->breaks = $input;
	}

	//setter for html email bit field
	public function html($input){

		//type check
		if(!is_bool($input)){
			throw new Exception("HTML input must be a boolean value.", 1);			
		}

		//set property
		$this->html = $input;
	}

	//adds an attachment
	public function attach($file, $required = true, $types = null, $size = null){

		//check allowed types
		if($types == NULL){
			$types = $this->default_file_types;
		}

		//file upload check
		if($_FILES[$file]['error'] > 0){
			if($required){
				throw new Exception("File upload required.", 1);
			}else{
				return true;
			}
		}

		//set the max file size to the PHP upload limit, if no size is provided
		if($size == NULL){
			$size = str_replace('M', '', ini_get("upload_max_filesize"));
		}

		//file types check
		if(!preg_match('/^.*\.('.$types.')$/i', $_FILES[$file]["name"])){
			throw new Exception("Error: Uploaded file type is not allowed.");
		}

		//file size check (converts bytes to megabytes)
		if($_FILES[$file]["size"] > ($size * 1048576)){
			throw new Exception("File too large. It exceeds the file size limit of {$size}M.");
		}

		//attachment piece hash
		$hash = md5(date('r', time()).$_FILES[$file]["name"]);

		//retrieve file data
		if(!($data = file_get_contents($_FILES[$file]['tmp_name']))){
			throw new Exception("Unable to retrieve attachment file data.", 1);			
		}

		//move file stream
		$this->attachments[$hash]['blob'] = $data;
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
	public function date_submitted($top = false){

		//create a datestamp array
		$date = array('date_submitted' => date(DateTime::COOKIE));

		//insert into data array
		$this->data = $top 
			? array_merge($date, $this->data)
			: array_merge($this->data, $date);
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

	//set dev mode locally
	public function dev_mode($input){

		//type check
		if(!is_bool($input)){
			throw new Exception("Dev mode input must be a boolean value.", 1);			
		}

		//set property
		$this->dev_mode = $input;
	}

	/*-------------------------------------------------------------------------------------------------------------------*/
	/*--- PRIVATE METHODS -----------------------------------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------*/

	//converts array elements into a usable text string
	private function array2text($array, $output = NULL, $prefix = NULL){

		//loop through data array
		foreach($array as $i => $x){

			//add an extra line break if certain conditions are met
			if(is_string($i) && in_array($i, $this->breaks) || is_array($x)){
				$output .= "\n";
			}

			//recursion
			if(is_array($x)){ 
				$output .= $prefix.strtoupper(str_replace('_',' ',$i))."\n";
				$output .= $this->array2text($x, '', $prefix."\t");

			//no recursion
			} else { 
				$output .= is_string($i) ? $prefix.ucwords(str_replace('_',' ',$i)).': ' : $prefix;
				$output .= $x."\n";
			}
		}

		//return the rendered output
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

			//array check
			if(is_array($x)){

				//recursion
				return $this->checkHeader($x);
			} 

			//check field for an email header
			if(preg_match("/(%0A|%0D|\n+|\r+)(content-type:|to:|cc:|bcc:)/i",$x)){
				throw new Exception("Email headers are not allowed, sorry!");
			}
		}

		//return a bit flag
		return true;
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

	/*-------------------------------------------------------------------------------------------------------------------*/
	/*--- STATIC METHODS ------------------------------------------------------------------------------------------------*/
	/*-------------------------------------------------------------------------------------------------------------------*/
	
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
	public static function oxyClean(&$laundry, $limit = false){

		//type check
		if(is_array($laundry)){

			//recursion
			foreach($laundry as $spot => $dirt){
				$laundry[$spot] = FormProcessor::oxyClean($dirt, $limit);
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

			//limit output to CHAR_LIMIT if limit is set
			$laundry = $limit ? substr($laundry, 0, $limit) : $laundry;
		}

		//return
		return $laundry;
	}
}
?>