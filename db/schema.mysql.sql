-- Keymate · go2ireland.site — contas, tokens e limites de tentativa.
-- Rodar UMA vez no phpMyAdmin (hPanel -> Bancos de dados -> phpMyAdmin -> SQL).

CREATE TABLE IF NOT EXISTS km_users (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  email             VARCHAR(190) NOT NULL UNIQUE,
  name              VARCHAR(120) NOT NULL,
  password_hash     VARCHAR(255) NOT NULL,
  email_verified_at DATETIME NULL,
  failed_logins     INT NOT NULL DEFAULT 0,
  locked_until      DATETIME NULL,
  created_at        DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS km_tokens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  kind       VARCHAR(16) NOT NULL,          -- 'verify' | 'reset'
  token_hash CHAR(64) NOT NULL,             -- SHA-256 do token; o claro só vai no e-mail
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_km_tokens_hash (token_hash),
  INDEX idx_km_tokens_user (user_id, kind),
  CONSTRAINT fk_km_tokens_user FOREIGN KEY (user_id) REFERENCES km_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS km_rate (
  rk       VARCHAR(64) PRIMARY KEY,
  hits     INT NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  INDEX idx_km_rate_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
