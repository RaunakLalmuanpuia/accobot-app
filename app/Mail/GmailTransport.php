<?php

namespace App\Mail;

use Google\Client;
use Google\Service\Gmail;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class GmailTransport extends AbstractTransport
{
    private Client $client;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $refreshToken,
    ) {
        parent::__construct();

        $this->client = new Client();
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->addScope(Gmail::GMAIL_SEND);
        $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $rawMessage = $message->toString();
        $encodedMessage = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

        $gmailMessage = new Gmail\Message();
        $gmailMessage->setRaw($encodedMessage);

        $service = new Gmail($this->client);
        $service->users_messages->send('me', $gmailMessage);
    }

    public function __toString(): string
    {
        return 'gmail';
    }
}
