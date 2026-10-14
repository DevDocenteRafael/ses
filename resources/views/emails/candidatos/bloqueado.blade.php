<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $atualizacaoPeriodica ? 'Atualização periódica do cadastro' : ($inatividade ? 'Seu cadastro está inativo' : 'Comunicado sobre seu cadastro') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6f8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="background:#004a8f;padding:24px;text-align:center;">
                            <img src="{{ $logoCid }}" alt="Senac" width="150" style="max-width:150px;height:auto;display:inline-block;border:0;outline:none;text-decoration:none;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px;">
                            <h1 style="margin:0 0 20px;font-size:24px;line-height:1.25;color:#004a8f;">Olá, {{ $nome }}!</h1>

                            @if ($atualizacaoPeriodica)
                                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Para continuar oferecendo oportunidades alinhadas ao seu perfil, o Senac DF realiza, a cada três meses, uma atualização das informações dos candidatos cadastrados no Portal de Empregabilidade.</p>
                                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Precisamos confirmar se você deseja manter seu cadastro ativo e se houve alguma mudança na sua situação profissional.</p>
                                <p style="font-size:16px;line-height:1.6;margin:0 0 24px;">Durante essa atualização, você responderá a oito perguntas sobre seu interesse em novas oportunidades, situação profissional, tipos de trabalho desejados, disponibilidade de horário e atualização dos seus dados.</p>

                                <div style="background:#f8fafc;border-left:4px solid #f7941d;padding:16px 18px;margin:22px 0;border-radius:8px;">
                                    <p style="margin:0 0 6px;font-size:14px;color:#4b5563;font-weight:bold;">Motivo:</p>
                                    <p style="margin:0;font-size:16px;line-height:1.5;color:#111827;">Atualização periódica do cadastro.</p>
                                </div>

                                <p style="font-size:16px;line-height:1.6;margin:0 0 24px;">A atualização é simples e rápida.</p>
                                <p style="font-size:16px;line-height:1.6;margin:0 0 24px;">Clique no botão abaixo para acessar o Portal de Empregabilidade Senac DF e confirmar suas informações.</p>
                            @elseif ($inatividade)
                                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Identificamos que seu cadastro no Portal de Empregabilidade Senac DF foi bloqueado por inatividade.</p>
                                <div style="background:#f8fafc;border-left:4px solid #f7941d;padding:16px 18px;margin:22px 0;border-radius:8px;">
                                    <p style="margin:0 0 6px;font-size:14px;color:#4b5563;font-weight:bold;">Motivo do bloqueio:</p>
                                    <p style="margin:0;font-size:16px;line-height:1.5;color:#111827;">{{ $motivo }}</p>
                                </div>
                                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Enquanto seu cadastro estiver inativo, seu perfil não ficará disponível para consulta pelas empresas participantes.</p>
                                <p style="font-size:16px;line-height:1.6;margin:0 0 24px;">Para solicitar a reativação do cadastro, acesse a plataforma com suas credenciais e responda ao questionário de atualização cadastral.</p>
                            @else
                                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Informamos que seu cadastro no Portal de Empregabilidade Senac DF foi bloqueado pela administração.</p>
                                <div style="background:#f8fafc;border-left:4px solid #f7941d;padding:16px 18px;margin:22px 0;border-radius:8px;">
                                    <p style="margin:0 0 6px;font-size:14px;color:#4b5563;font-weight:bold;">Motivo:</p>
                                    <p style="margin:0;font-size:16px;line-height:1.5;color:#111827;">{{ $motivo }}</p>
                                </div>
                                <p style="font-size:16px;line-height:1.6;margin:0 0 24px;">Para obter informações sobre a situação do seu cadastro e solicitar orientações, entre em contato com {{ $supportContact }}.</p>
                            @endif

                            <p style="text-align:center;margin:30px 0;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;background:#004a8f;color:#ffffff;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:8px;font-size:16px;">
                                    {{ $botaoTexto }}
                                </a>
                            </p>

                            @if ($atualizacaoPeriodica)
                                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Suas respostas nos ajudarão a manter seu perfil atualizado e melhorar a correspondência com as oportunidades disponíveis.</p>
                            @elseif ($inatividade)
                                <p style="font-size:14px;line-height:1.6;margin:0 0 20px;color:#4b5563;">Se você já reativou seu cadastro, desconsidere esta mensagem.</p>
                            @endif

                            <p style="font-size:16px;line-height:1.6;margin:24px 0 0;">Atenciosamente,<br>Equipe de Empregabilidade<br>Senac DF</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
