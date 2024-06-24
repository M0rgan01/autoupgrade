/* script intended for catching invalid requests in previous scripts */

ALTER TABLE `PREFIX_authorized_application` CHANGE `name` `name` VARCHAR(255) NOT NULL;
