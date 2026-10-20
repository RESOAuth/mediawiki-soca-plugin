CREATE TABLE /*_*/soca_identity (
 identity_key VARBINARY(64) PRIMARY KEY NOT NULL,
 issuer BLOB NOT NULL,
 subject VARBINARY(255) NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 created INT UNSIGNED NOT NULL,
 INDEX soca_identity_user (user_id)
) /*$wgDBTableOptions*/;
CREATE TABLE /*_*/soca_replay (
 state_hash VARBINARY(64) PRIMARY KEY NOT NULL,
 browser_hash VARBINARY(64) NOT NULL,
 expires INT UNSIGNED NOT NULL
) /*$wgDBTableOptions*/;
CREATE TABLE /*_*/soca_setting (
 setting_key VARBINARY(200) PRIMARY KEY NOT NULL,
 value MEDIUMBLOB NOT NULL,
 revision INT UNSIGNED NOT NULL
) /*$wgDBTableOptions*/;
