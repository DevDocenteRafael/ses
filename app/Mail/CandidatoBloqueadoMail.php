<?php

namespace App\Mail;

use App\Models\Candidato;
use App\Services\Candidatos\CandidatoStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class CandidatoBloqueadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public const LOGO_PATH = 'img/senac-logo.png';
    public const LOGO_CID = 'senac-logo@portal-senac-df';

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
                'logoCid' => 'cid:' . self::LOGO_CID,
                'motivo' => $inatividade
                    ? 'Inatividade na plataforma.'
                    : ($this->motivoPublico ?: 'Bloqueio administrativo do cadastro.'),
                'loginUrl' => $this->loginUrl ?: $this->urlLogin(),
                'supportContact' => config('portal.support_contact'),
            ])->withSymfonyMessage(function ($message): void {
                $message->addPart(
                    (new DataPart(new File(public_path(self::LOGO_PATH)), basename(self::LOGO_PATH), 'image/png'))
                        ->asInline()
                        ->setContentId(self::LOGO_CID)
                );
            });
    }

    private function urlLogin(): string
    {
        $base = rtrim((string) config('portal.frontend_url'), '/');
        $path = '/' . ltrim((string) config('portal.login_path', '/login'), '/');

        return Str::finish($base, '') . $path;
    }
}
