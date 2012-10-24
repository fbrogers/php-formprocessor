<?php
	try{
		//instantiate new object
		$f = new FormProcessor();

		//set the FROM field (default: DEV_EMAIL)
		$f->from('fbrogers@gmail.com');

		//set the TO field (default: null but required)
		$f->to(['keya.jatkar@ucf.edu', 'jr@ucf.edu']);

		//set the CC field (optional)
		$f->cc('rachel.davis@ucf.edu');

		//set the BCC field (optional)
		//note: DEV_EMAIL will be added to bcc unless devmode is off
		$f->bcc(['rpgoodin@gmail.com', 'sean@ucf.edu', 'sdesitdev@ucf.edu']);

		//set the SUBJECT field (default: 'Form Submission')
		$f->subject('Live and Learn Luncheon | Submission');

		//used to attach file field uploads to the email
		$f->attach('resume'); //default
		$f->attach('resume', true); //default
		$f->attach('resume', false);
		$f->attach('resume', true, 'docx|xlsx');
		$f->attach('resume', true, 'docx|xlsx', 2);
		$f->attach('resume', true, null, 16);

		//attach a filestream to the email. useful for including static files on the server
		$f->blob('resume.pdf', 'application/pdf', $blob);
		$f->blob('title.png', 'image/png', file_get_contents('images/title.png'));

		//replace the body of email, or add to it, and pick the position of addition (top or bottom)
		$text = 'SDES IT is always looking for feedback, good or bad! We want to 
		serve our customers the best we can, which includes all UCF students and faculty.
		Tell us anything! Thank you again for the feedback! We appreciate every word!';

		$f->body($text); //default
		$f->body($text, true, true); //default
		$f->body($text, false, true);
		$f->body($text, false, false);

		//set the style of the email to html (default) or plain text (false)
		$f->html(true) //default
		$f->html(false);

		//add the date and time of submission to the email
		$f->date_submitted(); //default
		$f->date_submitted(false); //default
		$f->date_submitted(true);

		//send the email once properties have been set, indicate direct path (optional)
		$f->send(); //default
		$f->send('thanks');

		//end script execution if at end of page lifecycle
		exit();
	}

	//catch errors and display them
	catch(Exception $e){
		die('Error: '.$e->getMessage());
	}
?>