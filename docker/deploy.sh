mkdir sql

cp -r ../application ./
cp -r ../webserver ./
cp -r ../database ./sql/
cp ../images/muscari-database.Containerfile ./sql/Dockerfile
cp ../images/muscari-webserver.Containerfile ./Dockerfile

docker-compose up -d --build