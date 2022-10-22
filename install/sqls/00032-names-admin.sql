CREATE TABLE `names` (
    `id` bigint UNSIGNED NOT NULL,
    `author` bigint UNSIGNED NOT NULL,
    `new_fn` varchar(50) NOT NULL,
    `new_ln` varchar(50) NOT NULL,
    `created` bigint UNSIGNED NOT NULL,
    `state` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `names`
    ADD PRIMARY KEY (`id`);

ALTER TABLE `names`
    MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;
