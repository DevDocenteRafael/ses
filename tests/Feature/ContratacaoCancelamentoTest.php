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

class ContratacaoCancelamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cancela_contratacao_e_candidato_volta_para_gestao(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-cancelar@example.com');
        $candidato = $this->criarCandidato('100000001');
        $empresa = $this->criarEmpresa('10000000000101');
        $contratacao = $this->registrarContratacao($candidato, $empresa);

        $this->withToken($token)->patchJson("/api/contratacoes/{$contratacao->id}/cancelar", [
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertOk()
            ->assertJsonPath('message', 'Contratação cancelada com sucesso.')
            ->assertJsonPath('candidato_contratado', false);

        $contratacao->refresh();
        $this->assertSame(Contratacao::STATUS_CANCELADA, $contratacao->status);
        $this->assertNotNull($contratacao->cancelado_em);
        $this->assertSame('Contratação registrada por engano', $contratacao->motivo_cancelamento);

        $this->withToken($token)->getJson('/api/contratacoes?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->withToken($token)->getJson('/api/candidatos?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.matricula', $candidato->matricula);
    }

    public function test_cancelamento_preserva_bloqueio_manual(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-manual@example.com');
        $candidato = $this->criarCandidato('100000002', status: false);
        $contratacao = $this->registrarContratacao($candidato, $this->criarEmpresa('10000000000102'));

        $this->withToken($token)->patchJson("/api/contratacoes/{$contratacao->id}/cancelar", [
            'motivo_cancelamento' => 'Empresa selecionada incorretamente',
        ])->assertOk();

        $this->withToken($token)->getJson('/api/candidatos?status=0&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.estado_efetivo', 'BLOQUEADO_MANUALMENTE');
    }

    public function test_multiplas_contratacoes_cancela_somente_vinculo_selecionado(): void
    {
        [, $token] = $this->criarAdministrativoAutenticado('admin-multipla@example.com');
        $candidato = $this->criarCandidato('100000003');
        $primeira = $this->registrarContratacao($candidato, $this->criarEmpresa('10000000000103'));
        $segunda = $this->registrarContratacao($candidato, $this->criarEmpresa('10000000000104'));

        $this->withToken($token)->patchJson("/api/contratacoes/{$primeira->id}/cancelar", [
            'motivo_cancelamento' => 'Candidato selecionado incorretamente',
        ])->assertOk()
            ->assertJsonPath('candidato_contratado', true);

        $this->assertSame(Contratacao::STATUS_CANCELADA, $primeira->refresh()->status);
        $this->assertSame(Contratacao::STATUS_VIGENTE, $segunda->refresh()->status);

        $this->withToken($token)->getJson('/api/contratacoes?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $segunda->id);
    }

    public function test_rejeita_cancelamento_duplicado_usuario_nao_admin_e_motivo_invalido(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado('admin-rejeita@example.com');
        [, $tokenEmpresa] = $this->criarEmpresaAutenticada('10000000000105');
        $contratacao = $this->registrarContratacao($this->criarCandidato('100000004'), $this->criarEmpresa('10000000000106'));

        $this->withToken($tokenEmpresa)->patchJson("/api/contratacoes/{$contratacao->id}/cancelar", [
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertForbidden();

        $this->withToken($tokenAdmin)->patchJson("/api/contratacoes/{$contratacao->id}/cancelar", [
            'motivo_cancelamento' => '',
        ])->assertStatus(422);

        $this->withToken($tokenAdmin)->patchJson("/api/contratacoes/{$contratacao->id}/cancelar", [
            'motivo_cancelamento' => 'Outro motivo',
        ])->assertStatus(422);

        $this->withToken($tokenAdmin)->patchJson("/api/contratacoes/{$contratacao->id}/cancelar", [
            'motivo_cancelamento' => 'Outro motivo',
            'observacao_cancelamento' => 'Registro feito no candidato errado.',
        ])->assertOk();

        $this->withToken($tokenAdmin)->patchJson("/api/contratacoes/{$contratacao->id}/cancelar", [
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertOk()
            ->assertJsonFragment(['message' => 'Esta contratação já estava cancelada.']);
    }

    public function test_recontrata_candidato_apos_cancelamento_preservando_historico(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado('admin-recontrata@example.com');
        $candidato = $this->criarCandidato('100000005');
        $empresaAntiga = $this->criarEmpresa('10000000000107');
        $empresaNova = $this->criarEmpresa('10000000000108');

        $primeira = $this->registrarContratacao($candidato, $empresaAntiga);

        $this->withToken($tokenAdmin)->patchJson("/api/contratacoes/{$primeira->id}/cancelar", [
            'motivo_cancelamento' => 'Contratação registrada por engano',
        ])->assertOk();

        $this->withToken($tokenAdmin)->postJson("/api/candidatos/{$candidato->matricula}/contratacao", [
            'empresa_cnpj' => $empresaNova->cnpj,
        ])->assertCreated()
            ->assertJsonPath('empresa_cnpj', $empresaNova->cnpj);

        $this->assertSame(2, Contratacao::query()->where('candidato_matricula', $candidato->matricula)->count());
        $this->assertSame(1, Contratacao::query()->where('candidato_matricula', $candidato->matricula)->vigentes()->count());
        $this->assertSame(Contratacao::STATUS_CANCELADA, $primeira->refresh()->status);
    }

    public function test_nao_registra_duas_contratacoes_vigentes_para_mesmo_candidato(): void
    {
        [, $tokenAdmin] = $this->criarAdministrativoAutenticado('admin-duplicada@example.com');
        $candidato = $this->criarCandidato('100000006');
        $empresaA = $this->criarEmpresa('10000000000109');
        $empresaB = $this->criarEmpresa('10000000000110');

        $this->withToken($tokenAdmin)->postJson("/api/candidatos/{$candidato->matricula}/contratacao", [
            'empresa_cnpj' => $empresaA->cnpj,
        ])->assertCreated();

        $this->withToken($tokenAdmin)->postJson("/api/candidatos/{$candidato->matricula}/contratacao", [
            'empresa_cnpj' => $empresaB->cnpj,
        ])->assertStatus(409)
            ->assertJsonFragment(['message' => 'Este candidato já possui uma contratação ativa.']);

        $this->assertSame(1, Contratacao::query()->where('candidato_matricula', $candidato->matricula)->vigentes()->count());
    }

    private function criarAdministrativoAutenticado(string $email): array
    {
        static $telefone = 61000000000;

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
        $pessoa = Pessoa::query()->create([
            'nome' => 'Lucas Silva ' . $matricula,
            'email' => $matricula . '@candidato.test',
            'senha' => Hash::make('secret'),
            'telefone' => '61988888888',
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
        $token = 'cancelamento-token-' . Str::random(10);
        Cache::put('auth_token:' . $token, $pessoa->id_pessoa, now()->addHour());

        return $token;
    }
}
