<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TesteIntegracaoPortalSenacMail extends Mailable
{
    use Queueable, SerializesModels;

    public function build(): self
    {
        return $this->subject('Teste de integração — Portal Senac DF')
            ->html('<p>Teste isolado de integração SMTP do Portal Senac DF.</p>');
    }
}
