-- Estructura de la base `consultorio`: 70 tablas, 5 vistas, 6 funciones, 2 procedimientos y 2 triggers.
-- Generado con generar-ddl-dml.sh: mysqldump --no-data --routines --triggers (sin los AUTO_INCREMENT).
-- Las vistas, funciones, procedimientos y triggers llevan DEFINER=consultorio_despliegue: los crea ese usuario.
-- Para cargarlo, ese usuario ya debe existir (seguridad.sql) y poder leer la base donde se carga; luego dml.sql.

-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: consultorio
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `accepts_promotions` tinyint(1) NOT NULL DEFAULT '0',
  `promotions_consent_at` timestamp NULL DEFAULT NULL,
  `privacy_accepted_at` timestamp NULL DEFAULT NULL,
  `privacy_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blocked_at` timestamp NULL DEFAULT NULL,
  `blocked_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blocked_by_user_id` bigint unsigned DEFAULT NULL,
  `web_booking_limit` tinyint unsigned DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_phone_unique` (`phone`),
  KEY `accounts_blocked_by_user_id_foreign` (`blocked_by_user_id`),
  CONSTRAINT `accounts_blocked_by_user_id_foreign` FOREIGN KEY (`blocked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `appointment_events`
--

DROP TABLE IF EXISTS `appointment_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint unsigned NOT NULL,
  `type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `from_starts_at` datetime DEFAULT NULL,
  `to_starts_at` datetime DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appointment_events_appointment_id_foreign` (`appointment_id`),
  KEY `appointment_events_user_id_foreign` (`user_id`),
  CONSTRAINT `appointment_events_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointment_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `doctor_id` bigint unsigned NOT NULL,
  `treatment_id` bigint unsigned NOT NULL,
  `treatment_plan_item_id` bigint unsigned DEFAULT NULL,
  `series_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `buffer_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `custom_duration` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `booked_via` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `origin` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `promo_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_new_patient` tinyint(1) NOT NULL DEFAULT '0',
  `fixed_date` tinyint(1) NOT NULL DEFAULT '0',
  `is_overbooked` tinyint(1) NOT NULL DEFAULT '0',
  `from_verified_device` tinyint(1) NOT NULL DEFAULT '0',
  `patient_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `internal_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `confirmed_via` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unconfirmed_flagged_at` timestamp NULL DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `no_show_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_by_user_id` bigint unsigned DEFAULT NULL,
  `cancellation_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reschedule_count` tinyint unsigned NOT NULL DEFAULT '0',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointments_treatment_id_foreign` (`treatment_id`),
  KEY `appointments_campaign_id_foreign` (`campaign_id`),
  KEY `appointments_cancelled_by_user_id_foreign` (`cancelled_by_user_id`),
  KEY `appointments_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `appointments_doctor_id_starts_at_index` (`doctor_id`,`starts_at`),
  KEY `appointments_patient_id_starts_at_index` (`patient_id`,`starts_at`),
  KEY `appointments_status_starts_at_index` (`status`,`starts_at`),
  KEY `appointments_treatment_plan_item_id_index` (`treatment_plan_item_id`),
  KEY `appointments_series_id_index` (`series_id`),
  KEY `appointments_starts_on_index` ((cast(`starts_at` as date))),
  CONSTRAINT `appointments_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_cancelled_by_user_id_foreign` FOREIGN KEY (`cancelled_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `appointments_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `appointments_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `appointments_treatment_plan_item_id_foreign` FOREIGN KEY (`treatment_plan_item_id`) REFERENCES `treatment_plan_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `event` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` bigint unsigned NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_created_at_index` (`created_at`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `treatment_package_id` bigint unsigned DEFAULT NULL,
  `discount_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `discount_value` decimal(10,2) DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `promo_code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_redemptions` int unsigned DEFAULT NULL,
  `show_on_home` tinyint(1) NOT NULL DEFAULT '1',
  `whatsapp_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `views_count` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_slug_unique` (`slug`),
  UNIQUE KEY `campaigns_promo_code_unique` (`promo_code`),
  KEY `campaigns_treatment_id_foreign` (`treatment_id`),
  KEY `campaigns_starts_at_ends_at_index` (`starts_at`,`ends_at`),
  KEY `campaigns_treatment_package_id_foreign` (`treatment_package_id`),
  CONSTRAINT `campaigns_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_treatment_package_id_foreign` FOREIGN KEY (`treatment_package_id`) REFERENCES `treatment_packages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `charges`
--

DROP TABLE IF EXISTS `charges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `charges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `consultation_id` bigint unsigned DEFAULT NULL,
  `treatment_plan_item_id` bigint unsigned DEFAULT NULL,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `doctor_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suggested_amount` decimal(10,2) DEFAULT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `charged_at` datetime NOT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by_user_id` bigint unsigned DEFAULT NULL,
  `void_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `charges_appointment_id_foreign` (`appointment_id`),
  KEY `charges_treatment_plan_item_id_foreign` (`treatment_plan_item_id`),
  KEY `charges_treatment_id_foreign` (`treatment_id`),
  KEY `charges_doctor_id_foreign` (`doctor_id`),
  KEY `charges_campaign_id_foreign` (`campaign_id`),
  KEY `charges_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `charges_voided_by_user_id_foreign` (`voided_by_user_id`),
  KEY `charges_patient_id_charged_at_index` (`patient_id`,`charged_at`),
  KEY `charges_consultation_id_foreign` (`consultation_id`),
  KEY `charges_charged_at_index` (`charged_at`),
  KEY `charges_patient_balance_index` (`patient_id`,`voided_at`,`amount`,`discount`),
  CONSTRAINT `charges_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `charges_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `charges_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `charges_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `charges_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `charges_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `charges_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `charges_treatment_plan_item_id_foreign` FOREIGN KEY (`treatment_plan_item_id`) REFERENCES `treatment_plan_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `charges_voided_by_user_id_foreign` FOREIGN KEY (`voided_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clinical_files`
--

DROP TABLE IF EXISTS `clinical_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinical_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `consultation_id` bigint unsigned DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disk` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` int unsigned NOT NULL DEFAULT '0',
  `taken_at` date DEFAULT NULL,
  `uploaded_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinical_files_patient_id_foreign` (`patient_id`),
  KEY `clinical_files_consultation_id_foreign` (`consultation_id`),
  KEY `clinical_files_uploaded_by_user_id_foreign` (`uploaded_by_user_id`),
  CONSTRAINT `clinical_files_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinical_files_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `clinical_files_uploaded_by_user_id_foreign` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clinical_templates`
--

DROP TABLE IF EXISTS `clinical_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinical_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clinical_templates_treatment_id_foreign` (`treatment_id`),
  KEY `clinical_templates_user_id_foreign` (`user_id`),
  CONSTRAINT `clinical_templates_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinical_templates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consent_templates`
--

DROP TABLE IF EXISTS `consent_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consent_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` smallint unsigned NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consent_templates_treatment_id_foreign` (`treatment_id`),
  CONSTRAINT `consent_templates_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consents`
--

DROP TABLE IF EXISTS `consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `consent_template_id` bigint unsigned DEFAULT NULL,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `doctor_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `signer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `signer_document` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_relationship` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'self',
  `signature_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clinical_file_id` bigint unsigned DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consents_patient_id_foreign` (`patient_id`),
  KEY `consents_consent_template_id_foreign` (`consent_template_id`),
  KEY `consents_treatment_id_foreign` (`treatment_id`),
  KEY `consents_doctor_id_foreign` (`doctor_id`),
  KEY `consents_clinical_file_id_foreign` (`clinical_file_id`),
  CONSTRAINT `consents_clinical_file_id_foreign` FOREIGN KEY (`clinical_file_id`) REFERENCES `clinical_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `consents_consent_template_id_foreign` FOREIGN KEY (`consent_template_id`) REFERENCES `consent_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `consents_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `consents_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `consents_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consultation_diagnoses`
--

DROP TABLE IF EXISTS `consultation_diagnoses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consultation_diagnoses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `consultation_id` bigint unsigned NOT NULL,
  `icd10_code_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tooth` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'definitive',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consultation_diagnoses_consultation_id_foreign` (`consultation_id`),
  KEY `consultation_diagnoses_icd10_code_id_foreign` (`icd10_code_id`),
  CONSTRAINT `consultation_diagnoses_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consultation_diagnoses_icd10_code_id_foreign` FOREIGN KEY (`icd10_code_id`) REFERENCES `icd10_codes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consultation_plan_items`
--

DROP TABLE IF EXISTS `consultation_plan_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consultation_plan_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `consultation_id` bigint unsigned NOT NULL,
  `treatment_plan_item_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consultation_plan_items_unique` (`consultation_id`,`treatment_plan_item_id`),
  KEY `consultation_plan_items_treatment_plan_item_id_foreign` (`treatment_plan_item_id`),
  CONSTRAINT `consultation_plan_items_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consultation_plan_items_treatment_plan_item_id_foreign` FOREIGN KEY (`treatment_plan_item_id`) REFERENCES `treatment_plan_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consultation_treatments`
--

DROP TABLE IF EXISTS `consultation_treatments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consultation_treatments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `consultation_id` bigint unsigned NOT NULL,
  `treatment_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consultation_treatments_unique` (`consultation_id`,`treatment_id`),
  KEY `consultation_treatments_treatment_id_foreign` (`treatment_id`),
  CONSTRAINT `consultation_treatments_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consultation_treatments_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consultations`
--

DROP TABLE IF EXISTS `consultations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consultations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `doctor_id` bigint unsigned NOT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `attended_at` datetime NOT NULL,
  `chief_complaint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `current_illness` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `clinical_exam` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `vital_signs` json DEFAULT NULL,
  `diagnosis_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `procedures` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `procedure_details` json DEFAULT NULL,
  `indications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `next_steps` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `prices` json DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consultations_doctor_id_foreign` (`doctor_id`),
  KEY `consultations_appointment_id_foreign` (`appointment_id`),
  KEY `consultations_patient_id_attended_at_index` (`patient_id`,`attended_at`),
  CONSTRAINT `consultations_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `consultations_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `consultations_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `daily_closings`
--

DROP TABLE IF EXISTS `daily_closings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_closings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `opening_cash` decimal(10,2) NOT NULL DEFAULT '0.00',
  `expected_cash` decimal(10,2) NOT NULL,
  `counted_cash` decimal(10,2) NOT NULL,
  `collected` decimal(10,2) NOT NULL,
  `pending` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closed_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_closings_date_unique` (`date`),
  KEY `daily_closings_closed_by_user_id_foreign` (`closed_by_user_id`),
  CONSTRAINT `daily_closings_closed_by_user_id_foreign` FOREIGN KEY (`closed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `desk_notes`
--

DROP TABLE IF EXISTS `desk_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `desk_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `body` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `due_on` date NOT NULL,
  `patient_id` bigint unsigned DEFAULT NULL,
  `done_at` timestamp NULL DEFAULT NULL,
  `done_by_user_id` bigint unsigned DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `desk_notes_patient_id_foreign` (`patient_id`),
  KEY `desk_notes_done_by_user_id_foreign` (`done_by_user_id`),
  KEY `desk_notes_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `desk_notes_due_on_index` (`due_on`),
  CONSTRAINT `desk_notes_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `desk_notes_done_by_user_id_foreign` FOREIGN KEY (`done_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `desk_notes_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `doctor_schedules`
--

DROP TABLE IF EXISTS `doctor_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` bigint unsigned NOT NULL,
  `weekday` tinyint unsigned NOT NULL,
  `starts_at` time NOT NULL,
  `ends_at` time NOT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `doctor_schedules_doctor_id_weekday_index` (`doctor_id`,`weekday`),
  CONSTRAINT `doctor_schedules_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `doctor_treatment`
--

DROP TABLE IF EXISTS `doctor_treatment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_treatment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` bigint unsigned NOT NULL,
  `treatment_id` bigint unsigned NOT NULL,
  `duration_minutes` smallint unsigned DEFAULT NULL,
  `learned_minutes` smallint unsigned DEFAULT NULL,
  `learned_samples` smallint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doctor_treatment_doctor_id_treatment_id_unique` (`doctor_id`,`treatment_id`),
  KEY `doctor_treatment_treatment_id_foreign` (`treatment_id`),
  CONSTRAINT `doctor_treatment_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_treatment_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `doctors`
--

DROP TABLE IF EXISTS `doctors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialty` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cop_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rne_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `education` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `photo_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agenda_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#0ea5e9',
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `accepts_online_booking` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doctors_slug_unique` (`slug`),
  UNIQUE KEY `doctors_user_id_unique` (`user_id`),
  CONSTRAINT `doctors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spent_at` datetime NOT NULL,
  `category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `paid_by_user_id` bigint unsigned DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by_user_id` bigint unsigned DEFAULT NULL,
  `void_reason` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_paid_by_user_id_foreign` (`paid_by_user_id`),
  KEY `expenses_voided_by_user_id_foreign` (`voided_by_user_id`),
  KEY `expenses_spent_at_index` (`spent_at`),
  CONSTRAINT `expenses_paid_by_user_id_foreign` FOREIGN KEY (`paid_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_voided_by_user_id_foreign` FOREIGN KEY (`voided_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `faqs`
--

DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `faqs_treatment_id_foreign` (`treatment_id`),
  CONSTRAINT `faqs_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `icd10_codes`
--

DROP TABLE IF EXISTS `icd10_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `icd10_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_dental` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `icd10_codes_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lab_orders`
--

DROP TABLE IF EXISTS `lab_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `doctor_id` bigint unsigned DEFAULT NULL,
  `description` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `laboratory` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_on` date NOT NULL,
  `due_on` date NOT NULL,
  `received_on` date DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `expense_id` bigint unsigned DEFAULT NULL,
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lab_orders_patient_id_foreign` (`patient_id`),
  KEY `lab_orders_doctor_id_foreign` (`doctor_id`),
  KEY `lab_orders_expense_id_foreign` (`expense_id`),
  KEY `lab_orders_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `lab_orders_due_on_index` (`due_on`),
  CONSTRAINT `lab_orders_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_orders_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_orders_expense_id_foreign` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_orders_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `magic_links`
--

DROP TABLE IF EXISTS `magic_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `magic_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `purpose` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `first_used_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `use_count` int unsigned NOT NULL DEFAULT '0',
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `magic_links_token_hash_unique` (`token_hash`),
  KEY `magic_links_account_id_foreign` (`account_id`),
  KEY `magic_links_appointment_id_foreign` (`appointment_id`),
  KEY `magic_links_created_by_user_id_foreign` (`created_by_user_id`),
  CONSTRAINT `magic_links_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `magic_links_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `magic_links_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `medical_backgrounds`
--

DROP TABLE IF EXISTS `medical_backgrounds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medical_backgrounds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `conditions` json DEFAULT NULL,
  `allergies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `medications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `surgeries` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_pregnant` tinyint(1) DEFAULT NULL,
  `pregnancy_weeks` tinyint unsigned DEFAULT NULL,
  `is_breastfeeding` tinyint(1) DEFAULT NULL,
  `smoker` tinyint(1) DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `filled_by_patient_at` timestamp NULL DEFAULT NULL,
  `updated_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medical_backgrounds_patient_id_unique` (`patient_id`),
  KEY `medical_backgrounds_updated_by_user_id_foreign` (`updated_by_user_id`),
  CONSTRAINT `medical_backgrounds_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_backgrounds_updated_by_user_id_foreign` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `message_templates`
--

DROP TABLE IF EXISTS `message_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_template_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'es',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_templates_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `odontogram_conditions`
--

DROP TABLE IF EXISTS `odontogram_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `odontogram_conditions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbreviation` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `applies_to` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tooth',
  `abbreviations` json DEFAULT NULL,
  `allows_good_state` tinyint(1) NOT NULL DEFAULT '1',
  `allows_bad_state` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `odontogram_conditions_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `odontogram_entries`
--

DROP TABLE IF EXISTS `odontogram_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `odontogram_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `odontogram_id` bigint unsigned NOT NULL,
  `tooth` tinyint unsigned NOT NULL,
  `surface` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `odontogram_condition_id` bigint unsigned NOT NULL,
  `state` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbreviation` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_tooth` tinyint unsigned DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `odontogram_entries_odontogram_condition_id_foreign` (`odontogram_condition_id`),
  KEY `odontogram_entries_odontogram_id_tooth_index` (`odontogram_id`,`tooth`),
  CONSTRAINT `odontogram_entries_odontogram_condition_id_foreign` FOREIGN KEY (`odontogram_condition_id`) REFERENCES `odontogram_conditions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `odontogram_entries_odontogram_id_foreign` FOREIGN KEY (`odontogram_id`) REFERENCES `odontograms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `odontograms`
--

DROP TABLE IF EXISTS `odontograms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `odontograms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `consultation_id` bigint unsigned DEFAULT NULL,
  `doctor_id` bigint unsigned DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'evolution',
  `dentition` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'permanent',
  `specifications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `observations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `odontograms_consultation_id_foreign` (`consultation_id`),
  KEY `odontograms_doctor_id_foreign` (`doctor_id`),
  KEY `odontograms_patient_id_created_at_index` (`patient_id`,`created_at`),
  CONSTRAINT `odontograms_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `odontograms_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `odontograms_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned DEFAULT NULL,
  `relationship` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'self',
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `sex` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occupation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_document` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardian_relationship` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referral_code` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referred_by_patient_id` bigint unsigned DEFAULT NULL,
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `first_visit_at` timestamp NULL DEFAULT NULL,
  `last_visit_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patients_document_type_document_number_unique` (`document_type`,`document_number`),
  UNIQUE KEY `patients_referral_code_unique` (`referral_code`),
  KEY `patients_account_id_foreign` (`account_id`),
  KEY `patients_referred_by_patient_id_foreign` (`referred_by_patient_id`),
  KEY `patients_last_name_first_name_index` (`last_name`,`first_name`),
  KEY `patients_last_visit_at_index` (`last_visit_at`),
  CONSTRAINT `patients_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patients_referred_by_patient_id_foreign` FOREIGN KEY (`referred_by_patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_audit_logs`
--

DROP TABLE IF EXISTS `payment_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint unsigned NOT NULL,
  `event` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `database_user` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_audit_logs_payment_id_index` (`payment_id`),
  KEY `payment_audit_logs_changed_at_index` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `doctor_id` bigint unsigned DEFAULT NULL,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime NOT NULL,
  `received_by_user_id` bigint unsigned DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by_user_id` bigint unsigned DEFAULT NULL,
  `void_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_appointment_id_foreign` (`appointment_id`),
  KEY `payments_doctor_id_foreign` (`doctor_id`),
  KEY `payments_treatment_id_foreign` (`treatment_id`),
  KEY `payments_received_by_user_id_foreign` (`received_by_user_id`),
  KEY `payments_voided_by_user_id_foreign` (`voided_by_user_id`),
  KEY `payments_paid_at_index` (`paid_at`),
  KEY `payments_patient_id_paid_at_index` (`patient_id`,`paid_at`),
  KEY `payments_patient_balance_index` (`patient_id`,`voided_at`,`amount`),
  KEY `payments_paid_on_index` ((cast(`paid_at` as date))),
  CONSTRAINT `payments_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payments_received_by_user_id_foreign` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_voided_by_user_id_foreign` FOREIGN KEY (`voided_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`consultorio_despliegue`@`%`*/ /*!50003 TRIGGER `trg_pagos_auditar_cambio` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    DECLARE v_antes JSON DEFAULT JSON_OBJECT();
    DECLARE v_despues JSON DEFAULT JSON_OBJECT();

    IF NOT (OLD.patient_id <=> NEW.patient_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.patient_id', OLD.patient_id),
            v_despues = JSON_SET(v_despues, '$.patient_id', NEW.patient_id);
    END IF;
    IF NOT (OLD.appointment_id <=> NEW.appointment_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.appointment_id', OLD.appointment_id),
            v_despues = JSON_SET(v_despues, '$.appointment_id', NEW.appointment_id);
    END IF;
    IF NOT (OLD.doctor_id <=> NEW.doctor_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.doctor_id', OLD.doctor_id),
            v_despues = JSON_SET(v_despues, '$.doctor_id', NEW.doctor_id);
    END IF;
    IF NOT (OLD.treatment_id <=> NEW.treatment_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.treatment_id', OLD.treatment_id),
            v_despues = JSON_SET(v_despues, '$.treatment_id', NEW.treatment_id);
    END IF;
    IF NOT (OLD.amount <=> NEW.amount) THEN
        SET v_antes = JSON_SET(v_antes, '$.amount', OLD.amount),
            v_despues = JSON_SET(v_despues, '$.amount', NEW.amount);
    END IF;
    IF NOT (OLD.method <=> NEW.method) THEN
        SET v_antes = JSON_SET(v_antes, '$.method', OLD.method),
            v_despues = JSON_SET(v_despues, '$.method', NEW.method);
    END IF;
    IF NOT (OLD.reference <=> NEW.reference) THEN
        SET v_antes = JSON_SET(v_antes, '$.reference', OLD.reference),
            v_despues = JSON_SET(v_despues, '$.reference', NEW.reference);
    END IF;
    IF NOT (OLD.paid_at <=> NEW.paid_at) THEN
        SET v_antes = JSON_SET(v_antes, '$.paid_at', CAST(OLD.paid_at AS CHAR)),
            v_despues = JSON_SET(v_despues, '$.paid_at', CAST(NEW.paid_at AS CHAR));
    END IF;
    IF NOT (OLD.received_by_user_id <=> NEW.received_by_user_id) THEN
        SET v_antes = JSON_SET(v_antes, '$.received_by_user_id', OLD.received_by_user_id),
            v_despues = JSON_SET(v_despues, '$.received_by_user_id', NEW.received_by_user_id);
    END IF;
    IF NOT (OLD.notes <=> NEW.notes) THEN
        SET v_antes = JSON_SET(v_antes, '$.notes', OLD.notes),
            v_despues = JSON_SET(v_despues, '$.notes', NEW.notes);
    END IF;

    -- Anular un pago que estaba vigente es lo único que hace la aplicación (y ya queda en audit_logs).
    -- Reabrir uno anulado, o cambiar quién o por qué se anuló, no lo hace nunca.
    IF OLD.voided_at IS NOT NULL THEN
        IF NOT (OLD.voided_at <=> NEW.voided_at) THEN
            SET v_antes = JSON_SET(v_antes, '$.voided_at', CAST(OLD.voided_at AS CHAR)),
                v_despues = JSON_SET(v_despues, '$.voided_at', CAST(NEW.voided_at AS CHAR));
        END IF;
        IF NOT (OLD.voided_by_user_id <=> NEW.voided_by_user_id) THEN
            SET v_antes = JSON_SET(v_antes, '$.voided_by_user_id', OLD.voided_by_user_id),
                v_despues = JSON_SET(v_despues, '$.voided_by_user_id', NEW.voided_by_user_id);
        END IF;
        IF NOT (OLD.void_reason <=> NEW.void_reason) THEN
            SET v_antes = JSON_SET(v_antes, '$.void_reason', OLD.void_reason),
                v_despues = JSON_SET(v_despues, '$.void_reason', NEW.void_reason);
        END IF;
    END IF;

    IF JSON_LENGTH(v_antes) > 0 THEN
        INSERT INTO payment_audit_logs (payment_id, event, old_values, new_values, database_user, changed_at)
        VALUES (OLD.id, 'updated', v_antes, v_despues, USER(), fn_ahora_lima());
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`consultorio_despliegue`@`%`*/ /*!50003 TRIGGER `trg_pagos_auditar_borrado` AFTER DELETE ON `payments` FOR EACH ROW BEGIN
    INSERT INTO payment_audit_logs (payment_id, event, old_values, new_values, database_user, changed_at)
    VALUES (
        OLD.id,
        'deleted',
        JSON_OBJECT(
            'patient_id', OLD.patient_id,
            'appointment_id', OLD.appointment_id,
            'doctor_id', OLD.doctor_id,
            'treatment_id', OLD.treatment_id,
            'amount', OLD.amount,
            'method', OLD.method,
            'reference', OLD.reference,
            'paid_at', CAST(OLD.paid_at AS CHAR),
            'received_by_user_id', OLD.received_by_user_id,
            'notes', OLD.notes,
            'voided_at', CAST(OLD.voided_at AS CHAR),
            'voided_by_user_id', OLD.voided_by_user_id,
            'void_reason', OLD.void_reason,
            'created_at', CAST(OLD.created_at AS CHAR)
        ),
        NULL,
        USER(),
        fn_ahora_lima()
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `prescription_items`
--

DROP TABLE IF EXISTS `prescription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint unsigned NOT NULL,
  `medication` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `presentation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instructions` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_items_prescription_id_foreign` (`prescription_id`),
  CONSTRAINT `prescription_items_prescription_id_foreign` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `prescriptions`
--

DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `doctor_id` bigint unsigned NOT NULL,
  `consultation_id` bigint unsigned DEFAULT NULL,
  `issued_at` datetime NOT NULL,
  `indications` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescriptions_patient_id_foreign` (`patient_id`),
  KEY `prescriptions_doctor_id_foreign` (`doctor_id`),
  KEY `prescriptions_consultation_id_foreign` (`consultation_id`),
  CONSTRAINT `prescriptions_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `prescriptions_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `supply_id` bigint unsigned NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `received_quantity` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchase_order_items_supply_id_foreign` (`supply_id`),
  CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_items_supply_id_foreign` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `received_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_orders_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `purchase_orders_received_by_user_id_foreign` (`received_by_user_id`),
  CONSTRAINT `purchase_orders_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_received_by_user_id_foreign` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `referrals`
--

DROP TABLE IF EXISTS `referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referrals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `referrer_patient_id` bigint unsigned NOT NULL,
  `referred_patient_id` bigint unsigned NOT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `qualified_at` timestamp NULL DEFAULT NULL,
  `referrer_redeemed_at` timestamp NULL DEFAULT NULL,
  `referred_redeemed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referrals_referred_patient_id_unique` (`referred_patient_id`),
  KEY `referrals_referrer_patient_id_foreign` (`referrer_patient_id`),
  KEY `referrals_appointment_id_foreign` (`appointment_id`),
  CONSTRAINT `referrals_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referrals_referred_patient_id_foreign` FOREIGN KEY (`referred_patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_referrer_patient_id_foreign` FOREIGN KEY (`referrer_patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `remembered_devices`
--

DROP TABLE IF EXISTS `remembered_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `remembered_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `remembered_devices_token_hash_unique` (`token_hash`),
  KEY `remembered_devices_account_id_foreign` (`account_id`),
  CONSTRAINT `remembered_devices_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reschedule_request_options`
--

DROP TABLE IF EXISTS `reschedule_request_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reschedule_request_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reschedule_request_id` bigint unsigned NOT NULL,
  `doctor_id` bigint unsigned NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reschedule_request_options_reschedule_request_id_foreign` (`reschedule_request_id`),
  KEY `reschedule_request_options_doctor_id_foreign` (`doctor_id`),
  CONSTRAINT `reschedule_request_options_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reschedule_request_options_reschedule_request_id_foreign` FOREIGN KEY (`reschedule_request_id`) REFERENCES `reschedule_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reschedule_requests`
--

DROP TABLE IF EXISTS `reschedule_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reschedule_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint unsigned NOT NULL,
  `requested_by` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by_user_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `requested_doctor_id` bigint unsigned DEFAULT NULL,
  `requested_treatment_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_option_id` bigint unsigned DEFAULT NULL,
  `resolved_by_user_id` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `response_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reschedule_requests_appointment_id_foreign` (`appointment_id`),
  KEY `reschedule_requests_requested_by_user_id_foreign` (`requested_by_user_id`),
  KEY `reschedule_requests_requested_doctor_id_foreign` (`requested_doctor_id`),
  KEY `reschedule_requests_requested_treatment_id_foreign` (`requested_treatment_id`),
  KEY `reschedule_requests_resolved_by_user_id_foreign` (`resolved_by_user_id`),
  KEY `reschedule_requests_status_index` (`status`),
  CONSTRAINT `reschedule_requests_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reschedule_requests_requested_by_user_id_foreign` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reschedule_requests_requested_doctor_id_foreign` FOREIGN KEY (`requested_doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reschedule_requests_requested_treatment_id_foreign` FOREIGN KEY (`requested_treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reschedule_requests_resolved_by_user_id_foreign` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `author_name` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint unsigned NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'google',
  `reviewed_on` date DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reviews_treatment_id_foreign` (`treatment_id`),
  CONSTRAINT `reviews_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schedule_blocks`
--

DROP TABLE IF EXISTS `schedule_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` bigint unsigned DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `schedule_blocks_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `schedule_blocks_doctor_id_starts_at_index` (`doctor_id`,`starts_at`),
  CONSTRAINT `schedule_blocks_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `schedule_blocks_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `smile_cases`
--

DROP TABLE IF EXISTS `smile_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `smile_cases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `doctor_id` bigint unsigned DEFAULT NULL,
  `patient_id` bigint unsigned DEFAULT NULL,
  `before_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `after_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `consent_confirmed_at` timestamp NOT NULL,
  `consent_confirmed_by_user_id` bigint unsigned DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `smile_cases_treatment_id_foreign` (`treatment_id`),
  KEY `smile_cases_doctor_id_foreign` (`doctor_id`),
  KEY `smile_cases_patient_id_foreign` (`patient_id`),
  KEY `smile_cases_consent_confirmed_by_user_id_foreign` (`consent_confirmed_by_user_id`),
  CONSTRAINT `smile_cases_consent_confirmed_by_user_id_foreign` FOREIGN KEY (`consent_confirmed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `smile_cases_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `smile_cases_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `smile_cases_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supply_id` bigint unsigned NOT NULL,
  `supply_batch_id` bigint unsigned DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `patient_id` bigint unsigned DEFAULT NULL,
  `consultation_id` bigint unsigned DEFAULT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `reverted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_supply_batch_id_foreign` (`supply_batch_id`),
  KEY `stock_movements_patient_id_foreign` (`patient_id`),
  KEY `stock_movements_appointment_id_foreign` (`appointment_id`),
  KEY `stock_movements_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `stock_movements_user_id_foreign` (`user_id`),
  KEY `stock_movements_supply_id_created_at_index` (`supply_id`,`created_at`),
  KEY `stock_movements_consultation_id_type_index` (`consultation_id`,`type`),
  CONSTRAINT `stock_movements_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_supply_batch_id_foreign` FOREIGN KEY (`supply_batch_id`) REFERENCES `supply_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_supply_id_foreign` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`),
  CONSTRAINT `stock_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `supplies`
--

DROP TABLE IF EXISTS `supplies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supplies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unidad',
  `stock` decimal(10,2) NOT NULL DEFAULT '0.00',
  `min_stock` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `tracks_serial` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplies_supplier_id_foreign` (`supplier_id`),
  CONSTRAINT `supplies_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `supply_batches`
--

DROP TABLE IF EXISTS `supply_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supply_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supply_id` bigint unsigned NOT NULL,
  `lot` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `remaining` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `received_at` timestamp NOT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supply_batches_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `supply_batches_supply_id_remaining_index` (`supply_id`,`remaining`),
  KEY `supply_batches_expires_on_index` (`expires_on`),
  CONSTRAINT `supply_batches_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supply_batches_supply_id_foreign` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `text_snippet_usages`
--

DROP TABLE IF EXISTS `text_snippet_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `text_snippet_usages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `text_snippet_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `count` int unsigned NOT NULL DEFAULT '0',
  `last_used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `text_snippet_usages_text_snippet_id_user_id_unique` (`text_snippet_id`,`user_id`),
  KEY `text_snippet_usages_user_id_foreign` (`user_id`),
  CONSTRAINT `text_snippet_usages_text_snippet_id_foreign` FOREIGN KEY (`text_snippet_id`) REFERENCES `text_snippets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `text_snippet_usages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `text_snippets`
--

DROP TABLE IF EXISTS `text_snippets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `text_snippets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `field` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'any',
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shortcut` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `text_snippets_user_id_foreign` (`user_id`),
  KEY `text_snippets_field_user_id_index` (`field`,`user_id`),
  KEY `text_snippets_shortcut_index` (`shortcut`),
  CONSTRAINT `text_snippets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `treatment_package_items`
--

DROP TABLE IF EXISTS `treatment_package_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `treatment_package_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `treatment_package_id` bigint unsigned NOT NULL,
  `treatment_id` bigint unsigned NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` smallint unsigned NOT NULL DEFAULT '1',
  `sessions_planned` smallint unsigned NOT NULL DEFAULT '1',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `treatment_package_items_treatment_package_id_foreign` (`treatment_package_id`),
  KEY `treatment_package_items_treatment_id_foreign` (`treatment_id`),
  CONSTRAINT `treatment_package_items_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`),
  CONSTRAINT `treatment_package_items_treatment_package_id_foreign` FOREIGN KEY (`treatment_package_id`) REFERENCES `treatment_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `treatment_packages`
--

DROP TABLE IF EXISTS `treatment_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `treatment_packages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `duration_months` tinyint unsigned DEFAULT NULL,
  `for_two` tinyint(1) NOT NULL DEFAULT '0',
  `allows_upfront` tinyint(1) NOT NULL DEFAULT '1',
  `upfront_discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `allows_installments` tinyint(1) NOT NULL DEFAULT '0',
  `down_payment` decimal(10,2) NOT NULL DEFAULT '0.00',
  `installments` tinyint unsigned DEFAULT NULL,
  `monthly_amount` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `treatment_packages_created_by_user_id_foreign` (`created_by_user_id`),
  CONSTRAINT `treatment_packages_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `treatment_plan_installments`
--

DROP TABLE IF EXISTS `treatment_plan_installments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `treatment_plan_installments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `treatment_plan_id` bigint unsigned NOT NULL,
  `number` smallint unsigned NOT NULL,
  `due_on` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `charge_id` bigint unsigned DEFAULT NULL,
  `reminded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `treatment_plan_installments_treatment_plan_id_foreign` (`treatment_plan_id`),
  KEY `treatment_plan_installments_charge_id_foreign` (`charge_id`),
  KEY `treatment_plan_installments_due_on_charge_id_index` (`due_on`,`charge_id`),
  CONSTRAINT `treatment_plan_installments_charge_id_foreign` FOREIGN KEY (`charge_id`) REFERENCES `charges` (`id`) ON DELETE SET NULL,
  CONSTRAINT `treatment_plan_installments_treatment_plan_id_foreign` FOREIGN KEY (`treatment_plan_id`) REFERENCES `treatment_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `treatment_plan_items`
--

DROP TABLE IF EXISTS `treatment_plan_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `treatment_plan_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `treatment_plan_id` bigint unsigned NOT NULL,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `treatment_package_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tooth` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surfaces` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` tinyint unsigned NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sessions_planned` tinyint unsigned NOT NULL DEFAULT '1',
  `sessions_done` tinyint unsigned NOT NULL DEFAULT '0',
  `session_interval_days` smallint unsigned DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `accepted_at` timestamp NULL DEFAULT NULL,
  `in_payment_schedule` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `treatment_plan_items_treatment_plan_id_foreign` (`treatment_plan_id`),
  KEY `treatment_plan_items_treatment_id_foreign` (`treatment_id`),
  KEY `treatment_plan_items_treatment_package_id_foreign` (`treatment_package_id`),
  CONSTRAINT `treatment_plan_items_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `treatment_plan_items_treatment_package_id_foreign` FOREIGN KEY (`treatment_package_id`) REFERENCES `treatment_packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `treatment_plan_items_treatment_plan_id_foreign` FOREIGN KEY (`treatment_plan_id`) REFERENCES `treatment_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `treatment_plan_signatures`
--

DROP TABLE IF EXISTS `treatment_plan_signatures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `treatment_plan_signatures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `treatment_plan_id` bigint unsigned NOT NULL,
  `signer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `signer_document` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_relationship` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `signature_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `signed_at` datetime NOT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `treatment_plan_signatures_treatment_plan_id_foreign` (`treatment_plan_id`),
  KEY `treatment_plan_signatures_created_by_user_id_foreign` (`created_by_user_id`),
  CONSTRAINT `treatment_plan_signatures_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `treatment_plan_signatures_treatment_plan_id_foreign` FOREIGN KEY (`treatment_plan_id`) REFERENCES `treatment_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `treatment_plans`
--

DROP TABLE IF EXISTS `treatment_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `treatment_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `doctor_id` bigint unsigned NOT NULL,
  `consultation_id` bigint unsigned DEFAULT NULL,
  `included_in_plan_id` bigint unsigned DEFAULT NULL,
  `campaign_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `proposed_at` timestamp NULL DEFAULT NULL,
  `payment_mode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `treatment_plans_doctor_id_foreign` (`doctor_id`),
  KEY `treatment_plans_consultation_id_foreign` (`consultation_id`),
  KEY `treatment_plans_patient_id_status_index` (`patient_id`,`status`),
  KEY `treatment_plans_included_in_plan_id_foreign` (`included_in_plan_id`),
  KEY `treatment_plans_campaign_id_foreign` (`campaign_id`),
  KEY `treatment_plans_status_proposed_at_index` (`status`,`proposed_at`),
  CONSTRAINT `treatment_plans_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL,
  CONSTRAINT `treatment_plans_consultation_id_foreign` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `treatment_plans_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `treatment_plans_included_in_plan_id_foreign` FOREIGN KEY (`included_in_plan_id`) REFERENCES `treatment_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `treatment_plans_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `treatment_supplies`
--

DROP TABLE IF EXISTS `treatment_supplies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `treatment_supplies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `treatment_id` bigint unsigned NOT NULL,
  `supply_id` bigint unsigned NOT NULL,
  `quantity` decimal(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `treatment_supplies_treatment_id_supply_id_unique` (`treatment_id`,`supply_id`),
  KEY `treatment_supplies_supply_id_foreign` (`supply_id`),
  CONSTRAINT `treatment_supplies_supply_id_foreign` FOREIGN KEY (`supply_id`) REFERENCES `supplies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `treatment_supplies_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `treatments`
--

DROP TABLE IF EXISTS `treatments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `treatments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `kind` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `price_from` decimal(10,2) DEFAULT NULL,
  `show_price` tinyint(1) NOT NULL DEFAULT '1',
  `duration_minutes` smallint unsigned NOT NULL DEFAULT '30',
  `buffer_minutes` smallint unsigned DEFAULT NULL,
  `sessions_label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkup_months` tinyint unsigned DEFAULT NULL,
  `next_session_days` smallint unsigned DEFAULT NULL,
  `before_instructions` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aftercare` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_evaluation` tinyint(1) NOT NULL DEFAULT '0',
  `online_bookable` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `treatments_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reception',
  `is_dentist` tinyint(1) NOT NULL DEFAULT '0',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `v_agenda_del_dia`
--

DROP TABLE IF EXISTS `v_agenda_del_dia`;
/*!50001 DROP VIEW IF EXISTS `v_agenda_del_dia`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_agenda_del_dia` AS SELECT 
 1 AS `cita_id`,
 1 AS `hora`,
 1 AS `termina`,
 1 AS `doctor`,
 1 AS `paciente`,
 1 AS `celular`,
 1 AS `tratamiento`,
 1 AS `estado`,
 1 AS `paciente_nuevo`,
 1 AS `sobreturno`,
 1 AS `saldo_paciente`,
 1 AS `inicio`,
 1 AS `doctor_id`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_cargos_pendientes`
--

DROP TABLE IF EXISTS `v_cargos_pendientes`;
/*!50001 DROP VIEW IF EXISTS `v_cargos_pendientes`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_cargos_pendientes` AS SELECT 
 1 AS `cargo_id`,
 1 AS `paciente_id`,
 1 AS `cargado_el`,
 1 AS `concepto`,
 1 AS `neto`,
 1 AS `por_pagar`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_consumo_materiales`
--

DROP TABLE IF EXISTS `v_consumo_materiales`;
/*!50001 DROP VIEW IF EXISTS `v_consumo_materiales`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_consumo_materiales` AS SELECT 
 1 AS `mes`,
 1 AS `material_id`,
 1 AS `material`,
 1 AS `categoria`,
 1 AS `unidad`,
 1 AS `usado`,
 1 AS `dado_de_baja`,
 1 AS `costo`,
 1 AS `movimientos`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_deuda_vencida`
--

DROP TABLE IF EXISTS `v_deuda_vencida`;
/*!50001 DROP VIEW IF EXISTS `v_deuda_vencida`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_deuda_vencida` AS SELECT 
 1 AS `cuota_id`,
 1 AS `plan_id`,
 1 AS `paciente_id`,
 1 AS `paciente`,
 1 AS `celular`,
 1 AS `plan`,
 1 AS `cuota`,
 1 AS `vencio`,
 1 AS `dias_de_atraso`,
 1 AS `por_pagar`,
 1 AS `avisado_el`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_saldo_paciente`
--

DROP TABLE IF EXISTS `v_saldo_paciente`;
/*!50001 DROP VIEW IF EXISTS `v_saldo_paciente`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_saldo_paciente` AS SELECT 
 1 AS `paciente_id`,
 1 AS `paciente`,
 1 AS `tipo_documento`,
 1 AS `documento`,
 1 AS `cargado`,
 1 AS `pagado`,
 1 AS `saldo`,
 1 AS `ultima_visita`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `waitlist_entries`
--

DROP TABLE IF EXISTS `waitlist_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `waitlist_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` bigint unsigned NOT NULL,
  `treatment_id` bigint unsigned DEFAULT NULL,
  `doctor_id` bigint unsigned DEFAULT NULL,
  `earliest_date` date DEFAULT NULL,
  `latest_date` date DEFAULT NULL,
  `preferred_period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'any',
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting',
  `offered_at` timestamp NULL DEFAULT NULL,
  `offered_starts_at` timestamp NULL DEFAULT NULL,
  `offered_until` timestamp NULL DEFAULT NULL,
  `offered_doctor_id` bigint unsigned DEFAULT NULL,
  `held_until` timestamp NULL DEFAULT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `waitlist_entries_patient_id_foreign` (`patient_id`),
  KEY `waitlist_entries_treatment_id_foreign` (`treatment_id`),
  KEY `waitlist_entries_doctor_id_foreign` (`doctor_id`),
  KEY `waitlist_entries_appointment_id_foreign` (`appointment_id`),
  KEY `waitlist_entries_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `waitlist_entries_status_index` (`status`),
  KEY `waitlist_entries_offered_doctor_id_foreign` (`offered_doctor_id`),
  CONSTRAINT `waitlist_entries_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `waitlist_entries_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `waitlist_entries_doctor_id_foreign` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `waitlist_entries_offered_doctor_id_foreign` FOREIGN KEY (`offered_doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `waitlist_entries_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `waitlist_entries_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `whatsapp_messages`
--

DROP TABLE IF EXISTS `whatsapp_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint unsigned DEFAULT NULL,
  `patient_id` bigint unsigned DEFAULT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `template_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `params` json DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `channel` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'click_to_chat',
  `due_at` datetime DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `opened_by_user_id` bigint unsigned DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `sent_by_user_id` bigint unsigned DEFAULT NULL,
  `provider_message_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `whatsapp_messages_account_id_foreign` (`account_id`),
  KEY `whatsapp_messages_patient_id_foreign` (`patient_id`),
  KEY `whatsapp_messages_appointment_id_foreign` (`appointment_id`),
  KEY `whatsapp_messages_opened_by_user_id_foreign` (`opened_by_user_id`),
  KEY `whatsapp_messages_sent_by_user_id_foreign` (`sent_by_user_id`),
  KEY `whatsapp_messages_status_due_at_index` (`status`,`due_at`),
  CONSTRAINT `whatsapp_messages_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `whatsapp_messages_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `whatsapp_messages_opened_by_user_id_foreign` FOREIGN KEY (`opened_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `whatsapp_messages_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `whatsapp_messages_sent_by_user_id_foreign` FOREIGN KEY (`sent_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'consultorio'
--
/*!50003 DROP FUNCTION IF EXISTS `fn_ahora_lima` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`consultorio_despliegue`@`%` FUNCTION `fn_ahora_lima`() RETURNS datetime
    NO SQL
    COMMENT 'Fecha y hora de Lima (UTC-5, sin horario de verano), como las guarda la aplicacion (APP_TIMEZONE=America/Lima)'
BEGIN
    RETURN CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '-05:00');
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_categoria_material` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`consultorio_despliegue`@`%` FUNCTION `fn_categoria_material`(p_categoria VARCHAR(30)) RETURNS varchar(40) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    NO SQL
    DETERMINISTIC
    COMMENT 'Como SupplyCategory::getLabel()'
BEGIN
    RETURN CASE p_categoria
        WHEN 'anesthesia' THEN 'Anestésicos'
        WHEN 'restorative' THEN 'Resinas y restauración'
        WHEN 'endodontics' THEN 'Endodoncia'
        WHEN 'prevention' THEN 'Prevención y limpieza'
        WHEN 'surgery' THEN 'Cirugía'
        WHEN 'implants' THEN 'Implantes'
        WHEN 'orthodontics' THEN 'Ortodoncia'
        WHEN 'biosafety' THEN 'Bioseguridad'
        WHEN 'laboratory' THEN 'Laboratorio'
        WHEN 'other' THEN 'Otros'
        ELSE p_categoria
    END;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_estado_cita` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`consultorio_despliegue`@`%` FUNCTION `fn_estado_cita`(p_estado VARCHAR(20)) RETURNS varchar(30) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    NO SQL
    DETERMINISTIC
    COMMENT 'Como AppointmentStatus::getLabel()'
BEGIN
    RETURN CASE p_estado
        WHEN 'pending' THEN 'Pendiente'
        WHEN 'confirmed' THEN 'Confirmada'
        WHEN 'checked_in' THEN 'En sala'
        WHEN 'in_progress' THEN 'En atención'
        WHEN 'completed' THEN 'Atendida'
        WHEN 'no_show' THEN 'No asistió'
        WHEN 'cancelled' THEN 'Cancelada'
        ELSE p_estado
    END;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_hoy_lima` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`consultorio_despliegue`@`%` FUNCTION `fn_hoy_lima`() RETURNS date
    NO SQL
    COMMENT 'El dia de hoy en Lima'
BEGIN
    RETURN DATE(fn_ahora_lima());
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_medio_pago` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`consultorio_despliegue`@`%` FUNCTION `fn_medio_pago`(p_medio VARCHAR(20)) RETURNS varchar(30) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    NO SQL
    DETERMINISTIC
    COMMENT 'Como PaymentMethod::getLabel()'
BEGIN
    RETURN CASE p_medio
        WHEN 'cash' THEN 'Efectivo'
        WHEN 'card' THEN 'Tarjeta'
        WHEN 'yape' THEN 'Yape'
        WHEN 'plin' THEN 'Plin'
        WHEN 'transfer' THEN 'Transferencia'
        WHEN 'other' THEN 'Otro'
        ELSE p_medio
    END;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_saldo_paciente` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`consultorio_despliegue`@`%` FUNCTION `fn_saldo_paciente`(p_paciente_id BIGINT UNSIGNED) RETURNS decimal(12,2)
    READS SQL DATA
    COMMENT 'Lo que debe el paciente (positivo) o su saldo a favor (negativo), como Patient::balance()'
BEGIN
    DECLARE v_cargado DECIMAL(12, 2);
    DECLARE v_pagado DECIMAL(12, 2);

    SELECT COALESCE(SUM(amount - discount), 0) INTO v_cargado
    FROM charges
    WHERE patient_id = p_paciente_id AND voided_at IS NULL;

    SELECT COALESCE(SUM(amount), 0) INTO v_pagado
    FROM payments
    WHERE patient_id = p_paciente_id AND voided_at IS NULL;

    RETURN v_cargado - v_pagado;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_cierre_caja` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`consultorio_despliegue`@`%` PROCEDURE `sp_cierre_caja`(IN p_fecha DATE, IN p_recibido_por BIGINT UNSIGNED)
    READS SQL DATA
    COMMENT 'Cierre de caja del dia, como CashClosing'
BEGIN
    DECLARE v_desde DATETIME;
    DECLARE v_hasta DATETIME;

    IF p_fecha IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Falta la fecha del cierre';
    END IF;

    SET v_desde = CAST(p_fecha AS DATETIME);
    SET v_hasta = v_desde + INTERVAL 1 DAY;

    SELECT
        p_fecha AS fecha,
        pg.pagos,
        pg.cobrado,
        pg.efectivo,
        gs.gastos,
        gs.gastos_en_efectivo,
        pg.efectivo - gs.gastos_en_efectivo AS efectivo_en_caja,
        pe.pendiente,
        pg.cobrado + pe.pendiente AS esperado,
        an.anulados,
        an.monto_anulado,
        dc.id IS NOT NULL AS cerrado,
        dc.opening_cash AS fondo,
        dc.expected_cash AS efectivo_esperado_al_cerrar,
        dc.counted_cash AS efectivo_contado,
        dc.counted_cash - dc.expected_cash AS diferencia
    FROM (
        SELECT
            COUNT(*) AS pagos,
            COALESCE(SUM(amount), 0) AS cobrado,
            COALESCE(SUM(CASE WHEN method = 'cash' THEN amount ELSE 0 END), 0) AS efectivo
        FROM payments
        WHERE voided_at IS NULL
            AND paid_at >= v_desde AND paid_at < v_hasta
            AND (p_recibido_por IS NULL OR received_by_user_id = p_recibido_por)
    ) pg
    CROSS JOIN (
        SELECT
            COALESCE(SUM(amount), 0) AS gastos,
            COALESCE(SUM(CASE WHEN method = 'cash' THEN amount ELSE 0 END), 0) AS gastos_en_efectivo
        FROM expenses
        WHERE voided_at IS NULL
            AND spent_at >= v_desde AND spent_at < v_hasta
            AND (p_recibido_por IS NULL OR paid_by_user_id = p_recibido_por)
    ) gs
    CROSS JOIN (
        -- Lo que se cargó ese día y no se pagó (CashClosing::pending): los pagos cubren primero lo más antiguo,
        -- así que de ese día falta lo que debe hoy menos lo cargado después, hasta lo cargado ese día.
        SELECT COALESCE(SUM(falta), 0) AS pendiente
        FROM (
            SELECT LEAST(dia.neto, GREATEST(0, dia.saldo - COALESCE(despues.neto, 0))) AS falta
            FROM (
                SELECT c.patient_id, SUM(c.amount - c.discount) AS neto, fn_saldo_paciente(c.patient_id) AS saldo
                FROM charges c
                JOIN patients pa ON pa.id = c.patient_id AND pa.deleted_at IS NULL
                WHERE c.voided_at IS NULL AND c.charged_at >= v_desde AND c.charged_at < v_hasta
                GROUP BY c.patient_id
            ) dia
            LEFT JOIN (
                SELECT patient_id, SUM(amount - discount) AS neto
                FROM charges
                WHERE voided_at IS NULL AND charged_at >= v_hasta
                GROUP BY patient_id
            ) despues ON despues.patient_id = dia.patient_id
            WHERE dia.saldo > 0
        ) f
        WHERE falta > 0
    ) pe
    CROSS JOIN (
        SELECT COUNT(*) AS anulados, COALESCE(SUM(amount), 0) AS monto_anulado
        FROM payments
        WHERE voided_at IS NOT NULL
            AND paid_at >= v_desde AND paid_at < v_hasta
            AND (p_recibido_por IS NULL OR received_by_user_id = p_recibido_por)
    ) an
    LEFT JOIN daily_closings dc ON dc.date = p_fecha;

    SELECT
        m.medio,
        fn_medio_pago(m.medio) AS medio_de_pago,
        COUNT(p.id) AS pagos,
        COALESCE(SUM(p.amount), 0) AS cobrado
    FROM (
        SELECT 'cash' AS medio, 1 AS orden
        UNION ALL SELECT 'card', 2
        UNION ALL SELECT 'yape', 3
        UNION ALL SELECT 'plin', 4
        UNION ALL SELECT 'transfer', 5
        UNION ALL SELECT 'other', 6
    ) m
    LEFT JOIN payments p ON p.method = m.medio
        AND p.voided_at IS NULL
        AND p.paid_at >= v_desde AND p.paid_at < v_hasta
        AND (p_recibido_por IS NULL OR p.received_by_user_id = p_recibido_por)
    GROUP BY m.medio, m.orden
    HAVING m.medio = 'cash' OR COUNT(p.id) > 0
    ORDER BY m.orden;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_reporte_mensual_ingresos` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`consultorio_despliegue`@`%` PROCEDURE `sp_reporte_mensual_ingresos`(IN p_anio SMALLINT, IN p_mes TINYINT)
    READS SQL DATA
    COMMENT 'Ingresos de un mes, como EarningsReport'
BEGIN
    DECLARE v_desde DATETIME;
    DECLARE v_hasta DATETIME;
    DECLARE v_cobrado DECIMAL(12, 2);

    IF p_anio IS NULL OR p_mes IS NULL OR p_mes NOT BETWEEN 1 AND 12 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El mes debe estar entre 1 y 12';
    END IF;

    SET v_desde = CAST(MAKEDATE(p_anio, 1) + INTERVAL (p_mes - 1) MONTH AS DATETIME);
    SET v_hasta = v_desde + INTERVAL 1 MONTH;

    SELECT COALESCE(SUM(amount), 0) INTO v_cobrado
    FROM payments
    WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta;

    SELECT
        DATE_FORMAT(v_desde, '%Y-%m') AS mes,
        v_cobrado AS cobrado,
        pg.pagos,
        rz.realizado,
        gs.gastos,
        mt.materiales,
        v_cobrado - gs.gastos - mt.materiales AS queda,
        sd.por_cobrar_hoy
    FROM (
        SELECT COUNT(*) AS pagos
        FROM payments
        WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta
    ) pg
    CROSS JOIN (
        SELECT COALESCE(SUM(amount - discount), 0) AS realizado
        FROM charges
        WHERE voided_at IS NULL AND charged_at >= v_desde AND charged_at < v_hasta
    ) rz
    CROSS JOIN (
        SELECT COALESCE(SUM(amount), 0) AS gastos
        FROM expenses
        WHERE voided_at IS NULL AND spent_at >= v_desde AND spent_at < v_hasta
    ) gs
    CROSS JOIN (
        SELECT CAST(GREATEST(0, COALESCE(SUM(-quantity * COALESCE(unit_cost, 0)), 0)) AS DECIMAL(12, 2)) AS materiales
        FROM stock_movements
        WHERE type IN ('use', 'return', 'write_off') AND created_at >= v_desde AND created_at < v_hasta
    ) mt
    CROSS JOIN (
        SELECT COALESCE(SUM(saldo), 0) AS por_cobrar_hoy
        FROM v_saldo_paciente
        WHERE saldo > 0
    ) sd;

    SELECT
        COALESCE(d.name, 'Sin doctor asignado') AS doctor,
        COALESCE(rz.total, 0) AS realizado,
        COALESCE(cb.total, 0) AS cobrado
    FROM (
        SELECT doctor_id FROM charges
        WHERE voided_at IS NULL AND charged_at >= v_desde AND charged_at < v_hasta
        UNION
        SELECT doctor_id FROM payments
        WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta
    ) ids
    LEFT JOIN (
        SELECT doctor_id, SUM(amount - discount) AS total
        FROM charges
        WHERE voided_at IS NULL AND charged_at >= v_desde AND charged_at < v_hasta
        GROUP BY doctor_id
    ) rz ON rz.doctor_id <=> ids.doctor_id
    LEFT JOIN (
        SELECT doctor_id, SUM(amount) AS total
        FROM payments
        WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta
        GROUP BY doctor_id
    ) cb ON cb.doctor_id <=> ids.doctor_id
    LEFT JOIN doctors d ON d.id = ids.doctor_id AND d.deleted_at IS NULL
    ORDER BY realizado DESC, cobrado DESC;

    SELECT
        method AS medio,
        fn_medio_pago(method) AS medio_de_pago,
        SUM(amount) AS cobrado,
        COUNT(*) AS pagos,
        CASE WHEN v_cobrado > 0 THEN ROUND(SUM(amount) / v_cobrado * 100, 1) ELSE 0 END AS porcentaje
    FROM payments
    WHERE voided_at IS NULL AND paid_at >= v_desde AND paid_at < v_hasta
    GROUP BY method
    ORDER BY cobrado DESC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `v_agenda_del_dia`
--

/*!50001 DROP VIEW IF EXISTS `v_agenda_del_dia`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`consultorio_despliegue`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_agenda_del_dia` AS select `a`.`id` AS `cita_id`,time_format(`a`.`starts_at`,'%H:%i') AS `hora`,time_format(`a`.`ends_at`,'%H:%i') AS `termina`,`d`.`name` AS `doctor`,trim(concat(`p`.`first_name`,' ',`p`.`last_name`)) AS `paciente`,`ac`.`phone` AS `celular`,`t`.`name` AS `tratamiento`,`fn_estado_cita`(`a`.`status`) AS `estado`,`a`.`is_new_patient` AS `paciente_nuevo`,`a`.`is_overbooked` AS `sobreturno`,`fn_saldo_paciente`(`a`.`patient_id`) AS `saldo_paciente`,`a`.`starts_at` AS `inicio`,`a`.`doctor_id` AS `doctor_id` from ((((`appointments` `a` join `patients` `p` on((`p`.`id` = `a`.`patient_id`))) join `doctors` `d` on((`d`.`id` = `a`.`doctor_id`))) join `treatments` `t` on((`t`.`id` = `a`.`treatment_id`))) left join `accounts` `ac` on((`ac`.`id` = `p`.`account_id`))) where ((cast(`a`.`starts_at` as date) = cast(convert_tz(utc_timestamp(),'+00:00','-05:00') as date)) and (`a`.`deleted_at` is null) and (`a`.`status` <> 'cancelled')) order by `a`.`starts_at`,`a`.`id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_cargos_pendientes`
--

/*!50001 DROP VIEW IF EXISTS `v_cargos_pendientes`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`consultorio_despliegue`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_cargos_pendientes` AS select `x`.`cargo_id` AS `cargo_id`,`x`.`paciente_id` AS `paciente_id`,`x`.`cargado_el` AS `cargado_el`,`x`.`concepto` AS `concepto`,`x`.`neto` AS `neto`,`x`.`por_pagar` AS `por_pagar` from (select `f`.`cargo_id` AS `cargo_id`,`f`.`paciente_id` AS `paciente_id`,`f`.`cargado_el` AS `cargado_el`,`f`.`concepto` AS `concepto`,`f`.`neto` AS `neto`,(case when ((`f`.`orden` = 1) and (`f`.`pagado` < 0)) then (`f`.`neto` - `f`.`pagado`) else least(`f`.`neto`,greatest(0,(`f`.`acumulado` - `f`.`pagado`))) end) AS `por_pagar` from (select `c`.`id` AS `cargo_id`,`c`.`patient_id` AS `paciente_id`,`c`.`charged_at` AS `cargado_el`,`c`.`description` AS `concepto`,(`c`.`amount` - `c`.`discount`) AS `neto`,row_number() OVER `w` AS `orden`,sum(greatest(0,(`c`.`amount` - `c`.`discount`))) OVER (`w` ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)  AS `acumulado`,coalesce(`g`.`pagado`,0) AS `pagado` from (`charges` `c` left join (select `payments`.`patient_id` AS `patient_id`,sum(`payments`.`amount`) AS `pagado` from `payments` where (`payments`.`voided_at` is null) group by `payments`.`patient_id`) `g` on((`g`.`patient_id` = `c`.`patient_id`))) where (`c`.`voided_at` is null) window `w` AS (PARTITION BY `c`.`patient_id` ORDER BY `c`.`charged_at`,`c`.`id` ) ) `f`) `x` where (`x`.`por_pagar` > 0) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_consumo_materiales`
--

/*!50001 DROP VIEW IF EXISTS `v_consumo_materiales`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`consultorio_despliegue`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_consumo_materiales` AS select date_format(`m`.`created_at`,'%Y-%m') AS `mes`,`s`.`id` AS `material_id`,`s`.`name` AS `material`,`fn_categoria_material`(`s`.`category`) AS `categoria`,`s`.`unit` AS `unidad`,sum((case when (`m`.`type` in ('use','return')) then -(`m`.`quantity`) else 0 end)) AS `usado`,sum((case when (`m`.`type` = 'write_off') then -(`m`.`quantity`) else 0 end)) AS `dado_de_baja`,cast(sum((-(`m`.`quantity`) * coalesce(`m`.`unit_cost`,0))) as decimal(12,2)) AS `costo`,count(0) AS `movimientos` from (`stock_movements` `m` join `supplies` `s` on((`s`.`id` = `m`.`supply_id`))) where (`m`.`type` in ('use','return','write_off')) group by date_format(`m`.`created_at`,'%Y-%m'),`s`.`id`,`s`.`name`,`s`.`category`,`s`.`unit` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_deuda_vencida`
--

/*!50001 DROP VIEW IF EXISTS `v_deuda_vencida`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`consultorio_despliegue`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_deuda_vencida` AS select `i`.`id` AS `cuota_id`,`pl`.`id` AS `plan_id`,`p`.`id` AS `paciente_id`,trim(concat(`p`.`first_name`,' ',`p`.`last_name`)) AS `paciente`,`ac`.`phone` AS `celular`,coalesce(nullif(`pl`.`title`,''),'Plan de tratamiento') AS `plan`,(case when (`pl`.`payment_mode` = 'upfront') then 'Pago único' when (`i`.`number` = 0) then 'Inicial' else concat('Cuota ',`i`.`number`,' de ',(select count(0) from `treatment_plan_installments` `n` where ((`n`.`treatment_plan_id` = `i`.`treatment_plan_id`) and (`n`.`number` > 0)))) end) AS `cuota`,`i`.`due_on` AS `vencio`,(to_days(cast(convert_tz(utc_timestamp(),'+00:00','-05:00') as date)) - to_days(`i`.`due_on`)) AS `dias_de_atraso`,(case when (`i`.`charge_id` is null) then (`i`.`amount` - `i`.`discount`) else coalesce(`cp`.`por_pagar`,0) end) AS `por_pagar`,`i`.`reminded_at` AS `avisado_el` from ((((`treatment_plan_installments` `i` join `treatment_plans` `pl` on((`pl`.`id` = `i`.`treatment_plan_id`))) join `patients` `p` on((`p`.`id` = `pl`.`patient_id`))) left join `accounts` `ac` on((`ac`.`id` = `p`.`account_id`))) left join `v_cargos_pendientes` `cp` on((`cp`.`cargo_id` = `i`.`charge_id`))) where ((`pl`.`deleted_at` is null) and (`pl`.`status` <> 'cancelled') and (`i`.`due_on` < cast(convert_tz(utc_timestamp(),'+00:00','-05:00') as date)) and ((`i`.`charge_id` is null) or (`cp`.`por_pagar` > 0))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_saldo_paciente`
--

/*!50001 DROP VIEW IF EXISTS `v_saldo_paciente`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`consultorio_despliegue`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_saldo_paciente` AS select `p`.`id` AS `paciente_id`,trim(concat(`p`.`first_name`,' ',`p`.`last_name`)) AS `paciente`,`p`.`document_type` AS `tipo_documento`,`p`.`document_number` AS `documento`,coalesce(`c`.`cargado`,0) AS `cargado`,coalesce(`g`.`pagado`,0) AS `pagado`,(coalesce(`c`.`cargado`,0) - coalesce(`g`.`pagado`,0)) AS `saldo`,`p`.`last_visit_at` AS `ultima_visita` from ((`patients` `p` left join (select `charges`.`patient_id` AS `patient_id`,sum((`charges`.`amount` - `charges`.`discount`)) AS `cargado` from `charges` where (`charges`.`voided_at` is null) group by `charges`.`patient_id`) `c` on((`c`.`patient_id` = `p`.`id`))) left join (select `payments`.`patient_id` AS `patient_id`,sum(`payments`.`amount`) AS `pagado` from `payments` where (`payments`.`voided_at` is null) group by `payments`.`patient_id`) `g` on((`g`.`patient_id` = `p`.`id`))) where (`p`.`deleted_at` is null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
