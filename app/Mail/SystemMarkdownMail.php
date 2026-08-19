<?php

namespace App\Mail;

use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Correo genérico con plantilla Markdown y marca (colores de configuración).
 *
 * Uso: Mail::to($user)->send(new SystemMarkdownMail('Asunto', 'Título', ['línea 1', 'línea 2'], urlOpcional, textoBotónOpcional));
 */
class SystemMarkdownMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $bodyLines
     */
    public function __construct(
        public string $subjectLine,
        public string $heading,
        public array $bodyLines,
        public ?string $actionUrl = null,
        public ?string $actionText = null,
    ) {}

    public function build(): static
    {
        return $this->subject($this->subjectLine)
            ->markdown('emails.system', [
                'heading' => $this->heading,
                'bodyLines' => $this->bodyLines,
                'actionUrl' => $this->actionUrl,
                'actionText' => $this->actionText,
                'mailBranding' => MailBranding::data(),
            ]);
    }
}
