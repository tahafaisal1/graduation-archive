<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $invitee,
        public User $inviter,
        public string $setupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'دعوة لإنشاء حسابك في منظومة أرشفة مشاريع التخرج',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-invitation',
            with: [
                'inviteeName' => $this->invitee->name,
                'inviterName' => $this->inviter->name,
                'setupUrl' => $this->setupUrl,
            ],
        );
    }
}
