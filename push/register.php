<?php

namespace safaripush; 

$arrHeaders = getallheaders();
$request_uri = $_SERVER["REQUEST_URI"];

$userID = trim(str_replace('ApplePushNotifications', '', $arrHeaders['Authorization']));
$deviceID = trim(str_replace('/v1/devices/', '', substr($request_uri, 0, strpos($request_uri, '/registrations'))));

if($userID){
    if($deviceID){
        if($_SERVER['REQUEST_METHOD']){
            $objPush = new pushClass($userID);
            
            switch($_SERVER['REQUEST_METHOD']){
                case 'POST':
                    $objPush->registerDevice($deviceID);
                    break;
                case 'DELETE':
                    $objPush->deregisterDevice($deviceID);
                    break;
            }
        } else error_log('registrations.php Error: Missing request method' . json_encode($_SERVER));
    } else error_log('registrations.php Error: Missing $deviceID' . json_encode($request_uri));
} else error_log('registrations.php Error: Missing $userID' . json_encode($arrHeaders));
