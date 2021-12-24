# Supervisord Multi Server Monitoring Tool

A minimum viable product for monitoring a fleet of supervisord instances across mulitple servers

Heavily adapted from https://github.com/mlazarov/supervisord-monitor, we took out all the stuff we didn't want to use, removed the frameworks
and overhauled the rest for PHP8+

## Features

* Monitor unlimited supervisord servers and processes
* Start/Stop/Restart process
* Monitor process uptime status

## Install

1.Clone supervisord-monitor to your vhost/webroot:
```
git clone https://github.com/Innserve/supervisord_php_monitor.git
```

2.Copy config/example.config.inc to config/config.inc
```
cp config/example.config.inc config/config.inc
```

3.Enable/Uncomment inet_http_server (found in supervisord.conf) for all your supervisord servers.
```ini
[inet_http_server]
port=*:9001
username="yourusername"
password="yourpass"
```
Do not forget to restart supervisord service after changing supervisord.conf

4.Edit supervisord-monitor configuration file and add all your supervisord servers
```
vim config/config.inc
```

5.Configure your web server to point one of your vhosts to 'public' directory.
6.Open web browser and enter your vhost url.
