-- =============================================================
--  RUNNING MATE SYSTEM — DATABASE CHANGES
--  Run this in phpMyAdmin → evoting_db → SQL tab
-- =============================================================

USE evoting_db;

-- Add running_mate_id to candidates table
ALTER TABLE candidates 
ADD COLUMN running_mate_id INT NULL DEFAULT NULL,
ADD COLUMN is_running_mate TINYINT(1) NOT NULL DEFAULT 0,
ADD FOREIGN KEY (running_mate_id) REFERENCES candidates(candidate_id) ON DELETE SET NULL;

-- Add running_mate_enabled to positions table
ALTER TABLE positions
ADD COLUMN running_mate_enabled TINYINT(1) NOT NULL DEFAULT 0;

