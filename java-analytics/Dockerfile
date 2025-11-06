FROM maven:3.9.4-eclipse-temurin-17 as builder
WORKDIR /build
COPY pom.xml ./
COPY src ./src
RUN mvn -B -DskipTests package

FROM eclipse-temurin:17-jre
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=builder /build/target/java-analytics-0.0.1-SNAPSHOT.jar ./app.jar
EXPOSE 9000
ENTRYPOINT ["java","-jar","/app/app.jar"]
