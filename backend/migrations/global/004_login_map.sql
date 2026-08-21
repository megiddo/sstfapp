CREATE TABLE login_map (
  provider TEXT NOT NULL CHECK (provider IN ('google', 'password')),
  login_key TEXT NOT NULL,
  repo_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  PRIMARY KEY (provider, login_key)
);

CREATE INDEX login_map_repo ON login_map (repo_hash);
