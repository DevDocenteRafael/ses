<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CepController extends Controller
{
    public function show(string $cep): JsonResponse
    {
        $cepNormalizado = preg_replace('/\D+/', '', $cep) ?? '';

        if (! preg_match('/^\d{8}$/', $cepNormalizado)) {
            return response()->json([
                'message' => 'Informe um CEP válido.',
            ], 422);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(8)
                ->retry(1, 200)
                ->get("https://viacep.com.br/ws/{$cepNormalizado}/json/");
        } catch (ConnectionException $e) {
            Log::warning('Falha de conexão ao consultar CEP no ViaCEP.', [
                'cep' => $cepNormalizado,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Não foi possível consultar o CEP no momento. Tente novamente ou preencha o endereço manualmente.',
            ], 503);
        }

        if (! $response->successful()) {
            Log::warning('ViaCEP respondeu com status inesperado.', [
                'cep' => $cepNormalizado,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'message' => 'Não foi possível consultar o CEP no momento. Tente novamente ou preencha o endereço manualmente.',
            ], 503);
        }

        $data = $response->json();

        if (! is_array($data)) {
            return response()->json([
                'message' => 'Não foi possível consultar o CEP no momento. Tente novamente ou preencha o endereço manualmente.',
            ], 503);
        }

        if (! empty($data['erro'])) {
            return response()->json([
                'message' => 'CEP não encontrado.',
            ], 404);
        }

        return response()->json([
            'cep' => $this->formatarCep((string) ($data['cep'] ?? $cepNormalizado)),
            'logradouro' => (string) ($data['logradouro'] ?? ''),
            'bairro' => (string) ($data['bairro'] ?? ''),
            'cidade' => (string) ($data['localidade'] ?? ''),
            'uf' => (string) ($data['uf'] ?? ''),
        ]);
    }

    private function formatarCep(string $cep): string
    {
        $digitos = preg_replace('/\D+/', '', $cep) ?? '';

        if (strlen($digitos) !== 8) {
            return $cep;
        }

        return substr($digitos, 0, 5) . '-' . substr($digitos, 5);
    }
}
