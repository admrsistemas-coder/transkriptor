# Manual de Deploy e Automação (Etapa 6) 🚀

Este documento consolida o passo a passo de como o projeto `Transkriptor` deixou de ser um script manual no terminal e se tornou uma aplicação profissional 100% autônoma.

## O Que Foi Criado no Projeto:

### 1. `Dockerfile`
A "receita" do servidor perfeito. Esse arquivo instrui o Docker a montar uma máquina virtual contendo exatamente a versão certa do **PHP 8**, do **Apache**, do **Python 3**, e instalando o **FFmpeg** e o **faster-whisper** automaticamente. Tudo isso sem que você precise baixar ou configurar nada manualmente na sua própria máquina.

### 2. `docker-compose.yml`
Esse é o maestro da orquestra! Ele sobe **3 serviços simultâneos**:
- O **Banco de Dados MySQL** (carregando automaticamente a estrutura do banco).
- O **Servidor Web** (responsável pelo painel do navegador).
- O **Worker** (a engrenagem de Inteligência Artificial).

**O Grande Diferencial**: Graças a esse arquivo, você **não precisa mais abrir o terminal** para rodar o Python ou o PHP! O container do *Worker* roda invisível em segundo plano (background) e inicia automaticamente sempre que você ligar o computador (ou servidor).

### 3. `README.md`
O manual geral do seu projeto. Um documento ensinando a como instalar o projeto usando apenas um único comando mágico: `docker-compose up -d --build`.

---

## Como Fazer a Transição (De XAMPP para Docker)?

No momento de desenvolvimento, rodamos as coisas localmente via XAMPP. Mas para colocar a plataforma em uso contínuo no dia-a-dia, a transição para o Docker é altamente recomendada.

**Para fazer a transição, siga os passos:**

1. Certifique-se de que o [Docker Desktop](https://www.docker.com/products/docker-desktop) está instalado e rodando no seu Windows.
2. Desligue os módulos do Apache e MySQL no seu XAMPP (para liberar as portas `80` e `3306`).
3. Abra um terminal dentro da pasta `transkriptor`.
4. Digite o seguinte comando:
```bash
docker-compose up -d --build
```

O Docker fará o download de todas as imagens, construirá os serviços e deixará tudo rodando.
A partir desse ponto, a IA e a fila de processamento passam a operar de forma completamente **autônoma e invisível**. Você pode fechar o terminal e usar o sistema apenas pelo navegador acessando `http://localhost`.
