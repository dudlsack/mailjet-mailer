# mailjet-mailer
Symfony Mailjet Mailer Bridge 

At the moment only for api use. You are invited to add smtp usage etc. ;)

# Installation
Install via composer.
```
composer require dudlsack/mailjet-mailer
```

# Configuration
Configure DSN either in .env or as environment variable. Make sure you replaced $PUBLIC_KEY and $PRIVATE_KEY with your credentials.
```
MAILER_DSN=mailjet+api://$PUBLIC_KEY:$PRIVATE_KEY@api.mailjet.com
```

Tag transport factory in services.yaml.
```
Duldsack\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory:
    tags: ['mailer.transport_factory']
```
