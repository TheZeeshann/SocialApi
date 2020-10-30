<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//use Slim\Factory\AppFactory
require '../vendor/autoload.php';
require_once '../include/DbHandler.php';
require_once '../vendor/autoload.php';
require_once '../include/JWT.php';

$JWT = new JWT;

$app = new \Slim\App;;

$app = new Slim\App([

    'settings' => [
        'displayErrorDetails' => true,
        'debug'               => true,
    ]
]);


$app->post('/register', function(Request $request, Response $response)
{
    if(!checkEmptyParameter(array('name','username','email','password'),$request,$response))
    {
        $db = new DbHandler();
        $requestParameter = $request->getParsedBody();
        $email = $requestParameter['email'];
        $password = $requestParameter['password'];
        $name = $requestParameter['name'];
        $username = $requestParameter['username'];
        if (strlen($name)>30)
            return returnException(true,NAME_GRETER,$response);
        if (strlen($username)>20)
            return returnException(true,USERNAME_GRETER,$response);
        $username = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($username))))));
        $username = str_replace(' ', '', $username);
        $result = $db->createUser($name,$username,$email,$password);
        if($result == USER_CREATION_FAILED)
            return returnException(true,USER_CREATION_FAILED,$response);
        else if($result == EMAIL_EXIST)
            return returnException(true,EMAIL_EXIST,$response);
        else if($result == USERNAME_EXIST)
            return returnException(true,USERNAME_EXIST,$response);
        else if($result == USER_CREATED){
            $code = $db->getCodeByEmail($email);
            if(prepareVerificationMail($name,$email,$code))
               return returnException(false,EMAIL_VERIFICATION_SENT.$email,$response);
            else
               return returnException(true,EMAIL_VERIFICATION_SENT_FAILED,$response);
        }
        else if($result == VERIFICATION_EMAIL_SENT_FAILED)
            return returnException(true,EMAIL_VERIFICATION_SENT_FAILED,$response);
        else if($result == EMAIL_NOT_VALID)
            return returnException(true,EMAIL_NOT_VALID,$response);
    }
});

$app->get('/demo/{id}',function(Request $request, Response $response,array $args )
{
    $db = new DbHandler;
    $id = $args['id'];
        $responseG = array();
        $responseG['success'] = true;
        $responseG[ERROR] = false;
        $responseG[MESSAGE] = "This is demo api call";
        $responseG['data'] = $db->getAllFriendsId($id);
        $response->write(json_encode($responseG));
        return $response->withHeader(CT,AJ)
                ->withStatus(200);
});

$app->post('/login', function(Request $request, Response $response)
{
    if(!checkEmptyParameter(array('email','password'),$request,$response))
    {
        $db = new DbHandler;
        $requestParameter = $request->getParsedBody();
        $email = $requestParameter[EMAIL];
        $password = $requestParameter['password'];
        if (!$db->isEmailValid($email)) 
        {
            $email = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($email))))));
            $email = str_replace(' ', '', $email);
            $email = $db->getEmailByUsername($email);
        }
        if (!empty($email)) 
        {
            $result = $db->login($email,$password);
            if($result ==LOGIN_SUCCESSFULL)
            {
                $user = $db->getUserByEmail($email);
                $user[TOKEN] = getToken($user['id']);
                $responseUserDetails = array();
                $responseUserDetails[ERROR] = false;
                $responseUserDetails[MESSAGE] = LOGIN_SUCCESSFULL;
                $responseUserDetails[USER] = $user;
                $response->write(json_encode($responseUserDetails));
                return $response->withHeader(CT, AJ)
                         ->withStatus(200);
            }
            else if($result ==USER_NOT_FOUND)
                return returnException(true,USER_NOT_FOUND,$response);
            else if($result ==PASSWORD_WRONG)
                return returnException(true,PASSWORD_WRONG,$response);
            else if($result ==UNVERIFIED_EMAIL)
                return returnException(true,UNVERIFIED_EMAIL,$response);
            else
                return returnException(true,SWW,$response);
        }
        else
            return returnException(true,USER_NOT_FOUND,$response);
    }
});

$app->post('/forgotPassword', function(Request $request, Response $response)
{
    if(!checkEmptyParameter(array('email'),$request,$response))
    {
        $db = new DbHandler;
        $requestParameter = $request->getParsedBody();
        $email= $requestParameter['email'];
        $result = $db->forgotPassword($email);
        if($result == CODE_UPDATED)
        {
            $name = $db->getNameByEmail($email);
            $code = decrypt($db->getCodeByEmail($email));
            if(prepareForgotPasswordMail($name,$email,$code))
                return returnException(false,EMAIL_OTP_SENT,$response);
            else
              return returnException(true,EMAIL_OTP_SEND_FAILED,$response);
        }
        else if($result == EMAIL_NOT_VALID)
            return returnException(true,EMAIL_NOT_VALID,$response);
        else if($result ==USER_NOT_FOUND)
            return returnException(true,EMAIL_NOT_EXIST,$response);
        else if($result ==EMAIL_NOT_VERIFIED)
            return returnException(true,EMAIL_NOT_VERIFIED,response);
        else if($result ==CODE_UPDATE_FAILED)
            return returnException(true,SWW,$response);
        else
            return returnException(true,SWW,$response);
    }
});

$app->post('/resetPassword', function(Request $request, Response $response)
{
    $result = array();
    if(!checkEmptyParameter(array('email','otp','newPassword'),$request,$response))
    {
        $db = new DbHandler;
        $requestParameter = $request->getParsedBody();
        $email = $requestParameter['email'];
        $otp = $requestParameter['otp'];
        $newPassword = $requestParameter['newPassword'];
        $result = $db->resetPassword($email,$otp,$newPassword);

        if($result == PASSWORD_RESET)
        {
            $name = $db->getNameByEmail($email);
            preparePasswordChangedMail($name,$email);
            return returnException(false,PASSWORD_CHANGED,$response);
        }
        else if($result == EMAIL_NOT_VALID)
            return returnException(true,EMAIL_NOT_VALID,$response);
        else if($result ==USER_NOT_FOUND)
            return returnException(true,USER_NOT_FOUND,$response);
        else if($result ==EMAIL_NOT_VERIFIED)
            return returnException(true,EMAIL_NOT_VERIFIED,$response);
        else if($result ==PASSWORD_RESET_FAILED)
            return returnException(true,SWW,$response);
        else if($result ==CODE_WRONG)
            return returnException(true,INVAILID_OTP,$response);
        else
            return returnException(true,SWW,$response);
    }
});

$app->post('/updatePassword',function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if(!checkEmptyParameter(array('password','newpassword'),$request,$response))
            {
                $requestParameter = $request->getParsedBody();
                $password = $requestParameter['password'];
                $newPassword = $requestParameter['newpassword'];
                $id = $db->getUserId();
                $result = $db->updatePassword($id,$password,$newPassword);
                if($result ==PASSWORD_WRONG)
                    return returnException(true,PASSWORD_CHANGED,$response);
                else if($result==PASSWORD_CHANGED)
                {
                    $email = $db->getEmailById($id);
                    $name = $db->getNameByEmail($email);
                    preparePasswordChangedMail($name,$email);
                    return returnException(false,PASSWORD_UPDATED,$response);
                }
                else if($result ==PASSWORD_CHANGE_FAILED)
                    return returnException(true,SWW,$response);
                else
                    return returnException(true,SWW,$response);
            }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/sendEmailVerfication',function(Request $request, Response $response)
{
    $result = array(); 
    if(!checkEmptyParameter(array('email'),$request,$response))
    {
        $db = new DbHandler();
        $requestParameter = $request->getParsedBody();
        $email = $requestParameter['email'];
        $result = $db->sendEmailVerificationAgain($email);
        if($result ==SEND_CODE)
        {
            $name = $db->getNameByEmail($email);
            $code = $db->getCodeByEmail($email);
            $process = prepareVerificationMail($name,$email,$code);
            if($process)
                return returnException(false,EMAIL_VERIFICATION_SENT.$email,$response);
            else
                return returnException(true,EMAIL_VERIFICATION_SENT_FAILED,$response);
        }
        else if($result ==USER_NOT_FOUND)
            return returnException(true,USER_NOT_FOUND,$response);
        else if($result == EMAIL_NOT_VALID)
            return returnException(true,EMAIL_NOT_VALID,$response);
        else if($result ==EMAIL_ALREADY_VERIFIED)
            return returnException(true,EMAIL_ALREADY_VERIFIED,$response);
        else
            return returnException(true,SWW,$response);
    }
});

$app->get('/verifyEmail/{email}/{code}',function(Request $request, Response $response, array $args)
{
    $email = $args['email']; 
    $email = decrypt($email);
    $code = $args['code'];
    $db = new DbHandler();
    $result = array();
    $result = $db->verfiyEmail($email,$code);

    if($result == EMAIL_VERIFIED)
        return returnException(false,EMAIL_VERIFIED,$response);
    else if($result ==EMAIL_NOT_VERIFIED)
        return returnException(true,EMAIL_NOT_VERIFIED,$response);
    else if($result ==INVAILID_USER)
        return returnException(true,INVAILID_USER,$response);
    else if($result ==INVALID_VERFICATION_CODE)
        return returnException(true,INVALID_VERFICATION_CODE,$response);
    else if($result ==EMAIL_ALREADY_VERIFIED)
        return returnException(true,EMAIL_ALREADY_VERIFIED,$response);
    else
        return returnException(true,SWW,$response);
});

$app->get('/users', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
            $tokenId = $db->getUserId();
            $users = $db->getUsers($tokenId);
            if (!empty($users)) 
            {
                $responseUserDetails = array();
                $responseUserDetails[ERROR] = false;
                $responseUserDetails[MESSAGE] = USERS_LIST_FOUND;
                $responseUserDetails[USERS] = $users;
                $response->write(json_encode($responseUserDetails));
                return $response->withHeader(CT, AJ)
                         ->withStatus(200);
            }
            else
                return returnException(true,USER_NOT_FOUND,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/user', function(Request $request, Response $response, array $args)
{
    $db = new DbHandler;
    if(validateToken($db,$request,$response))
    {
        $tokenId = $db->getUserId();
        if ($db->checkUserById($tokenId)) 
        {
            $user = $db->getUserById($tokenId);
            $user['feedsCount'] = $db->getFeedsCountById($tokenId);
            $user['friendsCount'] = $db->getFriendsCountById($tokenId);
            $user['friendshipStatus'] = 0;
            $responseUserDetails = array();
            $responseUserDetails[ERROR] = false;
            $responseUserDetails[MESSAGE] = USER_FOUND;
            $responseUserDetails[USER] = $user;
            $response->write(json_encode($responseUserDetails));
            return $response->withHeader(CT,AJ)
                            ->withStatus(200);
        }
        else
            return returnException(true,USERNAME_NOT_EXIST,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/user/{username}', function(Request $request, Response $response, array $args)
{
    $db = new DbHandler;
    if(validateToken($db,$request,$response))
    {
        $tokenId = $db->getUserId();
        $username = $args['username'];
        $username = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($username))))));
        $username = str_replace(' ', '', $username);
        if ($db->isUsernameExist($username)) 
        {
            $friendshipStatus = '';
            $id = $db->getUserIdByUsername($username);
            $user = $db->getUserById($id);
            $result = $db->getFriendshipStatus($tokenId,$id);
            if ($result==NOT_A_FRIEND)
                $friendshipStatus = 0;
            else if ($result==ALREADY_FRIEND)
                $friendshipStatus = 1;
            else if ($result==FRIEND_REQUEST_SENDER) 
                $friendshipStatus = 2;
            else if ($result==FRIEND_REQUEST_RECEIVER)
                $friendshipStatus = 3;
            $user['feedsCount'] = $db->getFeedsCountById($id);
            $user['friendsCount'] = $db->getFriendsCountById($id);
            $user['friendshipStatus'] = $friendshipStatus;
            if ($db->isUserAlreadyBlocked($db->getUserId(),$id))
                $user['status'] = 2;
            $responseUserDetails = array();
            $responseUserDetails[ERROR] = false;
            $responseUserDetails[MESSAGE] = USER_FOUND;
            $responseUserDetails[USER] = $user;
            $response->write(json_encode($responseUserDetails));
            return $response->withHeader(CT, AJ)
                     ->withStatus(200);
        }
        else
            return returnException(true,USERNAME_NOT_EXIST,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/user/update', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('name','username'),$request,$response)) 
        {
            $image = "";
            $requestParameter = $request->getParsedBody();
            $requestParameters = $request->getUploadedFiles();
            $name = $requestParameter['name'];
            $username = $requestParameter['username'];
            if (!empty($requestParameter['bio']))
                $bio = $requestParameter['bio'];
            else
                $bio = DEFAULT_BIO;
            $userId = $db->getUserId();
            $dbUsername = $db->getUsernameById($userId);
            if (strtolower($dbUsername)!=strtolower($username) AND $db->isUsernameExist($username)) 
            {
                return returnException(true,USERNAME_EXIST,$response);
                return;
            }
            $username = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($username))))));
            $username = str_replace(' ', '', $username);
            if (strlen($name)>25)
            {
                return returnException(true,NAME_GRETER,$response);
                return;
            }
            if (strlen($username)>15) 
            {
                return returnException(true,USERNAME_GRETER,$response);
                return;
            }
            if (empty($requestParameters['image']))
                $image = $db->getImageById($userId);
            else
                $image = $requestParameters['image'];
            $result = $db->updateUser($userId,$name,$username,$bio,$image);
            if ($result==USER_UPDATED)
                return returnException(false,PROFILE_UPDATED,$response);
            else if ($result==USER_UPDATE_FAILED)
                return returnException(true,SWW,$response);
        }
    } 
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/user/block', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('userId'),$request,$response)) 
        {
            $requestParameter = $request->getParsedBody();
            $userId = $requestParameter['userId'];
            $tokenId = $db->getUserId();
            if ($userId==$tokenId)
            {
                return returnException(false,BLOCK_SELF,$response);
                return;
            }
            if ($db->checkUserById($userId)) 
            {
                if (!$db->isUserAlreadyBlocked($tokenId,$userId)) 
                {
                    if ($db->doBlockUser($tokenId,$userId))
                    {
                        if ($db->isAlreadyFriend($tokenId,$userId)) 
                        {
                            if ($db->deleteFriend($tokenId,$userId)) 
                            {
                                $db->deleteAllNotification($tokenId,$userId);
                            }
                        }
                        else
                        {
                            if ($db->isFriendRequestAlreadySent($tokenId,$userId)) 
                            {
                                if ($db->cancelFriendRequest($tokenId,$userId)) 
                                {
                                    $db->deleteAllNotification($tokenId,$userId);
                                }
                            }
                            $db->deleteAllNotification($tokenId,$userId);
                        }
                        return returnException(false,BLOCK_SUCCESS,$response);
                    }
                    else
                        return returnException(true,BLOCK_FAILED,$response);
                }
                else
                    return returnException(true,BLOCK_ALREADY,$response);
            }
            else
                return returnException(true,USER_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});   

$app->get('/users/block', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
            $tokenId = $db->getUserId();
            $users = $db->getBlockedUsers($tokenId);
            if (!empty($users)) 
            {
                $responseUserDetails = array();
                $responseUserDetails[ERROR] = false;
                $responseUserDetails[MESSAGE] = USERS_LIST_FOUND;
                $responseUserDetails[USERS] = $users;
                $response->write(json_encode($responseUserDetails));
                return $response->withHeader(CT, AJ)
                         ->withStatus(200);
            }
            else
                return returnException(true,USER_NOT_FOUND,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/user/unblock', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('userId'),$request,$response)) 
        {
            $requestParameter = $request->getParsedBody();
            $userId = $requestParameter['userId'];
            $tokenId = $db->getUserId();
            if ($userId==$tokenId)
            {
                return returnException(false,UNBLOCK_SELF,$response);
                return;
            }
            if ($db->checkUserById($userId)) 
            {
                if ($db->isUserAlreadyBlocked($tokenId,$userId)) 
                {
                    if ($db->doUnBlockUser($tokenId,$userId)) 
                        return returnException(false,UNBLOCK_SUCCESS,$response);
                    else
                        return returnException(true,UNBLOCK_FAILED,$response);
                }
                else
                    return returnException(true,UNBLOCK_ALREADY,$response);
            }
            else
                return returnException(true,USER_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});  

$app->get('/update/{version}', function(Request $request, Response $response, array $args)
{
    $db = new DbHandler;
    $version = (float)$args['version'];
    if ($db->isUpdateAvailable($version))
    {
        $updateUrl = $db->getUpdateUrl();
        $responseUpdate = array();
        $responseUpdate[ERROR] = false;
        $responseUpdate[MESSAGE] = UPDATE_FOUND;
        $responseUpdate[UPDATES] = $updateUrl;
        $response->write(json_encode($responseUpdate));
        return $response->withHeader(CT,AJ)
        ->withStatus(200);
    }
    else
        return returnException(true,NO_UPDATE_FOUND,$response);
});

$app->get('/user/{username}/friends', function(Request $request, Response $response, array $args)    //Chnage Add User
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $userId = $db->getUserId();
        $username = $args['username'];
        $username = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($username))))));
        $username = str_replace(' ', '', $username);
        $id = $db->getUserIdByUsername($username);
        if (!empty($id)) 
        {
            $users = $db->getFriendsByUserId($id);
            if (!empty($users)) 
            { 
                $responseFriendsDetails = array();
                $responseFriendsDetails[ERROR] = false;
                $responseFriendsDetails[MESSAGE] = FRIEND_LIST_FOUND;
                $responseFriendsDetails[FRIENDS] = $users;
                $response->write(json_encode($responseFriendsDetails));
                return $response->withHeader(CT,AJ)
                                ->withStatus(200);
            }
            else
                return returnException(true,FRIEND_NOT_FOUND,$response);
        }
        else
            return returnException(true,USER_NOT_FOUND,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/user/{username}/images', function(Request $request, Response $response, array $args)     //Change Add User
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $userId = $db->getUserId();
        $username = $args['username'];
        $username = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($username))))));
        $username = str_replace(' ', '', $username);
        $id = $db->getUserIdByUsername($username);
        if (!empty($id)) 
        {
            $images = $db->getImagesByUserId($id);
            if (!empty($images)) 
            { 
                $responseImagesDetails = array();
                $responseImagesDetails[ERROR] = false;
                $responseImagesDetails[MESSAGE] = IMAGE_LIST_FOUND;
                $responseImagesDetails['Images'] = $images;
                $response->write(json_encode($responseImagesDetails));
                return $response->withHeader(CT,AJ)
                                ->withStatus(200);
            }
            else
                return returnException(true,IMAGE_NOT_FOUND,$response);
        }
        else
            return returnException(true,USER_NOT_FOUND,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/sendFriendRequest',function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('userId'),$request,$response)) 
        {
            $tokenId = $db->getUserId();
            $requestParameter = $request->getParsedBody();
            $userId = $requestParameter['userId'];
            if ($tokenId!=$userId) 
            {
                if ($db->checkUserById($userId)) 
                {
                    if (!$db->isUserBlocked($tokenId,$userId)) 
                    {
                        if (!$db->isAlreadyFriend($tokenId,$userId)) 
                        {
                            if (!$db->isFriendRequestAlreadySent($tokenId,$userId)) 
                            {
                                if ($db->makeFriendRequest($tokenId,$userId)) 
                                {
                                    $type = 2;
                                    $db->addNotification($tokenId,$userId,$type);
                                    return returnException(false,FRIEND_REQUEST_SENT,$response);
                                }
                                else
                                    return returnException(true,SWW,$response);
                            }
                            else
                                return returnException(true,FRIEND_REQUEST_ALREADY_SENT,$response);
                        }
                        else
                            return returnException(true,FRIEND_ALREADY,$response);
                    }
                    else
                        return returnException(true,FRIEND_REQUEST_CANT_SENT,$response);
                }
                else
                    return returnException(true,USER_NOT_FOUND,$response);
            }
            else
                return returnException(true,FRIEND_REQUEST_CANT_SENT_TO_SELF,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/acceptFriendRequest',function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('userId'),$request,$response)) 
        {
            $tokenId = $db->getUserId();
            $requestParameter = $request->getParsedBody();
            $userId = $requestParameter['userId'];
            if ($tokenId!=$userId) 
            {
                if ($db->checkUserById($userId)) 
                {
                    if (!$db->isAlreadyFriend($tokenId,$userId)) 
                    {
                        if ($db->isFriendRequestAlreadySent($tokenId,$userId)) 
                        {
                            if ($db-> isIAmTheFriendRequestReceiver($tokenId,$userId)) 
                            {
                                if ($db->acceptFriendRequest($tokenId,$userId)) 
                                {
                                    $type = 2;
                                    $notifcationType = 4;
                                    $db->deleteNotification($userId,$tokenId,$type);
                                    $db->addNotification($tokenId,$userId,$notifcationType);
                                    return returnException(false,FRIEND_REQUEST_ACCEPTED,$response);
                                }
                                else
                                    return returnException(true,SWW,$response);
                            }
                            else
                                return returnException(true,FRIEND_REQUEST_NOT_RECIEVED,$response);
                        }
                        else
                            return returnException(true,FRIEND_REQUEST_NOT_FOUND,$response);
                    }
                    else
                        return returnException(true,FRIEND_ALREADY,$response);
                }
                else
                    return returnException(true,USER_NOT_FOUND,$response);
            }
            else
                return returnException(true,SOCIAL_CODIA,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/cancelFriendRequest',function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('userId'),$request,$response)) 
        {
            $tokenId = $db->getUserId();
            $requestParameter = $request->getParsedBody();
            $userId = $requestParameter['userId'];
            if ($tokenId!=$userId) 
            {
                if ($db->checkUserById($userId)) 
                {
                    if (!$db->isAlreadyFriend($tokenId,$userId)) 
                    {
                        if ($db->isFriendRequestAlreadySent($tokenId,$userId)) 
                        {
                            if ($db->cancelFriendRequest($tokenId,$userId)) 
                            {
                                $notifcationType = 2;
                                $db->deleteNotification($tokenId,$userId,$notifcationType);
                                return returnException(false,FRIEND_REQUEST_CANCELED,$response);
                            }
                            else
                                return returnException(true,SWW,$response);
                        }
                        else
                            return returnException(true,FRIEND_REQUEST_NOT_FOUND,$response);
                    }
                    else
                        return returnException(true,FRIEND_ALREADY,$response);
                }
                else
                    return returnException(true,USER_NOT_FOUND,$response);
            }
            else
                return returnException(true,SOCIAL_CODIA,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/deleteFriend',function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('userId'),$request,$response)) 
        {
            $tokenId = $db->getUserId();
            $requestParameter = $request->getParsedBody();
            $userId = $requestParameter['userId'];
            if ($tokenId!=$userId) 
            {
                if ($db->checkUserById($userId)) 
                {
                    if ($db->isAlreadyFriend($tokenId,$userId)) 
                    {
                        if ($db->deleteFriend($tokenId,$userId)) 
                        {
                            $notificationType = 1;
                            $db->deleteNotification($tokenId,$userId,$notificationType);                    //The function call may be wrong, need to change the function or create a function to delete the friends notification using or condition.
                            return returnException(false,FRIENDSHIP_DELETED,$response);
                        }
                        else
                            return returnException(true,SWW,$response);
                    }
                    else
                        return returnException(true,FRIEND_NOT_FOUND,$response);
                }
                else
                    return returnException(true,USER_NOT_FOUND,$response);
            }
            else
                return returnException(true,SOCIAL_CODIA,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/feed/post', function(Request $request, Response $response)          //Added Feed
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $requestParameters = $request->getUploadedFiles();
        $requestParameter  = $request->getParsedBody();
        $tokenId = $db->getUserId();
        if (!empty($requestParameter['feedPrivacy']))
        {
            $feedPrivacy = $requestParameter['feedPrivacy'];
            if ($feedPrivacy==="1" || $feedPrivacy==="2" || $feedPrivacy=="3" || $feedPrivacy==="4") {

            }
            else
            {
                return returnException(true,FEED_PRIVACY_INVALID,$response);
                return;
            }
        }
        else
            $feedPrivacy = 1;
        if (empty($requestParameters['file'])) 
        {
            if(!empty($requestParameter['content']))
            {
                $file = null;
                $content = $requestParameter['content'];
                $result = $db->postFeed($tokenId,$content,$file,$feedPrivacy);
                if ($result==FEED_POSTED)
                    return returnException(false,FEED_POSTED,$response);
                else if ($result==FEED_POST_FAILED)
                    return returnException(true,SWW,$response);
            }
            else
                return returnException(true,FEED_EMPTY,$response);
        }
        else
        {
            $content = "";
            if (!empty($requestParameter['content']))
                $content = $requestParameter['content'];
            else
                $content = "";
            $file = $requestParameters['file'];
            $result = $db->postFeed($tokenId,$content,$file,$feedPrivacy);
            if ($result==FEED_POSTED)
                return returnException(false,FEED_POSTED,$response);
            else if ($result==FEED_POST_FAILED) 
                return returnException(true,SWW,$response);
            else
                return returnException(true,SWW.$result,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/feed/update', function (Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('feedId'),$request,$response)) 
        {
            $id = $db->getUserId();
            $requestParameter = $request->getParsedBody();
            $requestParameters = $request->getUploadedFiles();
            $feedId = $requestParameter['feedId'];
            if (empty($requestParameters['image'])) 
            {
                if(!empty($requestParameter['content']))
                {
                    $image = null;
                    $content = $requestParameter['content'];
                    $result = $db->updateFeed($feedId,$content,$image);
                    if ($result==FEED_UPDATED)
                        return returnException(false,FEED_UPDATED,$response);
                    else if ($result==FEED_UPDATE_FAILED)
                        return returnException(true,FEED_UPDATE_FAILED,$response);
                }
                else
                    return returnException(true,FEED_UPDATE_EMPTY,$response);
            }
            else
            {
                $image = $requestParameters['image'];
                if (!empty($requestParameter['content'])) 
                {
                    $content = $requestParameter['content'];
                    $result = $db->updateFeed($feedId,$content,$image);
                    if ($result==FEED_UPDATED)
                        return returnException(false,FEED_UPDATED,$response);
                    else if ($result==FEED_UPDATE_FAILED)
                        return returnException(true,FEED_UPDATE_FAILED,$response);
                }
                else
                {
                    $content = "";
                    $result = $db->updateFeed($feedId,$content,$image);
                    if ($result==FEED_UPDATED)
                        return returnException(false,FEED_UPDATED,$response);
                    else if ($result==FEED_UPDATE_FAILED)
                        return returnException(true,FEED_UPDATE_FAILED,$response);
                }
            }
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/feed/delete', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('id'),$request,$response)) 
        {   
            $requestParameter = $request->getParsedBody();
            $feedId = $requestParameter['id'];
            if ($db->isFeedExist($feedId)) 
            {
                $userId = $db->getUserId();
                if ($db->isFeedAuthor($feedId,$userId)) 
                {
                    $result = $db->deleteFeed($feedId,$userId);
                    if ($result== FEED_DELETED) 
                    {
                        $db->deleteAllFeedLikeNotification($feedId);
                        return returnException(false,FEED_DELETED,$response);
                    }
                    else if($result == FEED_DELETE_FAILED)
                        return returnException(true,FEED_DELETE_FAILED, $response);
                }
                else
                    return returnException(true,FEED_DELETE_WARNING,$response);
            }
            else
                return returnException(true,FEED_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/feeds', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $feedPrivacy = 1;
        $feeds = $db->getFeeds($feedPrivacy);
        if (!empty($feeds)) 
        {
            $responseFeedDetails = array();
            $responseFeedDetails[ERROR] = false;
            $responseFeedDetails[MESSAGE] = FEED_LIST_FOUND;
            $responseFeedDetails[FEEDS] = $feeds;
            $response->write(json_encode($responseFeedDetails));
            return $response->withHeader(CT,AJ)
                            ->withStatus(200);
        }
        else
            return returnException(true,FEED_NOT_FOUND,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/feeds/{feedPrivacy}', function(Request $request, Response $response, array $args)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $feedPrivacy = $args['feedPrivacy'];
        if ($feedPrivacy==="publics" || $feedPrivacy==="friends" || $feedPrivacy==="public" || $feedPrivacy==="friend") 
        {
            switch ($feedPrivacy) {
                case 'publics':
                    $feedPrivacy = 1;
                    break;
                case 'public':
                    $feedPrivacy = 1;
                    break;
                case 'friends':
                    $feedPrivacy = 2;
                    break;
                case 'friend':
                    $feedPrivacy = 2;
                    break;
                default:
                    # code...
                    break;
            }
            $feeds = $db->getFeeds($feedPrivacy);
            if (!empty($feeds)) 
            {
                $responseFeedDetails = array();
                $responseFeedDetails[ERROR] = false;
                $responseFeedDetails[MESSAGE] = FEED_LIST_FOUND;
                $responseFeedDetails[FEEDS] = $feeds;
                $response->write(json_encode($responseFeedDetails));
                return $response->withHeader(CT,AJ)
                                ->withStatus(200);
            }
            else
                return returnException(true,FEED_NOT_FOUND,$response);
        }
        else
            return returnException(true,FEED_PRIVACY_INVALID,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/user/{username}/feeds', function(Request $request, Response $response, array $args)      //Added User
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $username = $args['username'];
        $username = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($username))))));
        $username = str_replace(' ', '', $username);
        $id = $db->getUserIdByUsername($username);
        if (!empty($id)) 
        {
            $feeds = $db->getFeedsByUserId($id);
            if (!empty($feeds)) 
            { 
                $responseFeedDetails = array();
                $responseFeedDetails[ERROR] = false;
                $responseFeedDetails[MESSAGE] = FEED_LIST_FOUND;
                $responseFeedDetails[FEEDS] = $feeds;
                $response->write(json_encode($responseFeedDetails));
                return $response->withHeader(CT,AJ)
                                ->withStatus(200);  

            }
            else
                return returnException(true,FEED_NOT_FOUND,$response);
        }
        else
            return returnException(true,USER_NOT_FOUND,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/feed/{feedId}', function(Request $request, Response $response,array $args)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $feedId = $args['feedId'];
        if ($db->isFeedExist($feedId)) 
        {
            $feed = $db->getFeedById($feedId);
            if (!empty($feed)) 
            {
                $users = $db->getUserById($feed['userId']);
                $feed['userId']         =    $users['id'];
                $feed['userName']       =    $users['name'];
                $feed['userImage']      =    $users['image'];
                $feed['userStatus']      =    $users['status'];
                $feed['liked']          =    $db->checkFeedLike($feed['userId'],$feed['feedId']);
                $feed['feedLikes']      =    $db->getLikesCountByFeedId($feed['feedId']);
                $feed['feedComments']   =    $db->getCommentsCountByFeedId($feed['feedId']);
                $responseFeedDetails = array();
                $responseFeedDetails[ERROR] = false;
                $responseFeedDetails[MESSAGE] = FEED_LIST_FOUND;
                $responseFeedDetails[FEED] = $feed;
                $response->write(json_encode($responseFeedDetails));
                return $response->withHeader(CT,AJ)
                                ->withStatus(200);
            }
            else
                return returnException(true,FEED_NOT_FOUND,$response);
        }
        else
            return returnException(true,FEED_NOT_FOUND,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/feed/{feedId}/comments', function (Request $request, Response $response, array $args)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!empty($args['feedId'])) 
        {
            $userId = $db->getUserId();
            $feedId = $args['feedId'];
            if ($db->isFeedExist($feedId)) 
            {
                $comments = $db->getCommentsByFeedId($feedId);
                if (!empty($comments)) 
                {
                    $responseCommentDetails = array();
                    $responseCommentDetails[ERROR] = false;
                    $responseCommentDetails[MESSAGE] = COMMENT_FOUND;
                    $responseCommentDetails[COMMENTS] = $comments;
                    $response->write(json_encode($responseCommentDetails));
                    return $response->withHeader(CT,AJ)
                                    ->withStatus(200);
                }
                else
                    return returnException(true,COMMENT_NOT_FOUND,$response);
            }
            else
                return returnException(true,FEED_NOT_FOUND,$response);
        }
        else
            return returnException(true,FEED_ID_EMPTY_ERROR,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/comment/post', function(Request $request, Response $response)       //Chnages
{
    $db = new DbHandler;
    $comments = array();
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('feedId','comment'),$request,$response)) 
        {   
            $requestParameter = $request->getParsedBody();
            $feedId = $requestParameter['feedId'];
            $feedComment = $requestParameter['comment'];
            if ($db->isFeedExist($feedId)) 
            {
                $userId = $db->getUserId();
                $result = $db->addFeedComment($feedId,$feedComment,$userId);
                if ($result== FEED_COMMENT_ADDED) 
                {
                    $notificationType = 11;
                    $feedAuthorId = $db->getFeedAuthorIdByFeedId($feedId);
                    if ($userId!=$feedAuthorId) 
                    {
                        $db->addFeedLikeNotification($userId,$feedId,$notificationType);
                    }
                    $comments = $db->getLastCommentByUserId($userId);
                    if (!empty($comments)) 
                    {
                        $users = $db->getUserById($userId);
                        $comments['userId']             =    $users['id'];
                        $comments['userName']           =    $users['name'];
                        $comments['userUsername']       =    $users['username'];
                        $comments['userImage']          =    $users['image'];
                        $comments['liked']               =    false;
                        $comments['commentLikesCount']   =    0;
                        $responseCommentDetails = array();
                        $responseCommentDetails[ERROR] = false;
                        $responseCommentDetails[MESSAGE] = COMMENT_ADDED;
                        $responseCommentDetails[COMMENTS] = $comments;
                        $response->write(json_encode($responseCommentDetails));
                        return $response->withHeader(CT,AJ)
                                        ->withStatus(200);
                    }
                    else
                        return returnException(true,COMMENT_NOT_FOUND,$response);
                }
                else if($result == FEED_COMMENT_ADD_FAILED)
                    return returnException(true,COMMENT_ADD_FAILED, $response);
            }
            else
                return returnException(true,FEED_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/comment/like', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('commentId'),$request,$response)) 
        {   
            $requestParameter = $request->getParsedBody();
            $commentId = $requestParameter['commentId'];
            if ($db->isCommentExist($commentId)) 
            {
                $userId = $db->getUserId();
                if (!$db->isCommentLiked($commentId,$userId)) 
                {
                    $result = $db->likeFeedComment($commentId,$userId);
                    if ($result== COMMENT_LIKED) 
                    {   
                        $notificationType = 111;
                        $commentAuthorId = $db->getCommentAuthorIdByFeedId($commentId);
                        if ($userId!=$commentAuthorId) 
                        {
                            $feedId  = $db->getFeedIdByCommentId($commentId);
                            $db->addFeedLikeNotification($userId,$feedId,$notificationType);
                        }
                        $comments = array();
                        $comments['commentLikesCount'] = $db->getCommentLikesCountByCommentId($commentId);
                        $responseFeedDetails = array();
                        $responseFeedDetails[ERROR] = false;
                        $responseFeedDetails[MESSAGE] = COMMENT_LIKED;
                        $responseFeedDetails[COMMENTS] = $comments;
                        $response->write(json_encode($responseFeedDetails));
                        return $response->withHeader(CT,AJ)
                                        ->withStatus(200);
                    }
                    else if($result == COMMENT_LIKE_FAILED)
                        return returnException(true,COMMENT_LIKE_FAILED, $response);
                }
                else
                    return returnException(true,COMMENT_LIKE_ALREADY,$response);
            }
            else
                return returnException(true,COMMENT_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/comment/unlike', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('commentId'),$request,$response)) 
        {   
            $requestParameter = $request->getParsedBody();
            $commentId = $requestParameter['commentId'];
            if ($db->isCommentExist($commentId)) 
            {
                $userId = $db->getUserId();
                if ($db->isCommentLiked($commentId,$userId)) 
                {
                    $result = $db->unlikeFeedComment($commentId,$userId);
                    if ($result== COMMENT_UNLIKED) 
                    {
                        $notificationType = 111;
                        $commentAuthorId = $db->getCommentAuthorIdByFeedId($commentId);
                        if ($userId!=$commentAuthorId) 
                        {
                            $feedId  = $db->getFeedIdByCommentId($commentId);
                            $db->deleteFeedLikeNotification($userId,$feedId,$notificationType);
                        }
                        $comments = array();
                        $comments['commentLikesCount'] = $db->getCommentLikesCountByCommentId($commentId);
                        $responseFeedDetails = array();
                        $responseFeedDetails[ERROR] = false;
                        $responseFeedDetails[MESSAGE] = COMMENT_UNLIKED;
                        $responseFeedDetails[COMMENTS] = $comments;
                        $response->write(json_encode($responseFeedDetails));
                        return $response->withHeader(CT,AJ)
                                        ->withStatus(200);
                    }
                    else if($result == COMMENT_UNLIKE_FAILED)
                        return returnException(true,COMMENT_UNLIKE_FAILED, $response);
                }
                else
                    return returnException(true,COMMENT_UNLIKE_ALREADY,$response);
            }
            else
                return returnException(true,COMMENT_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/comment/delete', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('id'),$request,$response)) 
        {   
            $requestParameter = $request->getParsedBody();
            $commentId = $requestParameter['id'];
            if ($db->isCommentExist($commentId)) 
            {
                $userId = $db->getUserId();
                if ($db->isCommentAuthor($commentId,$userId)) 
                {
                    $result = $db->deleteFeedComment($commentId,$userId);
                    if ($result== FEED_COMMENT_DELETED)
                        return returnException(false,COMMENT_DELETED,$response);
                    else if($result == FEED_COMMENT_DELETE_FAILED)
                        return returnException(true,COMMENT_DELETE_FAILED, $response);
                }
                else
                    return returnException(true,COMMENT_WARNING,$response);            }
            else
                return returnException(true,COMMENT_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/feed/like', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('feedId'),$request,$response)) 
        {   
            $requestParameter = $request->getParsedBody();
            $feedId = $requestParameter['feedId'];
            if ($db->isFeedExist($feedId)) 
            {
                $userId = $db->getUserId();
                if (!$db->isFeedLiked($feedId,$userId)) {
                    $result = $db->likeFeed($feedId,$userId);
                    if ($result== FEED_LIKED) 
                    { 
                        $notificationType = 1;
                        $feedAuthorId = $db->getFeedAuthorIdByFeedId($feedId);
                        if ($userId!=$feedAuthorId) 
                        {
                            $db->addFeedLikeNotification($userId,$feedId,$notificationType);
                        }
                        $feed = array();
                        $feed['feedLikes'] = $db->getLikesCountByFeedId($feedId);
                        $responseFeedDetails = array();
                        $responseFeedDetails[ERROR] = false;
                        $responseFeedDetails[MESSAGE] = FEED_LIKED;
                        $responseFeedDetails[FEED] = $feed;
                        $response->write(json_encode($responseFeedDetails));
                        return $response->withHeader(CT,AJ)
                                        ->withStatus(200);
                    }
                    else
                        return returnException(true,FEED_LIKE_FAILED, $response);
                }
                else
                    return returnException(true,FEED_LIKE_ALREADY, $response);
            }
            else
                return returnException(true,FEED_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/feed/unlike', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('feedId'),$request,$response)) 
        {   
            $requestParameter = $request->getParsedBody();
            $feedId = $requestParameter['feedId'];
            if ($db->isFeedExist($feedId)) 
            {
                $userId = $db->getUserId();
                if ($db->isFeedLiked($feedId,$userId)) 
                {
                    $result = $db->unlikeFeed($feedId,$userId);
                    if ($result== FEED_UNLIKED) 
                    {
                        $notificationType = 1;
                        $db->deleteFeedLikeNotification($userId,$feedId,$notificationType);
                        $feed = array();
                        $feed['feedLikes'] = $db->getLikesCountByFeedId($feedId);
                        $responseFeedDetails = array();
                        $responseFeedDetails[ERROR] = false;
                        $responseFeedDetails[MESSAGE] = FEED_UNLIKED;
                        $responseFeedDetails[FEED] = $feed;
                        $response->write(json_encode($responseFeedDetails));
                        return $response->withHeader(CT,AJ)
                                        ->withStatus(200);
                    }
                    else
                        return returnException(true,FEED_UNLIKE_FAILED, $response);
                }
                else
                    return returnException(true,FEED_UNLIKE_ALREADY,$response);
            }
            else
                return returnException(true,FEED_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/feed/report', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('feedId'),$request,$response)) 
        {   
            $requestParameter = $request->getParsedBody();
            $feedId = $requestParameter['feedId'];
            if ($db->isFeedExist($feedId)) 
            {
                $userId = $db->getUserId();
                if (!$db->isFeedReported($feedId,$userId)) 
                {
                    $result = $db->reportFeed($feedId,$userId);
                    if ($result) 
                        return returnException(false,FEED_REPORTED,$response);
                    else
                        return returnException(true,FEED_REPORT_FAILED, $response);
                }
                else
                    return returnException(true,FEED_REPORT_ALREADY,$response);
            }
            else
                return returnException(true,FEED_NOT_FOUND,$response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/notifications', function(Request $request, Response $response, array $args)
{
    $db = new DbHandler;
    if(validateToken($db,$request,$response))
    {
        $tokenId = $db->getUserId();
        if ($db->checkUserById($tokenId)) 
        {
            $notifications = $db->getNotificationsByUserId($tokenId);
            $responseNotificationsDetails = array();
            $responseNotificationsDetails[ERROR] = false;
            $responseNotificationsDetails[MESSAGE] = NOTIFICATIONS_FOUND;
            $responseNotificationsDetails['notifications'] = $notifications;
            $response->write(json_encode($responseNotificationsDetails));
            return $response->withHeader(CT,AJ)
                            ->withStatus(200);
        }
        else
            return returnException(true,USERNAME_NOT_EXIST,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/notifications/Count', function(Request $request, Response $response, array $args)
{
    $db = new DbHandler;
    if(validateToken($db,$request,$response))
    {
        $tokenId = $db->getUserId();
        if ($db->checkUserById($tokenId)) 
        {
            $notificationsCount = $db->getActiveNotificationsCountByUserId($tokenId);
            $responseNotificationsDetails = array();
            $responseNotificationsDetails[ERROR] = false;
            $responseNotificationsDetails[MESSAGE] = NOTIFICATIONS_COUNT_FOUND;
            $responseNotificationsDetails['notificationsCount'] = $notificationsCount;
            $response->write(json_encode($responseNotificationsDetails));
            return $response->withHeader(CT,AJ)
                            ->withStatus(200);
        }
        else
            return returnException(true,USERNAME_NOT_EXIST,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/notifications/Seened', function(Request $request, Response $response, array $args)
{
    $db = new DbHandler;
    if(validateToken($db,$request,$response))
    {
        $tokenId = $db->getUserId();
        if ($db->checkUserById($tokenId)) 
        {
            $notificationsSeened = $db->setNotificationSeened($tokenId);
            if ($notificationsSeened)
                return returnException(false,NOTIFICATION_SEEN,$response);
            else
                return returnException(true,NOTIFICATION_SEEN_FAILED,$response);
        }
        else
            return returnException(true,USERNAME_NOT_EXIST,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/video/post', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $requestParameters = $request->getUploadedFiles();
        $requestParameter = $request->getParsedBody();
        $id = $db->getUserId();
        if (!empty($requestParameter['title']) || !!empty($requestParameter['description']))
        {
            if (!empty($requestParameters['video']))
            {
                if (empty($requestParameters['image'])) 
                {
                    $image = null;
                    $title = $requestParameter['title'];
                    $description = $requestParameter['description'];
                    $video = $requestParameters['video'];
                    $result = $db->postVideo($id,$title,$description,$image,$video);
                    if ($result==VIDEO_POSTED)
                        return returnException(false,"Video Has Been Published",$response);
                    else if ($result==VIDEO_POST_FAILED)
                        return returnException(true,"Oops...! Failed To Post Your Video",$response);
                }
                else
                {
                    $image = $requestParameters['image'];
                    $title = $requestParameter['title'];
                    $description = $requestParameter['description'];
                    $video = $requestParameters['video'];
                    $result = $db->postVideo($id,$title,$description,$image,$video);
                    if ($result==VIDEO_POSTED)
                        return returnException(false,"Video Has Been Published",$response);
                    else if ($result==VIDEO_POST_FAILED)
                        return returnException(true,"Oops...! Failed To Post Your Video",$response);
                }
            }
            else
                return returnException(true,"No Video Selected",$response);
        }
        else
            return returnException(true,"Title and Description should not be empty",$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/videos', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $videos = $db->getVideos();
        if (!empty($videos)) 
        {
            $responseFeedDetails = array();
            $responseFeedDetails[ERROR] = false;
            $responseFeedDetails[MESSAGE] = "Videos List Found";
            $responseFeedDetails['videos'] = $videos;
            $response->write(json_encode($responseFeedDetails));
            return $response->withHeader(CT,AJ)
                            ->withStatus(200);
        }
        else
            return returnException(true,"Video Not Found",$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->get('/video/{videoId}', function(Request $request, Response $response, array $args)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        $videoId = $args['videoId'];
        if (!empty($videoId)) 
        {
            $videosId = $db->getVideosIdByVideoId($videoId);   
            if (!empty($videosId)) 
            {
                $video = $db->getVideoById($videosId);
                $user = $db->getUserById($video['userId']);
                $video['userName']       =    $user['name'];
                $video['userImage']      =    $user['image'];
                $video['userUsername']   =    $user['username'];
                $responseFeedDetails = array();
                $responseFeedDetails[ERROR] = false;
                $responseFeedDetails[MESSAGE] = "Videos List Found";
                $responseFeedDetails['video'] = $video;
                $response->write(json_encode($responseFeedDetails));
                return $response->withHeader(CT,AJ)
                                ->withStatus(200);
            }
            else
                return returnException(true,"Invalid Video Id",$response);
        }
        else
            return returnException(true,"Video Not Found",$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/requests/post', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('name','username'),$request,$response)) 
        {
            $requestParameter = $request->getParsedBody();
            $requestParameters = $request->getUploadedFiles();
            $name = $requestParameter['name'];
            $username = $requestParameter['username'];
            $image = "";
            if (!empty($requestParameters['image']))
                $image = $requestParameters['image'];
            else
            {
                return returnException(true,IMAGE_NOT_SELECTED,$response);
                return;
            }
            $userId = $db->getUserId();
            $username = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($username))))));
            $username = str_replace(' ', '', $username);
            if (strlen($name)>25)
            {
                return returnException(true,NAME_GRETER,$response);
                return;
            }
            if (strlen($username)>15) 
            {
                return returnException(true,USERNAME_GRETER,$response);
                return;
            }
            if (!$db->isVerificationRequestAlreadySubmitted($userId)) 
            {
                $result = $db->postVerificationRequest($userId,$name,$username,$image);
                if ($result==VERIFICATION_REQUEST_SUBMITTED)
                    return returnException(false,"Verification Request Submitted",$response);
                else if ($result==VERIFICATION_REQUEST_SUBMIT_FAILED)
                    return returnException(true,"Oops...! Failed To To Submit Verification Request",$response);
            }
            else
                return returnException(true,"Your Verification Request Is In Pending",$response);
        }
    } 
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/contacts/post', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {
        if (!checkEmptyParameter(array('name','email',MESSAGE),$request,$response)) 
        {   
            $requestParameter = $request->getParsedBody();
            $name = $requestParameter['name'];
            $email = $requestParameter['email'];
            $message = $requestParameter[MESSAGE];
            $userId = $db->getUserId();
            if (!$db->isEmailValid($email)) 
            {
                return returnException(true,EMAIL_NOT_VALID,$response);
                return;
            }
            $result = $db->postContactUs($userId,$name,$email,$message);
            if ($result) 
                return returnException(false,SUBMITTED,$response);
            else
                return returnException(true,SUBMIT_FAILED, $response);
        }
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

$app->post('/uploadProfileImage', function(Request $request, Response $response)
{
    $db = new DbHandler;
    if (validateToken($db,$request,$response)) 
    {   
        $requestParameter = $request->getUploadedFiles();
        if (!empty($requestParameter['image'])) 
        {
            $image = $requestParameter['image'];
            $id = $db->getUserId();
            $result = $db->uploadProfileImage($id,$image);
            if($result == IMAGE_UPLOADED)
                    return returnException(false,IMAGE_UPLOADED,$response);
                else if($result ==IMAGE_UPLOADE_FAILED)
                    return returnException(true,IMAGE_UPLOADE_FAILED,$response);
                else if($result ==IMAGE_NOT_SELECTED)
                    return returnException(true,IMAGE_NOT_SELECTED,$response);
        }
        else
            return returnException(true,IMAGE_NOT_SELECTED,$response);
    }
    return returnException(true,UNAUTH_ACCESS,$response);
});

function checkEmptyParameter($requiredParameter,$request,$response)
{
    $result = array();
    $error = false;
    $errorParam = '';
    $requestParameter = $request->getParsedBody();
    foreach($requiredParameter as $param)
    {
        if(!isset($requestParameter[$param]) || strlen($requestParameter[$param])<1)
        {
            $error = true;
            $errorParam .= $param.', ';
        }
    }
    if($error)
        return returnException(true,"Required Parameter ".substr($errorParam,0,-2)." is missing",$response);
    return $error;
}

function prepareForgotPasswordMail($name,$email,$code)
{
    $websiteDomain = WEBSITE_DOMAIN;
    $websiteName = WEBSITE_NAME;
    $websiteEmail = WEBSITE_EMAIL;
    $websiteOwnerName = WEBSITE_OWNER_NAME;
    $ipAddress = "(".$_SERVER['REMOTE_ADDR'].")";
    $mailSubject = "Recover your $websiteName password";
    $mailBody= <<<HERE
    <body style="background-color: #f4f4f4; margin: 0 !important; padding: 0 !important;">
    <!-- HIDDEN PREHEADER TEXT -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <!-- LOGO -->
        <tr>
            <td bgcolor="#FFA73B" align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td align="center" valign="top" style="padding: 40px 10px 40px 10px;"> </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#FFA73B" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="center" valign="top" style="padding: 40px 20px 20px 20px; border-radius: 4px 4px 0px 0px; color: #111111; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 48px; font-weight: 400; letter-spacing: 4px; line-height: 48px;">
                            <h1 style="font-size: 48px; font-weight: 400; margin: 2;">Welcome!</h1><img src=" https://img.icons8.com/clouds/100/000000/handshake.png" width="125" height="120" style="display: block; border: 0px;" />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#f4f4f4" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 20px 30px 40px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">You told us you forgot your password, If you really did, Use this OTP (One Time Password) to choose a new one.</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td bgcolor="#ffffff" align="center" style="padding: 20px 30px 60px 30px;">
                                        <table border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td align="center" style="border-radius: 3px;" bgcolor="#FFA73B"><b style="font-size: 20px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; color: #ffffff; text-decoration: none; padding: 15px 25px; border-radius: 2px; border: 1px solid #FFA73B; display: inline-block;">$code</b></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr> <!-- COPY -->
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 0px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">For security, this request was recieved from ip address $ipAddress. <br> If you didn't make this request, you can safely ignore this email :)</p>
                        </td>
                    </tr> <!-- COPY -->
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 20px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 400; line-height: 25px;">
                           <br> <p style="margin: 0;">If you have any questions, just reply to this email—we're always happy to help out.</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 40px 30px; border-radius: 0px 0px 4px 4px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">$websiteOwnerName,<br>$websiteName Team</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#f4f4f4" align="center" style="padding: 30px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#FFECD1" align="center" style="padding: 30px 30px 30px 30px; border-radius: 4px 4px 4px 4px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <h2 style="font-size: 20px; font-weight: 400; color: #111111; margin: 0;">Need more help?</h2>
                            <p style="margin: 0;"><a href="$websiteDomain" target="_blank" style="color: #FFA73B;">We&rsquo;re here to help you out</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    </body>
    HERE;;

    if(sendMail($name,$email,$mailSubject,$mailBody))
        return true;
    return false;
}

function prepareVerificationMail($name,$email,$code)
{
    $emailEncrypted = encrypt($email);
    $websiteDomain = WEBSITE_DOMAIN;
    $websiteName = WEBSITE_NAME;
    $websiteEmail = WEBSITE_EMAIL;
    $websiteOwnerName = WEBSITE_OWNER_NAME;
    $endPoint = "/verifyEmail/";
    $mailSubject="Verify Your Email Address For $websiteName";
    $mailBody= <<<HERE
    <body style="background-color: #f4f4f4; margin: 0 !important; padding: 0 !important;">
    <!-- HIDDEN PREHEADER TEXT -->
    <div style="display: none; font-size: 1px; color: #fefefe; line-height: 1px; font-family: 'Lato', Helvetica, Arial, sans-serif; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;"> We're thrilled to have you here! Get ready to dive into your new account. </div>
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <!-- LOGO -->
        <tr>
            <td bgcolor="#FFA73B" align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td align="center" valign="top" style="padding: 40px 10px 40px 10px;"> </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#FFA73B" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="center" valign="top" style="padding: 40px 20px 20px 20px; border-radius: 4px 4px 0px 0px; color: #111111; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 48px; font-weight: 400; letter-spacing: 4px; line-height: 48px;">
                            <h1 style="font-size: 48px; font-weight: 400; margin: 2;">Welcome!</h1><img src=" https://img.icons8.com/clouds/100/000000/handshake.png" width="125" height="120" style="display: block; border: 0px;" />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#f4f4f4" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 20px 30px 40px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">We're excited to have you get started. First, you need to confirm your account. Just press the button below.</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td bgcolor="#ffffff" align="center" style="padding: 20px 30px 60px 30px;">
                                        <table border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td align="center" style="border-radius: 3px;" bgcolor="#FFA73B"><a href="$websiteDomain$endPoint$emailEncrypted/$code" target="_blank" style="font-size: 20px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; color: #ffffff; text-decoration: none; padding: 15px 25px; border-radius: 2px; border: 1px solid #FFA73B; display: inline-block;">Confirm Account</a></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr> <!-- COPY -->
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 0px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">If that doesn't work, copy and paste the following link in your browser:</p>
                        </td>
                    </tr> <!-- COPY -->
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 20px 30px 20px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;"><a href="#" target="_blank" style="color: #FFA73B;">$websiteDomain$endPoint$emailEncrypted/$code</a></p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 20px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">If you have any questions, just reply to this email—we're always happy to help out.</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 40px 30px; border-radius: 0px 0px 4px 4px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">$websiteOwnerName,<br>$websiteName Team</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#f4f4f4" align="center" style="padding: 30px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#FFECD1" align="center" style="padding: 30px 30px 30px 30px; border-radius: 4px 4px 4px 4px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <h2 style="font-size: 20px; font-weight: 400; color: #111111; margin: 0;">Need more help?</h2>
                            <p style="margin: 0;"><a href="$websiteDomain" target="_blank" style="color: #FFA73B;">We&rsquo;re here to help you out</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    </body>
    HERE;;
    if(sendMail($name,$email,$mailSubject,$mailBody))
        return true;
    return false;
}

function preparePasswordChangedMail($name,$email)
{
    $websiteDomain = WEBSITE_DOMAIN;
    $websiteName = WEBSITE_NAME;
    $websiteEmail = WEBSITE_EMAIL;
    $websiteOwnerName = WEBSITE_OWNER_NAME;
    $ipAddress = "(".$_SERVER['REMOTE_ADDR'].")";
    date_default_timezone_set('Asia/Kolkata');
    $currentDate = date('d');
    $currentMonth =  DateTime::createFromFormat('!m',date('m'));
    $currentMonth = $currentMonth->format('F');
    $currentYear = date('yy');
    $currentTime = date('h:i a');
    $mailSubject = "Your password has been changed.";
    $mailBody = <<<HERE
    <body style="background-color: #f4f4f4; margin: 0 !important; padding: 0 !important;">
    <!-- HIDDEN PREHEADER TEXT -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <!-- LOGO -->
        <tr>
            <td bgcolor="#FFA73B" align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td align="center" valign="top" style="padding: 40px 10px 40px 10px;"> </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#FFA73B" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="center" valign="top" style="padding: 40px 20px 20px 20px; border-radius: 4px 4px 0px 0px; color: #111111; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 48px; font-weight: 400; letter-spacing: 4px; line-height: 48px;">
                            <h1 style="font-size: 48px; font-weight: 400; margin: 2;">Welcome!</h1><img src=" https://img.icons8.com/clouds/100/000000/handshake.png" width="125" height="120" style="display: block; border: 0px;" />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#f4f4f4" align="center" style="padding: 0px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 20px 30px 40px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">This is a confirmation that your password was changed at $currentTime on $currentDate $currentMonth $currentYear</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 0px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">For security, The password was changed from the Ip Address $ipAddress. If this was you, then you can safely ignore this email :)</p>
                        </td>
                    </tr> <!-- COPY -->
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 20px 30px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 15px; font-weight: 400; line-height: 25px;">
                           <br> <p style="margin: 0;">If you have any questions, just reply to this email—we're always happy to help out.</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#ffffff" align="left" style="padding: 0px 30px 40px 30px; border-radius: 0px 0px 4px 4px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <p style="margin: 0;">$websiteOwnerName,<br>$websiteName Team</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td bgcolor="#f4f4f4" align="center" style="padding: 30px 10px 0px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td bgcolor="#FFECD1" align="center" style="padding: 30px 30px 30px 30px; border-radius: 4px 4px 4px 4px; color: #666666; font-family: 'Lato', Helvetica, Arial, sans-serif; font-size: 18px; font-weight: 400; line-height: 25px;">
                            <h2 style="font-size: 20px; font-weight: 400; color: #111111; margin: 0;">Need more help?</h2>
                            <p style="margin: 0;"><a href="$websiteDomain" target="_blank" style="color: #FFA73B;">We&rsquo;re here to help you out</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    </body>
    HERE;;
    sendMail($name,$email,$mailSubject,$mailBody);
}

function sendMail($name,$email,$mailSubject,$mailBody)
{
    $websiteEmail = WEBSITE_EMAIL;
    $websiteEmailPassword = WEBSITE_EMAIL_PASSWORD;
    $websiteName = WEBSITE_NAME;
    $websiteOwnerName = WEBSITE_OWNER_NAME;
    $mail = new PHPMailer;
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    // $mail->Host="smtp.gmail.com";
    // $mail->Port=587;
    $mail->Host="free.mboxhosting.com";
    $mail->Port=25;
    $mail->SMPTSecure="tls";
    $mail->SMTPAuth=true;
    $mail->Username = $websiteEmail;
    $mail->Password = $websiteEmailPassword;
    $mail->addAddress($email,$name);
    $mail->isHTML();
    $mail->Subject=$mailSubject;
    $mail->Body=$mailBody;
    $mail->From=$websiteEmail;
    $mail->FromName=$websiteName;
    if($mail->send())
    {
        return true;
    }
    return false;
}

function encrypt($data)
{
    $email = openssl_encrypt($data,"AES-128-ECB",null);
    $email = str_replace('/','socialcodia',$email);
    $email = str_replace('+','mufazmi',$email);
    return $email; 
}

function decrypt($data)
{
    $mufazmi = str_replace('mufazmi','+',$data);
    $email = str_replace('socialcodia','/',$mufazmi);
    $email = openssl_decrypt($email,"AES-128-ECB",null);
    return $email; 
}

function returnException($error,$message,$response)
{
    $errorDetails = array();
    $errorDetails[ERROR] = $error;
    $errorDetails[MESSAGE] = $message;
    $response->write(json_encode($errorDetails));
    return $response->withHeader(CT,AJ)
                    ->withStatus(200);
}

function returnResponse($error,$message,$response,$data)
{
    $responseDetails = array();
    $responseDetails[ERROR] = $error;
    $responseDetails[MESSAGE] = $message;
    $responseDetails[MESSAGE] = $data;
    $response->write(json_encode($responseDetails));
    return $response->withHeader(CT,AJ)
                    ->withStatus(200);
}

function getToken($userId)
{
    $key = JWT_SECRET_KEY;
    $payload = array(
        "iss" => "socialcodia.com",
        "iat" => time(),
        "user_id" => $userId
    );
    $token =JWT::encode($payload,$key);
    return $token;
}

function validateToken($db,$request,$response)
{
    $error = false;
    $header =$request->getHeaders();
    if (!empty($header['HTTP_TOKEN'][0])) 
    {
        $token = $header['HTTP_TOKEN'][0];
        $result = $db->validateToken($token);
        if (!$result == JWT_TOKEN_FINE)
            $error = true;
        else if($result == JWT_TOKEN_ERROR || $result==JWT_USER_NOT_FOUND)
        {
            $error = true;
        }
    }

    else
    {
        $error = true;
    }
    if ($error)
        return false;
    else
        return true;
}

$app->run();