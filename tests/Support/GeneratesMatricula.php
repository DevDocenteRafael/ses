<?php

namespace Tests\Support;

trait GeneratesMatricula
{
    protected function gerarMatricula(int $comprimento = 6): string
    {
        $comprimento = max(1, min(15, $comprimento));

        $primeiroDigito = (string) random_int(1, 9);

        if ($comprimento === 1) {
            return $primeiroDigito;
        }

        $restante = '';

        for ($i = 1; $i < $comprimento; $i++) {
            $restante .= (string) random_int(0, 9);
        }

        return $primeiroDigito . $restante;
    }
}
