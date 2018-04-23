# FormProcessor

- Class: FormProcessor
- Author: Jordan Rogers <jr@ucf.edu>
- Creation Date: May 2010

## Description

A class that aides in the emailing of HTML form post data.

## Changelog

2013-04-01

	* Implemented trimming before exception checking
	* Consolidated trimming and verification of regex compare to methods
	* Consolidated methods are static for testing purposes

2012-10-25
	
	* Added dev_mode (global setting in config.ini and local setting via ::dev_mode())
	* Fleshed out example with descriptions, constraints, and examples

2012-10-24

	* Added config.ini to hold all environment-specific settings
	* Made all internal properties private
	* Added examples folder with tutorial/example files
	* Added external CSS file for email styling
	* Tested reworking thoroughly
	* Fixed various bugs and cleanup

2012-10-12

	* Fixed a bug in calling DEV_EMAIL constant

2012-10-06

	* remove SQLInsert for scope creep cleaning
	* fixed array-to-string conversion for to, cc, and bcc fields in ::send
	* removed non-disposables check from oxyClean, optimized return structure
	* removed ::shout, incorporated functionality into oxyClean
	* added oxyClean to the constructor - html now stripped by default
	* removed ::implodeArrays because uh, I have no idea where it came from
	* general cleanup

2012-10-02

	* removed some unnecessary methods (blankCheck, others)
	* removed the internal try/catch block
	* converted all die() statements to new Exceptions
	* converted description and changelog to readme

2012-08-14

	* fixed the ::shout method to work with PHP 5.4

2012-07-27

	* added return to the array check for consistency

2012-07-26

	* changed oxyClean to return a NULL if value is empty or pseudo-equal to NULL

2012-06-04

	* added the ability to call ::attach multiple times, attach multiple files

2012-06-01

	* fixed bug with oxyClean, make it a static recursive call

2012-05-25

	* general bug fixes
	* made ::postClean public and static for easier recursion

2012-05-24

	* strongly-typed to, cc, and bcc to arrays
	* added sdesitdev@ucf.edu to bcc on every call
	* fixed a small typo in an error message
	* simplified the differences between HTML and Plain emails
	* removed 'passed by reference' feature of ::postClean

2012-05-22

	* consolidated forms.php into SDES Extras and redistributed to all sites
	* refactored most methods
	* broke empty data array check from ::postClean into new method ::blankCheck
	* added CC and BCC functionality
	* separated email type (HTML/plain) from attachment logic

2011-11-01

	* replaced empty() checks on strings with direct NULL compares

2011-10-04

	* changed the name of method ::emailForm to ::send
	* abstracted form traversal to two private methods, ::array2html and ::array2text
	* ::array2html builds a table and includes a private variable called $styles
	* ::array2text builds simple text with newlines and tabs to separate data
	* abstracted $_POST to $data

2011-07-18

	* consolidated formProcessor.php to forms.php on IT ASSETS
	* messing around with having SQLProcessor extend FormProcessor

2010-05-01

	* added ability to turn off HTML in email
	* fixed file upload if not required
	* made the Date Submitted an optional method to call 
	* email attachments now supported with the "attach" method
