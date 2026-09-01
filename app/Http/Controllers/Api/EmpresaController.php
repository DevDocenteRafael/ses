<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Pessoa;
use App\Models\ResponsavelContratual;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmpresaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $this->garantirAdministrativo($request);

        $validated = $request->validate([
            'razao_social' => ['required', 'string', 'max:45'],
            'cnpj' => ['required', 'string', 'max:18'],
            'atividade_economica' => ['required', 'string', 'max:45'],
            'email' => ['required', 'email', 'max:100', 'unique:pessoa,email'],
            'telefone' => ['nullable', 'string', 'max:16'],
            'responsavel_nome' => ['required', 'string', 'max:100'],
            'responsavel_email' => ['nullable', 'email', 'max:100', 'unique:pessoa,email'],
            'responsavel_telefone' => ['nullable', 'string', 'max:16'],
            'senha' => ['required', 'string', 'min:6', 'confirmed'],
            'status' => ['sometimes', 'boolean'],
        ], [
            'razao_social.required' => 'Informe a razão social.',
            'razao_social.max' => 'A razão social deve ter no máximo 45 caracteres.',
            'cnpj.required' => 'Informe o CNPJ.',
            'atividade_economica.required' => 'Informe a atividade da empresa.',
            'atividade_economica.max' => 'A atividade da empresa deve ter no máximo 45 caracteres.',
            'email.required' => 'Informe o e-mail da empresa.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'telefone.max' => 'Informe um telefone válido.',
            'responsavel_nome.required' => 'Informe o nome do responsável.',
            'responsavel_nome.max' => 'O nome do responsável deve ter no máximo 100 caracteres.',
            'responsavel_email.email' => 'Informe um e-mail válido para o responsável.',
            'responsavel_email.unique' => 'Este e-mail já está cadastrado.',
            'responsavel_telefone.max' => 'Informe um telefone válido para o responsável.',
            'senha.required' => 'Informe a senha.',
            'senha.min' => 'A senha deve ter no mínimo 6 caracteres.',
            'senha.confirmed' => 'As senhas não coincidem.',
        ]);

        $cnpj = preg_replace('/\D+/', '', $validated['cnpj']) ?? $validated['cnpj'];
        $telefone = preg_replace('/\D+/', '', (string) ($validated['telefone'] ?? ''));
        $telefoneResponsavel = preg_replace('/\D+/', '', (string) ($validated['responsavel_telefone'] ?? ''));

        if (strlen($cnpj) !== 14) {
            return response()->json([
                'errors' => ['cnpj' => ['Informe um CNPJ válido.']],
            ], 422);
        }

        if ($telefone !== '' && ! in_array(strlen($telefone), [10, 11], true)) {
            return response()->json([
                'errors' => ['telefone' => ['Informe um telefone válido.']],
            ], 422);
        }

        if ($telefoneResponsavel !== '' && ! in_array(strlen($telefoneResponsavel), [10, 11], true)) {
            return response()->json([
                'errors' => ['responsavel_telefone' => ['Informe um telefone válido para o responsável.']],
            ], 422);
        }

        if (Empresa::query()->where('cnpj', $cnpj)->exists()) {
            return response()->json([
                'errors' => ['cnpj' => ['Este CNPJ já está cadastrado.']],
            ], 422);
        }

        if ($telefone !== '' && Pessoa::query()->where('telefone', $telefone)->exists()) {
            return response()->json([
                'errors' => ['telefone' => ['Este telefone já está cadastrado.']],
            ], 422);
        }

        if ($telefoneResponsavel !== '' && Pessoa::query()->where('telefone', $telefoneResponsavel)->exists()) {
            return response()->json([
                'errors' => ['responsavel_telefone' => ['Este telefone já está cadastrado.']],
            ], 422);
        }

        DB::beginTransaction();

        try {
            $pessoaEmpresa = Pessoa::query()->create([
                'nome' => $validated['razao_social'],
                'email' => $validated['email'],
                'telefone' => $telefone !== '' ? $telefone : $this->gerarTelefonePlaceholder(),
                'senha' => Hash::make($validated['senha']),
                'data_cadastro' => now(),
            ]);

            $pessoaResponsavel = Pessoa::query()->create([
                'nome' => $validated['responsavel_nome'],
                'email' => $validated['responsavel_email'] ?? $this->gerarEmailResponsavelPlaceholder($cnpj),
                'telefone' => $telefoneResponsavel !== '' ? $telefoneResponsavel : $this->gerarTelefonePlaceholder(),
                'senha' => Hash::make($validated['senha']),
                'data_cadastro' => now(),
            ]);

            $responsavel = ResponsavelContratual::query()->create([
                'pessoa_id_pessoa' => $pessoaResponsavel->id_pessoa,
            ]);

            $empresa = Empresa::query()->create([
                'cnpj' => $cnpj,
                'razao_social' => $validated['razao_social'],
                'atividade_economica' => $validated['atividade_economica'],
                'status' => (bool) ($validated['status'] ?? false),
                'pessoa_id_pessoa' => $pessoaEmpresa->id_pessoa,
                'responsavel_contratual_id_responsavel_contratual' => $responsavel->id_responsavel_contratual,
            ]);

            DB::commit();

            return response()->json($empresa->load(['pessoa', 'responsavelContratual.pessoa']), 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Não foi possível cadastrar a empresa.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        $empresas = Empresa::with([
            'pessoa',
            'responsavelContratual.pessoa',
            'vagas',
            'historicoDeEngajamento',
        ])->get();

        return response()->json($empresas);
    }

    public function show(string $cnpj): JsonResponse
    {
        $empresa = Empresa::with([
            'pessoa',
            'responsavelContratual.pessoa',
            'vagas',
            'convites',
            'historicoDeEngajamento',
            'candidatos.pessoa',
            'candidatos.informacoesProfissionais',
            'candidatos.preferenciasDeTrabalho',
            'candidatos.dadosAcademicos',
        ])->findOrFail($cnpj);

        return response()->json($empresa);
    }

    public function update(Request $request, string $cnpj): JsonResponse
    {
        $this->garantirAdministrativo($request);

        $empresa = Empresa::findOrFail($cnpj);

        $validated = $request->validate([
            'razao_social'        => 'sometimes|string|max:45',
            'atividade_economica' => 'sometimes|string|max:45',
            'status'              => 'sometimes|boolean',
        ]);

        $empresa->update($validated);

        return response()->json($empresa->load('pessoa'));
    }

    public function destroy(Request $request, string $cnpj): JsonResponse
    {
        $this->garantirAdministrativo($request);

        $empresa = Empresa::findOrFail($cnpj);
        $empresa->delete();

        return response()->json(['message' => 'Empresa removida com sucesso.']);
    }

    // ── Favoritos (candidatos salvos pela empresa) ───────────────
    // Usa a tabela pivô `empresa_has_candidatos` (relação Empresa::candidatos()).

    public function favoritos(Request $request): JsonResponse
    {
        $empresa = $this->empresaAutenticada($request);

        $favoritos = $empresa->candidatos()
            ->with(['pessoa', 'informacoesProfissionais', 'preferenciasDeTrabalho', 'dadosAcademicos'])
            ->get();

        return response()->json($favoritos);
    }

    public function favoritar(Request $request, int $matricula): JsonResponse
    {
        $empresa = $this->empresaAutenticada($request);

        $empresa->candidatos()->syncWithoutDetaching([$matricula]);

        return response()->json(['message' => 'Candidato favoritado com sucesso.'], 201);
    }

    public function desfavoritar(Request $request, int $matricula): JsonResponse
    {
        $empresa = $this->empresaAutenticada($request);

        $empresa->candidatos()->detach($matricula);

        return response()->json(['message' => 'Candidato removido dos favoritos.']);
    }

    private function gerarTelefonePlaceholder(): string
    {
        do {
            $telefone = '61' . (string) random_int(10000000, 999999999);
        } while (Pessoa::query()->where('telefone', $telefone)->exists());

        return $telefone;
    }

    private function gerarEmailResponsavelPlaceholder(string $cnpj): string
    {
        do {
            $email = 'responsavel+' . $cnpj . '-' . bin2hex(random_bytes(3)) . '@placeholder.local';
        } while (Pessoa::query()->where('email', $email)->exists());

        return $email;
    }
}
