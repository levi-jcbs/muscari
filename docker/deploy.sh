ln -s ../application ./
ln -s ../webserver ./
ln -s ../database ./sql/
ln -s ../images/muscari-database.Containerfile ./sql/dockerfile
ln -s ../images/muscari-webserver.Containerfile ./dockerfile

docker-compose up -d --build