CREATE TABLE `cryptotransactions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `hash` varchar(45) COLLATE utf8mb4_general_nopad_ci NOT NULL,
  `lt` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci;
