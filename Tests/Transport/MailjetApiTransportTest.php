<?php

declare(strict_types = 1);

namespace Duldsack\Component\Mailer\Bridge\Mailjet\Tests\Transport;

use Duldsack\Component\Mailer\Bridge\Mailjet\Transport\MailjetApiTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;
use function current;
use function Safe\json_decode;
use function Safe\json_encode;

class MailjetApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MailjetApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new MailjetApiTransport('public:private'),
                'mailjet+api://api.mailjet.com',
            ],
            [
                (new MailjetApiTransport('public:private'))->setHost('test.com'),
                'mailjet+api://test.com',
            ],
            [
                (new MailjetApiTransport('public:private'))->setHost('test.com'),
                'mailjet+api://test.com',
            ],
            [
                (new MailjetApiTransport('public:private'))->setHost('test.com')->setPort(99),
                'mailjet+api://test.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mailjet.com/v3.1/send', $url);
            $this->assertArrayHasKey('headers', $options);
            $this->assertArrayHasKey('body', $options);

            $headers = $options['headers'];
            $this->assertArrayHasKey(2, $headers);
            $this->assertStringContainsString('Authorization: Basic', $headers[2]);

            $body = json_decode($options['body'], true);

            $this->assertArrayHasKey('Messages', $body);
            $messages = $body['Messages'];

            $this->assertNotEmpty($messages);
            $message = current($messages);

            $this->assertArrayHasKey('From', $message);
            $this->assertArrayHasKey('Email', $message['From']);
            $this->assertArrayHasKey('Name', $message['From']);
            $this->assertSame('sender@test.com', $message['From']['Email']);
            $this->assertSame('Sender', $message['From']['Name']);

            $this->assertArrayHasKey('To', $message);
            $this->assertArrayHasKey(0, $message['To']);
            $this->assertArrayHasKey('Name', $message['To'][0]);
            $this->assertArrayHasKey('Email', $message['To'][0]);
            $this->assertSame('Receiver', $message['To'][0]['Name']);
            $this->assertSame('receiver@test.com', $message['To'][0]['Email']);

            $this->assertArrayHasKey('Subject', $message);
            $this->assertSame('Hello!', $message['Subject']);

            $this->assertArrayHasKey('TextPart', $message);
            $this->assertSame('Hello There!', $message['TextPart']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $transport = new MailjetApiTransport('public:private', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('receiver@test.com', 'Receiver'))
            ->from(new Address('sender@test.com', 'Sender'))
            ->text('Hello There!');

        $transport->send($mail);
    }

    public function testSendThrowsExceptionForErrorResponse()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_encode(
                [
                    'Messages' => [
                        'Status' => 'error',
                        'Errors' => [
                            [
                                'ErrorIdentifier' => 'f987008f-251a-4dff-8ffc-40f1583ad7bc',
                                'ErrorCode' => 'mj-0004',
                                'StatusCode' => 400,
                                'ErrorMessage' => 'Type mismatch. Expected type \"array of emails\".',
                                'ErrorRelatedTo' => ["HTMLPart", "TemplateID"],
                            ],
                        ],
                    ],
                ]
            );

            return new MockResponse($body, [
                'http_code' => 400,
            ]);
        });

        $transport = new MailjetApiTransport('public:private', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('receiver@test.com', 'Receiver'))
            ->from(new Address('sender@test.com', 'Sender'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email:');

        $transport->send($mail);
    }
}
