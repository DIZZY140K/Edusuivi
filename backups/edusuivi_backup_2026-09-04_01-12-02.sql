-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: edusuivi
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `absences`
--

DROP TABLE IF EXISTS `absences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `absences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `justified` tinyint(1) DEFAULT 0,
  `reason` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `idx_absences_student` (`student_id`),
  KEY `idx_absences_date` (`date`),
  CONSTRAINT `absences_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `absences_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `absences_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `absences`
--

LOCK TABLES `absences` WRITE;
/*!40000 ALTER TABLE `absences` DISABLE KEYS */;
INSERT INTO `absences` VALUES (1,1,NULL,'2025-01-15',1,'Maladie',2,'2026-07-04 12:13:12'),(2,1,NULL,'2025-02-03',0,NULL,2,'2026-07-04 12:13:12'),(3,3,NULL,'2025-01-20',0,NULL,2,'2026-07-04 12:13:12'),(4,5,NULL,'2025-02-10',1,'Raison familiale',2,'2026-07-04 12:13:12'),(5,1,NULL,'2026-07-10',0,'malade',2,'2026-07-10 01:38:09'),(6,2,1,'2026-08-21',0,NULL,2,'2026-08-21 14:00:33'),(7,2,1,'2026-08-31',1,'Rendez-vous médical',2,'2026-09-03 21:03:11'),(8,2,2,'2026-09-01',0,'Retard imprévu transport',2,'2026-09-03 21:03:11'),(9,2,3,'2026-08-27',1,'Motif familial avec mot des parents',2,'2026-09-03 21:03:11'),(10,2,1,'2026-08-28',0,'Non justifiée',2,'2026-09-03 21:03:11');
/*!40000 ALTER TABLE `absences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'backup','Création de la sauvegarde edusuivi_backup_2026-08-21_15-50-36.sql',NULL,'2026-08-21 13:50:37');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agenda_events`
--

DROP TABLE IF EXISTS `agenda_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agenda_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('reunion','examen','activite','conge','autre') DEFAULT 'autre',
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `target` enum('all','teachers','parents','class') DEFAULT 'all',
  `target_class_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `target_class_id` (`target_class_id`),
  CONSTRAINT `agenda_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agenda_events_ibfk_2` FOREIGN KEY (`target_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agenda_events`
--

LOCK TABLES `agenda_events` WRITE;
/*!40000 ALTER TABLE `agenda_events` DISABLE KEYS */;
INSERT INTO `agenda_events` VALUES (1,'Réunion parents-enseignants','Réunion trimestrielle obligatoire','reunion','2026-07-11','10:00:00',NULL,'parents',NULL,1,'2026-07-04 12:13:12'),(2,'Examens du 2ème trimestre','Début des examens trimestriels','examen','2026-07-18','08:00:00',NULL,'all',NULL,1,'2026-07-04 12:13:12'),(3,'Fête de l\'école','Journée culturelle annuelle','activite','2026-07-25','09:00:00',NULL,'all',NULL,1,'2026-07-04 12:13:12'),(4,'reunion','azertyuio','reunion','2026-08-19','12:33:00',NULL,'all',NULL,1,'2026-08-19 02:59:48');
/*!40000 ALTER TABLE `agenda_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) NOT NULL,
  `target` enum('all','teachers','parents','class') DEFAULT 'all',
  `target_class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  KEY `target_class_id` (`target_class_id`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`target_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,'Rentrée scolaire 2025','La rentrée est prévue pour le 5 septembre 2025. Tous les élèves doivent se présenter en uniforme.',1,'all',NULL,'2026-07-04 12:13:12'),(2,'Réunion parents-enseignants','Une réunion est programmée le samedi 20 janvier à 10h dans la salle polyvalente.',1,'parents',NULL,'2026-07-04 12:13:12'),(3,'Examens du 1er trimestre','Les examens débuteront le 15 mars. Prière de bien préparer vos élèves.',1,'teachers',NULL,'2026-07-04 12:13:12');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `level` varchar(50) NOT NULL,
  `school_level` enum('primaire','secondaire') DEFAULT 'secondaire',
  `teacher_id` int(11) DEFAULT NULL,
  `max_students` int(11) DEFAULT 40,
  `room` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT '2024-2025',
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,'6ème A','Secondaire','secondaire',2,40,NULL,'2024-2025'),(2,'5ème B','Secondaire','secondaire',NULL,40,NULL,'2024-2025'),(3,'4ème A','Secondaire','secondaire',NULL,40,NULL,'2024-2025'),(4,'7ème année A','7ème année','secondaire',10,40,NULL,'2024-2025'),(5,'1ère Humanités Scientifique B','1ère Humanités','secondaire',10,40,NULL,'2024-2025'),(6,'1ère Humanités Pédagogie B','1ère Humanités','secondaire',NULL,40,NULL,'2024-2025'),(7,'3ème Humanités Scientifique E','3ème Humanités','secondaire',NULL,40,NULL,'2024-2025'),(8,'5ème année primaire A','5ème année','primaire',2,40,NULL,'2024-2025'),(9,'6ème année primaire B','6ème année','primaire',NULL,40,NULL,'2024-2025');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluations`
--

DROP TABLE IF EXISTS `evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `evaluation_type` enum('devoir','interrogation','exercice','examen') NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `period` varchar(50) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `max_score` decimal(5,2) NOT NULL DEFAULT 20.00,
  `eval_date` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  KEY `class_id` (`class_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluations_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluations_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluations`
--

LOCK TABLES `evaluations` WRITE;
/*!40000 ALTER TABLE `evaluations` DISABLE KEYS */;
INSERT INTO `evaluations` VALUES (1,7,1,9,2,'interrogation','Interro 1 Test','1ère Période',8.00,10.00,'2026-09-03','2026-09-03 20:48:56'),(2,7,1,9,2,'devoir','Devoir 1 Test','1ère Période',16.00,20.00,'2026-09-03','2026-09-03 20:48:56'),(3,7,1,9,2,'exercice','Exercice 1 Test','1ère Période',8.00,10.00,'2026-09-03','2026-09-03 20:48:56'),(4,2,1,2,2,'interrogation','interro eps','1ère Période',15.00,20.00,'2026-09-03','2026-09-03 20:56:18'),(5,5,1,2,2,'interrogation','interro eps','1ère Période',20.00,20.00,'2026-09-03','2026-09-03 20:56:18'),(6,2,1,2,2,'interrogation','Interro 1 : Calcul algébrique','1ère Période',17.50,20.00,'2026-08-30','2026-09-03 21:03:11'),(7,2,1,2,2,'devoir','Devoir maison : Équations du 1er degré','1ère Période',19.00,20.00,'2026-09-01','2026-09-03 21:03:11'),(8,2,1,2,2,'exercice','Exercice dirigé : Géométrie dans l\'espace','1ère Période',8.50,10.00,'2026-09-02','2026-09-03 21:03:11'),(9,2,2,2,2,'interrogation','Interro de Vocabulaire & Grammaire','1ère Période',15.00,20.00,'2026-08-31','2026-09-03 21:03:11'),(10,2,2,2,2,'devoir','Dissertation / Rédaction guidée','1ère Période',16.50,20.00,'2026-09-02','2026-09-03 21:03:11'),(11,2,3,2,2,'interrogation','Interro Sciences Physiques : Optique','1ère Période',14.00,20.00,'2026-08-29','2026-09-03 21:03:11'),(12,1,1,1,2,'devoir','Dvoir math','1ère Période',7.00,20.00,'2026-09-03','2026-09-03 21:22:20'),(13,4,1,1,2,'devoir','Dvoir math','1ère Période',8.00,20.00,'2026-09-03','2026-09-03 21:22:20'),(14,3,1,1,2,'devoir','Dvoir math','1ère Période',9.00,20.00,'2026-09-03','2026-09-03 21:22:20');
/*!40000 ALTER TABLE `evaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fee_payments`
--

DROP TABLE IF EXISTS `fee_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_fee_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `tranche_paid` varchar(100) DEFAULT NULL,
  `payment_currency` varchar(5) DEFAULT 'FC',
  `exchange_rate` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('cash','orange_money','mpesa','airtel_money','cheque','virement') DEFAULT 'cash',
  `transaction_ref` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `mm_transaction_id` int(10) unsigned DEFAULT NULL COMMENT 'Lien vers mobile_money_transactions.id',
  `mm_provider_ref` varchar(120) DEFAULT NULL COMMENT 'Référence opérateur',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_fee_id` (`student_fee_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `idx_payments_student` (`student_id`),
  CONSTRAINT `fee_payments_ibfk_1` FOREIGN KEY (`student_fee_id`) REFERENCES `student_fees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fee_payments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fee_payments_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_payments`
--

LOCK TABLES `fee_payments` WRITE;
/*!40000 ALTER TABLE `fee_payments` DISABLE KEYS */;
INSERT INTO `fee_payments` VALUES (1,1,1,100000.00,NULL,'FC',NULL,'orange_money',NULL,'2026-07-04','REC-2025-00001',NULL,NULL,NULL,1,'2026-07-04 12:13:12'),(2,3,2,150000.00,NULL,'FC',NULL,'cash',NULL,'2026-07-04','REC-2025-00002',NULL,NULL,NULL,1,'2026-07-04 12:13:12'),(3,10,2,20001.00,'','FC',NULL,'cash','','2026-07-08','REC-2026-40D89E',NULL,NULL,'',1,'2026-07-08 21:21:29'),(4,11,5,20001.00,'Acompte','FC',NULL,'cash','','2026-07-10','REC-2026-BAA1CD',NULL,NULL,'Paiement : Acompte',1,'2026-07-10 13:29:46'),(5,11,5,20001.00,'','FC',NULL,'cash','','2026-07-10','REC-2026-B538E8',NULL,NULL,'',1,'2026-07-10 13:41:36'),(6,13,4,22408.00,'Acompte','USD',2801.00,'airtel_money','0977346412','2026-08-21','REC-2026-3A7579',NULL,NULL,'Paiement : Acompte',1,'2026-08-21 13:44:18');
/*!40000 ALTER TABLE `fee_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fee_types`
--

DROP TABLE IF EXISTS `fee_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `school_year` varchar(20) DEFAULT '2024-2025',
  `is_active` tinyint(1) DEFAULT 1,
  `nb_tranches` tinyint(4) DEFAULT 1,
  `tranche_note` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_types`
--

LOCK TABLES `fee_types` WRITE;
/*!40000 ALTER TABLE `fee_types` DISABLE KEYS */;
INSERT INTO `fee_types` VALUES (1,'Minerval annuel',150000.00,'Frais de scolarité annuels','2024-2025',1,1,NULL),(2,'Frais d\'inscription',25000.00,'Inscription scolaire','2024-2025',1,1,NULL),(3,'Frais de bibliothèque',10000.00,'Accès bibliothèque','2024-2025',1,1,NULL),(4,'Frais d\'examens',20000.00,'Organisation des examens','2024-2025',1,1,NULL);
/*!40000 ALTER TABLE `fee_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grades`
--

DROP TABLE IF EXISTS `grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `grade` decimal(5,2) NOT NULL,
  `max_grade` decimal(5,2) DEFAULT 20.00,
  `period` varchar(30) DEFAULT '1er Trimestre',
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `idx_grades_student` (`student_id`),
  KEY `idx_grades_period` (`period`),
  CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grades`
--

LOCK TABLES `grades` WRITE;
/*!40000 ALTER TABLE `grades` DISABLE KEYS */;
INSERT INTO `grades` VALUES (1,1,1,2,16.00,20.00,'1er Trimestre','Très bon travail','2026-07-04 12:13:12'),(2,1,3,2,14.00,20.00,'1er Trimestre','Bon effort','2026-07-04 12:13:12'),(3,1,2,2,12.00,20.00,'1er Trimestre','Peut mieux faire','2026-07-04 12:13:12'),(4,2,1,2,18.00,20.00,'1er Trimestre','Excellent','2026-07-04 12:13:12'),(5,3,1,2,10.00,20.00,'1er Trimestre','Doit travailler plus','2026-07-04 12:13:12'),(6,4,1,2,15.00,20.00,'1er Trimestre','Bien','2026-07-04 12:13:12'),(7,1,1,2,14.00,20.00,'2ème Trimestre','Légère baisse','2026-07-04 12:13:12'),(8,1,3,2,15.00,20.00,'2ème Trimestre','En progression','2026-07-04 12:13:12'),(9,1,2,2,11.00,20.00,'2ème Trimestre','Efforts nécessaires','2026-07-04 12:13:12'),(10,2,1,2,17.00,20.00,'2ème Trimestre','Toujours excellent','2026-07-04 12:13:12'),(11,3,1,2,12.00,20.00,'2ème Trimestre','Amélioration visible','2026-07-04 12:13:12'),(12,4,1,2,14.00,20.00,'2ème Trimestre','Stable','2026-07-04 12:13:12'),(13,1,1,2,17.00,20.00,'3ème Trimestre','Très belle progression','2026-07-04 12:13:12'),(14,1,3,2,16.00,20.00,'3ème Trimestre','Excellent','2026-07-04 12:13:12'),(15,2,1,2,19.00,20.00,'3ème Trimestre','Parfait','2026-07-04 12:13:12'),(16,3,1,2,13.00,20.00,'3ème Trimestre','Continue ainsi','2026-07-04 12:13:12'),(17,4,3,2,15.00,20.00,'1er Trimestre','bon','2026-08-19 02:54:54'),(18,2,1,2,15.00,20.00,'1ère Période',NULL,'2026-09-03 20:56:18'),(19,5,1,2,20.00,20.00,'1ère Période',NULL,'2026-09-03 20:56:18'),(20,1,1,2,7.00,20.00,'1ère Période',NULL,'2026-09-03 21:22:20'),(21,3,1,2,9.00,20.00,'1ère Période',NULL,'2026-09-03 21:22:20'),(22,4,1,2,8.00,20.00,'1ère Période',NULL,'2026-09-03 21:22:20');
/*!40000 ALTER TABLE `grades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_deleted_sender` tinyint(1) DEFAULT 0,
  `is_deleted_receiver` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_messages_receiver` (`receiver_id`,`is_read`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,1,5,'Message','qsdfghjkl',0,0,0,'2026-07-10 01:14:24'),(2,2,6,'reunion','c\'est quand la reunion?',0,0,0,'2026-08-19 02:56:01'),(3,9,10,'Message','dfghjkl',1,0,0,'2026-08-19 02:56:51'),(4,10,9,'Conversation','sdfghjklm',0,0,0,'2026-08-19 02:57:42');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mobile_money_transactions`
--

DROP TABLE IF EXISTS `mobile_money_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mobile_money_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned NOT NULL,
  `operator` enum('mpesa','airtel','orange','africell') NOT NULL,
  `phone` varchar(20) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(5) NOT NULL DEFAULT 'CDF',
  `reference` varchar(80) NOT NULL,
  `provider_ref` varchar(120) DEFAULT NULL COMMENT 'Référence retournée par l''opérateur',
  `status` enum('pending','completed','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `status_message` varchar(255) DEFAULT NULL,
  `webhook_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Payload du webhook opérateur' CHECK (json_valid(`webhook_data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `idx_student` (`student_id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_operator` (`operator`),
  KEY `idx_status` (`status`),
  KEY `idx_ref` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Suivi des transactions Mobile Money EduSuivi';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mobile_money_transactions`
--

LOCK TABLES `mobile_money_transactions` WRITE;
/*!40000 ALTER TABLE `mobile_money_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `mobile_money_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('message','absence','note','fee','event','alert') DEFAULT 'alert',
  `title` varchar(200) NOT NULL,
  `body` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,3,'fee','✅ Paiement enregistré','Reçu REC-2026-40D89E — 20 001 FC',0,'/edusuivi/parent/fees.php','2026-07-08 21:21:29'),(2,5,'message','Message de Directeur Kalumba','qsdfghjkl',0,'/edusuivi/admin/messages.php?contact=1','2026-07-10 01:14:25'),(3,6,'message','Message de Prof. Ngozi Mutombo','c\'est quand la reunion?',0,'/edusuivi/teacher/messages.php?contact=2','2026-08-19 02:56:01'),(4,10,'message','Message de Chançard Menda','dfghjkl',0,'/edusuivi/admin/messages.php?contact=9','2026-08-19 02:56:51'),(5,9,'message','Message de lakumu michée','sdfghjklm',0,'/edusuivi/teacher/messages.php?contact=10','2026-08-19 02:57:42'),(6,1,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(7,2,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(8,3,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(9,4,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(10,5,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(11,6,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(12,7,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(13,8,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(14,9,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(15,10,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(16,11,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(17,12,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48'),(18,13,'event','📅 Nouvel événement : reunion','2026-08-19',0,'/edusuivi/admin/agenda.php','2026-08-19 02:59:48');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_reminders`
--

DROP TABLE IF EXISTS `payment_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `student_fee_id` int(11) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_by` int(11) NOT NULL,
  `method` enum('notification','message') DEFAULT 'notification',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `sent_by` (`sent_by`),
  CONSTRAINT `payment_reminders_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_reminders_ibfk_2` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_reminders`
--

LOCK TABLES `payment_reminders` WRITE;
/*!40000 ALTER TABLE `payment_reminders` DISABLE KEYS */;
INSERT INTO `payment_reminders` VALUES (1,1,0,'2026-07-06 12:37:01',1,'notification');
/*!40000 ALTER TABLE `payment_reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `period_maxima`
--

DROP TABLE IF EXISTS `period_maxima`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `period_maxima` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `period` varchar(50) NOT NULL,
  `max_grade` decimal(5,2) NOT NULL DEFAULT 20.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_sub_period` (`class_id`,`subject_id`,`period`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `period_maxima_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `period_maxima_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `period_maxima`
--

LOCK TABLES `period_maxima` WRITE;
/*!40000 ALTER TABLE `period_maxima` DISABLE KEYS */;
INSERT INTO `period_maxima` VALUES (1,9,1,'1ère Période',20.00),(2,2,1,'1ère Période',20.00);
/*!40000 ALTER TABLE `period_maxima` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schools`
--

DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schools`
--

LOCK TABLES `schools` WRITE;
/*!40000 ALTER TABLE `schools` DISABLE KEYS */;
INSERT INTO `schools` VALUES (1,'École Primaire Saint-Joseph','Avenue Kasa-Vubu, Kinshasa-Gombe','+243 81 234 5678','ecole@edusuivi.cd',NULL,'2026-07-04 12:14:00'),(2,'Complexe Scolaire Sainte Trinité','1ere Rue, quartier debonhomme/Des Marais n°333/Matete','','chancard@gmail.com',NULL,'2026-07-06 11:11:44'),(3,'École Primaire Saint-Joseph','Avenue Kasa-Vubu, Kinshasa-Gombe','+243 81 234 5678','admin@edusuivi.cd',NULL,'2026-07-06 11:44:45'),(4,'Complexe Scolaire Sainte Trinité','1ere rue debonhome/des marais n°333/matete','','chancard1@gmail.com',NULL,'2026-07-06 11:48:55'),(5,'Complexe Scolaire Sainte Trinité','1ere rue debonhome/des marais n°333','','chancard2@gmail.com',NULL,'2026-07-06 11:50:58'),(6,'Complexe Scolaire Sainte Trinité','1ere rue Debonhome/Des marais n°333/Matete','','chancard140@gmail.com',NULL,'2026-07-06 12:21:51'),(7,'Complexe Scolaire Sainte Trinité','1ere rue Debonhome/Des marais n°333/Matete','','chancard24@gmail.com',NULL,'2026-07-06 12:58:46'),(8,'Complexe Scolaire Sainte Trinité','azertyuioghjchsjkksckh','0977346412','chazer@gmail.com',NULL,'2026-08-19 02:47:49');
/*!40000 ALTER TABLE `schools` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `smart_alerts`
--

DROP TABLE IF EXISTS `smart_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `smart_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_type` enum('grade_drop','repeated_absence','unpaid_fees','event_reminder') NOT NULL,
  `threshold_value` decimal(5,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `smart_alerts`
--

LOCK TABLES `smart_alerts` WRITE;
/*!40000 ALTER TABLE `smart_alerts` DISABLE KEYS */;
INSERT INTO `smart_alerts` VALUES (1,'grade_drop',20.00,1,'2026-07-04 12:13:12'),(2,'repeated_absence',3.00,1,'2026-07-04 12:13:12'),(3,'unpaid_fees',NULL,1,'2026-07-04 12:13:12'),(4,'event_reminder',2.00,1,'2026-07-04 12:13:12');
/*!40000 ALTER TABLE `smart_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_fees`
--

DROP TABLE IF EXISTS `student_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `fee_type_id` int(11) NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `school_year` varchar(20) DEFAULT '2024-2025',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `fee_type_id` (`fee_type_id`),
  CONSTRAINT `student_fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_fees_ibfk_2` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_fees`
--

LOCK TABLES `student_fees` WRITE;
/*!40000 ALTER TABLE `student_fees` DISABLE KEYS */;
INSERT INTO `student_fees` VALUES (1,1,1,150000.00,'2024-2025','2026-07-04 12:13:12'),(2,1,2,25000.00,'2024-2025','2026-07-04 12:13:12'),(3,2,1,150000.00,'2024-2025','2026-07-04 12:13:12'),(4,2,2,25000.00,'2024-2025','2026-07-04 12:13:12'),(5,3,1,150000.00,'2024-2025','2026-07-04 12:13:12'),(6,3,2,25000.00,'2024-2025','2026-07-04 12:13:12'),(7,4,1,150000.00,'2024-2025','2026-07-04 12:13:12'),(8,5,1,150000.00,'2024-2025','2026-07-04 12:13:12'),(9,1,4,20000.00,'2026-2027','2026-07-08 21:09:19'),(10,2,4,20000.00,'2026-2027','2026-07-08 21:21:29'),(11,5,4,20000.00,'2026-2027','2026-07-10 13:29:46'),(12,5,2,25000.00,'2026-2027','2026-07-10 13:37:22'),(13,4,4,20000.00,'2026-2027','2026-08-21 13:44:18');
/*!40000 ALTER TABLE `student_fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_rankings`
--

DROP TABLE IF EXISTS `student_rankings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_rankings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `period` varchar(30) NOT NULL,
  `school_year` varchar(20) DEFAULT '2024-2025',
  `average` decimal(5,2) DEFAULT 0.00,
  `rank_position` int(11) DEFAULT 0,
  `computed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rank` (`student_id`,`period`,`school_year`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `student_rankings_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_rankings_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_rankings`
--

LOCK TABLES `student_rankings` WRITE;
/*!40000 ALTER TABLE `student_rankings` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_rankings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `matricule` varchar(20) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('M','F') DEFAULT 'M',
  `option_filiere` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT '2024-2025',
  `class_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,'MAT-2025-0001','Jean','Kabongo','2010-03-15','M','Sciences','0977346412','FYU',NULL,'2024-2025',1,3,'2026-07-04 12:13:12','2026-09-03 21:03:11'),(2,'MAT-2025-0002','Sarah','Kabongo','2012-07-22','F','Commerce',NULL,NULL,NULL,'2024-2025',2,3,'2026-07-04 12:13:12','2026-07-04 12:13:12'),(3,'MAT-2025-0003','Pierre','Mwamba','2011-01-10','M','Sciences',NULL,NULL,NULL,'2024-2025',1,NULL,'2026-07-04 12:13:12','2026-07-04 12:13:12'),(4,'MAT-2025-0004','Grace','Lukusa','2010-11-05','F','Lettres',NULL,NULL,NULL,'2024-2025',1,NULL,'2026-07-04 12:13:12','2026-07-04 12:13:12'),(5,'MAT-2025-0005','David','Tshimanga','2011-06-18','M','Sciences',NULL,NULL,NULL,'2024-2025',2,NULL,'2026-07-04 12:13:12','2026-07-04 12:13:12'),(6,'MAT-2026-9441','egrh','rer','3333-02-12','M','COMMERCIAL','0977346412','EEZTYUIO',NULL,'2024-2025',3,NULL,'2026-08-19 02:49:04','2026-08-19 02:49:04'),(7,'MAT-2026-1001','Dieudonné','Mukendi',NULL,'M',NULL,NULL,NULL,NULL,'2024-2025',9,3,'2026-09-03 20:00:32','2026-09-03 21:03:11'),(8,'MAT-2026-1002','Dorcas','Kanyinda',NULL,'F',NULL,NULL,NULL,NULL,'2024-2025',9,NULL,'2026-09-03 20:00:32','2026-09-03 20:00:32'),(9,'MAT-2026-1003','Jonathan','Kalala',NULL,'M',NULL,NULL,NULL,NULL,'2024-2025',8,NULL,'2026-09-03 20:00:32','2026-09-03 20:00:32');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `school_level` enum('primaire','secondaire','les_deux') NOT NULL DEFAULT 'secondaire',
  `category` varchar(100) NOT NULL DEFAULT 'Général',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,'Mathématiques','les_deux','Sciences Exactes'),(2,'Français','les_deux','Langues'),(3,'Sciences','secondaire','Sciences'),(4,'Histoire-Géographie','secondaire','Sciences Humaines'),(5,'Anglais','secondaire','Langues'),(6,'Éducation Civique','les_deux','Éducation Civique & Morale'),(7,'Langues Nationales & Traditions','primaire','Langues'),(8,'Sciences & Éveil','primaire','Sciences'),(9,'Éducation Physique & Santé','primaire','Sport & Santé'),(10,'Arts Plastiques & Musique','primaire','Arts & Culture'),(11,'Physique','secondaire','Sciences Exactes'),(12,'Chimie','secondaire','Sciences Exactes'),(13,'Biologie & Sciences de la Vie','secondaire','Sciences Naturelles'),(14,'Informatique & TIC','secondaire','Technologies'),(15,'Philosophie','secondaire','Sciences Humaines');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_assignments`
--

DROP TABLE IF EXISTS `teacher_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teacher_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_id` (`teacher_id`,`class_id`,`subject_id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `teacher_assignments_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_assignments_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_assignments_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_assignments`
--

LOCK TABLES `teacher_assignments` WRITE;
/*!40000 ALTER TABLE `teacher_assignments` DISABLE KEYS */;
INSERT INTO `teacher_assignments` VALUES (1,2,1,1),(2,2,1,3),(3,2,2,1),(4,10,2,5);
/*!40000 ALTER TABLE `teacher_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_preferences` (
  `user_id` int(11) NOT NULL,
  `lang` varchar(5) DEFAULT 'fr',
  `theme` varchar(20) DEFAULT 'dark',
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES (2,'fr','dark',1,'2026-07-10 01:37:04'),(9,'fr','dark',1,'2026-08-19 02:56:19'),(10,'ln','dark',1,'2026-08-19 02:53:37');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('school','admin','teacher','parent') NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_school` (`school_id`),
  KEY `idx_reset_token` (`reset_token`),
  CONSTRAINT `fk_users_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Directeur Kalumba','admin@edusuivi.cd','$2y$10$.uA8fXaa2WPbNS/bvuF3XecnY0J7XjGkK7.SwFVUBXA0CrA6RbcxO','admin',3,NULL,NULL,'2026-07-04 12:13:12'),(2,'Prof. Ngozi Mutombo','ngozi@edusuivi.cd','$2y$10$NafXRI6qYfpJa.EMVznj3eVvbDg/Is16yM1gjXmCzlHZKWv8qQCwq','teacher',NULL,NULL,NULL,'2026-07-04 12:13:12'),(3,'Marie Kabongo','marie@edusuivi.cd','$2y$10$EV91dwN3yd6zbQ0XgIA2.ub.bIMexvFkSSyANvLNvNZmAkGBDtZ/m','parent',NULL,NULL,NULL,'2026-07-04 12:13:12'),(4,'École Saint-Joseph','ecole@edusuivi.cd','$2y$10$CyA4FzTcEUDhmVNBhOBA6ugAVZXqDaBIU8wus2zYZ9Siupsd6Kz8u','admin',1,NULL,NULL,'2026-07-04 12:14:00'),(5,'Chançard Menda','chancard@gmail.com','$2y$10$LDfozerN3rOiQdCy1hRKmO4Rc4rvNkxRoefwkiTKobV8/XUjzz3tm','admin',2,NULL,NULL,'2026-07-06 11:11:45'),(6,'Chançard Menda','chancard1@gmail.com','$2y$10$CJSWJ91A9SAbEn8Rqee8UeYfTMQlzD6271dD5b3JdkALNXsiF4FJ2','admin',4,NULL,NULL,'2026-07-06 11:48:55'),(7,'Chançard Menda','chancard2@gmail.com','$2y$10$6Zuo/T.109SQaE7EzHkUHuW0iNJoHigW2RRcc8fzGxtfvqc3v2aMO','admin',5,NULL,NULL,'2026-07-06 11:50:58'),(8,'Chançard Menda','chancard140@gmail.com','$2y$10$3AvXkSmCrTfoOV16e94L1OIFUEE1W7pqBFE88lHlUVRnEY5DW16Dm','admin',6,NULL,NULL,'2026-07-06 12:21:51'),(9,'Chançard Menda','chancard24@gmail.com','$2y$10$0yL7OXHw3IQAIbGobi2syO9zqdkwOEdz3LUmfk7kRmJh9wt63OWM.','admin',7,NULL,NULL,'2026-07-06 12:58:46'),(10,'lakumu michée','lakumu@gmail.com','$2y$10$gkvKuxQjcPkzYREAOG9l9exze4LwwFUvCcCKPtG9x7snaz2fPF8Fq','teacher',NULL,NULL,NULL,'2026-07-10 00:33:43'),(11,'Chançard Menda','chazer@gmail.com','$2y$10$tutvw8EI4eqbRJVj9dOPV.SQXVSuHdOoAmJR3cVcEY2EGD/fNXzWS','admin',8,NULL,NULL,'2026-08-19 02:47:49'),(12,'WISE','Wise@gmail.com','$2y$10$POrReLQz2LTQ0ICNSo1KOOF1nMwqKAPgTxEWAtR8iAGX9wVYdR8zO','parent',NULL,NULL,NULL,'2026-08-19 02:50:07'),(13,'WISE PUNGI','Wise1@gmail.com','$2y$10$.L4rPrLHj4yrcGrnFWYmuu.FSNSasr5n/uWHWKvwJwgtiaVYbe6wu','teacher',NULL,NULL,NULL,'2026-08-19 02:52:48'),(14,'lfd','merdiebayeke11@gmail.com','$2y$10$u2tljbSLfzJt9W8vGTEJremRKnHfHKl3CRHbLEqwIU1OgFIhXhlG.','teacher',NULL,NULL,NULL,'2026-09-03 20:26:35');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-04  1:12:02
