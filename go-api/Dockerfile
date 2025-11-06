# Build stage
FROM golang:1.20-alpine AS build
WORKDIR /src

# Ensure we have a module file; copy go.mod first to leverage build cache
COPY go.mod go.sum* ./
RUN if [ -f go.mod ]; then go mod download && go mod tidy; fi

COPY . .

# Disable CGO for static build in alpine, and build the binary
RUN CGO_ENABLED=0 GOOS=linux go build -o /go-api-app ./cmd/api

FROM alpine:3.18
RUN apk add --no-cache ca-certificates
COPY --from=build /go-api-app /go-api-app
EXPOSE 8080
CMD ["/go-api-app"]
