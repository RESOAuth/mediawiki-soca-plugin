CREATE TABLE /*_*/soca_identity (
 identity_key BLOB PRIMARY KEY NOT NULL,
 issuer BLOB NOT NULL,
 subject BLOB NOT NULL,
 user_id INTEGER NOT NULL,
 created INTEGER NOT NULL
);
CREATE INDEX /*i*/soca_identity_user ON /*_*/soca_identity (user_id);
CREATE TABLE /*_*/soca_replay (
 state_hash BLOB PRIMARY KEY NOT NULL,
 browser_hash BLOB NOT NULL,
 expires INTEGER NOT NULL
);
CREATE TABLE /*_*/soca_setting (
 setting_key BLOB PRIMARY KEY NOT NULL,
 value BLOB NOT NULL,
 revision INTEGER NOT NULL
);
