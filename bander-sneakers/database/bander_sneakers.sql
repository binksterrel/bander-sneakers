-- Base de données: `bander_sneakers`
CREATE DATABASE IF NOT EXISTS `bander_sneakers` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `bander_sneakers`;

-- --------------------------------------------------------

-- Table structure for table `brands`
CREATE TABLE `brands` (
  `brand_id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(100) NOT NULL,
  `brand_logo` varchar(255) DEFAULT NULL,
  `brand_description` text DEFAULT NULL,
  PRIMARY KEY (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `brands`
INSERT INTO `brands` (`brand_id`, `brand_name`, `brand_logo`, `brand_description`) VALUES
(1, 'Nike', 'nike_logo.png', 'Nike, Inc. est une entreprise américaine créée en 1971 par Philip Knight et Bill Bowerman. Elle est spécialisée dans la fabrication d\'articles de sport.'),
(2, 'Adidas', 'adidas_logo.png', 'Adidas est une firme allemande fondée en 1949 par Adolf Dassler, spécialisée dans la fabrication d\'articles de sport.'),
(3, 'Jordan', 'jordan_logo.png', 'Air Jordan est une marque de baskets et de vêtements de sport créée par Nike pour le basketteur Michael Jordan.'),
(4, 'Puma', 'puma_logo.png', 'Puma SE est une entreprise allemande spécialisée dans la fabrication d\'articles de sport fondée en 1948 par Rudolf Dassler.'),
(5, 'Reebok', 'reebok_logo.png', 'Reebok est une entreprise américaine spécialisée dans les vêtements de sport fondée au Royaume-Uni en 1958.');

-- --------------------------------------------------------

-- Table structure for table `categories`
CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` text DEFAULT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `categories`
INSERT INTO `categories` (`category_id`, `category_name`, `category_description`) VALUES
(1, 'Running', 'Chaussures conçues pour la course à pied'),
(2, 'Basketball', 'Chaussures conçues pour le basketball'),
(3, 'Lifestyle', 'Chaussures casual pour un usage quotidien'),
(4, 'Skate', 'Chaussures conçues pour le skateboard'),
(5, 'Training', 'Chaussures conçues pour l\'entraînement et le fitness');

-- --------------------------------------------------------

-- Table structure for table `users`
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `newsletter_subscribed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Dumping data for table `users`
INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `first_name`, `last_name`, `is_admin`, `created_at`) VALUES
(1, 'admin', 'admin@bander-sneakers.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 1, NOW()),
(2, 'user', 'user@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Normal', 'User', 0, NOW());
-- Note: Les mots de passe sont 'password' (hashés avec bcrypt)


CREATE TABLE loyalty_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE spin_logs (
    spin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points_won INT NOT NULL,
    spin_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE newsletter_subscribers (
    subscriber_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1
);
-- --------------------------------------------------------

-- Table structure for table `sizes`
CREATE TABLE `sizes` (
  `size_id` int(11) NOT NULL AUTO_INCREMENT,
  `size_value` varchar(10) NOT NULL,
  `size_type` enum('EU','US','UK','CM') NOT NULL DEFAULT 'EU',
  PRIMARY KEY (`size_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `sizes`
INSERT INTO `sizes` (`size_id`, `size_value`, `size_type`) VALUES
(1, '36', 'EU'),
(2, '37', 'EU'),
(3, '38', 'EU'),
(4, '39', 'EU'),
(5, '40', 'EU'),
(6, '41', 'EU'),
(7, '42', 'EU'),
(8, '43', 'EU'),
(9, '44', 'EU'),
(10, '45', 'EU'),
(11, '46', 'EU'),
(12, '47', 'EU');

-- --------------------------------------------------------

-- Table structure for table `sneakers`
CREATE TABLE `sneakers` (
  `sneaker_id` int(11) NOT NULL AUTO_INCREMENT,
  `sneaker_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `brand_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `color` varchar(50) DEFAULT NULL,
  `gender` enum('homme','femme','enfant','unisex') NOT NULL DEFAULT 'unisex',
  `primary_image` varchar(255) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `release_date` date DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_new_arrival` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sneaker_id`),
  KEY `brand_id` (`brand_id`),
  KEY `category_id` (`category_id`),
  KEY `gender` (`gender`),
  CONSTRAINT `sneakers_ibfk_1` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`brand_id`) ON DELETE CASCADE,
  CONSTRAINT `sneakers_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `sneakers`
INSERT INTO `sneakers` (`sneaker_id`, `sneaker_name`, `description`, `price`, `discount_price`, `brand_id`, `category_id`, `color`, `gender`, `stock_quantity`, `release_date`, `is_featured`, `is_new_arrival`) VALUES
(1, 'Nike Air Force 1', 'Une chaussure intemporelle qui fait partie de la collection Nike depuis plus de 35 ans. Confort et style assurés.', 110.00, NULL, 1, 3, 'Blanc', 'unisex', 50, '2022-01-15', 1, 0),
(2, 'Adidas Superstar', 'Un design classique avec la célèbre coque en caoutchouc. Une icône de la street culture.', 100.00, 85.00, 2, 3, 'Blanc/Noir', 'unisex', 45, '2022-02-10', 1, 0),
(3, 'Air Jordan 1', 'La première chaussure créée pour Michael Jordan en 1985. Un modèle emblématique de la culture sneaker.', 160.00, NULL, 3, 2, 'Rouge/Noir', 'homme', 30, '2022-03-05', 1, 1),
(4, 'Nike Air Max 97', 'Avec son design inspiré des trains à grande vitesse japonais, l\'Air Max 97 ne passe pas inaperçue.', 180.00, 150.00, 1, 1, 'Argent', 'femme', 25, '2022-04-20', 0, 1),
(5, 'Adidas Ultraboost', 'Une chaussure de running qui offre un confort exceptionnel grâce à sa technologie Boost.', 200.00, NULL, 2, 1, 'Noir', 'homme', 35, '2022-05-15', 0, 1),
(6, 'Nike Air Max 270', 'Le premier Air Max lifestyle de Nike, offrant un confort exceptionnel pour un usage quotidien.', 150.00, NULL, 1, 3, 'Noir/Blanc', 'homme', 40, '2022-06-10', 1, 0),
(7, 'Adidas Gazelle', 'Un modèle iconique d\'Adidas, apprécié pour son style intemporel et son confort.', 90.00, 75.00, 2, 3, 'Bleu', 'unisex', 30, '2022-07-05', 0, 0),
(8, 'Nike Air Jordan 4', 'Un modèle emblématique de la ligne Air Jordan, offrant style et performance.', 190.00, NULL, 3, 2, 'Blanc/Rouge', 'homme', 25, '2022-08-15', 1, 0),
(9, 'Puma Cali', 'Un modèle tendance inspiré du style californien, parfait pour un look décontracté.', 90.00, NULL, 4, 3, 'Blanc', 'femme', 35, '2022-09-20', 0, 1),
(10, 'Adidas Stan Smith', 'Une icône du style minimaliste, appréciée pour son design épuré et polyvalent.', 95.00, 80.00, 2, 3, 'Blanc/Vert', 'unisex', 50, '2022-10-10', 1, 0),
(11, 'Nike Air Force 1 Shadow', 'Une version moderne et féminine de l\'Air Force 1 classique, avec des détails superposés.', 130.00, NULL, 1, 3, 'Multi', 'femme', 30, '2022-11-15', 1, 1),
(12, 'Jordan 11 Retro', 'L\'une des silhouettes les plus populaires de la ligne Jordan, connue pour son design élégant.', 220.00, NULL, 3, 2, 'Noir/Rouge', 'homme', 20, '2022-12-10', 1, 0),
(13, 'Nike Air Max 90', 'Un classique de Nike, célèbre pour son confort et son style intemporel.', 140.00, 120.00, 1, 3, 'Gris', 'unisex', 40, '2023-01-05', 0, 0),
(14, 'Adidas NMD R1', 'Un modèle moderne combinant style et technologie pour un confort optimal.', 140.00, NULL, 2, 1, 'Noir/Rouge', 'homme', 35, '2023-02-10', 0, 1),
(15, 'Puma RS-X', 'Un modèle chunky inspiré des années 80, parfait pour suivre la tendance des dad shoes.', 110.00, 90.00, 4, 3, 'Multi', 'unisex', 30, '2023-03-15', 0, 1),
(16, 'Nike Air Max Plus', 'Également connue sous le nom de TN, cette sneaker est appréciée pour son design audacieux.', 170.00, NULL, 1, 1, 'Noir/Bleu', 'homme', 25, '2023-04-20', 1, 0),
(17, 'Adidas Yeezy Boost 350', 'La collaboration emblématique entre Adidas et Kanye West, au design distinctif.', 220.00, NULL, 2, 3, 'Beige', 'unisex', 15, '2023-05-15', 1, 1),
(18, 'Nike Dunk Low', 'Un modèle emblématique du skateboard, devenu une icône de la mode streetwear.', 110.00, NULL, 1, 4, 'Bleu/Blanc', 'unisex', 30, '2023-06-10', 1, 1),
(19, 'Adidas Samba', 'Une chaussure de football devenue une icône du style casual.', 100.00, 85.00, 2, 3, 'Noir/Blanc', 'unisex', 40, '2023-07-05', 0, 0),
(20, 'Nike Cortez', 'La première chaussure de running de Nike, devenue un symbole de la culture californienne.', 90.00, NULL, 1, 3, 'Blanc/Rouge/Bleu', 'unisex', 35, '2023-08-15', 0, 0),
(21, 'Nike Air Force 1 Low Kids', 'Version enfant de l\'iconique Air Force 1, parfaite pour les petits pieds.', 70.00, NULL, 1, 3, 'Blanc', 'enfant', 40, '2023-09-10', 1, 0),
(22, 'Adidas Superstar Kids', 'Le modèle classique Superstar adapté aux enfants.', 60.00, 50.00, 2, 3, 'Blanc/Noir', 'enfant', 35, '2023-10-15', 1, 1),
(23, 'Nike Air Max 90 Kids', 'Version enfant de l\'Air Max 90, confortable et stylée.', 90.00, NULL, 1, 3, 'Gris/Rouge', 'enfant', 30, '2023-11-20', 0, 1),
(24, 'Adidas Stan Smith Kids', 'Le modèle Stan Smith en version enfant.', 65.00, NULL, 2, 3, 'Blanc/Vert', 'enfant', 35, '2023-12-05', 0, 0),
(25, 'Puma Carina Kids', 'Sneaker tendance pour les jeunes filles.', 55.00, 45.00, 4, 3, 'Rose', 'enfant', 30, '2024-01-10', 1, 1);

-- --------------------------------------------------------

-- Table structure for table `sneaker_images`
CREATE TABLE `sneaker_images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `sneaker_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`image_id`),
  KEY `sneaker_id` (`sneaker_id`),
  CONSTRAINT `sneaker_images_ibfk_1` FOREIGN KEY (`sneaker_id`) REFERENCES `sneakers` (`sneaker_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `sneaker_images`
INSERT INTO `sneaker_images` (`image_id`, `sneaker_id`, `image_url`, `is_primary`) VALUES
(1, 1, 'nike_air_force_1_1.jpg', 1),
(2, 2, 'adidas_superstar_1.jpg', 1),
(3, 3, 'air_jordan_1_1.jpg', 1),
(4, 4, 'nike_air_max_97_1.jpg', 1),
(5, 5, 'adidas_ultraboost_1.jpg', 1),
(6, 6, 'nike_air_max_270_1.jpg', 1),
(7, 7, 'adidas_gazelle_1.jpg', 1),
(8, 8, 'nike_air_jordan_4_1.jpg', 1),
(9, 9, 'puma_cali_1.jpg', 1),
(10, 10, 'adidas_stan_smith_1.jpg', 1),
(11, 11, 'nike_air_force_1_shadow_1.jpg', 1),
(12, 12, 'jordan_11_retro_1.jpg', 1),
(13, 13, 'nike_air_max_90_1.jpg', 1),
(14, 14, 'adidas_nmd_r1_1.jpg', 1),
(15, 15, 'puma_rs_x_1.jpg', 1),
(16, 16, 'nike_air_max_plus_1.jpg', 1),
(17, 17, 'adidas_yeezy_boost_350_1.jpg', 1),
(18, 18, 'nike_dunk_low_1.jpg', 1),
(19, 19, 'adidas_samba_1.jpg', 1),
(20, 20, 'nike_cortez_1.jpg', 1),
(21, 21, 'nike_air_force_1_kids_1.jpg', 1),
(22, 22, 'adidas_superstar_kids_1.jpg', 1),
(23, 23, 'nike_air_max_90_kids_1.jpg', 1),
(24, 24, 'adidas_stan_smith_kids_1.jpg', 1),
(25, 25, 'puma_carina_kids_1.jpg', 1);

-- --------------------------------------------------------

-- Table structure for table `sneaker_sizes`
CREATE TABLE `sneaker_sizes` (
  `sneaker_size_id` int(11) NOT NULL AUTO_INCREMENT,
  `sneaker_id` int(11) NOT NULL,
  `size_id` int(11) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sneaker_size_id`),
  UNIQUE KEY `sneaker_id_size_id` (`sneaker_id`,`size_id`),
  KEY `size_id` (`size_id`),
  CONSTRAINT `sneaker_sizes_ibfk_1` FOREIGN KEY (`sneaker_id`) REFERENCES `sneakers` (`sneaker_id`) ON DELETE CASCADE,
  CONSTRAINT `sneaker_sizes_ibfk_2` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`size_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `sneaker_sizes`
INSERT INTO `sneaker_sizes` (`sneaker_size_id`, `sneaker_id`, `size_id`, `stock_quantity`) VALUES
(1, 1, 4, 10),
(2, 1, 5, 10),
(3, 1, 6, 10),
(4, 1, 7, 10),
(5, 1, 8, 10),
(6, 2, 4, 9),
(7, 2, 5, 9),
(8, 2, 6, 9),
(9, 2, 7, 9),
(10, 2, 8, 9),
(11, 3, 5, 6),
(12, 3, 6, 6),
(13, 3, 7, 6),
(14, 3, 8, 6),
(15, 3, 9, 6),
(16, 4, 5, 5),
(17, 4, 6, 5),
(18, 4, 7, 5),
(19, 4, 8, 5),
(20, 4, 9, 5),
(21, 5, 6, 7),
(22, 5, 7, 7),
(23, 5, 8, 7),
(24, 5, 9, 7),
(25, 5, 10, 7);

-- --------------------------------------------------------

-- Table structure for table `cart`
CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'active',  -- Ajout de la colonne status
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cart_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `cart_items`
CREATE TABLE `cart_items` (
  `cart_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `cart_id` int(11) NOT NULL,
  `sneaker_id` int(11) NOT NULL,
  `size_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cart_item_id`),
  KEY `cart_id` (`cart_id`),
  KEY `sneaker_id` (`sneaker_id`),
  KEY `size_id` (`size_id`),
  CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`cart_id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`sneaker_id`) REFERENCES `sneakers` (`sneaker_id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_ibfk_3` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`size_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `wishlist`
CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sneaker_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`wishlist_id`),
  UNIQUE KEY `user_id_sneaker_id` (`user_id`,`sneaker_id`),
  KEY `sneaker_id` (`sneaker_id`),
  CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`sneaker_id`) REFERENCES `sneakers` (`sneaker_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `orders`
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `order_status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL,
  `shipping_address` varchar(255) NOT NULL,
  `shipping_city` varchar(100) NOT NULL,
  `shipping_postal_code` varchar(20) NOT NULL,
  `shipping_country` varchar(100) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `shipping_method` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `order_items`
CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `sneaker_id` int(11) NOT NULL,
  `size_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `sneaker_id` (`sneaker_id`),
  KEY `size_id` (`size_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`sneaker_id`) REFERENCES `sneakers` (`sneaker_id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`size_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- Table structure for table `reviews`
CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sneaker_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `review_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`review_id`),
  KEY `user_id` (`user_id`),
  KEY `sneaker_id` (`sneaker_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`sneaker_id`) REFERENCES `sneakers` (`sneaker_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE sneakers
ADD COLUMN gender ENUM('homme', 'femme', 'enfant', 'unisex') NOT NULL DEFAULT 'unisex';

-- ------------------------------------------------------------

-- Table structure for table `chat_messages`
CREATE TABLE `chat_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL, -- Peut être NULL pour les utilisateurs non connectés
  `admin_id` int(11) DEFAULT NULL, -- ID de l'admin qui répond, NULL si message de l'utilisateur
  `message_text` text NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0, -- 1 si envoyé par un admin, 0 si par un utilisateur
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0, -- 1 si supprimé, 0 si visible
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `user_id` (`user_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ajouter des paramètres initiaux
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_description`) VALUES
('site_name', 'Bander-Sneakers', 'Nom du site affiché dans l\'interface'),
('contact_email', 'bander.sneakers@gmail.com', 'Email de contact pour les notifications'),
('items_per_page', '10', 'Nombre d\'éléments par page dans les listes admin'),
('currency', '€', 'Symbole de la devise utilisée');

ALTER TABLE sneakers
ADD COLUMN primary_image VARCHAR(255) DEFAULT NULL AFTER gender;

CREATE TABLE secondhand_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    etat ENUM('neuf', 'très bon', 'bon', 'moyen', 'usagé') NOT NULL,
    category_id INT NOT NULL,
    brand_id INT,
    size VARCHAR(10) NOT NULL DEFAULT '',
    images TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    statut ENUM('actif', 'vendu', 'supprimé', 'en attente') DEFAULT 'actif',
    views INT DEFAULT 0 CHECK (views >= 0),
    location VARCHAR(100),
    shipping_method VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
    FOREIGN KEY (brand_id) REFERENCES brands(brand_id) ON DELETE SET NULL
);

CREATE TABLE reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reported_user_id INT NOT NULL,
    type ENUM('secondhand', 'review') NOT NULL,
    item_id INT NOT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    etat_signalement ENUM('en attente', 'résolu', 'rejeté') DEFAULT 'en attente',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reported_user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    secondhand_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (secondhand_id) REFERENCES secondhand_products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_closed TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user1_id) REFERENCES users(user_id),
    FOREIGN KEY (user2_id) REFERENCES users(user_id),
    UNIQUE KEY unique_conversation (user1_id, user2_id)
);

CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_text TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT DEFAULT 0,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id),
    FOREIGN KEY (sender_id) REFERENCES users(user_id)
);


CREATE TABLE `notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` ENUM('message', 'points_purchase', 'points_spin', 'order_update', 'report', 'price_change','stock_low', 'new_product') NOT NULL,
  `related_id` INT(11) DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subscriptions (
    subscriber_id INT NOT NULL,
    subscribed_to_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (subscriber_id, subscribed_to_id),
    FOREIGN KEY (subscriber_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subscribed_to_id) REFERENCES users(user_id) ON DELETE CASCADE
);


