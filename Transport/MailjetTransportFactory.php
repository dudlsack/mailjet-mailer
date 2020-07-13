<?php

declare(strict_types = 1);

namespace Duldsack\Component\Mailer\Bridge\Mailjet\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use function sprintf;

final class MailjetTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $key = sprintf('%s:%s', $this->getUser($dsn), $this->getPassword($dsn));

        if ('mailjet+api' === $dsn->getScheme()) {
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            return (new MailjetApiTransport($key, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'mailjet', $this->getSupportedSchemes());
    }

    /**
     * @return string[]
     */
    protected function getSupportedSchemes(): array
    {
        return ['mailjet+api'];
    }
}
