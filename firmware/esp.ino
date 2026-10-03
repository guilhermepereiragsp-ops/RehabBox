#include <Wire.h>
#include "DFRobot_RGBLCD1602.h"
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <time.h>

DFRobot_RGBLCD1602 lcd(16, 2);

// ================= CONFIGURAÇÃO WIFI / BD =================
const char* ssid       = "Laura";
const char* password   = "12345678";
const char* serverURL = "http://172.20.10.2/projeto/frontend/api/api.php";

const int PATIENT_ID = 3;

// ================= PINOS =================
#define VRY 35
#define SW 27
#define BTN_R 14
#define BTN_G 12
#define BTN_B 13
#define LED_R 4
#define LED_G 26
#define LED_B 33
#define TOUCH_PIN 15

// ================= VARIÁVEIS GLOBAIS =================
int gameGroupId = -1;   // id da GameSession criada
int nivelGlobal = 1;    // dificuldade lida da BD

// ================= MÉTRICAS - JOGO BOTÕES =================
int errosBotao    = 0;
int acertosBotao  = 0;
int totalReacoes  = 0;
unsigned long somaTempo = 0;
int gameIdBotoes = -1;

// ================= MÉTRICAS - JOGO OBSTÁCULOS =================
int colisoesBotao    = 0;
int gameIdObstaculos = -1;

// ================= MÉTRICAS - JOGO TOUCH =================
int touchAcertos   = 0;
int touchErros     = 0;
int touchMuitoCedo = 0;
int gameIdTouch    = -1;

// ================= WIFI =================
void connectWiFi() {
  lcd.clear();
  lcd.print("A ligar WiFi...");
  WiFi.begin(ssid, password);
  int tentativas = 0;
  while (WiFi.status() != WL_CONNECTED && tentativas < 20) {
    delay(500);
    Serial.print(".");
    tentativas++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    lcd.clear();
    lcd.print("WiFi OK!");
    configTime(0, 0, "pool.ntp.org", "time.nist.gov");
    Serial.print("Sincronizando relogio");
    time_t agora = time(nullptr);
    int contador = 0;
    while (agora < 24 * 3600 && contador < 20) {
      delay(500);
      Serial.print(".");
      agora = time(nullptr);
      contador++;
    }
    Serial.println();
    lcd.clear();
    lcd.print("Relogio OK");
    delay(1500);
  } else {
    lcd.clear();
    lcd.print("WiFi FALHOU");
    delay(2000);
  }
}

// ================= HTTP POST =================
int httpPost(String endpoint, String jsonBody) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi nao ligado!");
    return -1;
  }
  HTTPClient http;
  String url = String(serverURL) + endpoint;
  Serial.println("URL: " + url);
  Serial.println("JSON: " + jsonBody);
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  int httpCode = http.POST(jsonBody);
  Serial.print("HTTP Code: ");
  Serial.println(httpCode);
  int idCriado = -1;
  if (httpCode > 0) {
    String resposta = http.getString();
    Serial.println("Resposta: " + resposta);
    StaticJsonDocument<256> doc;
    if (deserializeJson(doc, resposta) == DeserializationError::Ok) {
      if (doc.containsKey("id"))            idCriado = doc["id"].as<int>();
      if (doc.containsKey("game_group_id")) idCriado = doc["game_group_id"].as<int>();
    }
  } else {
    Serial.print("Erro HTTP: ");
    Serial.println(http.errorToString(httpCode).c_str());
  }
  http.end();
  return idCriado;
}

// ================= OBTER DATA ATUAL =================
String obterDataAtual() {
  time_t now = time(nullptr);
  struct tm* timeinfo = localtime(&now);
  char buffer[11];
  sprintf(buffer, "%04d-%02d-%02d",
    timeinfo->tm_year + 1900,
    timeinfo->tm_mon + 1,
    timeinfo->tm_mday);
  return String(buffer);
}

// ================= OBTER DIFICULDADE DO PACIENTE NA BD ← NOVO =================
int obterDificuldadePaciente() {
  if (WiFi.status() != WL_CONNECTED) return 1;
  HTTPClient http;
  String url = String(serverURL) + "?action=get_patient_difficulty&patient_id=" + String(PATIENT_ID);
  http.begin(url);
  int httpCode = http.GET();
  int nivel = 1;
  if (httpCode > 0) {
    String resposta = http.getString();
    Serial.println("Dificuldade resposta: " + resposta);
    StaticJsonDocument<128> doc;
    if (deserializeJson(doc, resposta) == DeserializationError::Ok) {
      if (doc.containsKey("difficulty")) {
        nivel = doc["difficulty"].as<int>();
        if (nivel < 1 || nivel > 3) nivel = 1;
      }
    }
  } else {
    Serial.println("Erro ao obter dificuldade, usando nivel 1");
  }
  http.end();
  Serial.print("Dificuldade do paciente: ");
  Serial.println(nivel);
  return nivel;
}

// ================= CRIAR GAME SESSION ← NOVO =================
// Substitui obterProximoGameGroupId(). Cria uma linha em
// GameSession(patient_id) e devolve o id gerado.
int criarGameSession() {
  StaticJsonDocument<128> doc;
  doc["patient_id"] = PATIENT_ID;
  String body;
  serializeJson(doc, body);
  int id = httpPost("?action=create_game_session", body);
  Serial.print("GameSession criada com ID: ");
  Serial.println(id);
  return id;
}

// ================= CRIAR JOGO ← inclui duration_minutes =================
int criarJogo(String nome, int finalScore, int durationMinutes) {
  String dif = (nivelGlobal == 1) ? "facil" : (nivelGlobal == 2) ? "medio" : "dificil";
  StaticJsonDocument<256> doc;
  doc["name"]             = nome;
  doc["date"]             = obterDataAtual();
  doc["difficulty"]       = nivelGlobal;
  doc["final_score"]      = finalScore;
  doc["patient_id"]       = PATIENT_ID;
  doc["game_group_id"]    = gameGroupId;
  doc["duration_minutes"] = durationMinutes;  // ← campo novo
  String body;
  serializeJson(doc, body);
  int id = httpPost("?action=create_game", body);
  Serial.print("Game criado com ID: ");
  Serial.println(id);
  return id;
}

// ================= ATUALIZAR FINAL_SCORE =================
void atualizarScoreJogo(int gameId, int finalScore) {
  if (WiFi.status() != WL_CONNECTED || gameId == -1) return;
  HTTPClient http;
  String url = String(serverURL) + "?action=update_game_score";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  StaticJsonDocument<128> doc;
  doc["game_id"]     = gameId;
  doc["final_score"] = finalScore;
  String body;
  serializeJson(doc, body);
  int httpCode = http.POST(body);
  Serial.print("Score atualizado. HTTP: ");
  Serial.println(httpCode);
  http.end();
}

// ================= ATUALIZAR DURAÇÃO ← NOVO =================
void atualizarDuracaoJogo(int gameId, int durationMinutes) {
  if (WiFi.status() != WL_CONNECTED || gameId == -1) return;
  HTTPClient http;
  String url = String(serverURL) + "?action=update_game_duration";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  StaticJsonDocument<128> doc;
  doc["game_id"]          = gameId;
  doc["duration_minutes"] = durationMinutes;
  String body;
  serializeJson(doc, body);
  int httpCode = http.POST(body);
  Serial.print("Duracao atualizada. HTTP: ");
  Serial.println(httpCode);
  http.end();
}

// ================= REGISTAR ERRO NA BD =================
void registarErro(int gameId, String descricao) {
  if (gameId == -1) return;
  StaticJsonDocument<256> doc;
  doc["description"] = descricao;
  doc["game_id"]     = gameId;
  String body;
  serializeJson(doc, body);
  httpPost("?action=log_error", body);
}

// ================= REGISTAR RONDA COLORGAME =================
void registarRondaColorGame(int gameId, int ronda, String corAcendeu, String corClicada, bool acertou) {
  if (gameId == -1) return;
  StaticJsonDocument<256> doc;
  doc["game_id"]     = gameId;
  doc["ronda"]       = ronda;
  doc["cor_acendeu"] = corAcendeu;
  doc["cor_clicada"] = corClicada;
  doc["acertou"]     = acertou ? 1 : 0;
  String body;
  serializeJson(doc, body);
  httpPost("?action=log_color_round", body);
}


// ================= COUNTDOWN =================
void countdown(String nomeJogo) {
  lcd.clear();
  lcd.print(nomeJogo);
  for (int i = 3; i > 0; i--) {
    lcd.setCursor(0, 1);
    lcd.print("Comeca em: ");
    lcd.print(i);
    delay(1000);
  }
  lcd.clear();
}

// ================= ESPERA JOYSTICK =================
void waitStart() {
  lcd.clear();
  lcd.print("Carrega joystick");
  lcd.setCursor(0, 1);
  lcd.print("para comecar");
  while (digitalRead(SW) == HIGH);
  delay(300);
}

// ================= JOGO BOTÕES =================
void gameReaction() {
  waitStart();
  unsigned long inicioJogo = millis();  // ← medir duração real

  gameIdBotoes = criarJogo("Botoes", 0, 0);
  Serial.print("Game ID Botoes: ");
  Serial.println(gameIdBotoes);

  countdown("Botoes");

  int numRondas = 8;
  int tempoLED  = (nivelGlobal == 1) ? 5000 : (nivelGlobal == 2) ? 3000 : 1500;  // ← varia com dificuldade
  int pausaRonda = (nivelGlobal == 1) ? 1500 : (nivelGlobal == 2) ? 1000 : 600;

  errosBotao   = 0;
  acertosBotao = 0;

  const char* nomesCores[] = {"VERMELHO", "VERDE", "AZUL"};

for (int ronda = 0; ronda < numRondas; ronda++) {
    int cor = random(0, 3);

    lcd.clear();
    lcd.print("Ronda "); lcd.print(ronda + 1);
    lcd.setCursor(0, 1);
 
    delay(1000);

    digitalWrite(LED_R, LOW);
    digitalWrite(LED_G, LOW);
    digitalWrite(LED_B, LOW);

    if      (cor == 0) { digitalWrite(LED_R, HIGH); Serial.println("Cor sorteada: VERMELHO"); }
    else if (cor == 1) { digitalWrite(LED_G, HIGH); Serial.println("Cor sorteada: VERDE");    }
    else               { digitalWrite(LED_B, HIGH); Serial.println("Cor sorteada: AZUL");     }

    lcd.clear();
    lcd.print("Ronda "); lcd.print(ronda + 1);
    lcd.setCursor(0, 1);
   

    unsigned long start = millis();
    bool acertou        = false;
    bool respondeu      = false;
    int  botaoPremido   = -1;

    while (millis() - start < (unsigned long)tempoLED) {
      int leituraR = digitalRead(BTN_R);
      int leituraG = digitalRead(BTN_G);
      int leituraB = digitalRead(BTN_B);

      if (leituraR == HIGH || leituraG == HIGH || leituraB == HIGH) {
        delay(20);
        leituraR = digitalRead(BTN_R);
        leituraG = digitalRead(BTN_G);
        leituraB = digitalRead(BTN_B);

        if (leituraR == HIGH || leituraG == HIGH || leituraB == HIGH) {
          respondeu = true;
          Serial.print("R: "); Serial.print(leituraR);
          Serial.print(" G: "); Serial.print(leituraG);
          Serial.print(" B: "); Serial.println(leituraB);

          if      (leituraR == HIGH) botaoPremido = 0;
          else if (leituraG == HIGH) botaoPremido = 1;
          else                       botaoPremido = 2;

          Serial.print("Botao pressionado: ");
          Serial.println(nomesCores[botaoPremido]);

          acertou = (botaoPremido == cor);
          if (acertou) Serial.println("  -> ACERTO!");
          else         Serial.println("  -> ERRO! Era " + String(nomesCores[cor]));
          break;
        }
      }
      delay(10);
    }

    digitalWrite(LED_R, LOW);
    digitalWrite(LED_G, LOW);
    digitalWrite(LED_B, LOW);

    // Prepara os dados para a ColorGame
    String corAcendeuStr = nomesCores[cor];                         // VERMELHO, VERDE ou AZUL
    String corClicadaStr = respondeu ? nomesCores[botaoPremido] : "NENHUM";

    if (acertou) {
      acertosBotao++;
      lcd.clear();
      lcd.print("ACERTO!");
      Serial.print("Ronda "); Serial.print(ronda + 1); Serial.println(" - ACERTO");
    } else {
      errosBotao++;
      String desc = "Ronda " + String(ronda + 1);
      desc += respondeu ? " Erro Cor" : " Timeout";
      registarErro(gameIdBotoes, desc);
      lcd.clear();
      lcd.print(respondeu ? "ERRADO!" : "TIMEOUT!");
      Serial.print("Ronda "); Serial.print(ronda + 1); Serial.println(" - ERRO: " + desc);
    }

    // NOVO: envia os dados da ronda para a tabela ColorGame
    registarRondaColorGame(gameIdBotoes, ronda + 1, corAcendeuStr, corClicadaStr, acertou);

    delay(pausaRonda);
}

  // ← Duração real + score
  int duracaoMin = (int)((millis() - inicioJogo) / 60000UL);
  if (duracaoMin < 1) duracaoMin = 1;
  atualizarDuracaoJogo(gameIdBotoes, duracaoMin);

  int totalBotoes = acertosBotao + errosBotao;
  int scoreBotoes = (totalBotoes > 0) ? (acertosBotao * 100) / totalBotoes : 0;
  atualizarScoreJogo(gameIdBotoes, scoreBotoes);

  Serial.print("Score Botoes: ");
  Serial.print(acertosBotao); Serial.print(" / ");
  Serial.print(totalBotoes);  Serial.print(" = ");
  Serial.println(scoreBotoes);
}

// ================= JOGO OBSTÁCULOS =================
void gameObstacle() {
  waitStart();
  unsigned long inicioJogo = millis();  // ← medir duração real

  gameIdObstaculos = criarJogo("Obstaculos", 0, 0);
  countdown("Obstaculos");

  const unsigned long DURACAO_MS = 30000UL;
  int speed = (nivelGlobal == 1) ? 200 : (nivelGlobal == 2) ? 120 : 80;
  int playerY = 1, lastMove = 1, obstacleX = 15, obstacleY = random(0, 2);
  unsigned long inicio = millis();

  colisoesBotao = 0;
  int obstaculosCriados = 0;

  while (millis() - inicio < DURACAO_MS) {
    int yVal = analogRead(VRY);
    if      (yVal < 1000 && lastMove != 0) { playerY = 0; lastMove = 0; }
    else if (yVal > 3000 && lastMove != 1) { playerY = 1; lastMove = 1; }

    if (obstacleX == 15) obstaculosCriados++;
    obstacleX--;
    if (obstacleX < 0) { obstacleX = 15; obstacleY = random(0, 2); }

    if (obstacleX == 1 && playerY == obstacleY) {
      colisoesBotao++;
      registarErro(gameIdObstaculos, "Colisao");
      Serial.print("Colisao detectada: "); Serial.println(colisoesBotao);
      lcd.setRGB(255, 0, 0); delay(400); lcd.setRGB(0, 0, 255);
      obstacleX = 15;
    }

    lcd.clear();
    lcd.setCursor(12, 0); lcd.print((DURACAO_MS - (millis() - inicio)) / 1000); lcd.print("s");
    lcd.setCursor(1, playerY); lcd.print("O");
    lcd.setCursor(obstacleX, obstacleY); lcd.print("#");
    delay(speed);
  }

  // ← Duração real + score
  int duracaoMin = (int)((millis() - inicioJogo) / 60000UL);
  if (duracaoMin < 1) duracaoMin = 1;
  atualizarDuracaoJogo(gameIdObstaculos, duracaoMin);

  int obstaculosEvitados = max(0, obstaculosCriados - colisoesBotao);
  int scoreObstaculos = (obstaculosCriados > 0) ? (obstaculosEvitados * 100) / obstaculosCriados : 0;
  atualizarScoreJogo(gameIdObstaculos, scoreObstaculos);

  Serial.print("Score Obstaculos: ");
  Serial.print(obstaculosEvitados); Serial.print(" / ");
  Serial.print(obstaculosCriados);  Serial.print(" = ");
  Serial.println(scoreObstaculos);
}

// ================= JOGO TOUCH (RITMO) =================
void gameTouch() {
  waitStart();
  unsigned long inicioJogo = millis();  // ← medir duração real

  gameIdTouch = criarJogo("Touch", 0, 0);
  countdown("Ritmo");

  unsigned long inicioSessao = millis();
  const unsigned long DURACAO_TOTAL = 30000UL;

  // ← parâmetros variam com dificuldade
  int tempoFlashLED = (nivelGlobal == 1) ? 1500 : (nivelGlobal == 2) ? 1000 : 600;
  int margemExtra   = (nivelGlobal == 1) ? 1200 : (nivelGlobal == 2) ?  900 : 500;
  int esperaMin     = (nivelGlobal == 1) ? 2000 : (nivelGlobal == 2) ? 1200 : 700;
  int esperaMax     = (nivelGlobal == 1) ? 3500 : (nivelGlobal == 2) ? 2500 : 1500;

  int batidaContador = 0;
  touchAcertos   = 0;
  touchErros     = 0;
  touchMuitoCedo = 0;

  while (millis() - inicioSessao < DURACAO_TOTAL) {
    batidaContador++;
    int tempoEspera = random(esperaMin, esperaMax);

    unsigned long startEspera = millis();
    bool tocouCedo = false;

    while (millis() - startEspera < (unsigned long)tempoEspera) {
      unsigned long tempoRestante = (DURACAO_TOTAL - (millis() - inicioSessao)) / 1000;
      lcd.clear();
      lcd.print("Ritmo - T:"); lcd.print(tempoRestante); lcd.print("s");
      lcd.setCursor(0, 1);
      lcd.print("Aguarde...");
      if (digitalRead(TOUCH_PIN) == LOW) {
        tocouCedo = true;
        break;
      }
      delay(50);
    }

    if (tocouCedo) {
      touchErros++;
      lcd.clear();
      lcd.print("MUITO CEDO!");
      lcd.setCursor(0, 1);
      lcd.print("Espera sinal");
      registarErro(gameIdTouch, "Muito Cedo - Batida " + String(batidaContador));
      Serial.print("Batida "); Serial.print(batidaContador); Serial.println(" - MUITO CEDO");
      delay(1000);
      continue;
    }

    digitalWrite(LED_B, HIGH);
    lcd.clear();


    unsigned long startSinal = millis();
    bool acertou    = false;
    bool deuTimeout = false;

    while (millis() - startSinal < (unsigned long)(tempoFlashLED + margemExtra)) {
      if (digitalRead(TOUCH_PIN) == LOW) {
        acertou = true;
        break;
      }
      if (millis() - startSinal >= (unsigned long)tempoFlashLED) {
        digitalWrite(LED_B, LOW);
      }
      delay(10);
    }
    digitalWrite(LED_B, LOW);
    if (!acertou) deuTimeout = true;

    int tempoDebounce = 0;
    while (digitalRead(TOUCH_PIN) == LOW && tempoDebounce < 500) {
      delay(10);
      tempoDebounce += 10;
    }
    delay(100);

    if (acertou) {
      touchAcertos++;
      lcd.clear();
      lcd.print("BOA! ACERTOU!");
      Serial.print("Batida "); Serial.print(batidaContador); Serial.println(" - ACERTO");
    } else if (deuTimeout) {
      touchErros++;
      lcd.clear();
      lcd.print("TEMPO ESGOTADO!");
      registarErro(gameIdTouch, "Timeout - Batida " + String(batidaContador));
      Serial.print("Batida "); Serial.print(batidaContador); Serial.println(" - TIMEOUT");
    }
    delay(1000);
  }

  // ← Duração real + score
  int duracaoMin = (int)((millis() - inicioJogo) / 60000UL);
  if (duracaoMin < 1) duracaoMin = 1;
  atualizarDuracaoJogo(gameIdTouch, duracaoMin);

  int totalTouch = touchAcertos + touchErros;
  int scoreTouch = (totalTouch > 0) ? (touchAcertos * 100) / totalTouch : 0;
  atualizarScoreJogo(gameIdTouch, scoreTouch);

  Serial.print("Score Touch: ");
  Serial.print(touchAcertos); Serial.print(" / ");
  Serial.print(totalTouch);   Serial.print(" = ");
  Serial.println(scoreTouch);

  lcd.clear();
  lcd.print("Fim do Ritmo!");
  delay(2000);
}

// ================= RESULTADOS =================
void showResults() {
  String dif = (nivelGlobal == 1) ? "Facil" : (nivelGlobal == 2) ? "Medio" : "Dificil";
  lcd.clear();
  lcd.print("Dif: "); lcd.print(dif);
  lcd.setCursor(0, 1);
  lcd.print("Botao: "); lcd.print(acertosBotao); lcd.print("/8");
  delay(3000);

  lcd.clear();
  lcd.print("Touch: "); lcd.print(touchAcertos);
  lcd.setCursor(0, 1);
  lcd.print("Colisoes: "); lcd.print(colisoesBotao);
  delay(3000);
}

// ================= SETUP =================
void setup() {
  Serial.begin(115200);
  lcd.init();
  lcd.setRGB(0, 0, 255);
  pinMode(SW,        INPUT_PULLUP);
  pinMode(BTN_R,     INPUT);
  pinMode(BTN_G,     INPUT);
  pinMode(BTN_B,     INPUT);
  pinMode(LED_R,     OUTPUT);
  pinMode(LED_G,     OUTPUT);
  pinMode(LED_B,     OUTPUT);
  pinMode(TOUCH_PIN, INPUT_PULLUP);
  randomSeed(analogRead(34));
  connectWiFi();
}

// ================= LOOP =================
void loop() {
  // 1. Buscar dificuldade do paciente
  nivelGlobal = obterDificuldadePaciente();

  String nomeDif = (nivelGlobal == 1) ? "Facil" : (nivelGlobal == 2) ? "Medio" : "Dificil";
  lcd.clear();
  lcd.print("Dificuldade:");
  lcd.setCursor(0, 1);
  lcd.print(nomeDif);
  delay(2000);

  // 2. Esperar que o utilizador pressione o joystick para criar a sessão
  lcd.clear();
  lcd.print("Pressione o");
  lcd.setCursor(0, 1);
  lcd.print("joystick p/ iniciar");
  while (digitalRead(SW) == HIGH);   // aguarda pressionar
  delay(300);                        // debounce

  // 3. Só agora cria a GameSession
  gameGroupId = criarGameSession();
  if (gameGroupId == -1) {
    lcd.clear();
    lcd.print("ERRO sessao!");
    delay(3000);
    return;
  }

  lcd.clear();
  lcd.print("Sessao pronta");
  lcd.setCursor(0, 1);
  lcd.print("ID Paciente: "); lcd.print(PATIENT_ID);
  delay(1500);

  // 4. Jogos (cada um com o seu próprio waitStart())
  gameReaction();
  gameObstacle();
  gameTouch();

  showResults();

  // Reset de métricas e IDs
  errosBotao       = 0;
  acertosBotao     = 0;
  colisoesBotao    = 0;
  touchAcertos     = 0;
  touchErros       = 0;
  touchMuitoCedo   = 0;
  totalReacoes     = 0;
  somaTempo        = 0;
  gameGroupId      = -1;
  gameIdBotoes     = -1;
  gameIdObstaculos = -1;
  gameIdTouch      = -1;

  delay(5000);
}