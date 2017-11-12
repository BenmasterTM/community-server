### Neighbor protocol server example.

This server is done using the PHP Framework Phalcon, you can download and install following the instructions in their main website (https://phalconphp.com)

This server is done only for example proposes, you can use as community server without problems, but the code is extreme simple and can contain some bugs.

You are free to change, fork, commit bug fixes, etc, but have in mind that is a “basic implementation” and don't want to be more bigger or complex.

If you like the project and implement some CMS to manage the torrents of the community, or have server forks with other implementations, share with us and I mention here.

### Install

First install **[Phalcon](https://phalconphp.com)** in your server, this is required because Phalcon have some special libraries writed directly in C and is not like others PHP Framworks.

Create a folder or clone the Branch into one folder with git clone.

Edit the config.php, change the database configuration and the cache system. Have cache system is required, because speed up the request and prevent DOS attacks (prevent kill your DB server).

Point your domain to the **/public** folder.

Create in your database the following table:

´´´
CREATE TABLE `torrents` (
  `id` int(11) UNSIGNED NOT NULL,
  `hash` varchar(50) DEFAULT NULL,
  `name` text,
  `description` text,
  `tags` text,
  `languages` text,
  `magnet` text,
  `date` int(11) DEFAULT NULL,
  `metadata` text,
  `insert_date` decimal(10,0) DEFAULT NULL,
  `pub` int(1) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Index creation, you can speed up the searchs creating index in "name", "description", "tags" and "languages" fields, if you want to
-- improve more the speed, change the server code and use FULLTEXT search with FULLTEXT index for this fields.
--

ALTER TABLE `torrents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hash` (`hash`),
  ADD KEY `pub` (`pub`);
  
´´´

You can disable the **/library/announce** endpoint editing this option in the config.php, to create allow only add torrents from other CMS or manual DB edition.
