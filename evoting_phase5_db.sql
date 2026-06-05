-- =============================================================
--  PHASE 5 — SECURITY ADDITIONS
--  Run this in phpMyAdmin → evoting_db → SQL tab
-- =============================================================

USE evoting_db;

-- OTP Codes Table
CREATE TABLE IF NOT EXISTS otp_codes (
    otp_id     INT          PRIMARY KEY AUTO_INCREMENT,
    voter_id   INT          NOT NULL,
    otp_code   VARCHAR(6)   NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id) REFERENCES voters(voter_id) ON DELETE CASCADE
);

-- Index for fast OTP lookup
CREATE INDEX idx_otp_voter ON otp_codes(voter_id);
