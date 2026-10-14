<?php

namespace Tests\Feature;

use App\Models\Administrativo;
use App\Models\Candidato;
use App\Models\Contratacao;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContratacaoCancelamentoLoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cancela_multiplas_contratacoes(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-lote@example.com');

        $c1 = $this->criarCandidato('200000001');
        $c2 = $this->criarCandidato('200000002');
        $e1 = $this->criarEmpresa('20000000000001');
        $e2 = $this->criarEmpresa('20000000000002');

        $contr1 = $this->registrarContratacao($c1, $e1);
        $contr2 = $this->registrarContratacao($c2, $e2);

        $response = $this->withToken($token)->patchJson('/api/contratacoes/cancelar-lote', [
            'ids' => [$contr1->id, $contr2->id],
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('results', $data);
        $this->assertCount(2, $data['results']);

        $this->assertSame(Contratacao::STATUS_CANCELADA, $contr1->refresh()->status);
        $this->assertSame(Contratacao::STATUS_CANCELADA, $contr2->refresh()->status);
    }

    public function test_usuario_nao_autorizado_recebe_forbidden(): void
    {
        [, $tokenEmpresa] = $this->criarEmpresaAutenticada('30000000000001');

        $c = $this->criarCandidato('300000001');
        $e = $this->criarEmpresa('30000000000002');
        $contr = $this->registrarContratacao($c, $e);

        $this->withToken($tokenEmpresa)->patchJson('/api/contratacoes/cancelar-lote', [
            'ids' => [$contr->id],
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertForbidden();
    }

    public function test_requisicao_sem_ids_e_rejeitada(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-lote2@example.com');

        $this->withToken($token)->patchJson('/api/contratacoes/cancelar-lote', [
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertStatus(422);
    }

    public function test_ids_invalidos_sao_tratados(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-lote3@example.com');

        $this->withToken($token)->patchJson('/api/contratacoes/cancelar-lote', [
            'ids' => [999999],
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertStatus(422);
    }

    public function test_ids_duplicados_nao_geram_cancelamentos_duplicados(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-lote4@example.com');

        $c = $this->criarCandidato('400000001');
        $e = $this->criarEmpresa('40000000000001');
        $contr = $this->registrarContratacao($c, $e);

        $response = $this->withToken($token)->patchJson('/api/contratacoes/cancelar-lote', [
            'ids' => [$contr->id, $contr->id],
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ]);

        $response->assertOk();
        $results = $response->json('results');
        $this->assertCount(2, $results);
        $statuses = array_column($results, 'status');
        $this->assertContains('cancelled', $statuses);
        $this->assertContains('already_cancelled', $statuses);
    }

    public function test_contratacao_ja_cancelada_nao_e_cancelada_novamente(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-lote5@example.com');

        $c = $this->criarCandidato('500000001');
        $e = $this->criarEmpresa('50000000000001');
        $contr = $this->registrarContratacao($c, $e);

        // cancelar individualmente
        $this->withToken($token)->patchJson("/api/contratacoes/{$contr->id}/cancelar", [
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertOk();

        // tentar cancelar no lote
        $response = $this->withToken($token)->patchJson('/api/contratacoes/cancelar-lote', [
            'ids' => [$contr->id],
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ]);

        $response->assertOk();
        $this->assertSame('already_cancelled', $response->json('results.0.status'));
    }

    public function test_motivo_ausente_impede_operacao(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-lote6@example.com');

        $c = $this->criarCandidato('600000001');
        $e = $this->criarEmpresa('60000000000001');
        $contr = $this->registrarContratacao($c, $e);

        $this->withToken($token)->patchJson('/api/contratacoes/cancelar-lote', [
            'ids' => [$contr->id],
        ])->assertStatus(422);
    }

    public function test_historico_individual_e_admin_registrado(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-lote7@example.com');

        $c = $this->criarCandidato('700000001');
        $e = $this->criarEmpresa('70000000000001');
        $contr = $this->registrarContratacao($c, $e);

        $this->withToken($token)->patchJson('/api/contratacoes/cancelar-lote', [
            'ids' => [$contr->id],
            'motivo_cancelamento' => 'Empresa selecionada incorretamente',
            'observacao_cancelamento' => 'Motivo de teste',
        ])->assertOk();

        $contr = $contr->refresh();
        $this->assertSame(Contratacao::STATUS_CANCELADA, $contr->status);
        $this->assertNotNull($contr->cancelado_em);
        $this->assertSame('Empresa selecionada incorretamente', $contr->motivo_cancelamento);
        $this->assertSame('Motivo de teste', $contr->observacao_cancelamento);

        $historico = $contr->historico_alteracoes;
        $this->assertIsArray($historico);
        $last = end($historico);
        $this->assertSame('cancelada', $last['acao']);
        $this->assertArrayHasKey('por_pessoa_id', $last);
    }

    public function test_bloqueios_existentes_sao_preservados(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-lote8@example.com');

        $c = $this->criarCandidato('800000001', status: false);
        $e = $this->criarEmpresa('80000000000001');
        $contr = $this->registrarContratacao($c, $e);

        $this->withToken($token)->patchJson('/api/contratacoes/cancelar-lote', [
            'ids' => [$contr->id],
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertOk();

        $this->assertFalse($c->refresh()->status);
    }

    public function test_status_contratado_considerando_outras_contratacoes(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-lote9@example.com');

        $c = $this->criarCandidato('900000001');
        $e1 = $this->criarEmpresa('90000000000001');
        $e2 = $this->criarEmpresa('90000000000002');

        $contr1 = $this->registrarContratacao($c, $e1);
        $contr2 = $this->registrarContratacao($c, $e2);

        // cancelar somente o primeiro
        $this->withToken($token)->patchJson('/api/contratacoes/cancelar-lote', [
            'ids' => [$contr1->id],
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertOk();

        $this->assertSame(Contratacao::STATUS_CANCELADA, $contr1->refresh()->status);
        $this->assertSame(Contratacao::STATUS_VIGENTE, $contr2->refresh()->status);
    }

    // Helpers copied from ContratacaoCancelamentoTest
    private function criarAdministrativoAutenticado(string $email): array
    {
        static $telefone = 62000000000;

        $pessoa = Pessoa::query()->create([
            'nome' => 'Admin Teste',
            'email' => $email,
            'senha' => Hash::make('secret'),
            'telefone' => (string) $telefone++,
            'data_cadastro' => now()->toDateString(),
        ]);
        $admin = Administrativo::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa]);

        return [$admin, $this->token($pessoa)];
    }

    private function criarEmpresaAutenticada(string $cnpj): array
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Empresa Autenticada',
            'email' => $cnpj . '@empresa.test',
            'senha' => Hash::make('secret'),
            'telefone' => (string) random_int(10000000000, 99999999999),
            'data_cadastro' => now()->toDateString(),
        ]);
        $empresa = Empresa::query()->create([
            'cnpj' => $cnpj,
            'razao_social' => 'Empresa Autenticada ' . $cnpj,
            'atividade_economica' => 'Comércio',
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa])->id_responsavel_contratual,
        ]);

        return [$empresa, $this->token($pessoa)];
    }

    private function criarCandidato(string $matricula, bool $status = true): Candidato
    {
        static $telefone = 61900000000;

        $pessoa = Pessoa::query()->create([
            'nome' => 'Lucas Silva ' . $matricula,
            'email' => $matricula . '@candidato.test',
            'senha' => Hash::make('secret'),
            'telefone' => (string) ++$telefone,
            'data_cadastro' => now()->toDateString(),
        ]);

        return Candidato::query()->create([
            'matricula' => $matricula,
            'cpf' => str_pad($matricula, 11, '0'),
            'status' => $status,
            'ultima_atividade_em' => now(),
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
        ]);
    }

    private function criarEmpresa(string $cnpj): Empresa
    {
        $pessoa = Pessoa::query()->create([
            'nome' => 'Responsável ' . $cnpj,
            'email' => $cnpj . '@responsavel.test',
            'senha' => Hash::make('secret'),
            'telefone' => (string) random_int(10000000000, 99999999999),
            'data_cadastro' => now()->toDateString(),
        ]);

        return Empresa::query()->create([
            'cnpj' => $cnpj,
            'razao_social' => 'Comércio e Serviços ' . substr($cnpj, -4),
            'atividade_economica' => 'Comércio',
            'status' => true,
            'pessoa_id_pessoa' => $pessoa->id_pessoa,
            'responsavel_contratual_id_responsavel_contratual' => ResponsavelContratual::query()->create(['pessoa_id_pessoa' => $pessoa->id_pessoa])->id_responsavel_contratual,
        ]);
    }

    private function registrarContratacao(Candidato $candidato, Empresa $empresa): Contratacao
    {
        [$admin] = $this->criarAdministrativoAutenticado('registrador-' . $candidato->matricula . '-' . $empresa->cnpj . '@example.com');

        return Contratacao::query()->create([
            'candidato_matricula' => $candidato->matricula,
            'empresa_cnpj' => $empresa->cnpj,
            'registrado_por_pessoa_id' => $admin->pessoa_id_pessoa,
            'origem' => 'administrativo',
            'contratado_em' => now()->toDateString(),
            'status' => Contratacao::STATUS_VIGENTE,
        ]);
    }

    private function token(Pessoa $pessoa): string
    {
        $token = 'cancelamento-lote-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }
}
