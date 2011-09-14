<?php

// Enable debugging
error_reporting(E_ALL);
ini_set('display_errors', true);
$debug = false;

// Note that most of the error checking from form results just makes us switch to display
// the question (or first / last question as appropriate) - this is not fed back to the user they just the question
// look for $action = 'display' to see where we've switched back to default display


require_once("includes/setup.php");
require_once("includes/QuestionNavigation.php");	// used later for navigation buttons

// action is a string based on button pressed - determines how we handle the post
// default action is to show question
$action = 'display';
// message is used to provide feedback to the user
// most cases we ignore errors, but for instance if a user does not enter a number in a number field we can notify the user of this when we go in display mode
$message = '';


print "Navigation option is ".$_POST['nav']." <br />";

// get the list of questions and current status
$quiz_info = $quiz_session->getSessionInfo();
if (!isset($quiz_info['status'])||!is_int($quiz_info['status']))
{
	$err = Errors::getInstance();
	$err->errorEvent(WARNING_SESSION, "Session status is invalid");
	// kill session and send to index page
	$quiz_session->destroySession();
	// -here - return to main page on error - we need to provide a message to the user 
	// most likely session timed out or gone direct to question.php?
	header("Location: ".INDEX_FILE);
}

// Get the list of question numbers and current answers from session
$questions_array = $quiz_session->getQuestions();
// If we don't have questions then we error
if (count($questions_array)<1) 
{
	$err = Errors::getInstance();
	$err->errorEvent(ERROR_SESSION, "Session does not have any questions defined");
}
else {$num_questions = count($questions_array);}

$answers_array = $quiz_session->getAnswers();

// class for action
// we are using default from settings - could override here if required - will also need to pass with showNavigation
$navigation = new QuestionNavigation(1, $num_questions);

//submit buttons
// Determine what action required based on submit
if (isset($_POST['nav']))
{
	// see if this is a valid action - gets action back
	$action = $navigation->getAction ($_POST['nav']);
	if ($action == 'invalid') 
	{
		$err = Errors::getInstance();
		$err->errorEvent(WARNING_PARAMETER, "Action invalid - defaulting to display");
		$action = 'display';
	}
}


// get question number from the post
// question number is from 1 upwards - not 0 as the session array does
// Check that this is a number and that it is within the session questions - otherwise we default to 1st question
// if we have to change the question number to default then we also change the action to default - for example should not be saving answer if answer was given to out-of-range question
// note is_int does not work - so using is_numeric instead 
if (!isset($_POST['question']) || !is_numeric($_POST['question']) || $_POST['question'] < 1)
	{
		$question_num = 1;
		// set action to default as we didn't have a valid question number
		$err = Errors::getInstance();
		$err->errorEvent(INFO_PARAMETER, "No question number provided - using default display and question 1");
		$action == 'display';
	}
elseif ($_POST['question'] > $num_questions) 
	{
		$question_num = $num_questions;
		$err = Errors::getInstance();
		$err->errorEvent(WARNING_PARAMETER, "Parameter question number too high setting to max / display");
		// set action to default as we didn't have a valid question number
		$action == 'display';
	}
else {$question_num = $_POST['question'];}
// load this question - note -1 used to select array position (ie. question 1 = array 0)
// if we are just doing a display then we can use this same instance for the display later - otherwise we will need a new instance for the new question
$question_from = new Question(0, $qdb->getQuestion($questions_array[$question_num-1]));



// todo Save answer if changed
$answer = '';
// check for hidden field to show that this was from an existing question
if ($action != 'display')
{
	// no type - we didn't come from a question display
	if (!isset ($_POST['type']) || !$question_from->validateType($_POST['type'])) 
	{
		$err = Errors::getInstance();
		$err->errorEvent(WARNING_PARAMETER, "Parameter type does not match question type");
		$action = 'display';
	}
	// checkbox is handled differently for a checkbox as it can be multiple answers
	else if ($_POST['type'] == 'checkbox')
	{
	for ($i=0; $i<10; $i++)
		{
			if (isset ($_POST['answer-'.$i])) {$answer .= $i;}
		}
		// if none selected set back to default (-1)
		if ($answer == '') {$answer = -1;}
	}
	//  handle default where answer is not set at all (eg. radio with nothing ticked)
	else if (!isset ($_POST['answer']) || $_POST['answer'] == '') 
	{
		$answer = '';
	}
	else // All others we just have one value from post which is $answers 
	{
		if ($question_from->validateAnswer($_POST['answer']))
		{
			$answer = $_POST['answer'];
		}
		else
		{
		// set message as this may have been a genuine error (eg. seven instead of 7)
		$err = Errors::getInstance();
		$err->errorEvent(INFO_PARAMETER, "Answer was not a valid response for ". $question_from->getType());
		$message = 'Answer provided was not valid';
		$action = 'display';
		}
	}
}




// check that we haven't changed back to display due to invalid entry before we save
if ($action != 'display')
{
	$all_answers = $quiz_session->getAnswers();
	// debug Dump answers
	//print "<h3>Prev answers</h3>";
	//print_r ($all_answers);
	// check for exact match (checkbox 01 == 1 which is wrong)
	if ($all_answers[$question_num-1] !== $answer) 
	{
		$all_answers[$question_num-1] = $answer;
		$quiz_session->setAnswers($all_answers);
	}
	// otherwise not changed so no need to save
}

// debug Dump answers
//print "<h3>New answers</h3>";
//print_r ($all_answers);


	

if ($debug) {print "Action is $action";}
// todo Handle change in page (eg. Finish / trying to go past first) 
if ($action == 'first') {$question_num = 1;}
else if ($action == 'previous') {$question_num--;}
else if ($action == 'next') {$question_num ++;}
else if ($action == 'last') {$question_num = $num_questions;}

if ($question_num < 1) {$question_num = 1;}
if ($question_num > $num_questions || $action == 'review')
{
	// todo - go to review
	exit (1);
}



// Pull in templates
$templates->includeTemplate('header', 'normal');

// start form
// Form starts at the top
print "<form id=\"".CSS_ID_FORM."\" method=\"post\" action=\"question.php\">\n";

// show message if there is one
if ($message != '') {print "<p class=\"".CSS_CLASS_MESSAGE."\">$message</p>\n";}

// show position in quiz
// todo may want to allow this wording to be changed via a setting
print "<p class=\"".CSS_CLASS_STATUS."\">Question $question_num of $num_questions</p>\n";



// load this question - note -1 used to select array position (ie. question 1 = array 0)
$question = new Question(0, $qdb->getQuestion($questions_array[$question_num-1]));
// first print status bar if req'd (eg. question 1 of 10)
// answer is currently selected -1 = not answered
print ($question->getHtmlString($quiz_session->getAnswer($question_num-1)));


// add navigation buttons
print "<div id=\"".CSS_ID_NAVIGATION."\">\n";
$navigation->showNavigation($question_num);
print "\n</div><!-- ".CSS_ID_NAVIGATION." -->\n";

// end form
print "</form>\n";


// footer templates
$templates->includeTemplate('footer', 'normal');


// Debug mode - display any errors / warnings
if (isset($debug) && $debug)
{
	$err =  Errors::getInstance();
	if ($err->numEvents(INFO_LEVEL) > 0)
	{
		print "Debug Messages:\n";
		print $err->listEvents(INFO_LEVEL);
	}
}


?>