<?php

declare(strict_types = 1);

namespace Duldsack\Component\Mailer\Bridge\Mailjet\Transport;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use function explode;
use function in_array;
use function sprintf;
use function strpos;

class MailjetApiTransport extends AbstractApiTransport
{
    /**
     * @var string[]
     */
    private array $key;

    private string $mailjetUri;

    private ?string $mailjetHost;

    public function __construct(
        string $key,
        string $mailjetUri,
        ?string $mailjetHost = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    ) {
        $this->assertKeyIsValid($key);

        $this->key = explode(':', $key);
        $this->mailjetUri = $mailjetUri;
        $this->mailjetHost = $mailjetHost;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf('mailjet+api://%s', $this->getHostUrl());
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $url = sprintf('https://%s%s', $this->getHostUrl(), $this->mailjetUri);
        $options = $this->createOptions($email, $envelope);

        $response = $this->client->request('POST', $url, $options);

        if ($this->isSuccessful($response)) {
            return $response;
        }

        $result = $response->toArray(false);

        if ($this->hasErrorMessage($result)) {
            throw new HttpTransportException(sprintf('Unable to send an email: %s (code %s).', $result['message'], $result['code']), $response);
        }

        throw new HttpTransportException(sprintf('Unable to send an email (code %s).', $result['code']), $response);
    }

    /**
     * @return mixed[]
     */
    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        $recipients = [];

        foreach ($envelope->getRecipients() as $recipient) {
            $type = 'To';

            if (in_array($recipient, $email->getBcc(), true)) {
                $type = 'Bcc';
            } elseif (in_array($recipient, $email->getCc(), true)) {
                $type = 'Cc';
            }

            $recipients[$type][] = [
                'Email' => $recipient->getAddress(),
                'Name' => $recipient->getName(),
            ];
        }

        return $recipients;
    }

    private function assertKeyIsValid(string $key): void
    {
        if (strpos($key, ':') === false) {
            throw new InvalidArgumentException('Mailjet key should have format like <public-api-key>:<private-api-key>');
        }
    }

    private function getHostUrl(): string
    {
        return ($this->host ?: $this->mailjetHost).($this->port ? ':'.$this->port : '');
    }

    /**
     * @return mixed[]
     */
    private function createOptions(Email $email, Envelope $envelope): array
    {
        return [
            'auth_basic' => $this->key,
            'json' => $this->createPayload($email, $envelope),
        ];
    }

    /**
     * @return mixed[]
     */
    private function createPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $envelope->getSender()->getAddress(),
                        'Name' => $envelope->getSender()->getName(),
                    ],
                    'Subject' => $email->getSubject(),
                    'TextPart' => $email->getTextBody(),
                    'HTMLPart' => $email->getHtmlBody(),
                ] + $this->getRecipients($email, $envelope),
            ],
        ];

        foreach ($email->getAttachments() as $attachment) {
            $payload['Attachments'][] = [
                'ContentType' => sprintf('%s/%s', $attachment->getMediaType(), $attachment->getMediaSubtype()),
                'Filename' => $attachment->getHeaders()['name'] ?? 'unknown',
                'ContentID' => $attachment->getContentId(),
                'Base64Content' => $attachment->bodyToString(),
            ];
        }

        return $payload;
    }

    private function isSuccessful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 400;
    }

    /**
     * @param mixed[]
     */
    private function hasErrorMessage(array $result): bool
    {
        $status = $result['status'] ?? false;

        return $status === 'error';
    }
}

