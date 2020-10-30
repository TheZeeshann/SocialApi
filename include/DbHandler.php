<?php

require_once dirname(__FILE__).'/JWT.php';

    $JWT = new JWT;


class DbHandler
{
    private $con;
    private $userId;
    private $videoId;

    function __construct()
    {
        require_once dirname(__FILE__) . '/DbCon.php';
        $db = new DbCon;
        $this->con =  $db->Connect();
    }

    //Getter Setter For User Id Only

    function setUserId($userId)
    {
        $this->userId = $userId;
    }

    function getUserId()
    {
        return $this->userId;
    }

    function setVideoId($videoId)
    {
        $this->videoId = $videoId;
    }

    function getVideoId()
    {
        return $this->videoId;
    }

    function createUser($name,$username,$email,$password)
    {
        $user = array();
        if($this->isEmailValid($email))
        {
            if (!$this->isEmailExist($email))
            {
                if (!$this->isUsernameExist($username)) 
                {
                    $hashPass = password_hash($password,PASSWORD_DEFAULT);
                    $code = password_hash($email.time(),PASSWORD_DEFAULT);
                    $code = str_replace('/','socialcodia',$code);
                    $query = "INSERT INTO users (name,username,email,password,code,status) VALUES (?,?,?,?,?,?)";
                    $stmt = $this->con->prepare($query);
                    $status =0;
                    $stmt->bind_param('ssssss',$name,$username,$email,$hashPass,$code,$status);
                    if($stmt->execute())
                        return USER_CREATED;
                    else
                        return FAILED_TO_CREATE_USER;
                }
                else
                    return USERNAME_EXIST;
            }
            else
                return EMAIL_EXIST;
        }
        return EMAIL_NOT_VALID;
    }

    function login($email,$password)
    {
        if($this->isEmailValid($email))
        {
            if($this->isEmailExist($email))
            {
                $hashPass = $this->getPasswordByEmail($email);
                if(password_verify($password,$hashPass))
                {
                    if($this->isEmailVerified($email))
                        return LOGIN_SUCCESSFULL;
                    else
                        return UNVERIFIED_EMAIL;
                }
                else
                    return PASSWORD_WRONG;
            }
            else
                return USER_NOT_FOUND;
        }
        else
            return EMAIL_NOT_VALID;
    }

    function isUserAlreadyBlocked($tokenId,$userId)
    {
        $query = "SELECT fromUserId from block WHERE fromUserId=? AND toUserId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$tokenId,$userId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows()>0)
            return true;
        return false;
    }


    function getUsersWhichIHaveBlocked($tokenId)
    {
        $userId = array();
        $query = "SELECT toUserId FROM block WHERE fromUserId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$tokenId);
        $stmt->execute();
        $stmt->bind_result($toUserId);
        $stmt->store_result();
        if ($stmt->num_rows()>0) 
        {
            while ($stmt->fetch()) {
                if ($tokenId!=$toUserId)
                    array_push($userId, $toUserId);
            }
        }
        return $userId;
    }

    function getUserIdWhoBlockedMe($tokenId)
    {
        $userId = array();
        $query = "SELECT fromUserId FROM block WHERE toUserId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$tokenId);
        $stmt->execute();
        $stmt->bind_result($fromUserId);
        $stmt->store_result();
        if ($stmt->num_rows()>0) 
        {
            while ($stmt->fetch()) {
                if ($tokenId!=$fromUserId)
                    array_push($userId, $fromUserId);
            }
        }
        return $userId;
    }

    function isUserBlocked($tokenId,$userId)
    {
        $query = "SELECT fromUserId from block WHERE (fromUserId=? AND toUserId=?) OR (fromUserId=? AND toUserId=?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ssss",$tokenId,$userId,$userId,$tokenId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows()>0)
            return true;
        return false;
    }

    function doBlockUser($tokenId,$userId)
    {
        $query = "INSERT INTO block (fromUserId,toUserId) VALUES(?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$tokenId,$userId);
        if ($stmt->execute())
            return true;
        return false;
    }

    function isUpdateAvailable($version)
    {
        $result = array();
        $query = "SELECT updateUrl FROM updates WHERE updateVersion>?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$version);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows()>0)
            return true;
        else
            return false;
    }

    function getUpdateUrl()
    {
        $result = array();
        $query = "SELECT updateUrl FROM updates ORDER BY updateId DESC LIMIT 1";
        $stmt = $this->con->prepare($query);
        $stmt->execute();
        $stmt->bind_result($updateUrl);
        $stmt->fetch();
        $response['updateUrl'] = $updateUrl;
        return $response;
    }

    function doUnBlockUser($tokenId,$userId)
    {
        $query = "DELETE from block WHERE fromUserId=? AND toUserId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$tokenId,$userId);
        if ($stmt->execute())
            return true;
        return false;
    }

    function updateUser($id,$name,$username,$bio,$image)
    {
        $imageFromDb = $this->getImageById($id);
        if ($image!=$imageFromDb)
            $imageUrl = $this->uploadImage($image);
        else
            $imageUrl = $imageFromDb;
        $query = "UPDATE users SET name=?, username=?, bio=?, image=? WHERE id=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("sssss",$name,$username,$bio,$imageUrl,$id);
        if($stmt->execute())
            return USER_UPDATED;
        else
            return USER_UPDATE_FAILED;
    }

    function addNotification($tokenId,$userId,$type)
    {
        $isSeen = 0;
        $query = "INSERT INTO notifications (senderId,receiverId,notificationType,isSeen) VALUES(?,?,?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ssss',$tokenId,$userId,$type,$isSeen);
        if($stmt->execute())
            return true;
        else
            return false;
    }

    function addFeedLikeNotification($userId,$feedId,$notificationType)
    {
        $isSeen = 0;
        $feedAuthor = $this->getFeedAuthorIdByFeedId($feedId);
        $query = "INSERT INTO notifications (senderId,receiverId,feedId,notificationType,isSeen) VALUES(?,?,?,?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('sssss',$userId,$feedAuthor,$feedId,$notificationType,$isSeen);
        if(!$stmt->execute())
            return false;
    }


    function getFeedIdByCommentId($commentId)
    {
        $query = "SELECT feedId FROM comments WHERE commentId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$commentId);
        $stmt->execute();
        $stmt->bind_result($feedId);
        $stmt->fetch();
        return $feedId;
    }


    function deleteFeedLikeNotification($userId,$feedId,$notificationType)
    {
        $isSeen = 0;
        $feedAuthor = $this->getFeedAuthorIdByFeedId($feedId);
        $query = "DELETE FROM notifications WHERE senderId=? AND feedId=? AND notificationType=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('sss',$userId,$feedId,$notificationType);
        if($stmt->execute())
            return true;
        else
            return false;
    }

    function deleteAllFeedLikeNotification($feedId)
    {
        $isSeen = 0;
        $feedAuthor = $this->getFeedAuthorIdByFeedId($feedId);
        $query = "DELETE FROM notifications WHERE feedId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$feedId);
        if($stmt->execute())
            return true;
        else
            return false;
    }


    function deleteNotification($tokenId,$userId,$type)
    {
        $query = "DELETE FROM notifications WHERE senderId=? AND receiverId=? AND notificationType=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('sss',$tokenId,$userId,$type);
        if($stmt->execute())
            return true;
        else
            return false;
    }

    function deleteAllNotification($tokenId,$userId)
    {
        $query = "DELETE FROM notifications WHERE (senderId=? AND receiverId=?) OR (senderId=? AND receiverId=?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ssss',$tokenId,$userId,$userId,$tokenId);
        if($stmt->execute())
            return true;
        else
            return false;
    }

    function getNotificationsByUserId($tokenId)
    {
        $notifications = array();
        $notificationsData = array();
        $query = "SELECT * FROM notifications WHERE receiverId=? order by notificationId desc";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$tokenId);
        $stmt->execute();
        $stmt->bind_result($notificationId,$senderId,$receiverId,$feedId,$notificationType,$isSeen,$timestamp);
        while ($stmt->fetch()) 
        {
            $notification = array();
            $notification['notificationId'] = $notificationId ;
            $notification['userId'] = $senderId ;
            $notification['feedId'] = $feedId ;
            $notification['notificationType'] = $notificationType;
            $notification['isSeen'] = $isSeen;
            $notification['timestamp'] = $timestamp;
            array_push($notifications, $notification);
        }
        foreach ($notifications as $notification) 
        {   
            $comment = array();
            $users = array();
            $tokenId = $this->getUserId();
            $id = $notification['userId'];
            $notificationType = $notification['notificationType'];
            $users = $this->getUserById($id);
            $notification['notificationText'] = $this->getNotificationTextByType($notificationType);
            $notification['userUsername']   = $users['username'];
            $notification['userName']   = $users['name'];
            $notification['userImage']  = $users['image'];
            $notification['userStatus']  = $users['status'];
            array_push($notificationsData, $notification);
        }
        return $notificationsData;
    }

    function getActiveNotificationsCountByUserId($tokenId)
    {
        $isSeen = 0;
        $query = "SELECT isSeen FROM notifications WHERE receiverId=? AND isSeen=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$tokenId,$isSeen);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows;
    }

    function setNotificationSeened($tokenId)
    {
        $isSeen = 1;
        $query = "UPDATE notifications SET isSeen=? WHERE receiverId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$isSeen,$tokenId);
        if($stmt->execute())
            return true;
        else
            return false;
    }
       

    function getNotificationTextByType($notificationType)
    {
        $notification = "";
        if ($notificationType==1)
            $notification = "Like Your Feed";
        else if ($notificationType==2)
            $notification = "Sent You A Friend Request";
        else if ($notificationType==4)
            $notification = "Accept Your Friend Request";
        else if ($notificationType==11)
            $notification = "Comment On Your Feed";
        else if ($notificationType==111)
            $notification = "Like Your Comment";
        return $notification;
    }

    function getFeedAuthorIdByFeedId($feedId)
    {
        $query = "SELECT userId FROM feeds WHERE feedId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$feedId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        return $id;
    }

    function getCommentAuthorIdByFeedId($commentId)
    {
        $query = "SELECT userId FROM comments_likes WHERE commentId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$commentId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        return $id;
    }


    function getUserImageById($id)
    {
        $query = "SELECT image FROM users WHERE id=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$id);
        $stmt->execute();
        $stmt->bind_result($image);
        return WEBSITE_DOMAIN.$image;
    }

    function postVerificationRequest($userId,$name, $username, $image)
    {
        $imageUrl = $this->uploadImage($image);
        $query = "INSERT INTO requests (userId,name,username,image) VALUES(?,?,?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ssss",$userId,$name,$username,$imageUrl);
        if ($stmt->execute())
            return VERIFICATION_REQUEST_SUBMITTED;
        else
            return VERIFICATION_REQUEST_SUBMIT_FAILED;
    }

    function postContactUs($userId,$name,$email,$message)
    {
        $query = "INSERT INTO contact_us (userId,name,email,message) VALUES(?,?,?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ssss",$userId,$name,$email,$message);
        if ($stmt->execute())
            return true;
        else
            return false;
    }

    function postFeed($tokenId, $content, $file,$feedPrivacy)
    {
        if ($file!=null) 
        {
            
            //WARNING WARNING WARNING  || $file->type=='application/octet-stream' is invalid it will video type file in image section,
            if ($file->type=='image/jpg' || $file->type=='image/jpeg' || $file->type=='image/png' || $file->type=='application/octet-stream')
            {
                $imageUrl = $this->uploadImage($file);
                $result = $this->postImageFeed($imageUrl, $content,$feedPrivacy,$tokenId);
                if ($result)
                    return FEED_POSTED;
                else
                    return FEED_POST_FAILED;
            }
            else if ($file->type=='video/mp4' || $file->type=='application/octet-stream')
            {
                $videoUrl = $this->uploadFeedVideo($file);
                $result = $this->postVideoFeed($videoUrl,$content,$feedPrivacy,$tokenId);
                if ($result)
                    return FEED_POSTED;
                else
                    return FEED_POST_FAILED;
            }
            else
                return FEED_POST_FAILED;
        }
        else
        {
            $feedType = "text";
            $query = "INSERT INTO feeds (userId,feedContent,feedType,feedPrivacy) VALUES(?,?,?,?)";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param("ssss",$tokenId,$content,$feedType,$feedPrivacy);
            if ($stmt->execute())
                return FEED_POSTED;
            else
                return FEED_POST_FAILED;
        }
    }

    function postImageFeed($imageUrl, $content, $id)
    {
        $feedType = "image";
        $query = "INSERT INTO feeds (userId,feedContent,feedImage,feedType) VALUES(?,?,?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ssss",$id,$content,$imageUrl,$feedType);
        if ($stmt->execute())
            return true;
        else
            return false;
    }

    function postVideoFeed($feedVideoUrl, $content, $id)
    {
        $feedType = "video";
        $query = "INSERT INTO feeds (userId,feedContent,feedVideo,feedType) VALUES(?,?,?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ssss",$id,$content,$feedVideoUrl,$feedType);
        if ($stmt->execute())
            return true;
        else
            return false;
    }

    function postVideo($id, $title, $description, $image, $video)
    {
        $imageUrl = $this->uploadThumbnail($image);
        $videoUrl = $this->uploadVideo($video);
        $videoId = $this->getVideoId();
        $query = "INSERT INTO videos (videoId,userId,videoTitle,videoDesc,videoImage,videoUrl) VALUES(?,?,?,?,?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ssssss",$videoId,$id,$title,$description,$imageUrl,$videoUrl);
        if ($stmt->execute())
            return VIDEO_POSTED;
        else
            return VIDEO_POST_FAILED;
    }

    function updateFeed($feedId,$content,$image)
    {
        $imageUrl = $this->uploadImage($image);
        if (empty($imageUrl))
            $imageUrl = $this->getImageByFeedId($feedId);
        $query = "UPDATE feeds SET feedContent=?, feedImage=? WHERE feedId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("sss",$content,$imageUrl,$feedId);
        if($stmt->execute())
            return FEED_UPDATED;
        else
            return FEED_UPDATE_FAILED;
    }

    function deleteFeed($feedId,$userId)
    {
        $query = "DELETE FROM feeds WHERE feedId = ? AND userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$feedId,$userId);
        if ($stmt->execute())
            return FEED_DELETED;
        else
            return FEED_DELETE_FAILED;
    }

    function getLastCommentByUserId($userId)
    {
        $comments = array();
        $commentsData = array();
        $query = "SELECT commentId,userId,feedId,feedComment,timestamp FROM comments WHERE userId=? ORDER BY timestamp DESC LIMIT 1";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$userId);
        $stmt->execute();
        $stmt->bind_result($commentId,$userId,$feedId,$feedComment,$timestamp);
        $stmt->fetch();
        $comment = array();
        $comment['commentId'] = $commentId ;
        $comment['userId'] = $userId ;
        $comment['commentComment'] = $feedComment ;
        $comment['commentTimestamp'] = $timestamp ;
        return $comment;
    }

    function getCommentsByFeedId($feedId)
    {
        $comments = array();
        $commentsData = array();
        $query = "SELECT commentId,userId,feedId,feedComment,timestamp FROM comments WHERE feedId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$feedId);
        $stmt->execute();
        $stmt->bind_result($commentId,$userId,$feedId,$feedComment,$timestamp);
        while ($stmt->fetch()) 
        {
            $comment = array();
            $comment['commentId'] = $commentId ;
            $comment['userId'] = $userId ;
            $comment['feedId'] = $feedId ;
            $comment['feedComment'] = $feedComment ;
            $comment['timestamp'] = $timestamp ;
            array_push($comments, $comment);
        }
        foreach ($comments as $commentList) 
        {   
            $comment = array();
            $users = array();
            $userId = $this->getUserId();
            $id = $commentList['userId'];
            $users = $this->getUserById($id);
            $comment['userId']         =    $users['id'];
            $comment['userName']       =    $users['name'];
            $comment['userUsername']   =    $users['username'];
            $comment['userImage']      =    $users['image'];
            $comment['liked']          =    $this->checkCommentLike($userId,$commentList['commentId']);
            $comment['commentLikesCount']  =   $this->getCommentsLikeCountByCommentId($commentList['commentId']);
            $comment['commentId']      =       $commentList['commentId'];
            $comment['commentComment']    =       $commentList['feedComment'];
            $comment['commentTimestamp']  =    $commentList['timestamp'];
            array_push($commentsData, $comment);
        }
        return $commentsData;
    }

    function getCommentsLikeCountByCommentId($commentId)
    {
        $query = "SELECT commentLikeId FROM comments_likes WHERE commentId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$commentId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows;
    }

/*Added Feed Privacy And New Feature With Security
1 : 1 means public.
2 : 2 means friends.
3 : 3 means private or only me.
4 : The feed of who blocked me or who i blocked will not be return now.
5 : Public can not access private and friends data anymore.
*/
    function getFeeds($fp)
    {
        $feeds = array();
        $feedPrivacy = array($fp);
        $feedsData = array();
        $blocked = array_merge($this->getUserIdWhoBlockedMe($this->getUserId()),$this->getUsersWhichIHaveBlocked($this->getUserId()));
        $friendsId = $this->getAllFriendsId($this->getUserId());
        if ($fp === 1) 
        {
            // echo "if block running";
            if (!empty($blocked))
            {

                // echo " block if block running";
                $blocked = implode(',', $blocked);
                if (empty($friendsId)) 
                {   
                    // echo " friends if block running";
                    $feedPrivacy  = implode(',', $feedPrivacy);
                    $query = "SELECT feedId, userId, feedImage, feedContent, feedVideo, feedThumbnail,feedType, timestamp FROM feeds WHERE feedPrivacy IN ($feedPrivacy) AND userId NOT IN ($blocked) order by timestamp desc";
                }
                else
                {
                // echo " friends else block running";
                    $feedPrivacy = array($fp,2);
                    $feedPrivacy  = implode(',', $feedPrivacy);
                    $fid = array($this->getUserId());
                    $friendsId = array_merge($fid,$friendsId);
                    $friendsId = implode(',', $friendsId);
                    $query = "SELECT feedId, userId, feedImage, feedContent, feedVideo, feedThumbnail,feedType, timestamp FROM feeds WHERE (feedPrivacy IN ($fp) AND userId NOT IN ($blocked)) OR (userId IN ($friendsId) AND feedPrivacy IN ($feedPrivacy)) order by timestamp desc";
                }
            }
            else
            {
                // echo "block else block running";
                if (empty($friendsId))
                {
                    // echo "friends if block is running";
                    $feedPrivacy  = implode(',', $feedPrivacy);
                    $query = "SELECT feedId, userId, feedImage, feedContent, feedVideo, feedThumbnail,feedType, timestamp FROM feeds WHERE feedPrivacy IN ($feedPrivacy) order by timestamp desc";
                }
                else
                {
                    // echo "friends else block running"; 
                    $feedPrivacy = array($fp,2);
                    $feedPrivacy  = implode(',', $feedPrivacy); ///need to add self id to show the self feedtype friend in feeds
                    $friendsId = implode(',', $friendsId);
                    $query = "SELECT feedId, userId, feedImage, feedContent, feedVideo, feedThumbnail,feedType, timestamp FROM feeds WHERE (feedPrivacy IN ($fp)) OR (userId IN ($friendsId) AND feedPrivacy IN ($feedPrivacy)) order by timestamp desc";
                }
            }
        }
        else
        {
            // echo "else block running";
            $feedPrivacy = array($fp,1);
            $feedPrivacy  = implode(',', $feedPrivacy);
            $friendsId = implode(',', $friendsId);
            $query = "SELECT feedId, userId, feedImage, feedContent, feedVideo, feedThumbnail,feedType, timestamp FROM feeds WHERE (feedPrivacy IN ($feedPrivacy)) AND (userId IN ($friendsId) AND feedPrivacy IN ($feedPrivacy)) order by timestamp desc";
        }
        $stmt = $this->con->prepare($query);
        $stmt->execute();
        $stmt->bind_result($id,$userId,$image,$content,$feedVideo,$feedThumbnail,$feedType,$timestamp);
        while ($stmt->fetch()) 
        {
            $feed = array();
            $feed['feedUserId'] = $userId;
            $feed['feedId'] = $id;
            $feed['feedImage'] = $image;
            $feed['feedContent'] = $content;
            $feed['feedType'] = $feedType;
            $feed['feedTimestamp'] = $timestamp;
            $feed['feedVideo'] = $feedVideo;
            $feed['feedThumbnail'] = $feedThumbnail;
            array_push($feeds, $feed);
        }
        foreach ($feeds as $feedList) 
        {   
            $feed = array();
            $users = array();
            $userId = $this->getUserId();
            $id = $feedList['feedUserId'];
            $users = $this->getUserById($id);
            $feed['userId']         =    $users['id'];
            $feed['userName']       =    $users['name'];
            $feed['userUsername']   =    $users['username'];
            $feed['userImage']      =    $users['image'];
            $feed['userStatus']   =    $users['status'];
            $feed['feedId']         =    $feedList['feedId'];
            $feed['liked']          =    $this->checkFeedLike($userId,$feedList['feedId']);
            $feed['feedLikes']      =    $this->getLikesCountByFeedId($feedList['feedId']);
            $feed['feedComments']   =    $this->getCommentsCountByFeedId($feedList['feedId']);
            $feed['feedContent']    =    $feedList['feedContent'];
            $feed['feedType']       =    $feedList['feedType'];
            if ($feedList['feedType']=="video")
            {
                $feed['feedVideo'] = WEBSITE_DOMAIN.$feedList['feedVideo'];
                $feed['feedThumbnail'] = WEBSITE_DOMAIN.$feedList['feedThumbnail'];
            }
            else if ($feedList['feedType']=="image") 
            {
                $feed['feedImage']      =    WEBSITE_DOMAIN.$feedList['feedImage'];
            }
            $feed['feedTimestamp']  =    $feedList['feedTimestamp'];
            array_push($feedsData, $feed);
        }
        return $feedsData;
    }

    function getFriendsFeed()
    {
        $feedPrivacy = implode(',',array('1','2'));
        $feeds = array();
        $feedsData = array();
        $id = $this->getAllFriendsId($this->getUserId());
        $friends = implode(',', $id);
        print_r($id);
        $query = "SELECT feedId, userId, feedImage, feedContent, feedVideo, feedThumbnail,feedType, timestamp FROM feeds WHERE  feedPrivacy IN ($feedPrivacy) AND userId IN ($friends) order by timestamp desc";
        $stmt = $this->con->prepare($query);
        $stmt->execute();
        $stmt->bind_result($id,$userId,$image,$content,$feedVideo,$feedThumbnail,$feedType,$timestamp);
        while ($stmt->fetch()) 
        {
            $feed = array();
            $feed['feedUserId'] = $userId;
            $feed['feedId'] = $id;
            $feed['feedImage'] = $image;
            $feed['feedContent'] = $content;
            $feed['feedType'] = $feedType;
            $feed['feedTimestamp'] = $timestamp;
            $feed['feedVideo'] = $feedVideo;
            $feed['feedThumbnail'] = $feedThumbnail;
            array_push($feeds, $feed);
        }
        foreach ($feeds as $feedList) 
        {   
            $feed = array();
            $users = array();
            $userId = $this->getUserId();
            $id = $feedList['feedUserId'];
            $users = $this->getUserById($id);
            $feed['userId']         =    $users['id'];
            $feed['userName']       =    $users['name'];
            $feed['userUsername']   =    $users['username'];
            $feed['userImage']      =    $users['image'];
            $feed['userStatus']   =    $users['status'];
            $feed['feedId']         =    $feedList['feedId'];
            $feed['liked']          =    $this->checkFeedLike($userId,$feedList['feedId']);
            $feed['feedLikes']      =    $this->getLikesCountByFeedId($feedList['feedId']);
            $feed['feedComments']   =    $this->getCommentsCountByFeedId($feedList['feedId']);
            $feed['feedContent']    =    $feedList['feedContent'];
            $feed['feedType']       =    $feedList['feedType'];
            if ($feedList['feedType']=="video")
            {
                $feed['feedVideo'] = WEBSITE_DOMAIN.$feedList['feedVideo'];
                $feed['feedThumbnail'] = WEBSITE_DOMAIN.$feedList['feedThumbnail'];
            }
            else if ($feedList['feedType']=="image") 
            {
                $feed['feedImage']      =    WEBSITE_DOMAIN.$feedList['feedImage'];
            }
            $feed['feedTimestamp']  =    $feedList['feedTimestamp'];
            array_push($feedsData, $feed);
        }
        return $feedsData;
    }

    function getVideos()
    {
        $videos = array();
        $videosData = array();
        $query = "SELECT videoId, userId, videoTitle, videoDesc,videoImage, videoUrl, timestamp FROM videos order by timestamp desc";
        $stmt = $this->con->prepare($query);
        $stmt->execute();
        $stmt->bind_result($videoId,$userId,$videoTitle,$videoDesc,$videoImage,$videoUrl,$timestamp);
        while ($stmt->fetch()) 
        {
            $video = array();
            $video['videoId'] = $videoId;
            $video['userId'] = $userId;
            $video['videoTitle'] = $videoTitle;
            $video['videoDesc'] = $videoDesc;
            $video['videoImage'] = $videoImage;
            $video['videoUrl'] = $videoUrl;
            $video['timestamp'] = $timestamp;
            array_push($videos, $video);
        }
        foreach ($videos as $videoList) 
        {   
            $feed = array();
            $users = array();
            $userId = $this->getUserId();
            $id = $videoList['userId'];
            $users = $this->getUserById($id);
            $video['videoId']        =    $videoList['videoId'];
            $video['userId']         =    $videoList['userId'];
            $video['userName']       =    $users['name'];
            $video['userImage']      =    $users['image'];
            $video['userUsername']   =    $users['username'];
            $video['videoTitle']     =    $videoList['videoTitle'];
            $video['videoDesc']      =    $videoList['videoDesc'];
            $video['videoImage']     =    $videoList['videoImage'];
            $video['videoUrl']       =    $videoList['videoUrl'];
            $video['timestamp']      =    $videoList['timestamp'];
            // $video['liked']          =    $this->checkFeedLike($userId,$feedList['feedId']);
            // $video['feedLikes']      =    $this->getLikesCountByFeedId($feedList['feedId']);
            // $video['feedComments']   =    $this->getCommentsCountByFeedId($feedList['feedId']);
            array_push($videosData, $video);
        }
        return $videosData;
    }


    function getVideoById($videoId)
    {
        $feed = array();
        $feeds = array(); 
        $feedsData = array();
        $query = "SELECT videoId, userId, videoTitle, videoDesc,videoImage, videoUrl, timestamp FROM videos WHERE videosId=? order by timestamp desc";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$videoId);
        $stmt->execute();
        $stmt->bind_result($videoId,$userId,$videoTitle,$videoDesc,$videoImage,$videoUrl,$timestamp);
        $stmt->fetch();
        $video = array();
        $video['videoId'] = $videoId;
        $video['userId'] = $userId;
        $video['videoTitle'] = $videoTitle;
        $video['videoDesc'] = $videoDesc;
        $video['videoImage'] = $videoImage;
        $video['videoUrl'] = $videoUrl;
        $video['timestamp'] = $timestamp;
        return $video;
    }

    function checkFeedLike($userId,$feedId)
    {
        $query = "SELECT likeId FROM likes WHERE userId=? AND feedId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$userId,$feedId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows()>0)
            return true;
        else
            return false;
    }

    function getImagesByUserId($userId)
    {
        $images = array();
        $query = "SELECT feedId,feedImage FROM feeds WHERE userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$userId);
        $stmt->execute();
        $stmt->bind_result($feedImage,$image);
        if ($stmt->num_rows>0) 
        {
            while ($stmt->fetch()) 
            {
                if (!empty($image)) 
                {
                    $image['feedId'] = $feedImage;
                    $image['feedImage'] = WEBSITE_DOMAIN.$image;
                }
                array_push($images, $image);
            }
            return $images;
        }
    }

    function checkCommentLike($userId,$commentId)
    {
        $query = "SELECT commentLikeId FROM comments_likes WHERE userId=? AND commentId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$userId,$commentId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows()>0)
            return true;
        else
            return false;
    }

    function isAlreadyFriend($tokenId,$id)
    {
        $query = "SELECT friendsId FROM friends WHERE userOne=? AND userTwo=? or userOne=? AND userTwo=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ssss",$tokenId,$id,$id,$tokenId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows()==1)
            return true;
        else
            return false;
    }

    function isFriendRequestAlreadySent($tokenId,$userId)
    {
        $query = "SELECT friendRequestId FROM friendrequests WHERE senderId=? AND receiverId=? OR senderId=? AND receiverId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ssss",$tokenId,$userId,$userId,$tokenId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows()==1)
            return true;
        
        else
            return false;
    }


    function makeFriendRequest($tokenId,$userId)
    {
        $query = "INSERT INTO friendrequests (senderId,receiverId) VALUES(?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$tokenId,$userId);
        if($stmt->execute())
            return true;
        else
            return false;
    }

    function cancelFriendRequest($tokenId,$userId)
    {
        $query = "DELETE FROM friendrequests WHERE senderId=? AND receiverId=? OR senderId=? AND receiverId=?";
        $stmt =  $this->con->prepare($query);
        $stmt->bind_param("ssss",$tokenId,$userId,$userId,$tokenId);
        if ($stmt->execute())
            return true;
        else
            return false;
    }

    function acceptFriendRequest($tokenId,$userId)
    {
        $deleteQuery = "DELETE FROM friendrequests WHERE senderId=? AND receiverId=?";
        $deleteStmt =  $this->con->prepare($deleteQuery);
        $deleteStmt->bind_param("ss",$userId,$tokenId);
        if ($deleteStmt->execute()) 
        {
            $query = "INSERT INTO friends (userOne,userTwo) VALUES(?,?)";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param("ss",$tokenId,$userId);
            if ($stmt->execute())
                return true;
            else
                return false;
            return false;
        }
        else
            return false;
    }

    function deleteFriend($tokenId,$userId)
    {
        $query = "DELETE FROM friends WHERE userOne=? AND userTwo=? OR userOne=? AND userTwo=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ssss",$tokenId,$userId,$userId,$tokenId);
        if ($stmt->execute())
            return true;
        else
            return false;
    }

    function getFriendshipStatus($tokenId,$userId)
    {
        if (!$this->isAlreadyFriend($tokenId,$userId)) 
        {
            if ($this->isFriendRequestAlreadySent($tokenId,$userId)) 
            {
                if ($this->isIAmTheFriendRequestSender($tokenId,$userId))
                    return FRIEND_REQUEST_SENDER;
                else if ($this->isIAmTheFriendRequestReceiver($tokenId,$userId))
                    return FRIEND_REQUEST_RECEIVER;
            }
            else
                return NOT_A_FRIEND;
        }
        else
            return ALREADY_FRIEND;
    }

    function getFriendsCountById($userId)
    {
        $query = "SELECT * FROM friends WHERE userOne=? OR userTwo=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$userId,$userId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows;
    }

    function isIAmTheFriendRequestSender($tokenId,$userId)
    {
        $query = "SELECT friendRequestId FROM friendrequests WHERE senderId=? AND receiverId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$tokenId,$userId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows()==1)
            return true;
        else
            return false;
    }

    function isIAmTheFriendRequestReceiver($tokenId,$userId)
    {
        $query = "SELECT friendRequestId FROM friendrequests WHERE senderId=? AND receiverId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$userId,$tokenId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows()==1)
            return true;
        else
            return false;
    }
    

    function getFeedById($feedId)
    {
        $feed = array();
        $feeds = array(); 
        if ($this->isAlreadyFriend($this->getUserId(),$this->getFeedAuthorIdByFeedId($feedId)))
            $feedPrivacy = implode(',',array('1','2'));
        else
            $feedPrivacy = '1';
        if($this->isUserAlreadyBlocked($this->getUserId(),$this->getFeedAuthorIdByFeedId($feedId)))
        {
            return $feed;
        }
        $feedsData = array();
        $query = "SELECT feedId, userId, feedContent, feedImage,feedVideo,feedThumbnail,feedType, timestamp FROM feeds WHERE feedId=? AND feedPrivacy IN ($feedPrivacy)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$feedId);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id,$userId,$content,$image,$feedVideo,$feedThumbnail,$feedType,$timestamp);
        $stmt->fetch();
        $feed = array();
        if ($stmt->num_rows()>0) 
        {
            $feed['feedId'] = $id;
            $feed['feedContent'] = $content;
            $feed['feedType'] =  $feedType;
            if ($feedType=="video")
            {
                $feed['feedVideo'] = WEBSITE_DOMAIN.$feedVideo;
                $feed['feedThumbnail'] = WEBSITE_DOMAIN.$feedThumbnail;
            }
            else if ($feedType=="image")
                $feed['feedImage'] = WEBSITE_DOMAIN.$image;
            $feed['feedTimestamp'] = $timestamp;
            $feed['userId'] = $userId;
        }
        return $feed;
    }


    function likeFeed($feedId, $userId)
    {
        $query = "INSERT INTO likes (feedId,userId) VALUES (?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$feedId,$userId);
        if ($stmt->execute())
            return FEED_LIKED;
        else
            return FEED_LIKE_FAILED;
    }

    function reportFeed($feedId, $userId)
    {
        $query = "INSERT INTO reports (feedId,userId) VALUES (?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$feedId,$userId);
        if ($stmt->execute())
            return true;
        else
            return false;
    }

    function likeFeedComment($commentId, $userId)
    {
        $query = "INSERT INTO comments_likes (commentId,userId) VALUES (?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$commentId,$userId);
        if ($stmt->execute())
            return COMMENT_LIKED;
        else
            return COMMENT_LIKED_FAILED;
    }

    function unlikeFeedComment($commentId, $userId)
    {
        $query = "DELETE FROM comments_likes WHERE commentId=? AND userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$commentId,$userId);
        if ($stmt->execute())
            return COMMENT_UNLIKED;
        else
            return COMMENT_UNLIKE_FAILED;
    }

    function unlikeFeed($feedId, $userId)
    {
        $query = "DELETE FROM likes WHERE feedId=? AND userId =?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$feedId,$userId);
        if ($stmt->execute())
            return FEED_UNLIKED;
        else
            return FEED_UNLIKE_FAILED;
    }

    function addFeedComment($feedId, $feedComment, $userId)
    {
        $query = "INSERT INTO comments (feedId,feedComment,userId) VALUES (?,?,?)";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("sss",$feedId,$feedComment,$userId);
        if ($stmt->execute())
            return FEED_COMMENT_ADDED;
        else
            return FEED_COMMENT_ADD_FAILED;
    }

    function deleteFeedComment($commentId, $userId)
    {
        $query = "DELETE FROM comments WHERE commentId = ? AND userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$commentId,$userId);
        if ($stmt->execute())
            return FEED_COMMENT_DELETED;
        else
            return FEED_COMMENT_DELETE_FAILED;
    }


    function getFeedsByUserId($userId)
    {
        $feed = array();
        $feeds = array(); 
        $feedsData = array();
        if ($this->isAlreadyFriend($this->getUserId(),$userId))
            $feedPrivacy = implode(',',array('1','2'));
        else if ($userId===$this->getUserId())
            $feedPrivacy = implode(',',array('1','2','3'));
        else
            $feedPrivacy = '1';
        if ($this->isUserAlreadyBlocked($this->getUserId(),$userId))
            return $feedsData;
        $query = "SELECT feedId, userId, feedContent, feedImage, feedVideo, feedThumbnail, feedType, timestamp FROM feeds WHERE userId=? AND feedPrivacy IN ($feedPrivacy) order by timestamp desc";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$userId);
        $stmt->execute();
        $stmt->bind_result($id,$userId,$content,$image,$feedVideo,$feedThumbnail,$feedType,$timestamp);
        while ($stmt->fetch()) 
        {
            $feed = array();
            $feed['feedId'] = $id;
            $feed['feedContent'] = $content;
            $feed['feedImage'] = WEBSITE_DOMAIN.$image;
            $feed['feedTimestamp'] = $timestamp;
            $feed['userId'] = $userId;
            $feed['feedVideo'] = $feedVideo;
            $feed['feedThumbnail'] = $feedThumbnail;
            $feed['feedType'] = $feedType;
            array_push($feeds, $feed);
        }
        foreach ($feeds as $feedList) 
        {   
            $feed = array();
            $users = array();
            $users = $this->getUserById($userId);
            $feed['userId']         =    $users['id'];
            $feed['userName']       =    $users['name'];
            $feed['userUsername']   =    $users['username'];
            $feed['userImage']      =    $users['image'];
            $feed['userStatus']   =    $users['status'];
            $feed['feedId']         =    $feedList['feedId'];
            $feed['liked']          =    $this->checkFeedLike($userId,$feedList['feedId']);
            $feed['feedLikes']      =    $this->getLikesCountByFeedId($feedList['feedId']);
            $feed['feedComments']   =    $this->getCommentsCountByFeedId($feedList['feedId']);
            $feed['feedType']       =    $feedList['feedType'];
            if ($feed['feedType'] == "video") 
            {
                $feed['feedVideo'] = WEBSITE_DOMAIN.$feedList['feedVideo'];
                $feed['feedThumbnail'] = WEBSITE_DOMAIN.$feedList['feedThumbnail'];
            }
            else if ($feed['feedType']=="image")
                $feed['feedImage']      =    WEBSITE_DOMAIN.$feedList['feedImage'];
            $feed['feedContent']    =    $feedList['feedContent'];
            $feed['feedTimestamp']  =    $feedList['feedTimestamp'];
            array_push($feedsData, $feed);
        }
        return $feedsData;
    }

    // function getAllFriendsId($userId)
    // {
    //     $friends = array(); 
    //     $query = "SELECT userOne, userTwo  FROM friends WHERE userOne=? or userTwo=? order by timestamp desc";
    //     $stmt = $this->con->prepare($query);
    //     $stmt->bind_param("ss",$userId,$userId);
    //     $stmt->execute();
    //     $stmt->bind_result($userOne,$userTwo);
    //     while ($stmt->fetch()) 
    //     {
    //         if ($userOne!=$userId) 
    //         {
    //             if ($userTwo!=$userId) {
    //                 $friendsId = $userOne;
    //             }
    //             $friendsId = $userTwo;
    //         }
    //         array_push($friends, $friendsId);
    //     }
    //     array_push($friends, $userId);
    //     return $friends;
    // }

    function getAllFriendsId($userId)
    {
        $friends = array(); 
        $query = "SELECT userOne, userTwo  FROM friends WHERE userOne=? or userTwo=? order by timestamp desc";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$userId,$userId);
        $stmt->execute();
        $stmt->bind_result($userOne,$userTwo);
        while ($stmt->fetch()) 
        {
            if ($userOne!=$userId) {
                $friendsId = $userOne;
            }
            else if ($userTwo !=$userId) {
                $friendsId = $userTwo;
            }
            array_push($friends, $friendsId);
        }
        // array_push($friends, $userId);
        return $friends;
    }

    function getFriendsByUserId($userId)
    {
        $friends = array(); 
        $feedsData = array();
        $users = array();
        $usersData = array();
        $query = "SELECT friendsId, userOne, userTwo FROM friends WHERE userOne=? or userTwo=? order by timestamp desc";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$userId,$userId);
        $stmt->execute();
        $stmt->bind_result($friendsId,$userOne,$userTwo);
        while ($stmt->fetch()) 
        {
            $friend = array();
            $friend['friendsId'] = $friendsId;
            $friend['userOne'] = $userOne;
            $friend['userTwo'] = $userTwo;
            array_push($friends, $friend);
        }
        foreach ($friends as $friendList) 
        {   

            if ($friendList['userOne']!=$userId)
                $user = $this->getUserById($friendList['userOne']);
            if ($friendList['userTwo']!=$userId)
                $user = $this->getUserById($friendList['userTwo']);
            array_push($users, $user);
        }
        foreach ($users as $user) 
        {
            $tokenId = $this->getUserId();
            $result = $this->getFriendshipStatus($tokenId,$user['id']);
            if ($result==NOT_A_FRIEND)
                $friendshipStatus = 0;
            else if ($result==ALREADY_FRIEND)
                $friendshipStatus = 1;
            else if ($result==FRIEND_REQUEST_SENDER)
                $friendshipStatus = 2;
            else if ($result==FRIEND_REQUEST_RECEIVER)
                $friendshipStatus = 3;
            $user['friendshipStatus'] = $friendshipStatus;
            array_push($usersData, $user);
        }
        return $usersData;
    }

    function getUserById($id)
    {

        $query = "SELECT id,name,username,email,bio,image,status FROM users WHERE id=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$id);
        $stmt->execute();
        $stmt->bind_result($id,$name,$username,$email,$bio,$image,$status);
        $stmt->fetch();
        $user['id'] = $id;
        $user['name'] = $name;
        $user['username'] = $username;
        $user['email'] = $email;
        $user['bio'] = $bio;
        if (empty($image))
            $image = DEFAULT_USER_IMAGE;
        $user['image'] = WEBSITE_DOMAIN.$image;

        $user['status'] = $status;
        return $user;
    }

    function getUserIdByUsername($username)
    {
        $query = "SELECT id FROM users WHERE username=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$username);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        return $id;
    }

    function getVideosIdByVideoId($videoId)
    {
        $query = "SELECT videosId FROM videos WHERE videoId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$videoId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        return $id;
    }

    function getLikesCountByFeedId($feedId)
    {
        $query = "SELECT * FROM likes WHERE feedId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$feedId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows;
    }

    function getCommentLikesCountByCommentId($commentId)
    {
        $query = "SELECT * FROM comments_likes WHERE commentId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$commentId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows;
    }

    function getFeedsCountById($userId)
    {
        $query = "SELECT * FROM feeds WHERE userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$userId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows;
    }


    function getCommentsCountByFeedId($feedId)
    {
        $query = "SELECT * FROM comments WHERE feedId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("s",$feedId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows;
    }

    function uploadImage($image)
    {
        $imageUrl ="";
        if ($image!=null) 
        {
            $imageName = $image->getClientFilename();
            $image = $image->file;
            $targetDir = "uploads/";
            // $targetFile = $targetDir.uniqid().'.'.pathinfo($imageName,PATHINFO_EXTENSION);
            $targetFile = $targetDir.uniqid().'.'.pathinfo($imageName,PATHINFO_EXTENSION);
            if (move_uploaded_file($image,$targetFile))
                $imageUrl = $targetFile;
        }
        return $imageUrl;
    }

    function uploadFeedVideo($video)
    {
        $videoUrl ="";
        if ($video!=null) 
        {
            $videoName = $video->getClientFilename();
            $image = $video->file;
            $targetDir = "uploads/videos/";
            $targetFile = $targetDir.uniqid().'.'.pathinfo($videoName,PATHINFO_EXTENSION);
            if (move_uploaded_file($image,$targetFile))
                $videoUrl = $targetFile;
        }
        return $videoUrl;
    }

    function uploadThumbnail($image)
    {
        $imageUrl ="";
        if ($image!=null) 
        {
            $imageName = $image->getClientFilename();
            $image = $image->file;
            $targetDir = "uploads/thumbnail/";
            $targetFile = $targetDir.uniqid().'.'.pathinfo($imageName,PATHINFO_EXTENSION);
            if (move_uploaded_file($image,$targetFile))
                $imageUrl = $targetFile;
        }
        return $imageUrl;
    }

    function uploadVideo($video)
    {
        $videoUrl ="";
        if ($video!=null) 
        {
            $videoName = $video->getClientFilename();
            $video = $video->file;
            $targetDir = "uploads/videos/";
            $uniqid = uniqid();
            $this->setVideoId($uniqid);
            $targetFile = $targetDir.$uniqid.'.'.pathinfo($videoName,PATHINFO_EXTENSION);
            if (move_uploaded_file($video,$targetFile))
                $videoUrl = $targetFile;
        }
        return $videoUrl;
    }

    function uploadProfileImage($id,$image)
    {
        $imageName = $image->getClientFilename();
        $image = $image->file;
        if($image!=null)
        {
            $targetDir = "uploads/";
            $targetFile = $targetDir.uniqid().'.'.pathinfo($imageName, PATHINFO_EXTENSION);
            if(move_uploaded_file($image,$targetFile))
            {
                $query = "UPDATE users set image=? WHERE id=? ";
                $stmt = $this->con->prepare($query);
                $stmt->bind_param('ss',$targetFile,$id);
                if($stmt->execute())
                    return IMAGE_UPLOADED;
                else
                    return IMAGE_UPLOADE_FAILED;
            }
            else
                return IMAGE_UPLOADE_FAILED;
        }
        else
            return IMAGE_NOT_SELECTED;
    }

    function updatePassword($id,$password, $newPassword)
    {

        $hashPass = $this->getPasswordById($id);
        if(password_verify($password,$hashPass))
        {
            $newHashPassword = password_hash($newPassword,PASSWORD_DEFAULT);
            $query = "UPDATE users SET password=? WHERE id=?";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param('ss',$newHashPassword,$id);
            if($stmt->execute())
                return PASSWORD_CHANGED;
            else
                return PASSWORD_CHANGE_FAILED;
        }
        else
            return PASSWORD_WRONG;  
    }

    function forgotPassword($email)
    {
        $result = array();
        if($this->isEmailValid($email))
        {
            if($this->isEmailExist($email))
            {
                if($this->isEmailVerified($email))
                {
                    $code = rand(100000,999999);
                    $name = $this->getNameByEmail($email);
                    if($this->updateCode($email,$code))
                        return CODE_UPDATED;
                    else
                        return CODE_UPDATE_FAILED;
                }
                else
                    return EMAIL_NOT_VERIFIED;
            }
            else
                return USER_NOT_FOUND;
        }
        else
            return EMAIL_NOT_VALID;
    }

    function resetPassword($email,$code,$newPassword)
    {
        if($this->isEmailValid($email))
        {
            if($this->isEmailExist($email))
            {
                if($this->isEmailVerified($email))
                {
                    $hashCode = decrypt($this->getCodeByEmail($email));
                    if($code==$hashCode)
                    {
                        $hashPass = password_hash($newPassword,PASSWORD_DEFAULT);
                        $query = "UPDATE users SET password=? WHERE email=?";
                        $stmt = $this->con->prepare($query);
                        $stmt->bind_param('ss',$hashPass,$email);
                        if($stmt->execute())
                        {
                            $randCode = password_hash(rand(100000,999999),PASSWORD_DEFAULT);
                            $this->updateCode($email,$randCode);
                            return PASSWORD_RESET;
                        }
                        return PASSWORD_RESET_FAILED;
                    } 
                    return CODE_WRONG;
                }
                return EMAIL_NOT_VERIFIED;
            }
            return USER_NOT_FOUND;
        }
        return EMAIL_NOT_VALID;
    }

    function sendEmailVerificationAgain($email)
    {
        $result = array();
        if($this->isEmailValid($email))
        {
            if($this->isEmailExist($email))
            {
                if(!$this->isEmailVerified($email))
                {
                    $code = $this->getCodeByEmail($email);
                    $name = $this->getNameByEmail($email);
                    $result['code'] = $code;
                    $result['email'] = $email;
                    $result['name'] = $name;
                    return SEND_CODE;
                }
                return EMAIL_ALREADY_VERIFIED;
            }
            return USER_NOT_FOUND;
        }
        return EMAIL_NOT_VALID;
    }

    function updateCode($email,$code)
    {
        $hashCode = encrypt($code);
        $query = "UPDATE users SET code=? WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ss',$hashCode,$email);
        if($stmt->execute())
            return true;
        else
            return false;
    }

    function verfiyEmail($email,$code)
    {
        $result = array();
        if($this->isEmailExist($email))
        {
            $dbCode = $this->getCodeByEmail($email);
            if($dbCode==$code)
            { 
                if(!$this->isEmailVerified($email))
                {
                    $resp = $this->setEmailIsVerfied($email);
                    if($resp)
                        return EMAIL_VERIFIED;
                    else
                        return EMAIL_NOT_VERIFIED;
                }
                else
                    return EMAIL_ALREADY_VERIFIED;
            }
            else
                return INVALID_VERFICATION_CODE;
        }
        else
            return INVAILID_USER;
    }

    function isEmailExist($email)
    {
        $query = "SELECT id FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0 ;
    }

    function isFeedExist($feedId)
    {
        $query = "SELECT feedId FROM feeds WHERE feedId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$feedId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0 ;
    }

    function isVerificationRequestAlreadySubmitted($userId)
    {
        $status = 0;
        $query = "SELECT requestId FROM requests WHERE userId=? AND status=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ss',$userId,$status);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows>0)
             return true;
        else
            return false;
    }

    function isFeedAuthor($feedId,$userId)
    {
        $query = "SELECT feedId FROM feeds WHERE feedId=? AND userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ss',$feedId,$userId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0 ;
    }

    function isCommentAuthor($commnetId,$userId)
    {
        $query = "SELECT feedId FROM comments WHERE commentId=? AND userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("ss",$commnetId,$userId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0;
    }

    function isCommentExist($commnetId)
    {
        $query = "SELECT commentId FROM comments WHERE commentId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$commnetId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0 ;
    }

    function isFeedReported($feedId,$userId)
    {
        $query = "SELECT reportId FROM reports WHERE feedId=? AND userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ss',$feedId,$userId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0 ;
    }

    function isFeedLiked($feedId,$userId)
    {
        $query = "SELECT likeId FROM likes WHERE feedId=? AND userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ss',$feedId,$userId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0 ;
    }

    function isCommentLiked($commentId,$userId)
    {
        $query = "SELECT commentId FROM comments_likes WHERE commentId=? AND userId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ss',$commentId,$userId);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0 ;
    }

    function isUsernameExist($username)
    {
        $query = "SELECT id FROM users WHERE username=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$username);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows>0 ;
    }

    function isEmailVerified($email)
    {
        $query = "SELECT verified FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->bind_result($verified);
        $stmt->fetch();
        return $verified;
    }

    function getPasswordByEmail($email)
    {
        $query = "SELECT password FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->bind_result($password);
        $stmt->fetch();
        return $password;
    }

    function getImageById($userId)
    {
        $query = "SELECT image FROM users WHERE id=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$userId);
        $stmt->execute();
        $stmt->bind_result($image);
        $stmt->fetch();
        return WEBSITE_DOMAIN.$image;
    }

    function getImageByFeedId($feedId)
    {
        $query = "SELECT feedImage FROM feeds WHERE feedId=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$feedId);
        $stmt->execute();
        $stmt->bind_result($image);
        $stmt->fetch();
        return WEBSITE_DOMAIN.$image;
    }


    function getPasswordById($id)
    {
        $query = "SELECT password FROM users WHERE id=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$id);
        $stmt->execute();
        $stmt->bind_result($password);
        $stmt->fetch();
        return $password;
    }

    function getUsers($tokenId)
    {

        $blocked = array_merge($this->getUserIdWhoBlockedMe($this->getUserId()),$this->getUsersWhichIHaveBlocked($this->getUserId()));
        if (empty($blocked))
            $url = "SELECT id,name,username,email,image,status FROM users WHERE id !=? AND verified != ?";
        else
        {
            $blocked = implode(',', $blocked);
            $url = "SELECT id,name,username,email,image,status FROM users WHERE id !=? AND verified != ? AND id NOT IN ($blocked)";
        }
        $stmt = $this->con->prepare($url);
        $verified = "0";
        $stmt->bind_param("ss",$tokenId,$verified);
        $stmt->execute();
        $stmt->bind_result($id,$name,$username,$email,$image,$status);
        $users = array();
        $usersData = array();
        while ($stmt->fetch()) {
            $user = array();
            $user['id'] = $id;
            $user['name'] = $name;
            $user['username'] = $username;
            $user['email'] = $email;
            if (empty($image))
                $image = DEFAULT_USER_IMAGE;
            $user['image'] = WEBSITE_DOMAIN.$image;
            $user['status'] = $status;
            array_push($users, $user);
        }
        foreach ($users as $user) 
        {   
            $result = $this->getFriendshipStatus($tokenId,$user['id']);
            if ($result==NOT_A_FRIEND)
                $friendshipStatus = 0;
            else if ($result==ALREADY_FRIEND)
                $friendshipStatus = 1;
            else if ($result==FRIEND_REQUEST_SENDER)
                $friendshipStatus = 2;
            else if ($result==FRIEND_REQUEST_RECEIVER)
                $friendshipStatus = 3;
            $user['friendshipStatus'] = $friendshipStatus;
            array_push($usersData, $user);
        }
        return $usersData;
    }


    function getBlockedUsers($tokenId)
    {

        $blocked = $this->getUsersWhichIHaveBlocked($this->getUserId());
        if (empty($blocked))
            return false;
        else
        {
            $blocked = implode(',', $blocked);
            $url = "SELECT id,name,username,email,image,status FROM users WHERE id !=? AND verified != ? AND id IN ($blocked)";
        }
        $stmt = $this->con->prepare($url);
        $verified = "0";
        $stmt->bind_param("ss",$tokenId,$verified);
        $stmt->execute();
        $stmt->bind_result($id,$name,$username,$email,$image,$status);
        $users = array();
        $usersData = array();
        while ($stmt->fetch()) {
            $user = array();
            $user['id'] = $id;
            $user['name'] = $name;
            $user['username'] = $username;
            $user['email'] = $email;
            if (empty($image))
                $image = DEFAULT_USER_IMAGE;
            $user['image'] = WEBSITE_DOMAIN.$image;
            $user['status'] = 2;
            array_push($users, $user);
        }
        foreach ($users as $user) 
        {   
            $result = $this->getFriendshipStatus($tokenId,$user['id']);
            if ($result==NOT_A_FRIEND)
                $friendshipStatus = 0;
            else if ($result==ALREADY_FRIEND)
                $friendshipStatus = 1;
            else if ($result==FRIEND_REQUEST_SENDER)
                $friendshipStatus = 2;
            else if ($result==FRIEND_REQUEST_RECEIVER)
                $friendshipStatus = 3;
            $user['friendshipStatus'] = $friendshipStatus;
            array_push($usersData, $user);
        }
        return $usersData;
    }

    function checkUserById($id)
    {
        $query = "SELECT email FROM users WHERE id=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows>0)
            return true;
        else
            return false;
    }

    function getEmailById($id)
    {
        $query = "SELECT email FROM users WHERE id=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$id);
        $stmt->execute();
        $stmt->bind_result($email);
        $stmt->fetch();
        return $email;
    }

    function getUsernameById($userId)
    {
        $query = "SELECT username FROM users WHERE id=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$userId);
        $stmt->execute();
        $stmt->bind_result($username);
        $stmt->fetch();
        return $username;
    }

    function getEmailByUsername($username)
    {
        $query = "SELECT email FROM users WHERE username=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$username);
        $stmt->execute();
        $stmt->bind_result($email);
        $stmt->fetch();
        return $email;
    }

    function getNameByEmail($email)
    {
        $query = "SELECT name FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->bind_result($name);
        $stmt->fetch();
        return $name;
    }

    function getCodeByEmail($email)
    {
        $query = "SELECT code FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->bind_result($code);
        $stmt->fetch();
        return $code;
    }

    function setEmailIsVerfied($email)
    {
        $status = 1;
        $query = "UPDATE users SET status=? WHERE email =?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ss',$status,$email);
        if($stmt->execute())
            return true;
        else
            return false;
    }

    function getUserByEmail($email)
    {
        $query = "SELECT id,name,username,email,bio,image,status FROM users WHERE email=?";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s',$email);
        $stmt->execute();
        $stmt->bind_result($id,$name,$username,$email,$bio,$image,$status);
        $stmt->fetch();
        $user = array();
        $user['id'] = $id;
        $user['name'] = $name;
        $user['username'] = $username;
        $user['email'] = $email;
        $user['bio'] = $bio;
        $user['status'] = $status;
        if (empty($image))
            $image = DEFAULT_USER_IMAGE;
        $user['image'] = WEBSITE_DOMAIN.$image;
        return $user;
    }

    function isEmailValid($email)
    {
        if(filter_var($email,FILTER_VALIDATE_EMAIL))
            return true;
        else
            return false;
    }

    function validateToken($token)
    {
        try 
        {
            $key = JWT_SECRET_KEY;
            $payload = JWT::decode($token,$key,['HS256']);
            $id = $payload->user_id;
            if ($this->checkUserById($id)) 
            {
                $this->setUserId($payload->user_id);
                return JWT_TOKEN_FINE;
            }
            return JWT_USER_NOT_FOUND;
        } 
        catch (Exception $e) 
        {
            return JWT_TOKEN_ERROR;    
        }
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
}