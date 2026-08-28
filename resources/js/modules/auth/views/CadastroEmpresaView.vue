<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import empresaService from '../../../services/empresaServices';
import { formatarTelefone, somenteNumeros } from '../../../utils/telefone';
import '../../../../css/modules/auth/cadastro.css';

const router = useRouter();

const carregando = ref(false);
const mensagemErro = ref('');
const mensagemSucesso = ref('');

const formulario = reactive({
	cnpj: '',
	razaoSocial: '',
	atividadeEconomica: '',
	nome: '',
	email: '',
	telefone: '',
	senha: '',
	confirmarSenha: '',
	responsavelNome: '',
	responsavelEmail: '',
	responsavelTelefone: '',
	responsavelSenha: '',
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

function onTelefoneInput(campo, evento) {
    const valorFormatado = formatarTelefone(evento.target.value);
    formulario[campo] = valorFormatado;
    evento.target.value = valorFormatado;
}

async function enviarCadastro() {
	mensagemErro.value = '';
	mensagemSucesso.value = '';

	if (formulario.senha !== formulario.confirmarSenha) {
		mensagemErro.value = 'As senhas de acesso da empresa não coincidem.';
		return;
	}

	carregando.value = true;
	try {
		await empresaService.cadastrar({
			cnpj: Number(formulario.cnpj),
			razao_social: formulario.razaoSocial,
			atividade_economica: formulario.atividadeEconomica,
			nome: formulario.nome,
			email: formulario.email,
			telefone: somenteNumeros(formulario.telefone),
			senha: formulario.senha,
			responsavel_nome: formulario.responsavelNome,
			responsavel_email: formulario.responsavelEmail,
			responsavel_telefone: somenteNumeros(formulario.responsavelTelefone),
			responsavel_senha: formulario.responsavelSenha,
		});

		mensagemSucesso.value = 'Empresa cadastrada! Redirecionando para o login...';
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
			<p class="auth-cadastro-title mb-1">Cadastrar empresa parceira</p>
			<p class="auth-cadastro-subtitle mb-4">
				Seu cadastro passa por uma revisão da equipe do Senac DF antes de aparecer como parceiro ativo.
			</p>

			<div v-if="mensagemErro" class="alert alert-danger py-2 mb-4">{{ mensagemErro }}</div>
			<div v-if="mensagemSucesso" class="alert alert-success py-2 mb-4">{{ mensagemSucesso }}</div>

			<form @submit.prevent="enviarCadastro" novalidate>
				<p class="auth-cadastro-section-title">Dados da empresa</p>
				<div class="row g-3 mb-4">
					<div class="col-md-6">
						<input v-model.trim="formulario.cnpj" type="text" inputmode="numeric" class="form-control auth-cadastro-input" placeholder="CNPJ (somente números)" required>
					</div>
					<div class="col-md-6">
						<input v-model.trim="formulario.atividadeEconomica" type="text" class="form-control auth-cadastro-input" placeholder="Atividade econômica" required>
					</div>
					<div class="col-12">
						<input v-model.trim="formulario.razaoSocial" type="text" class="form-control auth-cadastro-input" placeholder="Razão social" required>
					</div>
				</div>

				<p class="auth-cadastro-section-title">Acesso da empresa ao portal</p>
				<div class="row g-3 mb-4">
					<div class="col-12">
						<input v-model.trim="formulario.nome" type="text" class="form-control auth-cadastro-input" placeholder="Nome de contato para login" required>
					</div>
					<div class="col-md-6">
						<input v-model.trim="formulario.email" type="email" class="form-control auth-cadastro-input" placeholder="Email de login" autocomplete="email" required>
					</div>
					<div class="col-md-6">
						<input v-model="formulario.telefone" type="tel" inputmode="numeric" autocomplete="tel" class="form-control auth-cadastro-input" placeholder="Telefone" maxlength="16" required @input="onTelefoneInput('telefone', $event)">
					</div>
					<div class="col-md-6">
						<input v-model="formulario.senha" type="password" class="form-control auth-cadastro-input" placeholder="Senha" autocomplete="new-password" minlength="6" required>
					</div>
					<div class="col-md-6">
						<input v-model="formulario.confirmarSenha" type="password" class="form-control auth-cadastro-input" placeholder="Confirmar senha" autocomplete="new-password" minlength="6" required>
					</div>
				</div>

				<p class="auth-cadastro-section-title">Responsável contratual</p>
				<p class="text-secondary small mb-3">Quem assina os convites de contratação enviados aos candidatos.</p>
				<div class="row g-3 mb-4">
					<div class="col-12">
						<input v-model.trim="formulario.responsavelNome" type="text" class="form-control auth-cadastro-input" placeholder="Nome do responsável" required>
					</div>
					<div class="col-md-6">
						<input v-model.trim="formulario.responsavelEmail" type="email" class="form-control auth-cadastro-input" placeholder="Email do responsável" required>
					</div>
					<div class="col-md-6">
						<input v-model="formulario.responsavelTelefone" type="tel" inputmode="numeric" autocomplete="tel" class="form-control auth-cadastro-input" placeholder="Telefone do responsável" maxlength="16" required @input="onTelefoneInput('responsavelTelefone', $event)">
					</div>
					<div class="col-md-6">
						<input v-model="formulario.responsavelSenha" type="password" class="form-control auth-cadastro-input" placeholder="Senha do responsável" autocomplete="new-password" minlength="6" required>
					</div>
				</div>

				<button type="submit" class="btn btn-primary w-100" :disabled="carregando">
					<span v-if="carregando" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />
					{{ carregando ? 'Enviando...' : 'Cadastrar empresa' }}
				</button>

				<p class="text-center small mt-4 mb-0">
					Já tem conta? <router-link :to="{ name: 'login' }" class="auth-cadastro-footer-link">Entrar</router-link>
					·
					É aluno do Senac? <router-link :to="{ name: 'cadastro-aluno' }" class="auth-cadastro-footer-link">Cadastre-se aqui</router-link>
				</p>
			</form>
		</div>
	</div>
</template>
