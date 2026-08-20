CREATE TABLE account (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  email TEXT NOT NULL,
  email_normalized TEXT NOT NULL,
  password_hash TEXT,
  timezone TEXT NOT NULL DEFAULT 'UTC',
  weight_unit TEXT NOT NULL DEFAULT 'lb' CHECK (weight_unit IN ('lb', 'kg')),
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE identities (
  id INTEGER PRIMARY KEY,
  provider TEXT NOT NULL CHECK (provider IN ('google', 'password')),
  provider_subject TEXT,
  created_at TEXT NOT NULL,
  UNIQUE (provider, provider_subject)
);
