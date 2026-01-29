# 🚀 Guia de Instalação - ISAC Sistema Acadêmico

## 📋 Requisitos
- XAMPP (PHP 8.0+ e MySQL 5.7+)
- Node.js 18+ (para o frontend)
- Git (opcional)

---

## 🖥️ Instalar num Novo Computador

### OPÇÃO A: Apenas Estrutura (Banco Vazio)

```bash
# 1. Copie a pasta do projeto para o novo PC (htdocs)
# 2. Crie o banco de dados no MySQL:
mysql -u root -p -e "CREATE DATABASE isac_academic"

# 3. Execute todas as migrações:
cd c:\xampp\htdocs\api-login
php migrations/run_all_migrations.php

# 4. (Opcional) Crie o usuário admin padrão:
php seeds/seed_users.php
```

### OPÇÃO B: Com Dados (Backup Completo)

**No computador de ORIGEM:**
```bash
cd c:\xampp\htdocs\api-login
php scripts/export_database.php
# Isto cria: backups/isac_backup_YYYYMMDD_HHMMSS.sql
```

**No computador de DESTINO:**
```bash
# 1. Copie a pasta do projeto (incluindo backups/) para o novo PC

# 2. Importe o banco de dados:
cd c:\xampp\htdocs\api-login
php scripts/import_database.php

# OU via linha de comando MySQL:
mysql -u root -p < backups/isac_backup_XXXXXX.sql

# OU via phpMyAdmin:
# - Abra http://localhost/phpmyadmin
# - Clique em "Importar"
# - Selecione o arquivo .sql
# - Clique em "Executar"
```

---

## ⚙️ Configuração

### 1. Banco de Dados (config/database.php)
```php
$host = 'localhost';
$dbname = 'isac_academic';
$username = 'root';
$password = '';  // Altere se tiver senha
```

### 2. Frontend (FrontOxford/Oxford/.env)
```env
VITE_API_URL=http://localhost/api-login/api
```

---

## 🚀 Iniciar o Sistema

### Backend (PHP):
1. Inicie o XAMPP (Apache + MySQL)
2. Verifique: http://localhost/api-login/api/health.php

### Frontend (React):
```bash
cd "FrontOxford - Cópia - Cópia/Oxford"
npm install
npm run dev
```
Acesse: http://localhost:5173

---

## 📁 Estrutura de Pastas
```
api-login/
├── api/              # Endpoints PHP
├── config/           # Configurações
├── migrations/       # Criação de tabelas
├── scripts/          # Scripts utilitários
│   ├── export_database.php  # Exportar banco
│   └── import_database.php  # Importar banco
├── backups/          # Backups do banco (criada automaticamente)
├── seeds/            # Dados iniciais
└── FrontOxford.../   # Frontend React
```

---

## 🔐 Credenciais Padrão

| Tipo       | Usuário              | Senha    |
|------------|---------------------|----------|
| Admin      | admin@isac.ac.mz    | admin123 |
| Professor  | (username)          | (senha)  |
| Estudante  | (enrollment_number) | (senha)  |

---

## ❓ Problemas Comuns

### "Connection refused"
- Verifique se o MySQL está rodando no XAMPP

### "Access denied"
- Verifique as credenciais em config/database.php

### "Table doesn't exist"
- Execute: `php migrations/run_all_migrations.php`

### "CORS error" no frontend
- Verifique se o Apache está rodando
- Verifique VITE_API_URL no .env do frontend
