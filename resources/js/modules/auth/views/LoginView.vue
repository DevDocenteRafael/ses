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

const painelPorTipo = {
	administrativo: '/admin',
	empresa: '/empresa/buscar-talentos',
	candidato: '/aluno/perfil',
};

const logoSenacSrc = '/img/senac-logo.png';

function obterMensagemErro(error) {
	if (error?.response?.data?.message) {
		return error.response.data.message;
	}

	if (error?.response?.data?.errors) {
		const primeiroCampo = Object.values(error.response.data.errors)[0];
		if (Array.isArray(primeiroCampo) && primeiroCampo.length) {
			return primeiroCampo[0];
		}
	}

	return 'Nao foi possivel autenticar com os dados informados.';
}

async function enviarLogin() {
	mensagemErro.value = '';
	carregando.value = true;

	try {
		const resultado = await auth.login(formulario);
		await router.push(painelPorTipo[resultado.tipo] || '/login');
	} catch (error) {
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
						<img
							:src="logoSenacSrc"
							alt="Logo Senac"
							class="auth-login-logo img-fluid"
						>
						<h1 class="auth-login-title mb-2">Bem-vindo!</h1>
						<p class="auth-login-subtitle mb-0">Senac DF: lugar de oportunidades.</p>
					</div>
				</section>

				<section class="col-12 col-lg-7 auth-login-content">
					<div class="auth-login-form">
						<div class="auth-login-form-inner">
							<div v-if="mensagemErro" class="alert alert-danger py-2 mb-4" role="alert">
								{{ mensagemErro }}
							</div>

							<form @submit.prevent="enviarLogin" novalidate>
								<div class="mb-4">
									<input
										id="email"
										v-model.trim="formulario.email"
										type="email"
										class="form-control auth-login-input"
										placeholder="Email"
										autocomplete="email"
										required
									>
								</div>

								<div class="mb-2 auth-login-password-wrapper">
									<input
										id="senha"
										v-model="formulario.senha"
										:type="mostrarSenha ? 'text' : 'password'"
										class="form-control auth-login-input auth-login-password-input"
										placeholder="Senha"
										autocomplete="current-password"
										required
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
