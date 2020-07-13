<?php

declare(strict_types = 1);

namespace Duldsack\Component\Mailer\Bridge\Mailjet\Tests\Transport;

use Duldsack\Component\Mailer\Bridge\Mailjet\Transport\MailjetApiTransport;
use Duldsack\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use function sprintf;

class MailjetApiTransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new MailjetTransportFactory($this->getDispatcher(), $this->getClient(), $this->getLogger());
    }

    public function supportsProvider(): iterable
    {
        yield [
            new Dsn('mailjet+api', 'default', self::USER, self::PASSWORD),
            true,
        ];

        yield [
            new Dsn('mailjet+api', 'test.com', self::USER, self::PASSWORD),
            true,
        ];
    }

    public function createProvider(): iterable
    {
        $dispatcher = $this->getDispatcher();
        $logger = $this->getLogger();
        $key = sprintf('%s:%s', self::USER, self::PASSWORD);

        yield [
            new Dsn('mailjet+api', 'default', self::USER, self::PASSWORD),
            new MailjetApiTransport($key, $this->getClient(), $dispatcher, $logger),
        ];

        yield [
            new Dsn('mailjet+api', 'example.com', self::USER, self::PASSWORD, 8080),
            (new MailjetApiTransport($key, $this->getClient(), $dispatcher, $logger))->setHost('example.com')->setPort(8080),
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('mailjet+foo', 'mailjet', self::USER, ''),
            'The "mailjet+foo" scheme is not supported; supported schemes for mailer "mailjet" are: "mailjet+api".',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('mailjet+api', 'default')];
    }
}

