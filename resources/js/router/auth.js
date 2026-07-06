/**
 * Rotas públicas de autenticação.
 * Usam o AuthLayout (tela limpa, sem sidebar/header do painel).
 */
export default [
    {
        path: '/login',
        name: 'login',
        component: () => import('../modules/auth/views/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/recuperar-senha',
        name: 'recuperar-senha',
        component: () => import('../modules/auth/views/RecuperarSenhaView.vue'),
        meta: { guestOnly: true },
    },
];