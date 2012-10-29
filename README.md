# Circular

#### An open source Buffer app built on Backbone, Bootstrap and MongoDB

http://circular.io

---

Circular is built as a Backbone.js application that communicates with a MongoDB datastore through a REST API written in PHP. 

The server part, based on the [Silex](http://silex.sensiolabs.org) PHP micro-framework, is intended to be the *dumbest possible*, i.e. we tried to put most "intelligence" in the Backbone app, not in the API. (For example, the API only takes UNIX timestamps and posts' content, while the Backbone app computes the scheduled timestamps).

A daemon based on [PHP-Daemon](https://github.com/shaneharter/PHP-Daemon) then runs in the background and is responsible for sending your posts to Twitter when they're due.


### Installation
---

Prerequisites:

* MongoDB, and PHP's [MongoDB driver](http://www.mongodb.org/display/DOCS/PHP+Language+Center)
* To run the background daemon based on [PHP-Daemon](https://github.com/shaneharter/PHP-Daemon), you need the POSIX and PCNTL extensions for PHP.
* [Composer](http://getcomposer.org)

Installation:

* Make sure you cloned this repo recursively, i.e. with submodules that are in `extlib`
* Create a new Twitter application on dev.twitter.com, then copy your credentials into `api/config.php.sample` and rename it to `api/config.php`
* Install the Silex application dependencies using Composer: `cd api && composer install`
* Create an `uploads` directory and make it writable by the Web server
* Your application's frontend should now be accessible where you set it up, for instance at `http://localhost/Tampon`. The MongoDB datastore's name will be `tampon` (you don't have to explicitly create it).
* Start the daemon with `php Daemon/run.php` 
  * Use option `-d` to run as daemon, i.e. detach and run in the background
  * Your daemon's log will be in `/var/log/daemons/tampon`, or if this is not writable, in `Daemon/logs`. You can use/rotate this log to monitor your daemon.
* That's it!


### License
---
* Copyright 2012 [Julien Chaumond](http://julien-c.fr)
* Distributed under the [MIT License](http://creativecommons.org/licenses/MIT/)