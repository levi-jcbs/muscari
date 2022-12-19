FROM mariadb:latest

ENV MARIADB_ROOT_PASSWORD = muscari
ENV MARIADB_USER = muscari
ENV MARIADB_PASSWORD = muscari
ENV MARIADB_DATABASE = muscari

ADD /database/muscari-mariadb.sql /docker-entrypoint-initdb.d/
RUN chown -R mysql:mysql /docker-entrypoint-initdb.d/
