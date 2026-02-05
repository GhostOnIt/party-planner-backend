<?php

namespace App\Mail;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;
    use \App\Mail\Concerns\EmbedsMailLogo;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Task $task,
        public int $daysUntilDue
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $urgency = $this->daysUntilDue === 0 ? '[URGENT] ' : '';

        return new Envelope(
            subject: "{$urgency}Rappel : Tâche due " . ($this->daysUntilDue === 0 ? "aujourd'hui" : "dans {$this->daysUntilDue} jour(s)"),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.task-reminder',
            with: [
                'task' => $this->task,
                'event' => $this->task->event,
                'daysUntilDue' => $this->daysUntilDue,
                'eventUrl' => route('events.tasks.index', $this->task->event_id),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
