import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../store/auth';

import authRoutes from './auth';
import adminRoutes from './admin';
import empresaRoutes from './empresa';

// Layouts (componentes "casca" que envolvem as views de cada área)
const AuthLayout = () => import('../layouts/AuthLayout.vue');
const AdminLayout = () => import('../layouts/AdminLayout.vue');
const EmpresaLayout = () => import('../layouts/EmpresaLayout.vue');

/**
 * Cada grupo de rotas (admin/empresa/aluno) é "encaixado" dentro do
 * seu respectivo Layout usando rota-pai + children. Isso evita repetir
 * o Layout em cada arquivo de rotas individual.
 */
const routes = [
    {
        path: '/',
        redirect: '/login',
    },
    {
        path: '/',
        component: AuthLayout,
        children: authRoutes,
    },
    {
        path: '/admin',
        component: AdminLayout,
        children: adminRoutes[0].children,
        meta: adminRoutes[0].meta,
    },
    {
        path: '/empresa',
        component: EmpresaLayout,
        children: empresaRoutes[0].children,
        meta: empresaRoutes[0].meta,
    },
    {
        // Única página do aluno após o login (sem Dashboard/Convites).
        path: '/aluno/perfil',
        name: 'aluno.perfil',
        component: () => import('../modules/aluno/views/MeuPerfilView.vue'),
        meta: { requiresAuth: true, role: 'candidato' },
    },
    {
        // Qualquer rota não mapeada cai aqui
        path: '/:pathMatch(.*)*',
        redirect: '/login',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

/**
 * Guard global de navegação — roda ANTES de qualquer troca de rota.
 *
 * Regras:
 * 1. Rota pública para visitante (guestOnly) + usuário já logado
 *    → redireciona para o painel dele (não faz sentido ver login logado).
 * 2. Rota protegida (requiresAuth) + usuário não logado
 *    → redireciona para o login.
 * 3. Rota protegida com 'role' específico + usuário logado com outro papel
 *    → redireciona para o painel correto dele (evita aluno acessar /admin).
 */
router.beforeEach((to, from, next) => {
    const auth = useAuthStore();

    const painelPorTipo = {
        administrativo: '/admin',
        empresa: '/empresa',
        candidato: '/aluno/perfil',
    };

    if (to.meta.guestOnly && auth.estaAutenticado) {
        return next(painelPorTipo[auth.tipo] || '/login');
    }

    if (to.meta.requiresAuth && !auth.estaAutenticado) {
        return next('/login');
    }

    if (to.meta.role && auth.tipo !== to.meta.role) {
        return next(painelPorTipo[auth.tipo] || '/login');
    }

    next();
});

export default router;