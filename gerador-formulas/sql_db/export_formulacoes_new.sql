-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: u849249951_innovasell
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
-- Table structure for table `formulacoes`
--

DROP TABLE IF EXISTS `formulacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `formulacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_formula` varchar(255) NOT NULL,
  `codigo_formula` varchar(50) NOT NULL,
  `antigo_codigo` varchar(255) DEFAULT NULL,
  `categoria` varchar(100) NOT NULL,
  `desenvolvida_para` varchar(255) DEFAULT NULL,
  `solicitada_por` varchar(255) DEFAULT NULL,
  `caminho_pdf` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `formulacoes`
--

LOCK TABLES `formulacoes` WRITE;
/*!40000 ALTER TABLE `formulacoes` DISABLE KEYS */;
INSERT INTO `formulacoes` VALUES (34,'Creme Hidratante - Papaya com Cassis','BC/092025002',NULL,'BC','Boticário - Innovation Day - 05-2025','Riedi',NULL,'2025-09-28 05:06:35'),(43,'Gel Creme com Retinol RRT','SKC/102025001','SC-05325','SKC','VIDELLI','Thiago Rodrigues',NULL,'2025-10-02 13:20:09'),(58,'Dual-Phase Mist','SKC/102025002','SC-05025','SKC','INCOSMETICS 2025','Marketing','temp/SKC-102025002.pdf','2025-10-03 17:34:10'),(59,'Dual-Phase Mist','SKC/102025003','SC-05025','SKC','INCOSMETICS 2025','Marketing','temp/SKC-102025003.pdf','2025-10-03 17:35:42'),(60,'PROTETOR SOLAR BIFÁSICO BRONZEADOR','SUC/102025001','SC-02125_01','SUC','Natura','Bruno','temp/SUC-102025001.pdf','2025-10-08 14:32:56'),(117,'srsrs','PC/102025001','asdasd','PC','aasdads','asda','temp/PC-102025001.pdf','2025-10-09 05:53:04'),(118,'srsrs','PC/102025002','asdasd','PC','aasdads','asda','temp/PC-102025002.pdf','2025-10-09 05:53:40'),(119,'srsrs','PC/102025003','asdasd','PC','aasdads','asda','temp/PC-102025003.pdf','2025-10-09 06:03:32'),(120,'srsrs','PC/102025004','asdasd','PC','aasdads','asda','temp/PC-102025004.pdf','2025-10-09 06:04:08'),(121,'2021_HC-00021_SUMMER SHIELD FLUID','IMP/012026010',NULL,'GEN',NULL,NULL,NULL,'2026-01-30 16:45:31'),(122,'2022_HC-00022_SENSITIVE SCALP COM PIROCTONE OLAMINE, NATIFLEX E PHEOHYDRANE','IMP/012026011',NULL,'GEN',NULL,NULL,NULL,'2026-01-30 16:45:41'),(123,'2022_HC-00122_02_SHAMPOO 3 EM 1','IMP/012026012',NULL,'GEN',NULL,NULL,NULL,'2026-01-30 16:45:49'),(124,'2022_HC-00122_SENSITIVE SCALP COM CAPIGUARD E CAPIBIOME','IMP/012026013',NULL,'GEN',NULL,NULL,NULL,'2026-01-30 16:45:59'),(125,'2022_HC-00222_TONICO COM WKPEP COPPER PEPTIDE E WKPEP MELIT','IMP/012026014',NULL,'GEN',NULL,NULL,NULL,'2026-01-30 16:46:05'),(126,'2022_HC-00322_TONICO COM WKPEP PRO-HAIR E WKPEP MELIT','IMP/012026015',NULL,'GEN',NULL,NULL,NULL,'2026-01-30 16:46:11'),(127,'2021_HC-00021_SUMMER SHIELD FLUID','IMP/012026016',NULL,'GEN',NULL,NULL,NULL,'2026-01-30 16:49:08');
/*!40000 ALTER TABLE `formulacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sub_formulacoes`
--

DROP TABLE IF EXISTS `sub_formulacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sub_formulacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `formulacao_id` int(11) NOT NULL,
  `nome_sub_formula` varchar(255) NOT NULL,
  `modo_preparo` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sub_formulacoes`
--

LOCK TABLES `sub_formulacoes` WRITE;
/*!40000 ALTER TABLE `sub_formulacoes` DISABLE KEYS */;
INSERT INTO `sub_formulacoes` VALUES (6,34,'Cassis','Pesar a Fase A e colocar em agitação (400 - 500 rpm).\r\nPesar a Fase B separadamente, homogeneizar e adicionar sobre a Fase A, em agitação (700 – 900 rpm).\r\nSe necessário, ajustar o pH da formulação entre 5,5 – 6,5, com a Fase C.\r\n'),(7,34,'Papaya','Pesar a Fase A e colocar em agitação (300 rpm).\r\nPesar a Fase B separadamente, homogeneizar, adicionar sobre a Fase A e aquecer até 80 ºC, sob agitação (700-900 rpm).\r\nPesar a Fase C separadamente, aquecer até 80 ºC e adicionar sobre as Fases A+B, ambas a 80 ºC, sob agitação (700-900 rpm) por 10 minutos. \r\nResfriar até 35º C.\r\nPesar a Fase D separadamente e adicionar sobre as Fases A+B+C, sob agitação (600 – 700 rpm).\r\nSe necessário, ajustar o pH entre 5,5 e 6,5 com a Fase E.\r\n'),(20,58,'Água','Pesar a Fase A e colocar em agitação (400 - 500 rpm).\r\nPesar a Fase B e adicionar, item a item, sobre a Fase A. \r\nHomogeneizar durante 20 minutos (500 - 600 rpm).\r\nSe necessário, ajustar o pH da formulação entre 5,5 – 6,5 com a Fase C.'),(21,59,'Água','Pesar a Fase A e colocar em agitação (400 - 500 rpm).\r\nPesar a Fase B e adicionar, item a item, sobre a Fase A. \r\nHomogeneizar durante 20 minutos (500 - 600 rpm).\r\nSe necessário, ajustar o pH da formulação entre 5,5 – 6,5 com a Fase C.'),(79,60,'Água',''),(80,121,'Fase Única (Importada)','| Preparo                                                                        |\n|--------------------------------------------------------------------------------|\n| Pesar a Fase A e homogeneizar.                                                 |\n| Ajustar o pH com a fase B entre 4,0 - 4,5.                                     |\n| Adicionar a faseC e colocar em agitação (500 rpm) até homogeneização completa. |\n\n<!-- image -->\n\nAdicionar item a item da fase D e envasar.\n\nDica: Caso deseje, acrescente 0,2% de fragrância.\n\n<!-- image -->'),(81,122,'Fase Única (Importada)','## Preparo\n\nPesar a Fase A e colocar em agitação (500 - 700 rpm).\n\nPesar a Fase B separadamente, adicionar sobre A e aquecer até 80 °C em agitação (700 rpm).\n\nPesar a Fase C separadamente e aquecer até 80 ºC.\n\nEm agitação, adicionar a Fase A+B sobre C ambas a 80 ºC.\n\nResfriar e adicionar item a item a Fase D.\n\nAdicionar as Fases E e F item a item mantendo a homogeneização.\n\nDica: Caso deseje acrescentar 0,5% de fragrância.\n\n<!-- image -->'),(82,123,'Fase Única (Importada)','Pesar a Fase A e colocar em agitação (500 -700 rpm).\n\nPesar a Fase B separadamente, adicionar sobre A e aquecer até 80 °C em agitação (700 rpm).\n\nPesar a Fase C separadamente e aquecer até 80 ºC.\n\nEm agitação, adicionar a Fase A+B sobre C ambas a 80 ºC.\n\nResfriar (35 -40 ºC).\n\nPesar a Fase D separadamente e adicionar sobre A+B+C.\n\nAdicionar as Fases E e F item a item mantendo a homogeneização.\n\n<!-- image -->\n\n<!-- image -->\n\n<!-- image -->'),(83,124,'Fase Única (Importada)','## Preparo\n\nPesar a Fase A e colocar em agitação (500 - 700 rpm).\n\nPesar a Fase B separadamente, adicionar sobre A e aquecer até 80 °C em agitação (700 rpm).\n\nPesar a Fase C separadamente e aquecer até 80 ºC.\n\nEm agitação, adicionar a Fase A+B sobre C ambas a 80 ºC.\n\nResfriar e adicionar item a item a Fase D.\n\nAdicionar as Fases E e F item a item mantendo a homogeneização.\n\nDica: Caso deseje acrescentar 0,5% de fragrância.\n\n<!-- image -->'),(84,125,'Fase Única (Importada)','## Preparo\n\nPesar a Fase A e homogeneizar.\n\nAdicionar os itens da Fase B mantendo a homogeneização.\n\nAdicionar a Fase C e envasar, preferencialmente em uma embalagem que proteja o produto da luz.\n\nDica: Caso deseje acrescentar 0,5% de fragrância.\n\n<!-- image -->'),(85,126,'Fase Única (Importada)','## Preparo\n\nPesar a Fase A e homogeneizar.\n\nAdicionar os itens da Fase B mantendo a homogeneização.\n\nPesar a Fase C separadamente e adicionar sobre as Fases A+B mantendo a homogeneização.\n\nAdicionar Fase D e se necessário ajustar o pH entre 5 - 6.\n\nDica: Caso deseje acrescentar 0,5% de fragrância juntamente na fase C.\n\n<!-- image -->'),(86,127,'Fase Única (Importada)','| Preparo                                                                        |\n|--------------------------------------------------------------------------------|\n| Pesar a Fase A e homogeneizar.                                                 |\n| Ajustar o pH com a fase B entre 4,0 - 4,5.                                     |\n| Adicionar a faseC e colocar em agitação (500 rpm) até homogeneização completa. |\n\n<!-- image -->\n\nAdicionar item a item da fase D e envasar.\n\nDica: Caso deseje, acrescente 0,2% de fragrância.\n\n<!-- image -->');
/*!40000 ALTER TABLE `sub_formulacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fases`
--

DROP TABLE IF EXISTS `fases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_fase` varchar(100) NOT NULL,
  `sub_formulacao_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=301 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fases`
--

LOCK TABLES `fases` WRITE;
/*!40000 ALTER TABLE `fases` DISABLE KEYS */;
INSERT INTO `fases` VALUES (66,'A',6),(67,'B',6),(68,'C',6),(69,'A',7),(70,'B',7),(83,'A',20),(84,'B',20),(85,'A',21),(86,'B',21),(289,'A',79),(290,'B',79),(291,'C',79),(292,'D',79),(293,'E',79),(294,'Fase A',80),(295,'Fase A',81),(296,'Fase A',82),(297,'Fase A',83),(298,'Fase A',84),(299,'Fase A',85),(300,'Fase A',86);
/*!40000 ALTER TABLE `fases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ingredientes`
--

DROP TABLE IF EXISTS `ingredientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ingredientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fase_id` int(11) NOT NULL,
  `materia_prima` varchar(255) NOT NULL,
  `inci_name` varchar(255) DEFAULT NULL,
  `percentual` varchar(20) DEFAULT NULL,
  `destaque` tinyint(1) NOT NULL DEFAULT 0,
  `qsp` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=446 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ingredientes`
--

LOCK TABLES `ingredientes` WRITE;
/*!40000 ALTER TABLE `ingredientes` DISABLE KEYS */;
INSERT INTO `ingredientes` VALUES (25,66,'Água','Water','91,10',0,0),(26,66,'EDTA','Disodium EDTA','0,10',0,0),(27,66,'Phenoxyethanol','Phenoxyethanol','0,80',0,0),(28,67,'Glicerina','Glycerin','5,00',0,0),(29,67,'Ziga Moist PM-II','Hydrolyzed Sclerotium Gum','3,00',1,0),(30,68,'Sol. Hidróxido de Sódio 30%','Water (and) Sodium Hydroxide','QSP',0,0),(31,69,'Água','Water','82,40',0,0),(32,69,'Phenoxyethanol (and) Caprylyl Glycol','Phenoxyethanol (and) Caprylyl Glycol','1,00',0,0),(33,69,'EDTA','Disodium EDTA','0,10',0,0),(34,70,'Glicerina','Glycerin','3,00',0,0),(35,70,'Ziga Moist PM-II','Hydrolyzed Sclerotium Gum','1,50',1,0),(50,83,'Água','Water','70.05',0,0),(51,84,'Peauvita','Water (and) Glycerin (and) Xanthan Gum (and) Camelina Sativa Oleosomes/Camelina Sativa sr-(Arabidopsis Thaliana Polypeptide-2 sh-Oligopeptide-1) (and) Gluconolactone','4.95',0,0),(52,85,'Água','Water','70.05',0,0),(53,86,'Peauvita','Water (and) Glycerin (and) Xanthan Gum (and) Camelina Sativa Oleosomes/Camelina Sativa sr-(Arabidopsis Thaliana Polypeptide-2 sh-Oligopeptide-1) (and) Gluconolactone','4',0,0),(362,289,'Água','Water','0',0,0),(363,289,'EDTA','Disodium EDTA','0',0,0),(364,289,'Phenoxyethanol','Phenoxyethanol','0',0,0),(365,289,'Phenylbenzimidazole Sulfonic Acid','Phenylbenzimidazole Sulfonic Acid','0',0,0),(366,289,'Sodium Chloride','Sodium Chloride','0',0,0),(367,290,'Aminomethyl propanol (and) Water','Aminomethyl propanol (and) Water ','0',0,0),(368,291,'Citric Acid','Citric Acid','0',0,0),(369,292,'Thalitan','Water (and) Hydrolyzed Algin (and) Magnesium sulfate (and) Manganese sulfate (and) Phenoxyethanol','0',0,0),(370,293,'Aminomethyl propanol (and) Water',' Aminomethyl propanol (and) Water ','0',0,0),(371,294,'ÁGUA','Water','91.6',0,0),(372,294,'EDTA','Dissodium EDTA','0.1',0,0),(373,294,'MICROCARE PHG','Phenoxyethanol (and) caprylyl glycol','1',0,0),(374,294,'ÁCIDO LÁTICO','Lactic Acid','0',0,0),(375,294,'*NATIFLEX HYAMATE','Hydroxypropyltrimonium Hyaluronate','0.3',0,0),(376,294,'*CAPIGUARD P','Water (and) Phenoxyethanol (and) Furcellaria lumbricalis extract','1',0,0),(377,294,'*PHEOHYDRANE','Water (and) Hydrolized Algin (and) Sea water (and) Chlorella vulgaris extract (and) Phenoxyethanol','1',0,0),(378,294,'*MUSAP','Polygonatum Odoratum Extract','5',0,0),(379,295,'ÁGUA','Water','73.8',0,0),(380,295,'ÁCIDO LÁTICO','Lactic Acid','0.7',0,0),(381,295,'EDTA','Disodium EDTA','0.1',0,0),(382,295,'GLICERINA','Glycerin','2',0,0),(383,295,'HIDROXIETIL CELULOSE','Hydroxyethylcellulose','0.2',0,0),(384,295,'ALCOOL CETO 30/70','Cetearyl Alcohol','5',0,0),(385,295,'BTMS','Cetearyl Alcohol (and) Behentrimonium Methosulfate','5',0,0),(386,295,'POLAWAX NF','Cetearyl Alcohol (and) Polysorbate 60','5',0,0),(387,295,'STEARAMIDOPROPYL DIMETHYLAMINE','Stearamidopropyl dimethylamine','2',0,0),(388,295,'POLIQUATERNIO 7','Polyquaternium-7','2',0,0),(389,295,'BETAÍNA','Cocamidopropyl Betaine','2',0,0),(390,295,'*PIROCTONE OLAMINE','Piroctone Olamine','0.5',0,0),(391,295,'*NATIFLEX HYAMATE','Hyaluronic Acid','0.2',0,0),(392,295,'*PHEOHYDRANE','Water (and) Hydrolized Algin (and) Sea water (and) Chlorella vulgaris extract (and) Phenoxyethanol','1',0,0),(393,295,'ISOTIAZOLINONA','Isotiazolinona','0.5',0,0),(394,296,'Água','Water','67',0,0),(395,296,'Lactic Acid','Lactic Acid','0.7',0,0),(396,296,'Disodium EDTA','Disodium EDTA','0.1',0,0),(397,296,'Glicerina','Glycerin','2',0,0),(398,296,'Hydroxyethylcellulose','Hydroxyethylcellulose','0.2',0,0),(399,296,'Cetearyl Alcohol','Cetearyl Alcohol','5',0,0),(400,296,'Cetearyl Alcohol (and) Behentrimonium Methosulfate','Cetearyl Alcohol (and) Behentrimonium Methosulfate\'','5',0,0),(401,296,'Cetearyl Alcohol (and) Polysorbate 60','Cetearyl Alcohol (and) Polysorbate 60','5',0,0),(402,296,'Stearamidopropyl dimethylamine','Stearamidopropyl dimethylamine','2',0,0),(403,296,'Polyquaternium-7','Polyquaternium-7','2',0,0),(404,296,'Betaína','Cocamidopropyl Betaine','2',0,0),(405,296,'Capibiome','Water (and) Sea water (and) Glycerin (and) Laminaria digitata extract (and) Phenoxyethanol (and) Chlorella vulgaris extract (and) Saccharide isomerate (and) Ethylhexylglycerin (and) Lavandula stoechas extract','2',0,0),(406,296,'Pheohydrane','Glycerin (and) Water (and) Hydrolyzed algin (and) Sea water (and) Chlorella vulgaris extract','2',0,0),(407,296,'Areaumat Perpetua','Glycerin (and) Water (and) Helichrysum italicum extract','1',0,0),(408,296,'Piroctone Olamine','Piroctone Olamine','2',0,0),(409,296,'Butylene Glycol','Butylene Glycol','2',0,0),(410,297,'ÁGUA','Water','73.9',0,0),(411,297,'ÁCIDO LÁTICO','Lactic Acid','0.7',0,0),(412,297,'EDTA','Disodium EDTA','0.1',0,0),(413,297,'GLICERINA','Glycerin','2',0,0),(414,297,'HIDROXIETIL CELULOSE','Hydroxyethylcellulose','0.2',0,0),(415,297,'ALCOOL CETO 30/70','Cetearyl Alcohol','5',0,0),(416,297,'BTMS','Cetearyl Alcohol (and) Behentrimonium Methosulfate','5',0,0),(417,297,'POLAWAX NF','Cetearyl Alcohol (and) Polysorbate 60','5',0,0),(418,297,'STEARAMIDOPROPYL DIMETHYLAMINE','Stearamidopropyl dimethylamine','2',0,0),(419,297,'POLIQUATERNIO 7','Polyquaternium-7','2',0,0),(420,297,'BETAÍNA','Cocamidopropyl Betaine','2',0,0),(421,297,'*CAPIGUARD P','Water (and) Phenoxyethanol (and) Furcellaria lumbricalis extract','1',0,0),(422,297,'*CAPIBIOME PE','Aqua & Maris aqua & Glycerin & Laminaria digitata extract & Phenoxyethanol & Chlorella vulgaris extract & Saccharide isomerate & Ethylhexylglycerin & Lavandula stoechas extract','0.6',0,0),(423,297,'ISOTIAZOLINONA','Isotiazolinona','0.5',0,0),(424,298,'ÁGUA','Water','83.3',0,0),(425,298,'BUTILENOGLICOL','Butylene Glycol','3',0,0),(426,298,'*BIO-SODIUM HYALURONATE 1% SOLUTION','Water (and) Sodium Hyaluronate (and) Phenoxyethanol','2',0,0),(427,298,'*MUSAP','Polygonatum Odoratum Extract','1',0,0),(428,298,'*WKPEP COPPER PEPTIDE','Copper tripeptide-1','0.1',0,0),(429,298,'*WKPEP MELIT','Acetyl Hexapeptide-1','10',0,0),(430,298,'PHENOXYETHANOL','Phenoxyethanol','0.6',0,0),(431,299,'ÁGUA','Water','82.25',0,0),(432,299,'EDTA','Disodium EDTA','0.15',0,0),(433,299,'*WKPEP PRO-HAIR','Biotinoyl Tripeptide-1 (and) Chrysin (and) Oleanolic Acid (and) PEG-40 Hydrogenated Castor Oil (and) Water (and) Butylene Glycol','5',0,0),(434,299,'*WKPEP MELIT','Acetyl Hexapeptide-1 (and) Water (and) Glycerin','3',0,0),(435,299,'POLYSORBATE 20','Polysorbate 20','4',0,0),(436,299,'2-PHENOXYETHANOL','2-Phenoxyethanol','0.6',0,0),(437,299,'ALCOOL ETILICO 96%','Alcohol','5',0,0),(438,300,'ÁGUA','Water','91.6',0,0),(439,300,'EDTA','Dissodium EDTA','0.1',0,0),(440,300,'MICROCARE PHG','Phenoxyethanol (and) caprylyl glycol','1',0,0),(441,300,'ÁCIDO LÁTICO','Lactic Acid','0',0,0),(442,300,'*NATIFLEX HYAMATE','Hydroxypropyltrimonium Hyaluronate','0.3',0,0),(443,300,'*CAPIGUARD P','Water (and) Phenoxyethanol (and) Furcellaria lumbricalis extract','1',0,0),(444,300,'*PHEOHYDRANE','Water (and) Hydrolized Algin (and) Sea water (and) Chlorella vulgaris extract (and) Phenoxyethanol','1',0,0),(445,300,'*MUSAP','Polygonatum Odoratum Extract','5',0,0);
/*!40000 ALTER TABLE `ingredientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ativos_destaque`
--

DROP TABLE IF EXISTS `ativos_destaque`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ativos_destaque` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `formulacao_id` int(11) NOT NULL,
  `nome_ativo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=172 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ativos_destaque`
--

LOCK TABLES `ativos_destaque` WRITE;
/*!40000 ALTER TABLE `ativos_destaque` DISABLE KEYS */;
INSERT INTO `ativos_destaque` VALUES (65,34,'ZIGA MOIST PM-II','polissacarídeo natural obtido por fermentação de um fungo filamentoso. Amplamente utilizado como agente espessante, estabilizador e hidratante;'),(66,34,'NATUREGEL MC750','gel anidro semissólido 100% natural, ideal para emulsões, oferece efeito matificante, toque aveludado, melhora a aplicação, a estabilidade e a lubricidade da fórmula. Biodegradável e aprovado globalmente;'),(67,34,'PEELING GEL','com papaína e fatores naturais de hidratação, contribui para a redução da acne e alívio da inflamação, além de melhorar a renovação celular. Além disso, ajuda a manter a elasticidade e a suavidade da pele, reforçando a barreira cutânea. Proporciona hidratação duradoura e um efeito iluminador.'),(91,43,'ZIGA MOIST PM-II','Polissacarídeo natural obtido por fermentação de um fungo filamentoso. Amplamente utilizado como agente espessante, estabilizador e hidratante'),(92,43,'NATUREGEL MC750','Gel anidro semissólido 100% natural, ideal para emulsões, oferece efeito matificante, toque aveludado, melhora a aplicação, a estabilidade e a lubricidade da fórmula. Biodegradável e aprovado globalmente'),(93,43,'INNOVA SHEA BUTTER','Manteiga vegetal altamente hidratante e emoliente, confere toque aveludado às formulações'),(94,43,'MARULA CARRIER OIL','Óleo com um sensorial leve e aveludado, rico em ômega 9 e ácidos graxos, proporcionando hidratação profunda para a pele'),(95,43,'RETINOL RRT','Retinol encapsulado em tecnologia de liberação lenta. Garante maior estabilidade e segurança ao ativo, reduz sensibilização e potencializa os efeitos anti-idade. Diminui rugas e linhas finas, melhora a textura da pele e uniformiza o tom, indicado para formulações anti-aging e clareadoras'),(110,58,'PEAUVITA','Oleossoma que contém o fator de crescimento EGF. Age estimulando a produção de colágeno tipo I, reduzindo a aparência de rugas e melhorando a firmeza da pele em 21 dias;\r\n'),(111,59,'PEAUVITA','Oleossoma que contém o fator de crescimento EGF. Age estimulando a produção de colágeno tipo I, reduzindo a aparência de rugas e melhorando a firmeza da pele em 21 dias;\r\n'),(170,60,'THALITAN','Polímero marinho que estimula a melanina, reforça o bronzeado, protege contra UVA/UVB, combate o envelhecimento e potencializa o DHA.'),(171,60,'MARULA CARRIER OIL','Óleo com um sensorial leve e aveludado, rico em ômega 9 e ácidos graxos, proporcionando hidratação profunda para a pele.');
/*!40000 ALTER TABLE `ativos_destaque` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-01-30 13:52:26
