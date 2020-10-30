<?php

///////////////////////////////////////////////////// CHANGED INFORMATION /////////////////////////////////////////////////////

//Database Connection
define('DB_NAME','3529708_famblahapi');   //your database username
define('DB_USER', '3529708_famblahapi');          //your database name
define('DB_PASS', '5ocialCodia');              //your database password
define('DB_HOST', 'fdb30.awardspace.net');     //your database host name

//Website Information
define('WEBSITE_DOMAIN', 'http://famblah.cf/public/');               //your domain name
define('WEBSITE_EMAIL', 'info@famblah.cf');                    //your email address
define('WEBSITE_EMAIL_PASSWORD', 'uumair88');                        //your email password
define('WEBSITE_EMAIL_FROM', 'Social Codia');                        // your website name here
define('WEBSITE_NAME', 'FAMBLAH');                              //your website name here
define('WEBSITE_OWNER_NAME', 'Umair Farooqui');                      //your name, or anyones name, we will send this name with email verification mail.

define('DEFAULT_USER_IMAGE', 'http://famblah.cf//public/uploads/api/user.png');

define('JWT_SECRET_KEY', 'SocialCodia');  								//your jwt secret key, Please use a very dificult secret key, which no one can guess it.

///////////////////////////////////////////// END CHANGE INFORMATION /////////////////////////////////////////////////////



///////////////////////////////////////////// DON'T TOUCH THIS /////////////////////////////////////////////////////

define('USER_CREATION_FAILED', 101);
define('USER_CREATED', 102);
define('EMAIL_EXIST', 103);
define('VERIFICATION_EMAIL_SENT', 104);
define('VERIFICATION_EMAIL_SENT_FAILED', 105);
define('USERNAME_EXIST', 1004); 		//change code


define('USER_NOT_FOUND', 106);
define('UNVERIFIED_EMAIL', 107);
define('LOGIN_SUCCESSFULL', 108);

define('EMAIL_VERIFIED', 109);
define('EMAIL_NOT_VERIFIED', 201);
define('INVALID_VERFICATION_CODE', 202);
define('SEND_CODE', 203);

define('EMAIL_ALREADY_VERIFIED', 204);
define('EMAIL_NOT_VALID', 205);

define('PASSWORD_CHANGED',206);
define('PASSWORD_CHANGE_FAILED', 207);
define('PASSWORD_WRONG', 208);
define('PASSWORD_SAME', 209);

define('INVAILID_USER', 301);
define('CODE_UPDATED', 302);
// define('CODE_WRONG', 303);
define('CODE_UPDATE_FAILED', 304);
define('CODE_WRONG', 305);

define('PASSWORD_RESET', 306);
define('PASSWORD_RESET_FAILED', 307);

define('IMAGE_NOT_SELECTED', 308);
define('IMAGE_UPLOADED', 309);
define('IMAGE_UPLOADE_FAILED', 401);

//For Feed
define('FEED_POSTED', 402);
define('FEED_POST_FAILED', 403);
define('FEED_LIKED', 405);
define('FEED_LIKE_FAILED', 406);
define('FEED_UNLIKED', 407);
define('FEED_UNLIKE_FAILED', 408);
define('FEED_COMMENT_ADDED', 409);
define('FEED_COMMENT_ADD_FAILED', 501);
define('FEED_COMMENT_DELETED', 502);
define('FEED_COMMENT_DELETE_FAILED', 503);
define('FEED_DELETED', 504);
define('FEED_DELETE_FAILED', 505);
define('FEED_UPDATED', 508);
define('FEED_UPDATE_FAILE', 509);



define('USER_UPDATED', 506);
define('USER_UPDATE_FAILED', 507);
define('FOLLOWED', 600);
define('FOLLOW_FAILED', 601);
define('UNFOLLOWED', 602);
define('UNFOLLOW_FAILED', 603);

define('VIDEO_POSTED', 604);
define('VIDEO_POST_FAILED', 605);


define('ALREADY_FRIEND', 606);
define('NOT_A_FRIEND', 607);
define('FRIEND_REQUEST_SENDER', 608);
define('FRIEND_REQUEST_RECEIVER', 609);
define('COMMENT_LIKED', 700);
define('COMMENT_LIKE_FAILED', 701);
define('COMMENT_UNLIKED', 702);
define('COMMENT_UNLIKE_FAILED', 703);

define('VERIFICATION_REQUEST_SUBMITTED', 704);
define('VERIFICATION_REQUEST_SUBMIT_FAILED', 705);

















//For JWT 
define('JWT_TOKEN_ERROR', 402);
define('JWT_TOKEN_FINE', 403);
define('JWT_USER_NOT_FOUND', 404);



///////////////////////////////////////////////////// END DON'T TOUCH THIS /////////////////////////////////////////////////////















