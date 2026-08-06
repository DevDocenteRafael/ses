/**
 * Rotas do painel da empresa.
 * Todas exigem login com tipo 'empresa'.
 */
export default [
    {
        path: '/empresa',
        meta: { requiresAuth: true, role: 'empresa' },
        children: [
            {
                path: '',
                name: 'empresa.dashboard',
                component: () => import('../modules/empresa/views/DashboardView.vue'),
            },
            {
                path: 'minhas-vagas',
                name: 'empresa.minhas-vagas',
                component: () => import('../modules/empresa/views/MinhasVagasView.vue'),
            },
            {
                path: 'favoritos',
                name: 'empresa.favoritos',
                component: () => import('../modules/empresa/views/FavoritosView.vue'),
            },
            {
                path: 'convites',
                name: 'empresa.convites',
                component: () => import('../modules/empresa/views/ConvitesView.vue'),
            },
            {
                path: 'perfil',
                name: 'empresa.perfil',
                component: () => import('../modules/empresa/views/PerfilEmpresaView.vue'),
            },
        ],
    },
];