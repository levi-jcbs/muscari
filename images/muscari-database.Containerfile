FROM mariadb:latest

ENV MARIADB_ROOT_PASSWORD = muscari

ADD /database/muscari-mariadb.sql /docker-entrypoint-initdb.d/
