-- =========================
-- schema.sql  (MySQL 8.4+)
-- =========================

-- Create and select DB
CREATE DATABASE IF NOT EXISTS `suites_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
USE `suites_db`;

-- Safety: drop old tables without FK errors
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `flag_records`;
DROP TABLE IF EXISTS `toolpublication`;
DROP TABLE IF EXISTS `benchmarksuitepublication`;
DROP TABLE IF EXISTS `result`;
DROP TABLE IF EXISTS `benchmark`;
DROP TABLE IF EXISTS `benchmarksuite`;
DROP TABLE IF EXISTS `toolrelease`;
DROP TABLE IF EXISTS `tool`;
DROP TABLE IF EXISTS `images`;
SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------
-- Table: images
-- -------------------------
CREATE TABLE `images` (
  `image_id` INT NOT NULL AUTO_INCREMENT,
  `data`     LONGBLOB,
  PRIMARY KEY (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------
-- Table: tool
-- -------------------------
CREATE TABLE `tool` (
  `tool_id`          INT NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(100) NOT NULL,
  `URL`              VARCHAR(255) DEFAULT NULL,
  `text_description` TEXT,
  `image_id`         INT DEFAULT NULL,
  PRIMARY KEY (`tool_id`),
  UNIQUE KEY `uk_tool_name` (`name`),
  KEY `idx_tool_image` (`image_id`),
  CONSTRAINT `fk_tool_image`
    FOREIGN KEY (`image_id`) REFERENCES `images`(`image_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------
-- Table: toolrelease  (includes tool_release_version)
-- -------------------------
CREATE TABLE `toolrelease` (
  `tool_release_id`     INT NOT NULL AUTO_INCREMENT,
  `tool_id`             INT NOT NULL,
  `name`                VARCHAR(100) DEFAULT NULL,
  `tool_release_version` VARCHAR(255) DEFAULT NULL,  -- NEW
  `URL`                 VARCHAR(255) DEFAULT NULL,
  `date`                DATE DEFAULT NULL,
  `text_description`    TEXT,
  PRIMARY KEY (`tool_release_id`),
  UNIQUE KEY `uk_toolrelease_tool_name` (`tool_id`,`name`),
  KEY `idx_toolrelease_tool` (`tool_id`),
  CONSTRAINT `fk_toolrelease_tool`
    FOREIGN KEY (`tool_id`) REFERENCES `tool`(`tool_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------
-- Table: benchmarksuite
-- -------------------------
CREATE TABLE `benchmarksuite` (
  `suite_id`         INT NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(100) NOT NULL,
  `variation`        VARCHAR(50) DEFAULT NULL,
  `url_benchmarks`   VARCHAR(255) DEFAULT NULL,
  `url_evaluator`    VARCHAR(255) DEFAULT NULL,
  `fom1_label`       VARCHAR(50) DEFAULT NULL,
  `fom2_label`       VARCHAR(50) DEFAULT NULL,
  `fom3_label`       VARCHAR(50) DEFAULT NULL,
  `fom4_label`       VARCHAR(50) DEFAULT NULL,
  `text_description` TEXT,
  `date`             DATE DEFAULT NULL,
  PRIMARY KEY (`suite_id`),
  UNIQUE KEY `uk_benchmarksuite_name_variation` (`name`,`variation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------
-- Table: benchmark
-- -------------------------
CREATE TABLE `benchmark` (
  `benchmark_id`     INT NOT NULL AUTO_INCREMENT,
  `suite_id`         INT NOT NULL,
  `name`             VARCHAR(100) NOT NULL,
  `URL`              VARCHAR(255) DEFAULT NULL,
  `text_description` TEXT,
  `file_hash`        VARCHAR(128) DEFAULT NULL,
  PRIMARY KEY (`benchmark_id`),
  UNIQUE KEY `uk_benchmark_suite_name` (`suite_id`,`name`),
  KEY `idx_benchmark_suite` (`suite_id`),
  CONSTRAINT `fk_benchmark_suite`
    FOREIGN KEY (`suite_id`) REFERENCES `benchmarksuite`(`suite_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------
-- Table: result  (includes run_version + composite index)
-- -------------------------
CREATE TABLE `result` (
  `result_id`       INT NOT NULL AUTO_INCREMENT,
  `tool_id`         INT DEFAULT NULL,
  `tool_release_id` INT DEFAULT NULL,
  `run_version`     VARCHAR(32) DEFAULT NULL,      -- NEW (after tool_release_id)
  `suite_id`        INT DEFAULT NULL,
  `benchmark_id`    INT DEFAULT NULL,
  `fom1`            DOUBLE DEFAULT NULL,
  `fom2`            DOUBLE DEFAULT NULL,
  `fom3`            DOUBLE DEFAULT NULL,
  `fom4`            DOUBLE DEFAULT NULL,
  `URL`             VARCHAR(255) DEFAULT NULL,
  `file_hash`       VARCHAR(128) DEFAULT NULL,
  `evaluator_output` TEXT,
  `text_description` TEXT,
  `image_id`        INT DEFAULT NULL,
  `date`            DATE DEFAULT NULL,
  `secret_key`      VARCHAR(128) DEFAULT NULL,
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `uk_result_identity`
    (`tool_id`,`tool_release_id`,`suite_id`,`benchmark_id`,`date`,`file_hash`),
  KEY `idx_result_fom1` (`fom1`),
  KEY `idx_result_fom2` (`fom2`),
  KEY `idx_result_fom3` (`fom3`),
  KEY `idx_result_fom4` (`fom4`),
  KEY `idx_result_tool` (`tool_id`),
  KEY `idx_result_release` (`tool_release_id`),
  KEY `idx_result_suite` (`suite_id`),
  KEY `idx_result_bench` (`benchmark_id`),
  KEY `idx_result_image` (`image_id`),
  KEY `ix_result_tool_bench_runver` (`tool_id`,`benchmark_id`,`run_version`), -- NEW composite index
  CONSTRAINT `fk_result_benchmark`
    FOREIGN KEY (`benchmark_id`) REFERENCES `benchmark`(`benchmark_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_result_image`
    FOREIGN KEY (`image_id`) REFERENCES `images`(`image_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_result_suite`
    FOREIGN KEY (`suite_id`) REFERENCES `benchmarksuite`(`suite_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_result_tool`
    FOREIGN KEY (`tool_id`) REFERENCES `tool`(`tool_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_result_toolrelease`
    FOREIGN KEY (`tool_release_id`) REFERENCES `toolrelease`(`tool_release_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------
-- Table: toolpublication
-- -------------------------
CREATE TABLE `toolpublication` (
  `tool_pub_id`      INT NOT NULL AUTO_INCREMENT,
  `tool_id`          INT DEFAULT NULL,
  `tool_release_id`  INT DEFAULT NULL,
  `URL`              VARCHAR(255) DEFAULT NULL,
  `DOI`              VARCHAR(100) DEFAULT NULL,
  `text_description` TEXT,
  `date`             DATE DEFAULT NULL,
  PRIMARY KEY (`tool_pub_id`),
  KEY `idx_toolpub_tool` (`tool_id`),
  KEY `idx_toolpub_release` (`tool_release_id`),
  CONSTRAINT `fk_toolpub_release`
    FOREIGN KEY (`tool_release_id`) REFERENCES `toolrelease`(`tool_release_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_toolpub_tool`
    FOREIGN KEY (`tool_id`) REFERENCES `tool`(`tool_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------
-- Table: benchmarksuitepublication
-- -------------------------
CREATE TABLE `benchmarksuitepublication` (
  `suite_pub_id`     INT NOT NULL AUTO_INCREMENT,
  `suite_id`         INT DEFAULT NULL,
  `URL`              VARCHAR(255) DEFAULT NULL,
  `DOI`              VARCHAR(100) DEFAULT NULL,
  `text_description` TEXT,
  `date`             DATE DEFAULT NULL,
  PRIMARY KEY (`suite_pub_id`),
  KEY `idx_suitepub_suite` (`suite_id`),
  CONSTRAINT `fk_suitepub_suite`
    FOREIGN KEY (`suite_id`) REFERENCES `benchmarksuite`(`suite_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------
-- Table: flag_records  (NEW)
-- -------------------------
CREATE TABLE `flag_records` (
  `flag_id`     INT NOT NULL AUTO_INCREMENT,
  `result_id`   INT NOT NULL,
  `description` VARCHAR(1000),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`flag_id`),
  KEY `idx_flag_result` (`result_id`),
  CONSTRAINT `fk_flag_result`
    FOREIGN KEY (`result_id`) REFERENCES `result`(`result_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
