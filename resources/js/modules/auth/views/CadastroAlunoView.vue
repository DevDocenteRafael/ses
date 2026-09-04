<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import alunosService from '../../../services/alunosServices';
import { formatarTelefone, somenteNumeros } from '../../../utils/telefone';
import '../../../../css/modules/auth/cadastro.css';

const router = useRouter();

const carregando = ref(false);
const mensagemErro = ref('');
const mensagemSucesso = ref('');

const formulario = reactive({
	nome: '',
	email: '',
	telefone: '',
	matricula: '',
	cpf: '',
	senha: '',
	confirmarSenha: '',
	aceiteLgpd: false,
});

function obterMensagemErro(error) {
	if (error?.response?.data?.errors) {
		const primeiroCampo = Object.values(error.response.data.errors)[0];
		if (Array.isArray(primeiroCampo) && primeiroCampo.length) {
			return primeiroCampo[0];
		}
	}
	if (error?.response?.data?.error) {
		return error.response.data.error;
	}
	return 'Não foi possível concluir o cadastro. Verifique os dados e tente novamente.';
}

function onTelefoneInput(evento) {
    const valorFormatado = formatarTelefone(evento.target.value);
    formulario.telefone = valorFormatado;
    evento.target.value = valorFormatado;
}

function onMatriculaInput(evento) {
	const valor = somenteNumeros(evento.target.value).slice(0, 15);
	formulario.matricula = valor;
	evento.target.value = valor;
}

async function enviarCadastro() {
	mensagemErro.value = '';
	mensagemSucesso.value = '';

	if (formulario.senha !== formulario.confirmarSenha) {
		mensagemErro.value = 'As senhas informadas não coincidem.';
		return;
	}

	if (!formulario.aceiteLgpd) {
		mensagemErro.value = 'É preciso aceitar os termos de uso de dados (LGPD) para continuar.';
		return;
	}

	carregando.value = true;
	try {
		await alunosService.cadastrar({
			matricula: somenteNumeros(formulario.matricula).slice(0, 15),
			cpf: formulario.cpf,
			status: formulario.aceiteLgpd,
			nome: formulario.nome,
			email: formulario.email,
            telefone: somenteNumeros(formulario.telefone),
			senha: formulario.senha,
		});

		mensagemSucesso.value = 'Cadastro realizado! Redirecionando para o login...';
		setTimeout(() => router.push({ name: 'login' }), 1500);
	} catch (error) {
		mensagemErro.value = obterMensagemErro(error);
	} finally {
		carregando.value = false;
	}
}
</script>

<template>
	<div class="auth-cadastro-page">
		<div class="auth-cadastro-card shadow-sm">
			<p class="auth-cadastro-title mb-1">Criar conta de aluno</p>
			<p class="auth-cadastro-subtitle mb-4">
				Use a matrícula do seu curso no Senac DF para ativar seu perfil no portal de empregabilidade.
			</p>

			<div v-if="mensagemErro" class="alert alert-danger py-2 mb-4">{{ mensagemErro }}</div>
			<div v-if="mensagemSucesso" class="alert alert-success py-2 mb-4">{{ mensagemSucesso }}</div>

			<form @submit.prevent="enviarCadastro" novalidate>
				<p class="auth-cadastro-section-title">Dados pessoais</p>
				<div class="row g-3 mb-4">
					<div class="col-12">
						<input v-model.trim="formulario.nome" type="text" class="form-control auth-cadastro-input" placeholder="Nome completo" required>
					</div>
					<div class="col-md-6">
						<input v-model.trim="formulario.email" type="email" class="form-control auth-cadastro-input" placeholder="Email" autocomplete="email" required>
					</div>
					<div class="col-md-6">
						<input v-model="formulario.telefone" type="tel" inputmode="numeric" autocomplete="tel" class="form-control auth-cadastro-input" placeholder="Telefone" maxlength="16" required @input="onTelefoneInput">
					</div>
					<div class="col-md-6">
					<input v-model="formulario.matricula" type="text" inputmode="numeric" class="form-control auth-cadastro-input" placeholder="Matrícula Senac" maxlength="15" required @input="onMatriculaInput">
					</div>
					<div class="col-md-6">
						<input v-model.trim="formulario.cpf" type="text" class="form-control auth-cadastro-input" placeholder="CPF" maxlength="14" required>
					</div>
				</div>

				<p class="auth-cadastro-section-title">Acesso</p>
				<div class="row g-3 mb-3">
					<div class="col-md-6">
						<input v-model="formulario.senha" type="password" class="form-control auth-cadastro-input" placeholder="Senha" autocomplete="new-password" minlength="6" required>
					</div>
					<div class="col-md-6">
						<input v-model="formulario.confirmarSenha" type="password" class="form-control auth-cadastro-input" placeholder="Confirmar senha" autocomplete="new-password" minlength="6" required>
					</div>
				</div>

				<div class="form-check mb-4">
					<input id="aceite-lgpd" v-model="formulario.aceiteLgpd" class="form-check-input" type="checkbox">
					<label class="form-check-label small" for="aceite-lgpd">
						Aceito que meus dados sejam usados para conectar meu perfil às empresas parceiras do Senac DF (LGPD).
					</label>
				</div>

				<button type="submit" class="btn btn-primary w-100" :disabled="carregando">
					<span v-if="carregando" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />
					{{ carregando ? 'Enviando...' : 'Criar conta' }}
				</button>

				<p class="text-center small mt-4 mb-0">
					Já tem conta? <router-link :to="{ name: 'login' }" class="auth-cadastro-footer-link">Entrar</router-link>
					·
					Representa uma empresa? <router-link :to="{ name: 'cadastro-empresa' }" class="auth-cadastro-footer-link">Cadastre-se aqui</router-link>
				</p>
			</form>
		</div>
	</div>
</template>
