n# Portal de Empregabilidade Senac DF ## 
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
### 2. Instalar dependências do PHP (No PowerShell)###
```bash
composer install
```
### 3. Instalar dependências do Frontend (No CMD)###
```bash
npm install
npm audit fix
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
### 6. Crie dentro do MySQL um banco de dados com o nome: ###

ses_db

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
Puxar todos os usuários principais
```bash
php artisan migrate:fresh --seed
```

Puxar UsersFicticios
```bash
php artisan db:seed --class=CandidatosFicticiosSeeder
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


**Instalação Mailpit**

## Configuração do Mailpit

O projeto utiliza o **Mailpit** para testar o envio de e-mails em ambiente de desenvolvimento.

O Mailpit captura as mensagens enviadas pela aplicação e permite visualizá-las em uma interface web, sem enviar os e-mails aos destinatários reais.

### 1. Pré-requisitos

É necessário ter instalado:

- Docker Desktop
- Docker Compose (incluído no Docker Desktop)

Download do Docker Desktop:
https://www.docker.com/products/docker-desktop/

### 2. Baixar e executar o Mailpit

Abra o Docker Desktop e aguarde sua inicialização.

Em seguida, abra um terminal (PowerShell ou terminal do VS Code) e execute:

```bash
docker pull axllent/mailpit
```

Esse comando baixa a imagem oficial do Mailpit.

Depois, execute:

```bash
docker run -d --name mailpit -p 127.0.0.1:8025:8025 -p 127.0.0.1:1025:1025 axllent/mailpit
```

Esse comando cria e inicia o contêiner Mailpit.

**Atenção:** se o projeto já possuir o serviço Mailpit configurado no `docker-compose.yml`, não crie outro contêiner. Nesse caso, utilize:

```bash
docker compose up -d mailpit
```

### 3. Acessar o Mailpit

Com o contêiner em execução, abra o navegador e acesse:

http://localhost:8025

A interface do Mailpit exibirá os e-mails capturados durante os testes.

**Portas utilizadas:**

| Porta | Função |
|-------|--------|
| 8025 | Interface web do Mailpit |
| 1025 | Servidor SMTP para receber e-mails |

### 4. Configurar o Laravel

Abra o arquivo `.env` na raiz do projeto.

Se o Laravel estiver rodando diretamente no computador, configure:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@senacdf.local"
MAIL_FROM_NAME="Portal de Empregabilidade Senac DF"
```

O endereço do remetente acima é apenas um exemplo para desenvolvimento.

**Importante:** caso o Laravel também esteja rodando dentro de um contêiner Docker na mesma rede do Mailpit, utilize:

```env
MAIL_HOST=mailpit
MAIL_PORT=1025
```

As demais configurações permanecem conforme o ambiente.

### 5. Limpar o cache de configuração

Após editar o `.env`, execute na raiz do projeto:

```bash
php artisan config:clear
```

Se o Laravel estiver em um contêiner, execute o comando dentro do contêiner da aplicação.

### 6. Testar o envio de e-mail

Com o Laravel e o Mailpit funcionando, execute uma funcionalidade existente da plataforma que envie um e-mail de teste.

Depois, acesse:

http://localhost:8025

A mensagem deverá aparecer na caixa de entrada do Mailpit.

Se o projeto utilizar filas para enviar e-mails, certifique-se de que o worker da fila esteja em execução.

### 7. Comandos úteis

**Verificar se o Mailpit está rodando:**

```bash
docker ps
```

**Parar o Mailpit:**

```bash
docker stop mailpit
```

**Iniciar novamente:**

```bash
docker start mailpit
```

**Consultar os logs:**

```bash
docker logs mailpit
```

**Remover o contêiner:**

```bash
docker stop mailpit
docker rm mailpit
```

Se o Mailpit for gerenciado pelo Docker Compose, prefira os comandos correspondentes do Compose.

### 8. Problemas comuns

**A página localhost:8025 não abre:**

Verifique se o Docker Desktop está aberto e se o contêiner está em execução.

**Os e-mails não aparecem:**

Verifique o `MAIL_HOST`, a porta `1025` e se as configurações do Laravel foram atualizadas.

**Erro de porta já utilizada:**

Verifique se outro contêiner ou programa está usando as portas `8025` ou `1025`.

**Erro informando que o contêiner já existe:**

Execute:

```bash
docker start mailpit
```

Não é necessário criar outro contêiner.

### 9. Observações importantes

- O Mailpit deve ser utilizado em ambiente de desenvolvimento e testes.
- Os e-mails capturados não são enviados automaticamente aos destinatários reais.
- Não utilize o Mailpit como serviço de e-mail de produção.
- Não exponha a interface web do Mailpit publicamente sem medidas de segurança.
- As mensagens podem conter informações sensíveis de testes.
- Por padrão, mensagens podem ser perdidas quando o contêiner é removido, caso não exista armazenamento persistente configurado.

### Documentação oficial

- Site: https://mailpit.axllent.org/
- Instalação: https://mailpit.axllent.org/docs/install/
- Docker: https://mailpit.axllent.org/docs/install/docker/
- GitHub: https://github.com/axllent/mailpit
