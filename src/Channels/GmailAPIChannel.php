<?php
 
namespace App\Notifications;

use App\Http\Controllers\GoogleClientController;
use App\Models\GoogleToken;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class GmailAPIChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, GmailNotification $notification): void
    {
        $message = $notification->toGmail($notifiable);
        $email = "From: arjuntanwar900@gmail.com\r\n";
        $email .= "To: ". $notifiable->getEmailForPasswordReset() . "\r\n";
        $email .= "Subject: 29K Password Recovery\r\n";
        $email .= "Content-Type: text/html; charset='UTF-8'\r\n";
        $email .= "\r\n";
        $email .= $message->render();
        $googleClient = new GoogleClientController();
        $accessToken = $googleClient->getAccessToken();
        $base64Email = base64_encode($email);
        $body = [
            "raw" => $base64Email,
        ];
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ];
        Http::withHeaders($headers)->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', $body);
        return;
    }
}