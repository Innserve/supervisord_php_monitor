# Supervisord Multi Server Monitoring Tool

![Screenshot](screen.png)

A minimum viable product for monitoring a fleet of supervisord instances across mulitple servers

Heavily adapted from https://github.com/mlazarov/supervisord_php_monitor, we took out all the stuff we didn't want to use, removed the frameworks
and overhauled the rest for PHP8+

## Features

* Monitor unlimited supervisord servers and processes
* Start/Stop/Restart process
* Monitor process uptime status

## Install

1. Clone supervisord_php_monitor to your vhost/webroot:
    ```
    git clone https://github.com/Innserve/supervisord_php_monitor.git
    ```
2. Run composer to install dependencies
    ```
    composer install
    ```
3. Copy .env.example to .env and set the SERVERS variable
    ```
    cp .env.example .env
    ```
4. Enable/Uncomment inet_http_server (found in supervisord.conf) for all your supervisord servers.
    ```ini
    [inet_http_server]
    port=*:9001
    username=yourusername ;optional, but do it
    password=yourpass ;optional, but do it
    ```
    _Do not forget to restart supervisord service after changing supervisord.conf_
5. Edit supervisord_php_monitor configuration file and add all your supervisord servers
    ```bash
    vim config/config.inc
    ```
6. Configure your web server to point one of your vhosts to 'public' directory.
7. Open web browser and enter your vhost url.
