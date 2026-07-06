// Importa a função responsável por criar a aplicação Vue
import { createApp } from 'vue';

// Importa o Pinia, que será nosso gerenciador de estado
// (substitui o antigo Vuex)
import { createPinia } from 'pinia';

// Importa o componente raiz da aplicação
import App from './App.vue';

// Importa as rotas configuradas em router/index.js
import router from './router';

// Importa o CSS do Bootstrap
// Responsável por toda a aparência dos componentes (botões, grid, cards, etc.)
import 'bootstrap/dist/css/bootstrap.min.css';

// Importa o JavaScript do Bootstrap
// Necessário para funcionamento de Modal, Dropdown,
// Collapse, Offcanvas, Tooltip...
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

// CSS próprio da aplicação
import '../css/app.css';

// Axios será usado para fazer chamadas ao Laravel
import axios from 'axios';

// Informa ao Laravel que as requisições vieram via Ajax.
// Algumas funcionalidades do framework dependem desse cabeçalho.
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Faz com que cookies de autenticação sejam enviados automaticamente.
// Muito utilizado quando a autenticação usa Sanctum.
axios.defaults.withCredentials = true;

// Cria a aplicação Vue usando App.vue como componente principal
const app = createApp(App);

// Disponibiliza o axios em todos os componentes.
// Assim podemos usar:
// this.$http.get(...)
app.config.globalProperties.$http = axios;

// Registra o Pinia na aplicação
app.use(createPinia());

// Registra o Vue Router
app.use(router);

// Renderiza a aplicação dentro da div:
// <div id="app"></div>
app.mount('#app');