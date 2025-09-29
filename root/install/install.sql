CREATE TABLE users (
    username VARCHAR(255) PRIMARY KEY,
    password VARCHAR(255) NOT NULL,
    admin BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE ip_blacklist (
    ip_address VARCHAR(45) PRIMARY KEY,
    login_attempts INT NOT NULL,
    blacklisted BOOLEAN NOT NULL,
    timestamp INT NOT NULL
);
