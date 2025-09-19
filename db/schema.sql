CREATE DATABASE  IF NOT EXISTS `suites_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `suites_db`;
-- MySQL dump 10.13  Distrib 8.0.36, for Win64 (x86_64)
--
-- Host: localhost    Database: suites_db
-- ------------------------------------------------------
-- Server version	8.4.6

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `benchmark`
--

DROP TABLE IF EXISTS `benchmark`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `benchmark` (
  `benchmark_id` int NOT NULL AUTO_INCREMENT,
  `suite_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `text_description` text,
  `file_hash` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`benchmark_id`),
  UNIQUE KEY `uk_benchmark_suite_name` (`suite_id`,`name`),
  KEY `idx_benchmark_suite` (`suite_id`),
  CONSTRAINT `fk_benchmark_suite` FOREIGN KEY (`suite_id`) REFERENCES `benchmarksuite` (`suite_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `benchmarksuite`
--

DROP TABLE IF EXISTS `benchmarksuite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `benchmarksuite` (
  `suite_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `variation` varchar(50) DEFAULT NULL,
  `url_benchmarks` varchar(255) DEFAULT NULL,
  `url_evaluator` varchar(255) DEFAULT NULL,
  `fom1_label` varchar(50) DEFAULT NULL,
  `fom2_label` varchar(50) DEFAULT NULL,
  `fom3_label` varchar(50) DEFAULT NULL,
  `fom4_label` varchar(50) DEFAULT NULL,
  `text_description` text,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`suite_id`),
  UNIQUE KEY `uk_benchmarksuite_name_variation` (`name`,`variation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `benchmarksuitepublication`
--

DROP TABLE IF EXISTS `benchmarksuitepublication`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `benchmarksuitepublication` (
  `suite_pub_id` int NOT NULL AUTO_INCREMENT,
  `suite_id` int DEFAULT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `DOI` varchar(100) DEFAULT NULL,
  `text_description` text,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`suite_pub_id`),
  KEY `idx_suitepub_suite` (`suite_id`),
  CONSTRAINT `fk_suitepub_suite` FOREIGN KEY (`suite_id`) REFERENCES `benchmarksuite` (`suite_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `flag_records`
--

DROP TABLE IF EXISTS `flag_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flag_records` (
  `flag_id` int NOT NULL AUTO_INCREMENT,
  `result_id` int NOT NULL,
  `description` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`flag_id`),
  KEY `idx_flag_result` (`result_id`),
  CONSTRAINT `fk_flag_result` FOREIGN KEY (`result_id`) REFERENCES `result` (`result_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `images`
--

DROP TABLE IF EXISTS `images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `images` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `data` longblob,
  PRIMARY KEY (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `result`
--

DROP TABLE IF EXISTS `result`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `result` (
  `result_id` int NOT NULL AUTO_INCREMENT,
  `tool_id` int DEFAULT NULL,
  `tool_release_id` int DEFAULT NULL,
  `run_version` varchar(32) DEFAULT NULL,
  `suite_id` int DEFAULT NULL,
  `benchmark_id` int DEFAULT NULL,
  `fom1` double DEFAULT NULL,
  `fom2` double DEFAULT NULL,
  `fom3` double DEFAULT NULL,
  `fom4` double DEFAULT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `file_hash` varchar(128) DEFAULT NULL,
  `evaluator_output` text,
  `text_description` text,
  `image_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `secret_key` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`result_id`),
  UNIQUE KEY `uk_result_identity` (`tool_id`,`tool_release_id`,`suite_id`,`benchmark_id`,`date`,`file_hash`),
  KEY `idx_result_fom1` (`fom1`),
  KEY `idx_result_fom2` (`fom2`),
  KEY `idx_result_fom3` (`fom3`),
  KEY `idx_result_fom4` (`fom4`),
  KEY `idx_result_tool` (`tool_id`),
  KEY `idx_result_release` (`tool_release_id`),
  KEY `idx_result_suite` (`suite_id`),
  KEY `idx_result_bench` (`benchmark_id`),
  KEY `idx_result_image` (`image_id`),
  KEY `ix_result_tool_bench_runver` (`tool_id`,`benchmark_id`,`run_version`),
  CONSTRAINT `fk_result_benchmark` FOREIGN KEY (`benchmark_id`) REFERENCES `benchmark` (`benchmark_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_result_image` FOREIGN KEY (`image_id`) REFERENCES `images` (`image_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_result_suite` FOREIGN KEY (`suite_id`) REFERENCES `benchmarksuite` (`suite_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_result_tool` FOREIGN KEY (`tool_id`) REFERENCES `tool` (`tool_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_result_toolrelease` FOREIGN KEY (`tool_release_id`) REFERENCES `toolrelease` (`tool_release_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tool`
--

DROP TABLE IF EXISTS `tool`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tool` (
  `tool_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `text_description` text,
  `image_id` int DEFAULT NULL,
  PRIMARY KEY (`tool_id`),
  UNIQUE KEY `uk_tool_name` (`name`),
  KEY `idx_tool_image` (`image_id`),
  CONSTRAINT `fk_tool_image` FOREIGN KEY (`image_id`) REFERENCES `images` (`image_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `toolpublication`
--

DROP TABLE IF EXISTS `toolpublication`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `toolpublication` (
  `tool_pub_id` int NOT NULL AUTO_INCREMENT,
  `tool_id` int DEFAULT NULL,
  `tool_release_id` int DEFAULT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `DOI` varchar(100) DEFAULT NULL,
  `text_description` text,
  `date` date DEFAULT NULL,
  PRIMARY KEY (`tool_pub_id`),
  KEY `idx_toolpub_tool` (`tool_id`),
  KEY `idx_toolpub_release` (`tool_release_id`),
  CONSTRAINT `fk_toolpub_release` FOREIGN KEY (`tool_release_id`) REFERENCES `toolrelease` (`tool_release_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_toolpub_tool` FOREIGN KEY (`tool_id`) REFERENCES `tool` (`tool_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `toolrelease`
--

DROP TABLE IF EXISTS `toolrelease`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `toolrelease` (
  `tool_release_id` int NOT NULL AUTO_INCREMENT,
  `tool_id` int NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `tool_release_version` varchar(255) DEFAULT NULL,
  `URL` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `text_description` text,
  PRIMARY KEY (`tool_release_id`),
  UNIQUE KEY `uk_toolrelease_tool_name` (`tool_id`,`name`),
  KEY `idx_toolrelease_tool` (`tool_id`),
  CONSTRAINT `fk_toolrelease_tool` FOREIGN KEY (`tool_id`) REFERENCES `tool` (`tool_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-17 20:32:28
