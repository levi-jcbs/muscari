FROM mariadb:latest

ENV MARIADB_ROOT_PASSWORD = liveqa

ADD /database/liveqa.sql /docker-entrypoint-initdb.d/
