<?php

namespace App\Mail\Transports;

use Illuminate\Http\Client\Factory as HttpFactory;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoTransport extends AbstractTransport
{
    public function __construct(
        protected HttpFactory $http,
        protected string $apiKey,
        protected string $endpoint = 'https://api.brevo.com/v3/smtp/email',
        protected int $timeout = 15,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'brevo+api://default';
    }

    protected function doSend(SentMessage $sentMessage): void
    {
        $email = $sentMessage->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('Brevo transport only supports Symfony Email messages.');
        }

        if ($this->apiKey === '') {
            throw new TransportException('BREVO_API_KEY is not configured.');
        }

        $from = $email->getFrom()[0] ?? null;

        if (! $from instanceof Address) {
            throw new TransportException('The email must have a sender address.');
        }

        $payload = array_filter([
            'sender' => $this->address($from),
            'to' => $this->addresses($email->getTo()),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'replyTo' => isset($email->getReplyTo()[0])
                ? $this->address($email->getReplyTo()[0])
                : null,
            'subject' => $email->getSubject() ?? '',
            'htmlContent' => $this->body($email->getHtmlBody()),
            'textContent' => $this->body($email->getTextBody()),
            'attachment' => $this->attachments($email),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        try {
            $response = $this->http
                ->withHeaders([
                    'api-key' => $this->apiKey,
                    'accept' => 'application/json',
                ])
                ->asJson()
                ->timeout($this->timeout)
                ->post($this->endpoint, $payload)
                ->throw();
        } catch (\Throwable $exception) {
            throw new TransportException(
                'Brevo API email delivery failed: '.$exception->getMessage(),
                0,
                $exception
            );
        }

        if ($messageId = $response->json('messageId')) {
            $sentMessage->setMessageId($messageId);
        }
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    protected function addresses(array $addresses): array
    {
        return array_map(fn (Address $address): array => $this->address($address), $addresses);
    }

    /**
     * @return array{email: string, name?: string}
     */
    protected function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName() ?: null,
        ], static fn (?string $value): bool => $value !== null);
    }

    protected function body(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return (string) $body;
    }

    /**
     * @return array<int, array{content: string, name: string}>
     */
    protected function attachments(Email $email): array
    {
        return array_map(static function ($attachment): array {
            $body = $attachment->getBody();

            if (is_resource($body)) {
                $body = stream_get_contents($body);
            }

            return [
                'content' => base64_encode((string) $body),
                'name' => $attachment->getFilename() ?: 'attachment',
            ];
        }, $email->getAttachments());
    }
}
