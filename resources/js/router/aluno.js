/**
 * Rotas do painel do aluno (candidato).
 * Todas exigem login com tipo 'candidato'.
 */
export default [
    {
        path: '/aluno',
        meta: { requiresAuth: true, role: 'candidato' },
        children: [
            {
                path: '',
                name: 'aluno.dashboard',
                component: () => import('../modules/aluno/views/DashboardView.vue'),
            },
            {
                path: 'convites',
                name: 'aluno.convites',
                component: () => import('../modules/aluno/views/ConvitesView.vue'),
            },
        ],
    },
];
