<?php

///////////////////////////////////////////////////// CHANGED INFORMATION /////////////////////////////////////////////////////

//Database Connection
define('DB_NAME','famblah');   //your database username
define('DB_USER', 'root');          //your database name
define('DB_PASS', '');              //your database password
define('DB_HOST', 'localhost');     //your database host name

//Website Information
define('WEBSITE_DOMAIN', 'http://111.111.111.111/SocialApiFriendsSystemVideoThumbs/public/');               //your domain name
// define('WEBSITE_DOMAIN', 'http://socialcodia.net/SocialApiFriendsSystemVideoThumb/public/');               //your domain name
define('WEBSITE_EMAIL', 'socialcodia@gmail.com');                    //your email address
define('WEBSITE_EMAIL_PASSWORD', 'PASSWORD');                        //your email password
define('WEBSITE_EMAIL_FROM', 'Social Codia');                        // your website name here
define('WEBSITE_NAME', 'FAMBLAH');                              //your website name here
define('WEBSITE_OWNER_NAME', 'Umair Farooqui');                      //your name, or anyones name, we will send this name with email verification mail.

define('DEFAULT_USER_IMAGE', 'uploads/api/user.png');

define('JWT_SECRET_KEY', 'SocialCodia');  							//your jwt secret key, Please use a very dificult secret key, which no one can guess it.

///////////////////////////////////////////// END CHANGE INFORMATION /////////////////////////////////////////////////////



///////////////////////////////////////////// DON'T TOUCH THIS /////////////////////////////////////////////////////

define('DEFAULT_BIO', "This user is lazy. So they didn't written any bio.");

define('USER_CREATION_FAILED', "Failed to create an account");
define('USER_CREATED', 102);
define('USER_FOUND', 'User Found');

define('VERIFICATION_EMAIL_SENT', 104);
define('VERIFICATION_EMAIL_SENT_FAILED', 105);
define('USERNAME_EXIST', "Username not available"); 		//change code
define('USERNAME_NOT_EXIST', "Username Not Exist");

define('USER_NOT_FOUND', "User Not Found");
define('USERS_LIST_FOUND', "Users List Found");
define('UNVERIFIED_EMAIL', 'Email Is Not Verified');
define('LOGIN_SUCCESSFULL', "Login Successfull");

define('EMAIL_EXIST', "Email already registered");
define('EMAIL_NOT_EXIST', "Email Not Exist");
define('EMAIL_VERIFIED', "Email Has Been Verified");
define('EMAIL_NOT_VERIFIED', "Email Is Not Verified");
define('EMAIL_VERIFICATION_SENT_FAILED', 'Failed To Send Verification Email');
define('EMAIL_VERIFICATION_SENT', 'An Email Verification Link Has Been Sent Your Email Address: ');

define('INVALID_VERFICATION_CODE', "INVALID VERIFCATION CODE");
define('SEND_CODE', 203);

define('EMAIL_ALREADY_VERIFIED', 'Your Email Address Already Verified');
define('EMAIL_NOT_VALID', 'Enter Valid Email');
define('EMAIL_OTP_SENT', 'OTP has been sent to your email address');
define('EMAIL_OTP_SEND_FAILED', 'Failed To Send OTP Email');

define('PASSWORD_CHANGED',"Password Has Been Changed");
define('PASSWORD_UPDATED',"Password Has Been Updated");
define('PASSWORD_CHANGE_FAILED', 207);
define('PASSWORD_WRONG', "Wrong Password");
define('PASSWORD_SAME', 209);

define('INVAILID_USER', "INVALID USER");
define('CODE_UPDATED', 302);
// define('CODE_WRONG', 303);
define('CODE_UPDATE_FAILED', 304);
define('CODE_WRONG', 305);

define('PASSWORD_RESET', 306);
define('PASSWORD_RESET_FAILED', 307);

define('IMAGE_NOT_SELECTED', 'Image Not Selected');
define('IMAGE_UPLOADED', 'Image Uploaded');
define('IMAGE_UPLOADE_FAILED', 'Failed To Upload The Image');

//For Feed
define('FEED_EMPTY', "Can't Post Empty Feed");
define('FEED_PRIVACY_INVALID', 'Invalid Feed Privacy Type');

define('FEED_POSTED', "Feed Has Been Posted");
define('FEED_POST_FAILED', 403);
define('FEED_LIKED', 'Feed Liked');
define('FEED_LIKE_FAILED', 'Failed To Like Feed');
define('FEED_LIKE_ALREADY', 'Feed Already Liked');
define('FEED_UNLIKED','Feed Unliked');
define('FEED_UNLIKE_FAILED', 'Failed To Unlike Feed');
define('FEED_UNLIKE_ALREADY', 'Feed Already Unliked');

define('FEED_COMMENT_ADDED', 409);
define('FEED_COMMENT_ADD_FAILED', 501);
define('FEED_COMMENT_DELETED', 502);
define('FEED_COMMENT_DELETE_FAILED', 503);
define('FEED_DELETED', "Feed Deleted");
define('FEED_DELETE_FAILED', "Failed To Delete Feed");
define('FEED_DELETE_WARNING', "WARNING..! STOP..! You can delete only your own Feeds");
define('FEED_UPDATED', "Oops...! Failed To Update Your Feed");
define('FEED_UPDATE_FAILED', "Oops...! Failed To Update Your Feed");
define('FEED_UPDATE_EMPTY', "Can't Update Empty Feed");
define('FEED_NOT_FOUND', 'Feed Not Found');
define('FEED_LIST_FOUND', 'Feed List Found');
define('FEEDS_NOT_FOUND', 'Feeds Not Found');
define('FEED_REPORTED', 'Feed Reported');
define('FEED_REPORT_FAILED', 'Failed To Report Feed');
define('FEED_REPORT_ALREADY', 'Feed Already Reported');
define('FEED_ID_EMPTY_ERROR', 'Invalid Request...! Feed Id must not be empty');

define('COMMENT_FOUND', 'Comment Found');
define('COMMENT_NOT_FOUND', 'Comment Not Found');
define('COMMENT_ADDED', 'Comment Added');
define('COMMENT_ADD_FAILED', 'Failed To Add Comment');
define('COMMENT_LIKED', 'Comment Liked');
define('COMMENT_LIKE_FAILED', 'Failed To Like Comment');
define('COMMENT_LIKE_ALREADY', 'Comment Already Liked');
define('COMMENT_UNLIKED', 'Comment UnLiked');
define('COMMENT_UNLIKE_FAILED', 'Failed To UnLike Comment');
define('COMMENT_UNLIKE_ALREADY', 'Comment Already UnLiked');
define('COMMENT_DELETED', 'Comment Deleted');
define('COMMENT_DELETE_FAILED', 'Failed To Delete Comment');
define('COMMENT_WARNING', 'WARNING..! STOP..! You can delete only your own comments');


define('PROFILE_UPDATED', 'Profile Updated');


define('FRIEND_LIST_FOUND', 'Friends List Found');
define('FRIEND_NOT_FOUND', 'No Friend Found');

define('IMAGE_LIST_FOUND', 'Image List Found');
define('IMAGE_NOT_FOUND', 'No Image Found');

define('FRIEND_REQUEST_SENT', 'Friend Request Sent');
define('FRIEND_REQUEST_ALREADY_SENT', 'Friend Request Already Sent');
define('FRIEND_ALREADY', 'Already Friend');
define('FRIEND_REQUEST_CANT_SENT', "You can't send a friend request to this person");
define('FRIEND_REQUEST_CANT_SENT_TO_SELF', "Can't Send Friend Request To Your Self");

define('FRIEND_REQUEST_ACCEPTED', 'Friend Request Accepted');
define('FRIEND_REQUEST_NOT_RECIEVED', 'No Friend Request Recieved');
define('FRIEND_REQUEST_NOT_FOUND', 'No Friend Request Found');

define('FRIEND_REQUEST_CANCELED', 'Friend Request Canceled');
define('FRIENDSHIP_DELETED', 'Friendship Deleted');

define('NOTIFICATION_SEEN', '"Notifications Seen"');
define('NOTIFICATION_SEEN_FAILED', '"Notifications Seened Failed"');
define('NOTIFICATIONS_FOUND', 'Notification Found');
define('NOTIFICATIONS_COUNT_FOUND', 'Notifications Count Found');

define('NAME_GRETER', 'Name Should Not Be Greater Than 30 Charater');
define('USERNAME_GRETER', 'Username Should Not Be Greater Than 20 Charater');

define('BLOCK_SUCCESS', 'Blocked Successfully');
define('BLOCK_FAILED', 'Failed to Block');
define('BLOCK_ALREADY', 'You already blocked this user');
define('UBLOCK_SELF', "You can't block yourself");

define('UNBLOCK_SUCCESS', 'Unblocked Successfully');
define('UNBLOCK_FAILED', 'Failed to Unblock');
define('UNBLOCK_ALREADY', 'You already Unblocked this user');
define('UNBLOCK_SELF', "You can't unblock yourself");

define('UPDATE_FOUND', 'Update Found');
define('NO_UPDATE_FOUND', 'No Update Found');

define('SUBMITTED', 'Submitted');
define('SUBMIT_FAILED', 'Failed to submit');

define('USER_UPDATED', 506);
define('USER_UPDATE_FAILED', 507);
// define('FOLLOWED', 600);
// define('FOLLOW_FAILED', 601);
// define('UNFOLLOWED', 602);
// define('UNFOLLOW_FAILED', 603);

define('VIDEO_POSTED', "Video Posted");
define('VIDEO_POST_FAILED', "Failed To Post Video");

define('ALREADY_FRIEND', "Alredy Friend");
define('NOT_A_FRIEND', "Not A Friend");
define('FRIEND_REQUEST_SENDER', 608);
define('FRIEND_REQUEST_RECEIVER', 609);


define('VERIFICATION_REQUEST_SUBMITTED', 704);
define('VERIFICATION_REQUEST_SUBMIT_FAILED', 705);
define('SWW', 'Oops..! Something went wrong.');
define('INVALID_OTP', 'Invalid Otp');

define('UNAUTH_ACCESS', 'Unauthorized Access');
define('TOKEN_ERROR', 'Token Error...! Please Login Again');

define('SOCIAL_CODIA', "You can report, if you found any bug");

//////////////////////////////// WARNING DON'T CHANGE PLEASE /////////////////////////////

define('CT', 'Content-Type');
define('AJ', 'application/json');
define('USERID', 'userId');
define('COMMENTS', 'comments');
define('USER', 'user');

define('USERS', 'users');
define('EMAIL', 'email');

define('FRIENDS', 'friends');
define('UPDATES', 'updates');
define('TOKEN', 'token');




define('FEED', 'feed');
define('FEEDS', 'feeds');
define('MESSAGE', 'message');
define('ERROR', 'error');

//////////////////////////// END WARNING DON'T CHANGE PLEASE /////////////////////////////

//For JWT 
define('JWT_TOKEN_ERROR', 402);
define('JWT_TOKEN_FINE', 403);
define('JWT_USER_NOT_FOUND', 404);


///////////////////////////////////////////////////// END DON'T TOUCH THIS /////////////////////////////////////////////////////















