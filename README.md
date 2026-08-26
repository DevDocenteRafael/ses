# Portal de Empregabilidade Senac DF ## 
Sistema desenvolvido para conectar alunos e empresas, facilitando processos de recrutamento, divulgação de oportunidades e acompanhamento dos indicadores de empregabilidade.

## Sobre o Projeto ## 
O Portal de Empregabilidade Senac DF tem como objetivo aproximar alunos e empresas por meio de uma plataforma moderna e intuitiva.

### Funcionalidades ## 
 
 #### Área do Aluno #### 
- Dashboard personalizado
- Visualização do perfil profissional
- Recebimento de convites para vagas
- Aceite ou recusa de oportunidades
- Acompanhamento do status do perfil
- Dicas de empregabilidade

#### Área da Empresa #### 
- Busca de talentos
- Gerenciamento de candidatos favoritos
- Envio de convites
- Gestão de vagas
- Acompanhamento de contratações

#### Área Administrativa #### 
- Indicadores de empregabilidade
- Gestão de empresas
- Gestão de alunos
- Relatórios gerenciais
- Dashboard analítico

## Tecnologias Utilizadas ##

### Backend ###
- Laravel
- PHP 8+
- MySQL

### Frontend ###
- Vue.js
- Bootstrap 5
- Axios
- Vite

### Infraestrutura ###
- Docker
- Docker Compose

##  Estrutura do Projeto ##
```bash
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
│   ├── js/
│   ├── css/
│   └── views/
├── routes/
├── storage/
├── tests/
├── docker/
├── docker-compose.yml
└── vite.config.js
```
### Pré-requisitos ###

Antes de iniciar o projeto, certifique-se de possuir instalado:

- PHP 8.2+
- Composer
- Node.js 18+
- NPM
- Docker
- Docker Compose
- MySQL

##  Instalação ##

### 1. Clonar o repositório ###
```bash
git clone: https://github.com/volktz/ses

cd ses
```
### 2. Instalar dependências do PHP ###
```bash
composer install
```
### 3. Instalar dependências do Frontend ###
```bash
npm install
```

### 4. Configurar ambiente ###

Copie o arquivo de exemplo:
```bash
copy .env.example .env
```

Configure as variáveis de ambiente conforme necessário para sqlite.

### 5. Gerar chave da aplicação ###
```bash
php artisan key:generate
```
### 6. Gerar arquivo de DB para testes(POWERSHELL) ###

```bash
New-Item -ItemType File -Path database\database.sqlite -Force
```

### 7. Executar migrações e seeds ###
```bash
php artisan migrate
php artisan db:seed
```

### Rodando servidor ###
Em um terminal
```bash
npm run dev
```

Em outro terminal diferente
```bash
php artisan serve
```

Acesse http://127.0.0.1:8000

## Executando com Docker ##

Suba os containers:
```bash
docker-compose up -d
```

Verifique os containers:
```bash
docker ps
```

## Executando o Projeto ##

### Terminal 1 - Backend Laravel ###
```bash
php artisan serve
```

A aplicação estará disponível em:
```text
http://127.0.0.1:8000
---

### Terminal 2 - Frontend Vite
```bash
npm run dev
```
O Vite iniciará o servidor de desenvolvimento e fará o hot reload automaticamente.

## Principais Dependências ##

### Laravel
```bash
composer install
```
### Vue
```bash
npm install vue
```
### Axios
```bash
npm install axios
```
### Bootstrap
```bash
npm install bootstrap
```
### Vite
```bash
npm install vite
```

## Telas do Sistema ##
### Dashboard Administrativo ###
- Indicadores de empregabilidade
- Gestão de empresas
- Relatórios gerenciais

### Dashboard do Aluno ###
- Perfil profissional
- Convites recebidos
- Acompanhamento de oportunidades

### Dashboard da Empresa ###
- Busca de talentos
- Convites enviados
- Gestão de candidatos

## Perfis de Acesso ##
### Administrador ###
Responsável pelo gerenciamento completo da plataforma.

### Empresa ###
Responsável pelo recrutamento e seleção de candidatos.

### Aluno
Responsável pela atualização do perfil e participação em processos seletivos.

## Comandos Úteis ##

Limpar cache:
```bash
php artisan optimize:clear
```
Executar migrations:
```bash
php artisan migrate
```
Reverter migrations:
```bash
php artisan migrate:rollback
```
Compilar assets para produção:
```bash
npm run build
```
## Equipe de Desenvolvimento ##
Projeto desenvolvido como parte da solução de empregabilidade do Senac DF.
## Licença ##

Este projeto é destinado para fins acadêmicos e institucionais do Senac DF.
