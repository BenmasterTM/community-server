### Neighbor protocol server example.

This server is done using the PHP Framework Phalcon, you can download and install following the instructions in their main website (https://phalconphp.com)

This server is done only for example proposes, you can use as community server without problems, but the code is extreme simple and can contain some bugs.

You are free to change, fork, commit bug fixes, etc, but have in mind that is a “basic implementation” and don't want to be more bigger or complex.

If you like the project and implement some CMS to manage the torrents of the community, or have server forks with other implementations, share with us and I mention here.

### Install

First install **[Phalcon](https://phalconphp.com)** in your server, this is required because Phalcon have some special libraries writed directly in C and is not like others PHP Framworks.

Create a folder or clone the Branch into one folder with git clone.

Edit the config.php, change the database configuration and the cache system. Have cache system is required, because speed up the request and prevent DOS attacks (prevent kill your DB server).

Point your domain to the **/public** folder and thats all.

You can disable the **/library/announce** endpoint editing this option in the config.php, to create allow only add torrents from other CMS or manual DB edition.
