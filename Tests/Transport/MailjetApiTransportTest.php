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
use function json_decode;

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
                new MailjetApiTransport('public:private', '/v3.1/send', 'api.mailjet.com'),
                'mailjet+api://api.mailjet.com',
            ],
            [
                (new MailjetApiTransport('public:private', '/v3.1/send'))->setHost('test.com'),
                'mailjet+api://test.com',
            ],
            [
                (new MailjetApiTransport('public:private', '/v3.1/send', 'api.mailjet.com'))->setHost('test.com'),
                'mailjet+api://test.com',
            ],
            [
                (new MailjetApiTransport('public:private', '/v3.1/send', 'api.mailjet.com'))->setHost('test.com')->setPort(99),
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

        $transport = new MailjetApiTransport('public:private', '/v3.1/send', 'api.mailjet.com', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('receiver@test.com', 'Receiver'))
            ->from(new Address('sender@test.com', 'Sender'))
            ->text('Hello There!');

        $transport->send($mail);
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            return new MockResponse(json_encode(['status' => 'error', 'message' => 'i\'m a teapot', 'code' => 418]), [
                'http_code' => 418,
            ]);
        });

        $transport = new MailjetApiTransport('public:private', '/v3.1/send', 'api.mailjet.com', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('receiver@test.com', 'Receiver'))
            ->from(new Address('sender@test.com', 'Sender'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: i\'m a teapot (code 418).');

        $transport->send($mail);
    }
}
