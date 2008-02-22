<?php
/**
  * Youtube API functions
  * Mario Mansour
  * http://www.tubepress.net/
  */
 
class youtubeService{
 
/**
  * A youtube dev id - http://youtube.com/my_profile_dev
  * @access private
  * @var integer|string
  */
private $devkey;
 
 
/**
  * Constructor - sets up youtube devid
  * @param string $devkey
  */
function youtubeService($devid){
    $this->devkey= $devid;
}
 
/**
  * Retrieves the public parts of a user profile.
  * @param string $user
  * @return string $results
  */
function users_get_profile($user)
{
    $functionName = "youtube.users.get_profile";
    $payload = array("user"=>$user);
    $results = $this->getRestResponse($functionName, $payload);
    return $results;
}
 
/**
  * Lists a user's favorite videos.
  * @param string $user
  * @return string $results
  */
function users_list_favorite_videos($user)
{
    $functionName = "youtube.users.list_favorite_videos";
    $payload = array("user"=>$user);
    $results = $this->getRestResponse($functionName, $payload);
    return $results;
}
 
/**
  * Lists a user's friends.
  * @param string $user
  * @return string $results
  */
function users_list_friends($user)
{
    $functionName = "youtube.users.list_friends";
    $payload = array("user"=>$user);
    $results = $this->getRestResponse($functionName, $payload);
    return $results;
}
 
/**
  * Displays the details for a video.
  * @param string $video_id
  * @return string $results
  */
function videos_get_details($video_id)
{
    $functionName = "youtube.videos.get_details";
    $payload = array("video_id"=>$video_id);
    $results = $this->getRestResponse($functionName, $payload);
    return $results;
}
 
/**
  * Lists all videos that have the specified tag.
  * @param string $tag
  * @param string $page
  * @param string $per_page
  * @return string $results
  */
function videos_list_by_tag($tag, $page, $per_page)
{
    $functionName = "youtube.videos.list_by_tag";
    $payload = array("tag"=>$tag,"page"=>$page,"per_page"=>$per_page);
    $results = $this->getRestResponse($functionName, $payload);
    return $results;
}
 
/**
  * Lists all videos that were uploaded by the specified user
  * @param string $user
  * @return string $results
  */
function videos_list_by_user($user)
{
    $functionName = "youtube.videos.list_by_user";
    $payload = array("user"=>$user);
    $results = $this->getRestResponse($functionName, $payload);
    return $results;
}
 
/**
  * Lists the most recent 25 videos that have been featured on the 
  * front page of the YouTube site.
  * @return string $results
  */
function videos_list_featured()
{
    $functionName = "youtube.videos.list_featured";
    $payload = "";
    $results = $this->getRestResponse($functionName, $payload);
    return $results;
}
 
/**
  * A helper wrapper for the videos.list_by_tag. 
  * @param string $tags
  * @param string $tags
  * @return array $videos
  */
function getVideos($tags= '', $numvids = 10){
    str_replace(" ","+",$tags);
    $videos = $this->videos_list_by_tag($tags, 1, $numvids);
    return $videos;
}
 
/**
  * Get a rest response from the youtube api. Takes a functionName.
  * and a array payload
  * @param string $functionName
  * @param string $payload
  * @return string $response
  */
function getRestResponse($functionName, $payload)
{
    $url = $this->buildRestUrl($functionName, $payload);
    $response = $this->doRestRequest($url);
    return $response;
}
 
/**
  * Does a REST request based on a specific url
  * @param string $request_url
  * @return string $xmlResults
  */
function doRestRequest($request_url)
{
    $results = file_get_contents($request_url);
    $xmlResults = $this->resultXML($results);
    return $xmlResults;
}
 
/**
  * Turns the XML results into an simpleXML array
  * @param string $results
  * @return string $xml
  */
function resultXML($results)
{
    $xml_array = $xml = new SimpleXMLElement($results);
    return $xml_array;
}
 
/**
  * Builds REST URL based on  payload and function
  * @param string $functionName
  * @param array $payload
  * @return string $url
  */
function buildRestUrl($functionName, $payload)
{
    if ($payload != ""){
        foreach ($payload as $name => $value){
            $payloadString .= $name.'='.$value.'&';
        }
    }
    $url = 'http://www.youtube.com/api2_rest?method='.$functionName.'&dev_id='.$this->devkey.'&'.$payloadString;
    return $url;
}
 
 
 
}

?>