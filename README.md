# Muscari

Web Application for live Q&A Sessions with community.

[TOC]

## Description

This is a object oriented rewrite of "levi-jcbs/LiveQA" in very early development.

## Build Container Images

### Description

There are two Container Images wich have to be bundled by **docker-compose** or **podman pods** to run muscari.
**muscari-database** contains a MariaDB SQL-Server. Databases and Users are preconfigured. To avoid Loss of Data, `/var/lib/mysql/` should be mounted in a persistent volume or directory.
**muscari-webserver** contains a Apache webserver. Configuration files and application files are included in the builds. In order to be able to change application files *on the fly*, as needed in development environment, simply mount `/application/` to `/var/www/muscari/`.

### Building

Before building, the configuration (`/muscari/application/config/`) of muscari should be adjusted. At least the correct mysql configuration has to be selected. That can be done by copying `config.default.json` into `config.json` and changing `"database": "default"` to `"database": "docker/podman"`.

Workdir: **Project Root**

```bash
podman/docker build -t muscari-database -f images/muscari-database.Containerfile .
podman/docker build -t muscari-webserver -f images/muscari-webserver.Containerfile .
```

## Deploy Muscari

| Property         | Value                                   | Description                                        |
| ---------------- | --------------------------------------- | -------------------------------------------------- |
| Name             | muscari                                 |                                                    |
| Port             | 22125                                   | This is the default. Can be changed in deployment. |
| Requiered Images | muscari-database<br />muscari-webserver | Build them before deployment.                      |

### Podman

You can deploy muscari using the following commands. To autostart muscari via systemd, follow the autostart instructions.

If you're lazy, just run `bash podman/deploy.sh create|start|autostart production|dev [port]` .

#### Create pod

Create pod, publish port **22125**:
```bash
$ podman pod create --replace --publish 22125:80 muscari
```

Assign database to pod:
```bash
$ podman create --replace --pod muscari \
	--volume muscari-mysql:/var/lib/mysql/:Z \
	--name muscari-database muscari-database:latest
```

Assign webserver to pod, uncomment volume mount for dev environments:
```bash
$ podman create --replace --pod muscari \
#	--volume ./application/:/var/www/muscari/:Z \
	--name muscari-webserver muscari-webserver:latest
```

Start pod:

```bash
$ podman pod start muscari
```

#### Autostart pod

Workingdir: **/podman/systemd/**

Generating systemd units from pod:

```bash
$ podman generate systemd --files --new --name muscari
```

Copy to systemd, enable and start pod:

```bash
$ cp *.service ~/.config/systemd/user/
$ systemctl --user daemon-reload
$ systemctl --user enable --now pod-muscari.service
```

#### Permissions in dev environments

When working in a development environment, it's necessary to have **rw** permisssions to the application both on the host side and the container side. This can be gained by changing the group owner to the container's **www-data**.

```bash
$ chmod 770 -R application/*
$ podman unshare chgrp 33 -R application/*
```

### Manually

To deploy Muscari manually, you need to fulfill the following requirements:

1. The machine is running running Linux (to ensure data safety 😉)
2. Webserver using `/application/public/` Directory of this project as its public dir
3. Webserver able to run **PHP8** with sockets and mysqli
4. MySQL Database (MariaDB) with dump (`/database/muscari-mariadb.sql`) applied.
   - User: **muscari**
   - Password: **muscari**
   - Database: **muscari**
5. The application has access to the MySQL Server by configuring the SQL server hostname/IP and port correctly. This can be changed in `/muscari/application/config/mysql/default.json`

## API

### Event Stream (Server -> Client)

The server can send JSON Messages, called "events" to the client. In muscari there are two types of events: **sys**-events for basic system level information such as appearance, user or project and **content**-events for all fast changing content, such as questions and comments. To transfer several information in one single event, the information is splitted in blocks, called "chunks". Each chunks is an information block containing question, user, etc. data.

#### Event Structure

```json
{
    "event": "sys/content",
    "data": [
        [
            "type": "chunk-type",
            "chunkdata-key": "chunkdata-value"
        ],
        [
            "type": "chunk-type",
            "chunkdata-key": "chunkdata-value"
        ]
    ]
}
```

#### Sys chunks

```json
[
    "type": "text",
    "host": "",
    "headline": ""
],
[
    "type": "css",
    "key": "--var-xyz",
    "value": ""
],
[
    "type": "project",
    "id": 0,
    "name": "",
    "active": 0,
    "remove": 0
],
[
    "type": "user",
    "name": "",
    "sessionid": "",
    "id": 0,
    "level": "",
    "os": "",
    "mod": 0,
    "unset": 0
]
```

#### Data chunks

```json
[
    "type": "frage",
    "id": 0,
    "username": "",
    "level": "",
    "os": "",
    "forum": 0,
    "inhalt": "",
    "status": 0,
    "userid": 0,
    "remove": 0
]
```

### Request API (Client -> Server)

#### Status Return

```json
[
    [
        "component": "",
        "status": "success|notice|error",
        "description": ""
    ],
    ...
]
```

## License

This project is licensed under the **GNU General Public License v3.0**.
