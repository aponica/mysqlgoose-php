-- ============================================================================
-- Copyright 2019-2022 Opplaud LLC and other contributors. MIT licensed.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `mysqlgoose_test` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'mysqlgoose_tester' IDENTIFIED
    WITH mysql_native_password -- newer MySQL
    BY 'password';
GRANT INSERT, SELECT, UPDATE, DELETE ON mysqlgoose_test.* TO 'mysqlgoose_tester';
USE mysqlgoose_test;

-- ----------------------------------------------------------------------------

CREATE TABLE `customer` (
    `nId` INT(10) UNSIGNED NOT NULL,
    `zName` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL
    );

ALTER TABLE `customer`
  ADD PRIMARY KEY (`nId`),
  ADD UNIQUE KEY `nId` (`nId`);

ALTER TABLE `customer`
  MODIFY `nId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 1;

INSERT INTO `customer` ( `nId`, `zName` )
  VALUES( 1, 'First Customer' );

-- ----------------------------------------------------------------------------

CREATE TABLE `order` (
    `nId` INT(10) UNSIGNED NOT NULL,
    `nCustomerId` INT(10) UNSIGNED NOT NULL,
    `dtCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `dtPaid` DATETIME DEFAULT NULL
    );

ALTER TABLE `order`
  ADD PRIMARY KEY (`nId`),
  ADD UNIQUE KEY `nId` (`nId`);

ALTER TABLE `order`
  ADD CONSTRAINT `order_fk_customer`
    FOREIGN KEY (`nCustomerId`)
    REFERENCES `customer` (`nId`);

ALTER TABLE `order`
  MODIFY `nId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 1;

INSERT INTO `order` ( `nId`, `nCustomerId` )
  VALUES( 1, 1 );


-- ----------------------------------------------------------------------------

CREATE TABLE `product` (
    `nId` INT(10) UNSIGNED NOT NULL,
    `zName` VARCHAR(50) NOT NULL,
    `nPrice` DECIMAL( 6, 2 ) DEFAULT NULL,
    `bDiscontinued` BOOLEAN DEFAULT FALSE
    );

ALTER TABLE `product`
  ADD PRIMARY KEY (`nId`),
  ADD UNIQUE KEY `nId` (`nId`);

INSERT INTO `product` ( `nId`, `zName`, `nPrice` )
  VALUES( 1, 'Primary Product', 1234.56 );


-- ----------------------------------------------------------------------------

CREATE TABLE `order_product` (
    `nId` INT(10) UNSIGNED NOT NULL,
    `nOrderId` INT(10) UNSIGNED NOT NULL,
    `nProductId` INT(10) UNSIGNED NOT NULL
    );

ALTER TABLE `order_product`
  ADD PRIMARY KEY (`nId`),
  ADD UNIQUE KEY `nId` (`nId`);

ALTER TABLE `order_product`
  ADD CONSTRAINT `order_product_fk_order`
    FOREIGN KEY (`nOrderId`)
    REFERENCES `order` (`nId`),
  ADD CONSTRAINT `order_product_fk_product`
    FOREIGN KEY (`nProductId`)
    REFERENCES `product` (`nId`);

ALTER TABLE `order_product`
  MODIFY `nId` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 1;

INSERT INTO `order_product` ( `nId`, `nOrderId`, `nProductId` )
  VALUES( 1, 1, 1 );


-- ----------------------------------------------------------------------------

CREATE TABLE `review` (
    `nProductId` INT(10) UNSIGNED NOT NULL,
    `zUser` CHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `zText` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `bVerified` BOOLEAN DEFAULT NULL
    );

ALTER TABLE `review`
  ADD CONSTRAINT `review_fk_product`
    FOREIGN KEY (`nProductId`)
    REFERENCES `product` (`nId`);

INSERT INTO `review` ( `nProductId`, `zUser`, `zText`, `bVerified` ) VALUES
  ( 1, 'Andrew', 'test-verified #1', true ),
  ( 1, 'Bob', 'test-verified #2', false ),
  ( 1, 'Caroline', 'test-verified #3', null ),
  ( 1, 'Doug', 'test-verified #4', true ),
  ( 1, 'Elena', 'test-verified #5', false ),
  ( 1, 'Frank', 'test-verified #6', null );

-- EOF
