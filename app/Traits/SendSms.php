<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;

trait SendSms
{
    public function sendSms($phone,$message)
    {
        $response = Http::post('https://www.bulksmsnigeria.com/api/v1/sms/create?api_token='.env('BULKSMS_TOKEN').'&to='.$phone.'&from=TRANSAVE&body='.$message.'&dnd=');

        return $response;
    }

    public function sendSms1($username, $password, $message, $mobiles, $sender)
    {
        try {

            //
            $postdata = http_build_query(
                array(
                    'username' => $username,
                    'password' => $password,
                    'message'  => $message,
                    'mobiles'  => $mobiles,
                    'sender'   => $sender,
                )
            );

            // prepare a http post request
            $opts = array(
                'http' =>
                array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postdata
                )
            );

            // craete a stream to communicate with betasms api
            $context  = stream_context_create($opts);

            //get result from communication
            $result = file_get_contents('http://login.betasms.com/api/', false, $context);

            // return result to client, this will return the appropriate respond code
            return $result;
        } catch (\Throwable $th) {
            throw $th;
            return null;
        }
    }
}
