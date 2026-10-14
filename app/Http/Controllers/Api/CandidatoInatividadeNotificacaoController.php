<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TesteIntegracaoPortalSenacMail;
use App\Models\Candidato;
use App\Models\CandidatoInatividadeCampanha;
use App\Services\Candidatos\CandidatoInatividadeManualNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CandidatoInatividadeNotificacaoController extends Controller
{
    public function contagem(Request $request, CandidatoInatividadeManualNotificationService $service)
    {
        $this->garantirAdministrativo($request);

        return response()->json($service->resumo());
    }

    public function global(Request $request, CandidatoInatividadeManualNotificationService $service)
    {
        $this->garantirNotificacoesManuaisHabilitadas();
        $admin = $this->garantirAdministrativo($request);
        $validated = $request->validate(['confirmacao' => ['required', 'accepted']]);
        $campanha = $service->iniciarEnvioGlobal($admin);

        return response()->json([
            'message' => 'Envio das notificações iniciado.',
            'campanha' => $campanha,
        ], 202);
    }

    public function campanha(Request $request, CandidatoInatividadeCampanha $campanha, CandidatoInatividadeManualNotificationService $service)
    {
        $this->garantirAdministrativo($request);

        return response()->json(['campanha' => $service->statusCampanha($campanha->fresh())]);
    }

    public function individual(Request $request, string $matricula, CandidatoInatividadeManualNotificationService $service)
    {
        $this->garantirNotificacoesManuaisHabilitadas();
        $admin = $this->garantirAdministrativo($request);
        $request->validate(['confirmacao' => ['required', 'accepted']]);
        $candidato = Candidato::query()->with('pessoa', 'contratacao')->findOrFail($matricula);
        $notificacao = $service->enviarIndividual($candidato, $admin);

        return response()->json([
            'message' => $notificacao->status === 'enviado' ? 'E-mail enviado com sucesso.' : 'E-mail encaminhado para envio.',
            'notificacao' => $notificacao,
        ]);
    }

    public function testeSmtp(Request $request)
    {
        $this->garantirNotificacoesManuaisHabilitadas();
        $this->garantirAdministrativo($request);
        Mail::to('teste@senac.test')->send(new TesteIntegracaoPortalSenacMail());

        return response()->json(['message' => 'E-mail de teste enviado para teste@senac.test.']);
    }

    private function garantirNotificacoesManuaisHabilitadas(): void
    {
        if (! filter_var(config('candidato.notificacoes_manuais_habilitadas', false), FILTER_VALIDATE_BOOL)) {
            abort(response()->json([
                'message' => 'O reenvio manual de notificações está temporariamente desabilitado.',
            ], 403));
        }
    }
}
