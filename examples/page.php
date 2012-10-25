<?php
	try{
		//instantiate new object (required, obviously)
		$f = new FormProcessor();


		/* ----------------------------------------------------------------------------
			EMAIL: FROM
		------------------------------------------------------------------------------- 
		constraints:
		- must be type string
		- the string must be a valid email address

		default: $this->dev_email

		example usage:
		$f->from('fbrogers@gmail.com');
		$f->from($_POST['email']);
		---------------------------------------------------------------------------- */
		$f->from('fbrogers@gmail.com');


		/* ----------------------------------------------------------------------------
			EMAIL: TO (REQUIRED)
		------------------------------------------------------------------------------- 
		constraints:
		- required field
		- must be type string or array of strings
		- all strings must be a valid email address

		default: none

		example usage:
		$f->to('jr@ucf.edu');
		$f->to(['keya.jatkar@ucf.edu', 'jr@ucf.edu']);
		---------------------------------------------------------------------------- */
		$f->to(['keya.jatkar@ucf.edu', 'jr@ucf.edu']);


		/* ----------------------------------------------------------------------------
			EMAIL: CC
		------------------------------------------------------------------------------- 
		constraints:
		- must be type string or array of strings
		- all strings must be a valid email address

		default: none

		example usage:
		$f->cc('jr@ucf.edu');
		$f->cc(['keya.jatkar@ucf.edu', 'jr@ucf.edu']);
		---------------------------------------------------------------------------- */
		$f->cc('rachel.davis@ucf.edu');


		/* ----------------------------------------------------------------------------
			EMAIL: BCC
		------------------------------------------------------------------------------- 
		note: DEV_EMAIL will be added to bcc unless devmode is off

		constraints:
		- must be type string or array of strings
		- all strings must be a valid email address

		default: none

		example usage:
		$f->bcc('jr@ucf.edu');
		$f->bcc(['keya.jatkar@ucf.edu', 'jr@ucf.edu']);
		---------------------------------------------------------------------------- */
		$f->bcc(['rpgoodin@gmail.com', 'sean@ucf.edu', 'sdesitdev@ucf.edu']);


		/* ----------------------------------------------------------------------------
			EMAIL: SUBJECT
		------------------------------------------------------------------------------- 
		constraints:
		- must be type string
		- all html will be stripped

		default: 'Form Submission'

		example usage:
		$f->subject('Live and Learn Luncheon | Submission');
		$f->subject('Submission | '.$_POST['name']);
		---------------------------------------------------------------------------- */
		$f->subject('Live and Learn Luncheon | Submission');


		/* ----------------------------------------------------------------------------
			EMAIL: BREAKS IN THE FORMATTING
		------------------------------------------------------------------------------- 
		description:
		this method takes in an array of strings that correspond to inputs in the form.
		for each match, the send() method will add styling separation above the listed
		input. Useful for visually separating sections of a form in the resulting email

		constraints:
		- must be type array
		- all strings in array must exist in $this->data

		default: none

		example usage:
		$f->breaks(['name', 'classes']);
		---------------------------------------------------------------------------- */
		$f->breaks(['name', 'classes']);


		/* ----------------------------------------------------------------------------
			EMAIL: TYPE OF EMAIL (HTML or PLAIN TEXT)
		------------------------------------------------------------------------------- 
		description:
		this method takes in a bool that set the style of the email to html (default, 
		true) or plain text (false).

		default: true

		example usage:
		$f->html(true); //default
		$f->html(false);
		---------------------------------------------------------------------------- */
		$f->html(true);


		/* ----------------------------------------------------------------------------
			EMAIL: ATTACHMENT(S)
		------------------------------------------------------------------------------- 
		description:
		this method takes in a string that corresponds to the "name" attribute of a 
		file input expected to be in the $_FILES superglobal. it takes in four
		parameters (three optional):

		1. string: file input name
		2. bool: whether or not the file upload is required
		3. string: allowed file extensions, separated by pipes (used in a regex)
		4. int: maximum allowed size (in MB) of file

		note: your form tag must include the attribute: enctype="multipart/form-data"
		in order for file inputs to post data to the $_FILES superglobal.

		constraints:
		- parameter 1: must be type string
		- parameter 2: must be type bool
		- parameter 3: must be type string
		- parameter 4: must be type int

		parameter defaults:
		1. none (required)
		2. true
		3. null (defaults to string in config.ini)
		4. null (defaults to "upload_max_filesize" in php.ini)

		example usage:
		$f->attach('resume');
		$f->attach('resume', true);
		$f->attach('resume', false);
		$f->attach('resume', true, 'docx|xlsx');
		$f->attach('resume', true, 'docx|xlsx', 2);
		$f->attach('resume', true, null, 16);
		---------------------------------------------------------------------------- */
		$f->attach('resume');


		/* ----------------------------------------------------------------------------
			EMAIL: FILESTREAM ATTACHMENT(S)
		------------------------------------------------------------------------------- 
		description:
		this method takes in a collection of parameters that describe a filestream.
		it takes in three parameters:

		1. string: file name, including extension
		2. string: MIME content-type
		3. binary: php variable containing the entire contents of a file

		defaults: none (all parameters required)

		example usage:
		$f->blob('resume.pdf', 'application/pdf', $blob);
		$f->blob('title.png', 'image/png', file_get_contents('images/title.png'));
		---------------------------------------------------------------------------- */
		$f->blob('resume.pdf', 'application/pdf', $blob);


		/* ----------------------------------------------------------------------------
			EMAIL: DATE SUBMITTED
		------------------------------------------------------------------------------- 
		description:
		this method adds the date/time of submission to the email. it takes in a bool
		that determines the placement of the date/time.

		default: off

		example usage:
		$f->date_submitted(); //default
		$f->date_submitted(false); //default
		$f->date_submitted(true);
		---------------------------------------------------------------------------- */
		$f->date_submitted(); //default


		/* ----------------------------------------------------------------------------
			EMAIL: BODY OF EMAIL
		------------------------------------------------------------------------------- 
		description:
		this method takes in a string of text or html to append to the current body or
		replace the body with. it takes in three parameters (two optional):

		1. string: plain text or html to use in append/replace
		2. bool: if true, appends text to body; if false; replaces body
		3. bool: if true, appends text above body; if false, appends text below body

		defaults:
		1. none (required)
		2. true (append)
		3. true (top)

		example usage:
		$text = 'SDES IT is always looking for feedback, good or bad! We want to 
		serve our customers the best we can, which includes all UCF students and faculty.
		Tell us anything! Thank you again for the feedback! We appreciate every word!';

		$f->body($text); //default
		$f->body($text, true, true); //default
		$f->body($text, false, true);
		$f->body($text, false, false);
		---------------------------------------------------------------------------- */
		$f->body($text);


		/* ----------------------------------------------------------------------------
			EMAIL: TYPE OF EMAIL (HTML or PLAIN TEXT)
		------------------------------------------------------------------------------- 
		description:
		this method sets $this->dev_mode locally (as dev_mode is set globally via the
		config.ini file). This is useful if your global setting is TRUE but you wish to
		exclude certain forms, or if your global setting is FALSE but you wish to see
		a new form in action for a few days/weeks/months.

		default: config.ini setting for DEV_MODE

		example usage:
		$f->dev_mode(true);
		$f->dev_mode(false);
		---------------------------------------------------------------------------- */
		$f->dev_mode(true);


		/* ----------------------------------------------------------------------------
			EMAIL: SEND
		------------------------------------------------------------------------------- 
		description:
		this method sends the email once properties have been set. it takes in one
		optional parameter of type string that describes a relative URL to redirect the
		user to after completion of the method.

		default: none (required)

		example usage:
		$f->send(); //default
		$f->send('thanks');
		---------------------------------------------------------------------------- */		
		$f->send('thanks');


		//end script exec if at end of page lifecycle (improves load times slightly)
		exit();
	}

	//catch errors and display them
	catch(Exception $e){
		die('Error: '.$e->getMessage());
	}
?>