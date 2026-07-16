-- Espelho do schema MySQL para os testes locais (mesma lógica, sintaxe do SQLite).
CREATE TABLE IF NOT EXISTS km_users (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  email             TEXT NOT NULL UNIQUE,
  name              TEXT NOT NULL,
  password_hash     TEXT NOT NULL,
  email_verified_at TEXT NULL,
  failed_logins     INTEGER NOT NULL DEFAULT 0,
  locked_until      TEXT NULL,
  created_at        TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS km_tokens (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES km_users(id) ON DELETE CASCADE,
  kind       TEXT NOT NULL,
  token_hash TEXT NOT NULL,
  expires_at TEXT NOT NULL,
  used_at    TEXT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_km_tokens_hash ON km_tokens(token_hash);
CREATE TABLE IF NOT EXISTS km_rate (
  rk       TEXT PRIMARY KEY,
  hits     INTEGER NOT NULL DEFAULT 0,
  reset_at TEXT NOT NULL
);
