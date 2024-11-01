<?php


namespace UNSProjectApp\Helpers;


class SessionHelper
{
    public static function getSessionId(){
        $session_id = wp_get_session_token();
        if(!empty($session_id)){
            return $session_id;
        }
        if(empty(session_id())){
            @session_start();
        }

        return session_id();
    }
}