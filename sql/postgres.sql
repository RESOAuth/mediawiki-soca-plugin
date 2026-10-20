CREATE TABLE /*_*/soca_identity (
 identity_key BYTEA PRIMARY KEY NOT NULL,
 issuer BYTEA NOT NULL,
 subject BYTEA NOT NULL,
 user_id INTEGER NOT NULL,
 created BIGINT NOT NULL
);
CREATE INDEX soca_identity_user ON /*_*/soca_identity (user_id);
CREATE TABLE /*_*/soca_replay (
 state_hash BYTEA PRIMARY KEY NOT NULL,
 browser_hash BYTEA NOT NULL,
 expires BIGINT NOT NULL
);
CREATE TABLE /*_*/soca_setting (
 setting_key BYTEA PRIMARY KEY NOT NULL,
 value BYTEA NOT NULL,
 revision INTEGER NOT NULL
);
