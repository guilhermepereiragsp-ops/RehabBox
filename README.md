# RehabBox — Reabilitação e Monitorização Remota da Doença de Parkinson

Projeto desenvolvido na UC de Telemedicina e Telessaúde (2025/2026), Mestrado em Engenharia Biomédica, ISEP.

## Conceito

A RehabBox é uma consola física de jogos terapêuticos para pacientes com Doença de Parkinson (estágios II–III da escala Hoehn & Yahr). O paciente joga em casa, com botões, joystick e sensor de toque, e um ESP32 processa localmente os eventos (acertos, erros, timeouts, colisões, tempos de reação) e calcula as métricas de cada sessão (edge computing). Os resultados são enviados por Wi-Fi para uma plataforma web, onde o médico acompanha a evolução do paciente à distância.

## Aplicação

- **Três jogos:** correspondência de cores (atenção visual), desvio de obstáculos com joystick (coordenação motora fina e dupla tarefa) e reação a estímulos com sensor de toque (bradicinesia e velocidade psicomotora).
- **Comunicação bidirecional:** o médico define o nível de dificuldade (1 a 3) no portal e o ESP32 obtém-no via API no início de cada sessão.
- **Portal web com três perfis:**
  - *Paciente:* perfil, avaliações clínicas, exportação e pedido de revogação de consentimento.
  - *Médico:* lista de pacientes, histórico de sessões, controlo de dificuldade e avaliação clínica.
  - *Administrador:* dashboard global, aprovação de registos, pedidos de revogação e logs de segurança.
- **Segurança e RGPD:** palavras-passe com bcrypt, reCAPTCHA v2, controlo de acessos por perfil, consentimentos separados e auditoria de logins com bloqueio de IPs suspeitos.

## Tecnologias

| Componente | Tecnologia |
|---|---|
| Hardware | ESP32 WROOM, joystick KY-023, 3 botões, sensor tátil capacitivo, LCD 16x2, LED RGB |
| Firmware | C++ no Arduino IDE (leitura do ADC de 12 bits, lógica dos jogos, envio de dados por HTTP/JSON) |
| Web | PHP, HTML, CSS e JavaScript |
| Base de dados | MySQL |
| Ambiente local | Laragon |

## Diretórios

```
.
├── firmware/      # Código do ESP32 (Arduino IDE): ADC, jogos e envio de dados
├── web/           # Portal web (PHP + JS): index, login/registo e painéis
├── database/      # Dump SQL da base de dados (TMTS)
└── relatorio.pdf  # Relatório do projeto
```

## Como executar

**Servidor (Laragon)**

1. Iniciar o Apache e o MySQL no Laragon (*Start All*).
2. Colocar a pasta do portal web dentro de `C:\laragon\www\`.
3. No HeidiSQL, criar a base de dados e importar o ficheiro `.sql`.
4. Confirmar no código PHP as credenciais de ligação à base de dados (por omissão no Laragon: `root`, sem password).
5. Abrir `http://localhost/<nome_da_pasta>` no navegador.

**Dispositivo (Arduino IDE)**

1. Instalar o suporte para placas ESP32 e abrir o código da pasta `firmware/`.
2. Configurar o nome e a password da rede Wi-Fi e o endereço IP do computador que corre o Laragon, que tem de estar na mesma rede local que o ESP32.
3. Carregar o código para o ESP32.

## Autores

Guilherme Pereira, Laura Freitas e Mariana Sá  
Mestrado em Engenharia Biomédica, ISEP, Porto, Portugal  
Docentes: Ana Faria e Nuno Gueiral
