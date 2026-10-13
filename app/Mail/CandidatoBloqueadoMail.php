<?php

namespace App\Mail;

use App\Models\Candidato;
use App\Services\Candidatos\CandidatoStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class CandidatoBloqueadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Candidato $candidato,
        public string $tipoBloqueio,
        public ?string $motivoPublico = null,
        public ?string $loginUrl = null,
    ) {}

    public function build(): self
    {
        $inatividade = $this->tipoBloqueio === CandidatoStatusService::BLOQUEADO_POR_INATIVIDADE;
        $assunto = $inatividade
            ? 'Portal Senac DF — Seu cadastro está inativo'
            : 'Portal Senac DF — Comunicado sobre seu cadastro';

        return $this->subject($assunto)
            ->view('emails.candidatos.bloqueado')
            ->with([
                'nome' => $this->candidato->pessoa?->nome ?? 'candidato',
                'inatividade' => $inatividade,
                'motivo' => $inatividade
                    ? 'Inatividade na plataforma.'
                    : ($this->motivoPublico ?: 'Bloqueio administrativo do cadastro.'),
                'loginUrl' => $this->loginUrl ?: $this->urlLogin(),
                'supportContact' => config('portal.support_contact'),
            ]);
    }

    private function urlLogin(): string
    {
        $base = rtrim((string) config('portal.frontend_url'), '/');
        $path = '/' . ltrim((string) config('portal.login_path', '/login'), '/');

        return Str::finish($base, '') . $path;
    }
}
