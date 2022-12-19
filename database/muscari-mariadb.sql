CREATE TABLE `projects` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `name`      varchar(30)                             ,   /*                                                           */
  `active`    int           DEFAULT 0                 );  /* 0: Nein, 1: Ja                                            */

CREATE TABLE `user` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `session`   varchar(32)                             ,   /* PHP Session ID                                            */
  `name`      varchar(30)                             ,   /*                                                           */
  `os`        varchar(30)                             ,   /* Betriebsystem                                             */
  `level`     int           DEFAULT 1                 );  /* 0: Anfänger, 1: Nutzer, 2: Fortgeschrittener, 3: Profi    */

CREATE TABLE `fragen` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `user`      int                                     ,   /* user.id                                                   */
  `project`   int                                     ,   /* projects.id                                               */
  `time`      int                                     ,   /* UNIX Timestamp                                            */
  `forum`     int           DEFAULT 0                 ,   /* 0: Nein, 1: Ja                                            */
  `status`    int           DEFAULT 0                 ,   /* 0: Keinen, 1: Aktuelle Frage, 2: Nächste Frage            */
  `inhalt`    varchar(300)                            );  /*                                                           */

CREATE TABLE `interesse` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `user`      int                                     ,   /* user.id                                                   */
  `frage`     int                                     );  /* fragen.id                                                 */

CREATE TABLE `kommentare` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `user`      int                                     ,   /* user.id                                                   */
  `frage`     int                                     ,   /* fragen.id                                                 */
  `inhalt`    varchar(300)                            );  /*                                                           */

CREATE TABLE `umfragen` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `project`   int                                     ,   /* projects.id                                               */
  `inhalt`    varchar(300)                            );  /*                                                           */

CREATE TABLE `antworten` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `umfrage`   int                                     ,   /* umfragen.id                                               */
  `inhalt`    varchar(300)                            );  /*                                                           */

CREATE TABLE `stimmen` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `user`      int                                     ,   /* user.id                                                   */
  `umfrage`   int                                     ,   /* umfragen.id                                               */
  `antwort`   int                                     );  /* antworten.id                                              */

CREATE TABLE `sockets` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `port`      int                                     ,   /*                                                           */
  `user`      int                                     );  /* user.id                                                   */

CREATE TABLE `timeline` (
  `id`        int           PRIMARY KEY auto_increment,   /*                                                           */
  `project`   int                                     ,   /* projects.id                                               */
  `frage`     int                                     ,   /* fragen.id                                                 */
  `time`      int                                     );  /* UNIX Timestamp                                            */
