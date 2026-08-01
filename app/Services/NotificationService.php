<?php

namespace App\Services;

class NotificationService
{
    /**
     * @param String $emailAddress
     * @return String
    */
    public function sendMail(String $emailAddress): String
    {
        $randomArray = [
            'item1', 'item2',
            'items3',
        ];
        return "Mail sent";
    }

}
