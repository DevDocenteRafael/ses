/**
 * Rotas do painel administrativo (SENAC).
 * Todas exigem login com tipo 'administrativo' (ver meta.requiresAuth/role).
 */
export default [
    {
        path: '/admin',
        meta: { requiresAuth: true, role: 'administrativo' },
        children: [
            {
                path: '',
                name: 'admin.dashboard',
                component: () => import('../modules/admin/views/DashboardView.vue'),
            },
            {
                path: 'alunos',
                name: 'admin.alunos',
                component: () => import('../modules/admin/views/GestaoAlunosView.vue'),
            },
            {
                path: 'empresas',
                name: 'admin.empresas',
                component: () => import('../modules/admin/views/GestaoEmpresasView.vue'),
            },
            {
                path: 'vagas',
                name: 'admin.vagas',
                component: () => import('../modules/admin/views/VagasView.vue'),
            },
            {
                path: 'relatorios',
                name: 'admin.relatorios',
                component: () => import('../modules/admin/views/RelatoriosView.vue'),
            },
        ],
    },
];