<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../store/auth';
import '../../../../css/modules/auth/login.css';

const auth = useAuthStore();
const router = useRouter();

const carregando = ref(false);
const mensagemErro = ref('');
const mostrarSenha = ref(false);

const formulario = reactive({
	email: '',
	senha: '',
});

const errors = reactive({
	email: '',
	senha: '',
});

const painelPorTipo = {
	administrativo: '/admin',
	empresa: '/empresa/buscar-talentos',
	candidato: '/aluno/perfil',
};

const logoSenacSrc = '/img/senac-logo.png';

const MENSAGEM_CAMPOS_OBRIGATORIOS = 'Existem campos obrigatórios não preenchidos.';
const MENSAGEM_VALIDACAO = 'Corrija os campos destacados para continuar.';
const MENSAGEM_AUTENTICACAO = 'Não foi possível entrar com os dados informados.';
const MENSAGEM_ERRO_TECNICO = 'Não foi possível concluir o login no momento. Tente novamente mais tarde.';

function limparErrosCampos() {
	errors.email = '';
	errors.senha = '';
}

function existeErroCampo() {
	return Boolean(errors.email || errors.senha);
}

function emailTemFormatoValido(email) {
	return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validarFormulario() {
	limparErrosCampos();

	if (!formulario.email) {
		errors.email = 'Informe o e-mail.';
	} else if (!emailTemFormatoValido(formulario.email)) {
		errors.email = 'Informe um e-mail válido.';
	}

	if (!formulario.senha) {
		errors.senha = 'Informe a senha.';
	}

	if (!existeErroCampo()) {
		return true;
	}

	mensagemErro.value = (!formulario.email || !formulario.senha)
		? MENSAGEM_CAMPOS_OBRIGATORIOS
		: MENSAGEM_VALIDACAO;

	return false;
}

function limparErroCampo(campo) {
	if (errors[campo]) {
		errors[campo] = '';
	}

	if (!existeErroCampo()) {
		mensagemErro.value = '';
	}
}

function aplicarErrosValidacaoBackend(error) {
	const errosBackend = error?.response?.data?.errors;

	if (!errosBackend || error?.response?.status !== 422) {
		return false;
	}

	errors.email = errosBackend.email?.[0] || '';
	errors.senha = errosBackend.senha?.[0] || '';

	if (existeErroCampo()) {
		mensagemErro.value = error.response.data.message || MENSAGEM_AUTENTICACAO;
		return true;
	}

	return false;
}

function obterMensagemErro(error) {
	if (!error?.response) {
		return 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.';
	}

	if (error.response.status >= 500) {
		return MENSAGEM_ERRO_TECNICO;
	}

	if (error?.response?.data?.message) {
		return error.response.data.message;
	}

	return MENSAGEM_ERRO_TECNICO;
}

async function enviarLogin() {
	mensagemErro.value = '';

	if (!validarFormulario()) {
		return;
	}

	carregando.value = true;

	try {
		const resultado = await auth.login(formulario);
		await router.push(painelPorTipo[resultado.tipo] || '/login');
	} catch (error) {
		if (aplicarErrosValidacaoBackend(error)) {
			return;
		}

		mensagemErro.value = obterMensagemErro(error);
	} finally {
		carregando.value = false;
	}
}
</script>

<template>
	<div class="auth-login-page">
		<div class="auth-login-card shadow-sm">
			<div class="row g-0 h-100">
				<section class="col-12 col-lg-5 auth-login-aside text-center text-white">
					<div class="auth-login-aside-inner px-4 py-5">
						<h1 class="auth-login-title mb-0">Bem-vindo!</h1>
						<img
							:src="logoSenacSrc"
							alt="Logo Senac"
							class="auth-login-logo img-fluid"
						>
						<p class="auth-login-subtitle mb-0">Lugar de oportunidades</p>
					</div>
				</section>

				<section class="col-12 col-lg-7 auth-login-content">
					<div class="auth-login-form">
						<div class="auth-login-form-inner">
							<div v-if="mensagemErro" id="login-mensagem-erro" class="alert alert-danger py-2 mb-4" role="alert">
								{{ mensagemErro }}
							</div>

							<form @submit.prevent="enviarLogin" novalidate>
								<div class="mb-4">
									<input
										id="email"
										v-model.trim="formulario.email"
										type="email"
										class="form-control auth-login-input"
										:class="{ 'auth-login-input-invalid': errors.email }"
										placeholder="Email"
										autocomplete="email"
										:aria-invalid="Boolean(errors.email)"
										:aria-describedby="errors.email ? 'login-email-erro' : undefined"
										required
										@input="limparErroCampo('email')"
									>
									<p v-if="errors.email" id="login-email-erro" class="auth-login-field-error">
										{{ errors.email }}
									</p>
								</div>

								<div class="mb-2">
									<div class="auth-login-password-wrapper">
										<input
											id="senha"
											v-model="formulario.senha"
											:type="mostrarSenha ? 'text' : 'password'"
											class="form-control auth-login-input auth-login-password-input"
											:class="{ 'auth-login-input-invalid': errors.senha }"
											placeholder="Senha"
											autocomplete="current-password"
											:aria-invalid="Boolean(errors.senha)"
											:aria-describedby="errors.senha ? 'login-senha-erro' : undefined"
											required
											@input="limparErroCampo('senha')"
										>
										<button
											type="button"
											class="auth-login-password-toggle"
											:aria-label="mostrarSenha ? 'Ocultar senha' : 'Mostrar senha'"
											:title="mostrarSenha ? 'Ocultar senha' : 'Mostrar senha'"
											@click="mostrarSenha = !mostrarSenha"
										>
											<i :class="mostrarSenha ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
										</button>
									</div>
									<p v-if="errors.senha" id="login-senha-erro" class="auth-login-field-error">
										{{ errors.senha }}
									</p>
								</div>

								<div class="auth-login-actions mt-3">
									<div class="form-check auth-login-check m-0">
										<input id="salvar-dados" class="form-check-input" type="checkbox">
										<label class="form-check-label" for="salvar-dados">Salvar dados</label>
									</div>

									<button type="submit" class="btn btn-primary auth-login-button" :disabled="carregando">
										<span
											v-if="carregando"
											class="spinner-border spinner-border-sm me-2"
											role="status"
											aria-hidden="true"
										/>
										{{ carregando ? 'Entrando...' : 'Entrar' }}
									</button>
								</div>
							</form>
						</div>
					</div>
				</section>
			</div>
		</div>
	</div>
</template>
