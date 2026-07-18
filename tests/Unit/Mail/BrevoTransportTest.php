<?php

namespace Tests\Unit\Mail;

use App\Mail\Transports\BrevoTransport;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class BrevoTransportTest extends TestCase
{
    public function test_it_sends_a_symfony_email_through_the_brevo_api(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => '<brevo-message-id>'], 201),
        ]);

        $transport = new BrevoTransport(
            http: $this->app->make(Factory::class),
            apiKey: 'xkeysib-test',
        );

        $sentMessage = $transport->send(
            (new Email)
                ->from('Party Planner <no-reply@party-planner.cg>')
                ->to('Guest <guest@example.com>')
                ->subject('Votre code')
                ->text('Votre code est 1234')
                ->html('<p>Votre code est <strong>1234</strong></p>')
        );

        $this->assertSame('<brevo-message-id>', $sentMessage?->getMessageId());

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'xkeysib-test')
                && $request['sender']['email'] === 'no-reply@party-planner.cg'
                && $request['to'][0]['email'] === 'guest@example.com'
                && $request['subject'] === 'Votre code'
                && $request['textContent'] === 'Votre code est 1234';
        });
    }
}
