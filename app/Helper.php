<?php

use App\Models\Notification;

function push_notification($push_arr)
{
    $apiKey = "AAAAW4gumeY:APA91bHJR1UmDUoxEtrSQUM9In5TDW-nbISw0S_wMxcR9eNniPYn-ymZT6DzD_LfOm3b9z3E3V5qikrkjOTy0t-2vC1mM9NG97V6CbY98qrIGK4xnOnmtqhFvTAhdDKcuvO1fNHwV-yQ";
    $registrationIDs         = (array) $push_arr['device_token'];
    $message                 = array(
        "body"                 => $push_arr['description'],
        "title"                => $push_arr['title'],
        "notification_type" => $push_arr['type'],
        "other_id"          => $push_arr['record_id'],
        "sender_id"         => isset($push_arr['sender_id']) ? $push_arr['sender_id'] : null,
        "receiver_id"       => isset($push_arr['receiver_id']) ? $push_arr['receiver_id'] : null,

        'token' => isset($push_arr['token']) ? $push_arr['token'] : null,
        'room_id' => isset($push_arr['room_id']) ? $push_arr['room_id'] : null,
        'first_name' => isset($push_arr['first_name']) ? $push_arr['first_name'] : null,
        'last_name' => isset($push_arr['last_name']) ? $push_arr['last_name'] : null,
        'profile_image' => isset($push_arr['profile_image']) ? $push_arr['profile_image'] : null,

        "date"                => now(),
        'vibrate'           => 1,
        'sound'             => 1,
    );
    $url = 'https://fcm.googleapis.com/fcm/send';

    // if($push_arr->user_device == "ios"){
    //     $fields = array(
    //         'registration_ids'     =>  $registrationIDs,
    //         'notification'         =>  $message,
    //         'data'         =>  $message
    //     );
    // }else if($push_arr->user_device == "android"){
    $fields = array(
        'registration_ids'     =>  $registrationIDs,
        'notification'         =>  $message,
        'data'         =>  $message
    );
    // }

    $headers = array(
        'Authorization: key=' . $apiKey,
        'Content-Type: application/json'
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

function in_app_notification($data)
{
    $notification = new Notification();
    $notification->sender_id = $data['sender_id'];
    $notification->receiver_id = $data['receiver_id'];
    $notification->title = $data['title'];
    $notification->description = $data['description'];
    $notification->record_id = $data['record_id'];
    $notification->type = $data['type'];
    $notification->token = $data['token'] ?? null;
    $notification->room_id = $data['room_id'] ?? null;
    $notification->save();
}
